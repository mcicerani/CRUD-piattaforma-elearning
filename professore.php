<?php
global $conn;
require_once "./includes/config.php";
require_once "./includes/auth.php";

// Controlla se è professore
function checkProfessorPrivileges(): void
{
    if (!isLoggedIn() || !hasRole('professore')) {
        header("Location: index.php");
        exit();
    }
}

checkProfessorPrivileges();



//Salva id del professore loggato
$professore_id = $_SESSION['user_id'];

// Bottone logout
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] == 'logout') {
    logout();
}

// Funzione per creare corso del professore loggato
function createMyCourse($titolo, $descrizione, $professore_id): void
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO corsi (titolo, descrizione, professore_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $titolo, $descrizione, $professore_id);
    $stmt->execute();
    $stmt->close();
}

// Funzione per aggiornare corso del professore loggato
function updateMyCourse($id, $titolo, $descrizione): void
{
    global $conn;

    $stmt = $conn->prepare("UPDATE corsi SET titolo = ?, descrizione = ? WHERE id = ?");
    $stmt->bind_param("ssi", $titolo, $descrizione, $id);
    $stmt->execute();
    $stmt->close();
}

// Funzione per uploadare file .zip della lezione
function uploadLesson($corso_id, $titolo, $descrizione, $file): string
{
    global $conn;  // Connessione al database

    // Controlla se il file è stato caricato senza errori
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Recupera il nome temporaneo del file caricato
        $file_tmp_name = $file['tmp_name'];  // Nome temporaneo del file
        $file_name_original = basename($file['name']);  // Nome originale del file
        $file_extension = pathinfo($file_name_original, PATHINFO_EXTENSION);  // Estensione del file

        // Recupera il titolo del corso
        $stmt_corso = $conn->prepare("SELECT titolo FROM corsi WHERE id = ?");
        $stmt_corso->bind_param("i", $corso_id);
        $stmt_corso->execute();
        $stmt_corso->bind_result($corso_titolo);
        $stmt_corso->fetch();
        $stmt_corso->close();

        // Crea un nome per il file nel formato: titolocorso_titololezione.zip
        $file_name = strtolower(str_replace(" ", "_", $corso_titolo)) . "_" . strtolower(str_replace(" ", "_", $titolo)) . ".zip";

        // Definisci la cartella di destinazione
        $target_dir = "lezioni/";  // Cartella di destinazione sul server
        $target_file = $target_dir . $file_name;  // Percorso completo del file

        // Controlla se il file è un .zip
        if (strtolower($file_extension) === 'zip') {
            // Prova a spostare il file nella directory di destinazione
            if (move_uploaded_file($file_tmp_name, $target_file)) {
                // Prepara la query per inserire i dettagli della lezione nel database
                $stmt = $conn->prepare("INSERT INTO lezioni (corso_id, titolo, descrizione, file_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $corso_id, $titolo, $descrizione, $target_file);
                $stmt->execute();
                $stmt->close();

                // Ritorna un messaggio di successo
                return "Lezione caricata con successo!";
            } else {
                // Ritorna un errore se il file non può essere spostato nella destinazione
                return "Errore nel caricamento del file.";
            }
        } else {
            // Ritorna un errore se il tipo di file non è .zip
            return "Errore: il file deve essere in formato .zip.";
        }
    } else {
        // Ritorna un errore se c'è un problema con il caricamento del file
        return "Errore nel caricamento del file.";
    }
}

// Funzione per recuperare i corsi del professore
function getCoursesForProfessor($professore_id): array
{
    global $conn;

    // Query per ottenere i corsi del professore loggato
    $query_corsi = "
        SELECT corsi.id, corsi.titolo, corsi.descrizione, utenti.nome AS professore_nome 
        FROM corsi 
        JOIN utenti ON corsi.professore_id = utenti.id 
        WHERE corsi.professore_id = ?
    ";

    $stmt = $conn->prepare($query_corsi);
    $stmt->bind_param("i", $professore_id); // Parametro è l'ID del professore
    $stmt->execute();
    $result = $stmt->get_result();
    $corsi = $result->fetch_all(MYSQLI_ASSOC);

    return $corsi;
}

// Recupera i corsi del professore loggato
$corsi = getCoursesForProfessor($professore_id);

// Funzione per recuperare le lezioni per un corso
function getLessonsForCourse($corso_id): array
{
    global $conn;

    // Query per ottenere le lezioni associate al corso
    $query_lezioni = "
        SELECT id, titolo, descrizione, file_path
        FROM lezioni
        WHERE corso_id = ?
    ";

    $stmt_lezioni = $conn->prepare($query_lezioni);
    $stmt_lezioni->bind_param("i", $corso_id);
    $stmt_lezioni->execute();
    $result_lezioni = $stmt_lezioni->get_result();

    return $result_lezioni->fetch_all(MYSQLI_ASSOC);
}


