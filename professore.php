<?php
global $conn;

use JetBrains\PhpStorm\NoReturn;

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

// CREATE - Funzione per creare corso del professore loggato
function createMyCourse($titolo, $descrizione, $professore_id): void
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO corsi (titolo, descrizione, professore_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $titolo, $descrizione, $professore_id);
    $stmt->execute();
    $stmt->close();
}

// UPDATE - Funzione per aggiornare corso del professore loggato
function updateMyCourse($id, $titolo, $descrizione): void
{
    global $conn;

    $stmt = $conn->prepare("UPDATE corsi SET titolo = ?, descrizione = ? WHERE id = ?");
    $stmt->bind_param("ssi", $titolo, $descrizione, $id);
    $stmt->execute();
    $stmt->close();
}

// DELETE - Funzione per eliminare un corso
function deleteCourse($id): void
{
    global $conn;

    // Elimina le iscrizioni
    $stmt_iscrizioni = $conn->prepare("DELETE FROM iscrizioni WHERE corso_id = ?");
    $stmt_iscrizioni->bind_param("i", $id);
    $stmt_iscrizioni->execute();
    $stmt_iscrizioni->close();

    // Elimina le lezioni
    $stmt_lezioni = $conn->prepare("SELECT * FROM lezioni WHERE corso_id = ?");
    $stmt_lezioni->bind_param('i', $id);
    $stmt_lezioni->execute();
    $result = $stmt_lezioni->get_result();

    // Elimina ogni lezione associata al corso
    while ($lezione = $result->fetch_assoc()) {
        // Chiamata alla funzione deleteLesson per eliminare il file e la lezione
        deleteLesson($lezione['id'], $lezione['file_path']);
    }

    $stmt_lezioni->close();

    // Elimina il corso
    $stmt_corso = $conn->prepare("DELETE FROM corsi WHERE id = ?");
    $stmt_corso->bind_param("i", $id);
    $stmt_corso->execute();
    $stmt_corso->close();
}

// UPLOAD - Funzione per Caricare la lezione
function uploadLesson($corso_id, $titolo, $descrizione, $file): void
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
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/lezioni/";

        // Verifica se la directory esiste, altrimenti la crea
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                // In caso di errore, non fare nulla, funzione void
                return;
            }
        }

        // Percorso completo del file
        $target_file = $target_dir . $file_name;

        // Controlla se il file è un .zip
        if (strtolower($file_extension) === 'zip') {
            // Prova a spostare il file nella directory di destinazione
            if (move_uploaded_file($file_tmp_name, $target_file)) {
                // Prepara la query per inserire i dettagli della lezione nel database
                $stmt = $conn->prepare("INSERT INTO lezioni (corso_id, titolo, descrizione, file_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $corso_id, $titolo, $descrizione, $target_file);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// DOWNLOAD - Funzione per il download
#[NoReturn] function downloadLesson($filePath): void{

    $fileName = basename($filePath);
    $fileSize = filesize($filePath);

    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header("Content-Length: $fileSize");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    readfile($filePath);
}


// READ - Funzione per recuperare i corsi del professore
function getCoursesForProfessor($professore_id): array
{
    global $conn;

    // Query per ottenere i corsi del professore loggato
    $query_corsi = "
    SELECT 
        corsi.id, 
        corsi.titolo, 
        corsi.descrizione, 
        utenti_professore.nome AS professore_nome, 
        GROUP_CONCAT(studenti.nome ORDER BY studenti.nome ASC SEPARATOR ', ') AS studenti_nomi
    FROM 
        corsi
    LEFT JOIN 
        utenti AS utenti_professore ON corsi.professore_id = utenti_professore.id AND utenti_professore.role = 'professore'
    LEFT JOIN 
        iscrizioni ON corsi.id = iscrizioni.corso_id
    LEFT JOIN 
        utenti AS studenti ON iscrizioni.studente_id = studenti.id AND studenti.role = 'studente'
    WHERE 
        corsi.professore_id = ?
    GROUP BY
        corsi.id, corsi.titolo, corsi.descrizione, utenti_professore.nome";

    $stmt = $conn->prepare($query_corsi);
    $stmt->bind_param("i", $professore_id); // Parametro è l'ID del professore
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Recupera i corsi del professore loggato
$corsi = getCoursesForProfessor($professore_id);

// READ - Funzione per recuperare le lezioni per un corso
function getLessonsForCourse($corso_id): array
{
    global $conn;

    // Query per ottenere le lezioni associate al corso
    $query_lezioni = "
        SELECT id, titolo, descrizione, data_caricamento, file_path
        FROM lezioni
        WHERE corso_id = ?
    ";

    $stmt_lezioni = $conn->prepare($query_lezioni);
    $stmt_lezioni->bind_param("i", $corso_id);
    $stmt_lezioni->execute();
    $result_lezioni = $stmt_lezioni->get_result();

    return $result_lezioni->fetch_all(MYSQLI_ASSOC);
}

// DELETE - Funzione per eliminare la lezione
function deleteLesson($lezione_id, $filePath): void
{
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            echo "Errore: impossibile eliminare il file.";
        }
    }

    global $conn;

    $sql = "DELETE FROM lezioni WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $lezione_id);
    $stmt->execute();
}


// CRUD: Operazioni per utente professore
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case 'create_course':
                // Creazione nuovo corso
                createMyCourse($_POST["titolo"], $_POST["descrizione"], $professore_id);
                header("Location: professore.php");
                break;

            case 'update_course':
                // Aggiorna corso
                updateMyCourse($_POST["corso_id"], $_POST["titolo"], $_POST["descrizione"]);
                header("Location: professore.php");
                break;

            case 'delete_course':
                // Elimina corso
                deleteCourse($_POST["corso_id"]);
                header("Location: professore.php");
                break;

            case 'upload_lesson':
                // Carica lezione
                $corso_id = $_POST['corso_id'];  // campo nascosto nel modulo per il corso_id
                $titolo = $_POST['titolo'];      // Titolo della lezione
                $descrizione = $_POST['descrizione'];  // Descrizione della lezione

                // Invoca la funzione passando $_FILES['lezione_file']
                uploadLesson($corso_id, $titolo, $descrizione, $_FILES['lezione_file']);
                break;

            case 'download_lesson':
                // Scarica lezione
                $filePath = $_POST['file_path'];
                downloadLesson($filePath);

            case 'delete_lesson':
                // Elimina lezione
                $filePath = $_POST['file_path'];
                $lezione_id = $_POST['lezione_id'];
                deleteLesson($lezione_id, $filePath);
                break;

            case 'logout':
                logout();
        }
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

