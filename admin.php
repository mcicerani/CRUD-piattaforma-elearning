<?php
global $conn;
require_once "./includes/config.php";
require_once "./includes/auth.php";

// Funzione per verificare se l'utente è loggato e ha il ruolo di admin
function checkAdminPrivileges(): void
{
    if (!isLoggedIn() || !hasRole('admin')) {
        header("Location: index.php");
        exit();
    }
}

checkAdminPrivileges();

// Funzione per creare un nuovo utente
function createUser($nome, $email, $password, $role): void
{
    global $conn;
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO utenti (nome, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $email, $password_hashed, $role);
    $stmt->execute();
    $stmt->close();
}

// Funzione per aggiornare un utente esistente
function updateUser($id, $nome, $role): void
{
    global $conn;

    $stmt = $conn->prepare("UPDATE utenti SET nome = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssi", $nome, $role, $id);
    $stmt->execute();
    $stmt->close();
}

// Funzione per eliminare un utente
function deleteUser($id): void
{
    global $conn;

    // Elimina prima le iscrizioni
    $stmt_iscrizioni = $conn->prepare("DELETE FROM iscrizioni WHERE studente_id = ?");
    $stmt_iscrizioni->bind_param("i", $id);
    $stmt_iscrizioni->execute();
    $stmt_iscrizioni->close();

    // Elimina prima i corsi di cui è professore
    $stmt_iscrizioni = $conn->prepare("DELETE FROM corsi WHERE professore_id = ?");
    $stmt_iscrizioni->bind_param("i", $id);
    $stmt_iscrizioni->execute();
    $stmt_iscrizioni->close();


    // Elimina l'utente
    $stmt_utente = $conn->prepare("DELETE FROM utenti WHERE id = ?");
    $stmt_utente->bind_param("i", $id);
    $stmt_utente->execute();
    $stmt_utente->close();
}

// Funzione per creare un nuovo corso
function createCourse($titolo, $descrizione, $professore_id): void
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO corsi (titolo, descrizione, professore_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $titolo, $descrizione, $professore_id);
    $stmt->execute();
    $stmt->close();
}

// Funzione per aggiornare un corso esistente
function updateCourse($id, $titolo, $descrizione, $professore_id): void
{
    global $conn;

    // Controlla se esiste il professore
    $stmt_check_prof = $conn->prepare("SELECT id FROM utenti WHERE id = ? AND role = 'professore'");
    $stmt_check_prof->bind_param("i", $professore_id);
    $stmt_check_prof->execute();
    $stmt_check_prof->store_result();

    if ($stmt_check_prof->num_rows == 0) {
        echo "Professore non trovato!";
        exit();
    }

    $stmt = $conn->prepare("UPDATE corsi SET titolo = ?, descrizione = ?, professore_id = ? WHERE id = ?");
    $stmt->bind_param("ssii", $titolo, $descrizione, $professore_id, $id);
    $stmt->execute();
    $stmt->close();
}

// Funzione per eliminare un corso
function deleteCourse($id): void{
    global $conn;

    // Elimina le iscrizioni
    $stmt_iscrizioni = $conn->prepare("DELETE FROM iscrizioni WHERE corso_id = ?");
    $stmt_iscrizioni->bind_param("i", $id);
    $stmt_iscrizioni->execute();
    $stmt_iscrizioni->close();

    // Elimina il corso
    $stmt_corso = $conn->prepare("DELETE FROM corsi WHERE id = ?");
    $stmt_corso->bind_param("i", $id);
    $stmt_corso->execute();
    $stmt_corso->close();
}

// CRUD: Operazioni per utente admin
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case 'create_user':
                // Creazione nuovo utente
                createUser($_POST["nome"], $_POST["email"], $_POST["password"], $_POST["role"]);
                break;

            case 'update_user':
                // Aggiornamento utente
                if (isset($_POST["studente_id"])){
                    updateUser($_POST["studente_id"], $_POST["nome"], $_POST["role"]);
                } elseif (isset($_POST["professore_id"])) {
                    updateUser($_POST["professore_id"], $_POST["nome"], $_POST["role"]);
                }
                break;


            case 'delete_user':
                // Eliminazione utente
                if (isset($_POST["studente_id"])) {
                    deleteUser($_POST["studente_id"]);
                } elseif (isset($_POST["professore_id"])) {
                    deleteUser($_POST["professore_id"]);
                }
                break;

            case 'create_course':
                // Creazione nuovo corso
                createCourse($_POST["titolo"], $_POST["descrizione"], $_POST["professore_id"]);
                break;

            case 'update_course':
                // Aggiornamento corso
                updateCourse($_POST["corso_id"], $_POST["titolo"], $_POST["descrizione"], $_POST["professore_id"]);
                break;

            case 'delete_course':
                // Eliminazione corso
                deleteCourse($_POST["corso_id"]);
                break;

            case 'logout':
                logout();
        }
    }
}