// CRUD: Operazioni per utente professore
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case 'create_course':
                // Creazione nuovo corso
                createMyCourse($_POST["titolo"], $_POST["descrizione"], $professore_id);
                break;

            case 'update_course':
                // Aggiornamento corso
                updateMyCourse($_POST["corso_id"], $_POST["titolo"], $_POST["descrizione"]);
                break;

            case 'delete_course':
                // Eliminazione corso
                deleteCourse($_POST["corso_id"]);
                break;
        }
    } elseif (isset($_FILES['lezione_file'])) {
        $corso_id = $_POST['corso_id'];  // campo nascosto nel modulo per il corso_id
        $titolo = $_POST['titolo'];      // Titolo della lezione
        $descrizione = $_POST['descrizione'];  // Descrizione della lezione

        // Invoca la funzione passando $_FILES['lezione_file']
        $message = uploadLesson($corso_id, $titolo, $descrizione, $_FILES['lezione_file']);
        echo $message;
    }
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Corsi</title>
    <link rel="stylesheet" href="./css/style.css">
    <script src="./js/index.js"></script>
    <script src="js/admin.js"></script>
</head>
<body>
<!-- Gestione Corsi -->
<h1>Corsi</h1>

<?php if (empty($corsi)): ?>
    <p>Non hai ancora creato nessun corso.</p>
<?php else: ?>
    <?php foreach ($corsi as $corso): ?>
        <!-- Dettagli del Corso -->
        <h2><?php echo htmlspecialchars($corso["titolo"]); ?></h2>
        <h3>Descrizione:</h3>
        <p><?php echo htmlspecialchars($corso["descrizione"]); ?></p>
        <h3>Professore:</h3>
        <p><?php echo htmlspecialchars($corso["professore_nome"]); ?></p>

        <form method="POST">
            <input type="hidden" name="corso_id" value="<?= $corso['id'] ?>">
            <button type="submit" name="action" value="delete_course">Elimina</button>
        </form>

        <button onclick="toggleForm('modifica_corso', <?= $corso["id"] ?>)">Modifica Corso</button>

        <form id="modifica_corso-form-<?= $corso["id"] ?>" method="POST" style="display: none">
            <input type="hidden" name="corso_id" value="<?= $corso['id'] ?>">
            <label>
                <input type="text" name="titolo" placeholder="Titolo" required/>
            </label>
            <label>
                <input type="text" name="descrizione" placeholder="Descrizione" required/>
            </label>
            <button type="submit" name="action" value="update_course">Aggiorna</button>
        </form>


        <!-- Tabella delle Lezioni -->
        <h3>Lezioni:</h3>
        <?php
        // Recupera le lezioni associate al corso
        $lezioni = getLessonsForCourse($corso['id']);
        if (empty($lezioni)): ?>
            <p>Non ci sono lezioni per questo corso.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Titolo Lezione</th>
                    <th>Descrizione</th>
                    <th>File</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lezioni as $lezione): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lezione['titolo']); ?></td>
                        <td><?php echo htmlspecialchars($lezione['descrizione']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($lezione['file_path']); ?>" target="_blank">Scarica</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <button onclick="toggleForm('upload_lezioni', <?= $corso["id"] ?> )">Upload Lezioni</button>

        <form id="upload_lezioni-form-<?= $corso["id"] ?>" style="display: none" method="POST" enctype="multipart/form-data">
            <!-- ID del corso a cui appartiene la lezione -->
            <input type="hidden" name="corso_id" value="<?= $corso["id"] ?>">  <!-- Modifica questo valore dinamicamente -->

            <!-- Titolo della lezione -->
            <label for="titolo">Titolo della lezione:</label>
            <input type="text" name="titolo" id="titolo" placeholder="Titolo della lezione" required/>

            <!-- Descrizione della lezione -->
            <label for="descrizione">Descrizione della lezione:</label>
            <input name="descrizione" id="descrizione" placeholder="Descrizione della lezione" required></input>

            <!-- Caricamento del file .zip della lezione -->
            <label for="lezione_file">File lezione (formato .zip):</label>
            <input type="file" name="lezione_file" id="lezione_file" accept=".zip" required/>

            <!-- Pulsante per inviare il modulo -->
            <button type="submit" name="action" value="upload_lesson">Carica Lezione</button>
        </form>

    <?php endforeach; ?>
<?php endif; ?>

<!-- Crea Corso -->

<button onclick="toggle('corso')">Crea Corso</button>

<form id="corso" method="POST" style="display: none">
    <label>
        <input type="text" name="titolo" placeholder="Titolo" required/>
    </label>
    <label>
        <input type="text" name="descrizione" placeholder="Descrizione" required/>
    </label>
    <button type="submit" name="action" value="create_course">Crea Corso</button>
</form>

<!-- Logout -->
<form method="POST" class="logout">
    <button type="submit" name="action" value="logout">Logout</button>
</form>

</body>
</html>