<?php if (empty($corsi)): ?>
    <p>Non hai ancora creato nessun corso.</p>
<?php else: ?>
    <?php foreach ($corsi as $corso): ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Titolo</th>
            <th>Descrizione</th>
            <th>Professore</th>
            <th>Alunni</th>
            <th>Azioni</th>
        </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $corso['id'] ?></td>
                <td><?= htmlspecialchars($corso["titolo"])?></td>
                <td><?= htmlspecialchars($corso["descrizione"])?></td>
                <td><?= htmlspecialchars($corso["professore_nome"])?></td>
                <td><?= htmlspecialchars($corso["studenti_nomi"])?></td>
                <td class="azioni">
                    <button class="btn-azione" onclick="toggleForm('corso', <?= $corso['id']?>)">Modifica</button>
                    <form method="POST">
                        <input type="hidden" name="corso_id" value="<?= $corso['id'] ?>">
                        <button class="btn-azione" type="submit" name="action" value="delete_course">Elimina</button>
                    </form>
                </td>
            </tr>
            <tr class="form-row" id="corso-form-<?= $corso['id'] ?>" style="display: none;">
                <td colspan="6">
                    <form method="post">
                        <input type="hidden" name="corso_id" value="<?= $corso['id'] ?>">
                        <label>
                            Titolo:
                            <input type="text" name="titolo" value="<?= htmlspecialchars($corso["titolo"]) ?>" required>
                        </label>
                        <label>
                            Descrizione:
                            <input type="text" name="descrizione" value="<?= htmlspecialchars($corso["descrizione"]) ?>" required>
                        </label>
                        <button type="submit" name="action" value="update_course">Salva</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>


        <!-- Tabella delle Lezioni -->
        <h3>Lezioni:</h3>
        <?php
        $lezioni = getLessonsForCourse($corso['id']);
        if (empty($lezioni)): ?>
        <p>Non ci sono lezioni</p>
        <table>
            <tr class="form-row" id="upload_lezioni-form-<?= $corso["id"] ?>" style="display: none;">
                <td colspan="4">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="corso_id" value="<?= $corso["id"] ?>">  <!-- Modifica questo valore dinamicamente -->
                        <label for="titolo">Titolo della lezione:</label>
                        <input type="text" name="titolo" id="titolo" placeholder="Titolo della lezione" required/>
                        <label for="descrizione">Descrizione della lezione:</label>
                        <input name="descrizione" id="descrizione" placeholder="Descrizione della lezione" required>
                        <label for="lezione_file">File lezione (formato .zip):</label>
                        <input type="file" name="lezione_file" id="lezione_file" accept=".zip" required/>
                        <button type="submit" name="action" value="upload_lesson">Carica Lezione</button>
                    </form>
                </td>
            </tr>
        </table>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Titolo Lezione</th>
                    <th>Descrizione</th>
                    <th>Data</th>
                    <th>File</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lezioni as $lezione): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lezione['titolo']); ?></td>
                        <td><?php echo htmlspecialchars($lezione['descrizione']); ?></td>
                        <td><?php echo htmlspecialchars($lezione['data_caricamento']); ?></td>
                        <!-- Form for the download button -->
                        <td>
                            <form method="POST">
                                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($lezione['file_path']); ?>">
                                <button class="btn-azione" name="action" value="download_lesson" type="submit">Scarica</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="lezione_id" value="<?php echo htmlspecialchars($lezione['id']); ?>">
                                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($lezione['file_path']); ?>">
                                <button class="btn-azione" name="action" value="delete_lesson" type="submit">Elimina</button>
                            </form>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <tr class="form-row" id="upload_lezioni-form-<?= $corso["id"] ?>" style="display: none;">
                    <td colspan="4">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="corso_id" value="<?= $corso["id"] ?>">  <!-- Modifica questo valore dinamicamente -->
                            <label for="titolo">Titolo della lezione:</label>
                            <input type="text" name="titolo" id="titolo" placeholder="Titolo della lezione" required/>
                            <label for="descrizione">Descrizione della lezione:</label>
                            <input name="descrizione" id="descrizione" placeholder="Descrizione della lezione" required>
                            <label for="lezione_file">File lezione (formato .zip):</label>
                            <input type="file" name="lezione_file" id="lezione_file" accept=".zip" required/>
                            <button type="submit" name="action" value="upload_lesson">Carica Lezione</button>
                        </form>
                    </td>
                </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <button onclick="toggleForm('upload_lezioni', <?= $corso["id"] ?> )">Upload Lezioni</button>

    <?php endforeach; ?>
<?php endif; ?>

<!-- Logout -->
<form method="POST" class="logout">
    <button type="submit" name="action" value="logout">Logout</button>
</form>

</body>
</html>