<?php
global $conn;

use JetBrains\PhpStorm\NoReturn;

require_once './includes/config.php';
require_once './includes/auth.php';

// Controlla se è studente
function checkStudentPrivileges(): void
{
    if (!isLoggedIn() || !hasRole('studente')) {
        header("Location: index.php");
        exit();
    }
}

checkStudentPrivileges();

//Salva id dello studente loggato
$studente_id = $_SESSION['user_id'];

// Funzione per iscriversi a un corso
function subscribe($studente_id, $corso_id) :void {

    global $conn;

    // Crea Iscrizione
    $stmt = $conn->prepare("INSERT INTO iscrizioni (studente_id, corso_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $studente_id, $corso_id);
    $stmt->execute();
    $stmt->close();
}

// Funzione per annullare iscrizione al corso
function unsubscribe($studente_id, $corso_id) :void {

    global $conn;

    // Elimina la iscrizione
    $stmt_iscrizioni = $conn->prepare("DELETE FROM iscrizioni WHERE corso_id = ? AND studente_id = ?");
    $stmt_iscrizioni->bind_param("ii", $corso_id, $studente_id);
    $stmt_iscrizioni->execute();
    $stmt_iscrizioni->close();
}

// Funzione per scaricare le lezioni
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

$corsi_con_professore = "
    SELECT 
        corsi.*,
        utenti_professore.nome AS professore_nome  -- Nome del professore
    FROM corsi
    LEFT JOIN 
        utenti AS utenti_professore 
        ON corsi.professore_id = utenti_professore.id 
        AND utenti_professore.role = 'professore'
";

// Esegui la query e ottieni i risultati
$corsi = $conn->query($corsi_con_professore)->fetch_all(MYSQLI_ASSOC);

function getCoursesforStudent($studente_id): array
{
    global $conn;

    // Query per ottenere i corsi dello studente loggato
    $query_corsi = "
    SELECT 
        corsi.id, 
        corsi.titolo, 
        corsi.descrizione,
        corsi.professore_id,
        utenti_professore.nome AS professore_nome,  -- Nome del professore
        studenti.nome AS studente_nome             -- Nome dello studente
    FROM 
        corsi
    LEFT JOIN 
        utenti AS utenti_professore ON corsi.professore_id = utenti_professore.id AND utenti_professore.role = 'professore'
    LEFT JOIN 
        iscrizioni ON corsi.id = iscrizioni.corso_id
    LEFT JOIN 
        utenti AS studenti ON iscrizioni.studente_id = studenti.id AND studenti.role = 'studente'
    WHERE 
        iscrizioni.studente_id = ?   -- Qui filtra per lo studente specificato
    ";

    $stmt = $conn->prepare($query_corsi);
    $stmt->bind_param("i", $studente_id); // Parametro è l'ID del professore
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

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

// Funzione per verificare se uno studente è iscritto a un corso
function hasSubscribed($studente_id, $corso_id): bool
{
    global $conn; // Usa la connessione globale al database

    // Prepara la query per verificare se esiste una iscrizione per lo studente e il corso
    $stmt = $conn->prepare("SELECT 1 FROM iscrizioni WHERE corso_id = ? AND studente_id = ?");
    $stmt->bind_param("ii", $corso_id, $studente_id); // Bind dei parametri per evitare SQL injection
    $stmt->execute();

    // Se la query restituisce un risultato, significa che lo studente è iscritto
    $result = $stmt->get_result();
    $is_subscribed = $result->num_rows > 0; // Se ci sono righe, lo studente è iscritto

    $stmt->close(); // Chiudi il prepared statement

    return $is_subscribed; // Restituisci true o false
}

// CRUD: Operazioni per utente studente
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case "subscribe" :
                // Iscriviti al corso
                $corso_id = $_POST["corso_id"];
                subscribe($studente_id, $corso_id);
                break;

            case "unsubscribe" :
                // Annulla iscrizione al corso
                $corso_id = $_POST["corso_id"];
                unsubscribe($studente_id, $corso_id);
                break;

            case 'download_lesson':
                // Scarica lezione
                $filePath = $_POST['file_path'];
                downloadLesson($filePath);
                break;

            case 'logout':
                logout();
                break;
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

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Titolo</th>
        <th>Descrizione</th>
        <th>Professore</th>
        <th>Azioni</th>
    </tr>
    </thead>
    <tbody>
    <?php
    // Verifica se lo studente è iscritto a ciascun corso
    foreach ($corsi as $corso):
        // Usa la funzione hasSubscribed per determinare se lo studente è iscritto
        $is_iscritto = hasSubscribed($studente_id, $corso['id']);
    ?>
        <tr>
            <td><?= htmlspecialchars($corso['id']) ?></td>
            <td><?= htmlspecialchars($corso['titolo']) ?></td>
            <td><?= htmlspecialchars($corso['descrizione']) ?></td>
            <td><?= htmlspecialchars($corso['professore_nome']) ?></td>
        <td>
            <?php if ($is_iscritto): ?>
                <!-- Se lo studente è iscritto, mostra "Annulla Iscrizione" -->
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="corso_id" value="<?= $corso['id'] ?>">
                    <button class="btn-azione" type="submit" name="action" value="unsubscribe">Annulla Iscrizione</button>
                </form>
            <?php else: ?>
                <!-- Se lo studente non è iscritto, mostra "Iscriviti" -->
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="corso_id" value="<?= $corso['id'] ?>">
                    <button class="btn-azione" type="submit" name="action" value="subscribe">Iscriviti</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h1>Corsi Seguiti</h1>

<?php
// Recupera i corsi a cui lo studente è iscritto
$corsi = getCoursesforStudent($studente_id);

if (empty($corsi)): ?>
    <p>Non sei iscritto a nessun corso.</p>
<?php else: ?>
    <?php foreach ($corsi as $corso): ?>
        <!-- Tabella dei corsi -->
        <h2>Corso: <?= htmlspecialchars($corso['titolo']) ?></h2>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Titolo</th>
                <th>Descrizione</th>
                <th>Professore</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><?= htmlspecialchars($corso['id']) ?></td>
                <td><?= htmlspecialchars($corso['titolo']) ?></td>
                <td><?= htmlspecialchars($corso['descrizione']) ?></td>
                <td><?= htmlspecialchars($corso['professore_nome']) ?></td>
            </tr>
            </tbody>
        </table>
        <?php
        // Recupera le lezioni per questo corso
        $lezioni = getLessonsForCourse($corso['id']);

        if (empty($lezioni)): ?>
            <p>Non ci sono lezioni caricate per questo corso.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Titolo Lezione</th>
                    <th>Descrizione</th>
                    <th>Data Caricamento</th>
                    <th>File</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lezioni as $lezione): ?>
                    <tr>
                        <td><?= htmlspecialchars($lezione['titolo']) ?></td>
                        <td><?= htmlspecialchars($lezione['descrizione']) ?></td>
                        <td><?= htmlspecialchars($lezione['data_caricamento']) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="file_path" value="<?= htmlspecialchars($lezione['file_path']); ?>">
                                <button class="btn-azione" name="action" value="download_lesson" type="submit">Scarica</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Logout -->
<form method="POST" class="logout">
    <button type="submit" name="action" value="logout">Logout</button>
</form>

</body>
</html>