// READ - Chiamo info utenti e corsi

// Query per tabella studenti (id, nome e ruolo) con i corsi a cui sono iscritti
$query_studenti = "
    SELECT  utenti.id AS studente_id, utenti.nome AS studente_nome, utenti.role AS studente_role,
            utenti.email AS studente_email,
            GROUP_CONCAT(corsi.titolo SEPARATOR ', ') AS corsi
    FROM utenti
    LEFT JOIN iscrizioni ON utenti.id = iscrizioni.studente_id
    LEFT JOIN corsi ON iscrizioni.corso_id = corsi.id
    WHERE utenti.role = 'studente'
    GROUP BY utenti.id
    ";

$studenti = $conn->query($query_studenti)->fetch_all(MYSQLI_ASSOC);

// Query per tabella professori (id, nome e ruolo) con i corsi che gestiscono
$query_professori = "
    SELECT  utenti.id AS professore_id, utenti.nome AS professore_nome, utenti.role AS professore_role,
            utenti.email AS professore_email,
            GROUP_CONCAT(corsi.titolo SEPARATOR ', ') AS corsi
    FROM utenti
    LEFT JOIN corsi ON corsi.professore_id = utenti.id
    WHERE utenti.role = 'professore'
    GROUP BY utenti.id
    ";

$professori = $conn->query($query_professori)->fetch_all(MYSQLI_ASSOC);

// Query per tabella corsi (id, titolo e descrizione) con i prof del corso e gli studenti iscritti
$query_corsi = "
    SELECT  corsi.id AS corso_id, corsi.titolo AS corso_titolo, corsi.descrizione AS corso_descrizione,
            corsi.professore_id AS professore_id, utenti.nome AS professore_nome,
            GROUP_CONCAT(utenti_studente.nome SEPARATOR ', ') AS studenti
    FROM corsi
    LEFT JOIN utenti ON corsi.professore_id = utenti.id AND utenti.role = 'professore'
    LEFT JOIN iscrizioni ON corsi.id = iscrizioni.corso_id
    LEFT JOIN utenti AS utenti_studente ON iscrizioni.studente_id = utenti.id
    GROUP BY corsi.id
    ";

$corsi = $conn->query($query_corsi)->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Utenti e Corsi</title>
    <link rel="stylesheet" href="./css/style.css">
    <script src="./js/index.js"></script>
    <script src="js/admin.js"></script>
</head>

