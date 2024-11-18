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
function updateUser($id, $nome, $email, $password, $role): void
{
    global $conn;
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE utenti SET nome = ?, email = ?, password = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $nome, $email, $password_hashed, $role, $id);
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
function deleteCourse($id): void
{
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

// Controllo privilegi dell'admin
checkAdminPrivileges();

// CRUD: Operazioni per utente admin
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create_user"])) {
        // Creazione nuovo utente
        createUser($_POST["nome"], $_POST["email"], $_POST["password"], $_POST["role"]);
    } elseif (isset($_POST["update_user"])) {
        // Aggiornamento utente
        updateUser($_POST["studente_id"], $_POST["nome"], $_POST["email"], $_POST["password"], $_POST["role"]);
    } elseif (isset($_POST["delete_user"])) {
        // Eliminazione utente
        deleteUser($_POST["studente_id"]);
    } elseif (isset($_POST["create_course"])) {
        // Creazione nuovo corso
        createCourse($_POST["titolo"], $_POST["descrizione"], $_POST["professore_id"]);
    } elseif (isset($_POST["update_course"])) {
        // Aggiornamento corso
        updateCourse($_POST["corso_id"], $_POST["titolo"], $_POST["descrizione"], $_POST["professore_id"]);
    } elseif (isset($_POST["delete_course"])) {
        // Eliminazione corso
        deleteCourse($_POST["corso_id"]);
    }
}

//READ - Chiamo info utenti e corsi
$studenti = null;
$professori = null;
$corsi = null;