<body>

    <h1>Gestione Utenti e Corsi</h1>

    <!-- Studenti -->

    <h2>Studenti</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Corsi</th>
            <th>Ruolo</th>
            <th>Azioni</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($studenti as $studente): ?>
            <tr>
                <td><?= $studente['studente_id'] ?></td>
                <td><?= htmlspecialchars($studente["studente_nome"])?></td>
                <td><?= htmlspecialchars($studente["studente_email"])?></td>
                <td><?= htmlspecialchars($studente["corsi"])?></td>
                <td><?= htmlspecialchars($studente["studente_role"])?></td>
                <td class="azioni">
                    <button class="btn-azione" onclick="toggleForm('studente', <?= $studente['studente_id']?>)">Modifica</button>
                    <form method="post">
                        <input type="hidden" name="studente_id" value="<?= $studente['studente_id'] ?>">
                        <button class="btn-azione" type="submit" name="action" value="delete_user">Elimina</button>
                    </form>
                </td>
            </tr>
            <tr class="form-row" id="studente-form-<?= $studente['studente_id'] ?>" style="display: none;">
                <td colspan="6">
                    <form method="post">
                        <input type="hidden" name="studente_id" value="<?= $studente['studente_id'] ?>">
                        <label>
                            Nome:
                            <input type="text" name="nome" value="<?= htmlspecialchars($studente["studente_nome"]) ?>" required>
                        </label>
                        <label>
                            Ruolo:
                            <!-- Menu a tendina per il Ruolo -->
                            <select name="role" required>
                                <option value="studente" <?= $studente["studente_role"] === 'studente' ? 'selected' : '' ?>>Studente</option>
                                <option value="professore" <?= $studente["studente_role"] === 'professore' ? 'selected' : '' ?>>Professore</option>
                            </select>
                        </label>
                        <button type="submit" name="action" value="update_user">Salva</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Professori -->

    <h2>Professori</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Corsi</th>
            <th>Ruolo</th>
            <th>Azioni</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($professori as $professore): ?>
            <tr>
                <td><?= $professore['professore_id'] ?></td>
                <td><?= htmlspecialchars($professore["professore_nome"])?></td>
                <td><?= htmlspecialchars($professore["professore_email"])?></td>
                <td><?= htmlspecialchars($professore["corsi"])?></td>
                <td><?= htmlspecialchars($professore["professore_role"])?></td>
                <td class="azioni">
                    <button class="btn-azione" onclick="toggleForm('professore', <?= $professore['professore_id']?>)">Modifica</button>
                    <form method="post">
                        <input type="hidden" name="professore_id" value="<?= $professore['professore_id'] ?>">
                        <button class="btn-azione" type="submit" name="action" value="delete_user">Elimina</button>
                    </form>
                </td>
            </tr>
            <tr class="form-row" id="professore-form-<?= $professore['professore_id'] ?>" style="display: none;">
                <td colspan="6">
                    <form method="post">
                        <input type="hidden" name="professore_id" value="<?= $professore['professore_id'] ?>">
                        <label>
                            Nome:
                            <input type="text" name="nome" value="<?= htmlspecialchars($professore["professore_nome"]) ?>" required>
                        </label>
                        <label>
                            Ruolo:
                            <!-- Menu a tendina per il Ruolo -->
                            <select name="role" required>
                                <option value="studente" <?= $professore["professore_role"] === 'studente' ? 'selected' : '' ?>>Studente</option>
                                <option value="professore" <?= $professore["professore_role"] === 'professore' ? 'selected' : '' ?>>Professore</option>
                            </select>
                        </label>
                        <button type="submit" name="action" value="update_user">Salva</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Corsi -->

    <h2>Corsi</h2>
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
        <?php foreach ($corsi as $corso): ?>
            <tr>
                <td><?= $corso['corso_id'] ?></td>
                <td><?= htmlspecialchars($corso["corso_titolo"])?></td>
                <td><?= htmlspecialchars($corso["corso_descrizione"])?></td>
                <td><?= htmlspecialchars($corso["professore_nome"])?></td>
                <td><?= htmlspecialchars($corso["studenti"])?></td>
                <td class="azioni">
                    <button class="btn-azione" onclick="toggleForm('corso', <?= $corso['corso_id']?>)">Modifica</button>
                    <form method="post">
                        <input type="hidden" name="corso_id" value="<?= $corso['corso_id'] ?>">
                        <button class="btn-azione" type="submit" name="action" value="delete_course">Elimina</button>
                    </form>
                </td>
            </tr>
            <tr class="form-row" id="corso-form-<?= $corso['corso_id'] ?>" style="display: none;">
                <td colspan="6">
                    <form method="post">
                        <input type="hidden" name="corso_id" value="<?= $corso['corso_id'] ?>">
                        <label>
                            Titolo:
                            <input type="text" name="titolo" value="<?= htmlspecialchars($corso["corso_titolo"]) ?>" required>
                        </label>
                        <label>
                            Descrizione:
                            <input type="text" name="descrizione" value="<?= htmlspecialchars($corso["corso_descrizione"]) ?>" required>
                        </label>
                        <label>
                            ID Professore:
                            <input type="text" name="professore_id" value="<?= htmlspecialchars($corso["professore_id"]) ?>" required>
                        </label>

                        <button type="submit" name="action" value="update_course">Salva</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Crea Utente -->
    <div style="display: flex; flex-direction: row; width: 100%;justify-content: center;align-items: center;">
        <button style="margin: 10px 10px;" onclick="toggle('utente')">Crea Utente</button>
        <button style="margin: 10px 10px;" onclick="toggle('corso')">Crea Corso</button>
    </div>

    <form id="utente" method="POST" style="display: none">
        <label>
            <input type="text" name="nome" placeholder="Nome" required/>
        </label>
        <label>
            <input type="email" name="email" placeholder="Email" required/>
        </label>
        <label>
            <input type="password" name="password" placeholder="Password" required/>
        </label>
        <label>
            <select name="role" required>
                <option value="studente">Studente</option>
                <option value="professore">Professore</option>
            </select>
        </label>
        <button type="submit" name="action" value="create_user">Crea Utente</button>
    </form>

    <!-- Crea Corso -->


    <form id="corso" method="POST" style="display: none">
        <label>
            <input type="text" name="titolo" placeholder="Titolo" required/>
        </label>
        <label>
            <input type="text" name="descrizione" placeholder="Descrizione" required/>
        </label>
        <label>
            <input type="text" name="professore_id" placeholder="ID Professore" required/>
        </label>
        <button type="submit" name="action" value="create_course">Crea Corso</button>
    </form>

    <!-- Logout -->

    <form method="POST" class="logout">
        <button type="submit" name="action" value="logout">Logout</button>
    </form>

</body>

</html>