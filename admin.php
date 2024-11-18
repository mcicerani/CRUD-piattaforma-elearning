<?php
global $conn;
require_once "./includes/config.php";
require_once "./includes/auth.php";

//Controlla se utente è loggato e che il ruolo sia admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: index.php");
    exit();
}

//CRUD: Operazioni per utente admin
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create_user"])) {
        //CREATE - Aggiungi nuovo utente
        $nome = $_POST["nome"];
        $email = $_POST["email"];
        $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $role = $_POST["role"];

        $stmt = $conn->prepare("INSERT INTO utenti (nome, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nome, $email, $password, $role);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST["update_user"])) {
        //UPDATE - Modifica un utente esistente
        $id = $_POST["studente_id"];
        $nome = $_POST["nome"];
        $email = $_POST["email"];
        $role = $_POST["role"];

        $stmt = $conn->prepare("UPDATE utenti SET nome = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssss", $nome, $email, $password, $role);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST["delete_user"])) {
        //DELETE - Elimina un utente
        $id = $_POST["studente_id"];

        //Elimino prima iscrizioni
        $stmt_iscrizioni = $conn->prepare("DELETE FROM iscrizioni WHERE studente_id = ?");
        $stmt_iscrizioni->bind_param("i", $id);
        $stmt_iscrizioni->execute();
        $stmt_iscrizioni->close();

        //Elimino utente
        $stmt_utente = $conn->prepare("DELETE FROM utenti WHERE id = ?");
        $stmt_utente->bind_param("i", $id);
        $stmt_utente->execute();
        $stmt_utente->close();
    } elseif (isset($_POST["create_course"])) {
        //CREATE - Aggiungi nuovo corso
        $titolo = $_POST["titolo"];
        $descrizione = $_POST["descrizione"];
        $professore_id = $_POST["professore_id"];

        $stmt = $conn->prepare("INSERT INTO corsi (titolo, descrizione, professore_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $titolo, $descrizione, $professore_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST["update_course"])) {
        //UPDATE - Modifica un corso esistente
        $id = $_POST["corso_id"];
        $titolo = $_POST["titolo"];
        $descrizione = $_POST["descrizione"];
        $professore_id = $_POST["professore_id"];

        $stmt = $conn->prepare("UPDATE corsi SET titolo = ?, descrizione = ?, professore_id = ? WHERE id = ?");
        $stmt->bind_param("ssi", $titolo, $descrizione, $professore_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST["delete_course"])) {
        //DELETE - Elimina un corso
        $id = $_POST["corso_id"];

        //Elimino iscrizioni
        $stmt_iscrizioni = $conn->prepare("DELETE FROM iscrizioni WHERE corso_id = ?");
        $stmt_iscrizioni->bind_param("i", $id);
        $stmt_iscrizioni->execute();
        $stmt_iscrizioni->close();

        //Elimino corso
        $stmt_corso = $conn->prepare("DELETE FROM corsi WHERE id = ?");
        $stmt_corso->bind_param("i", $id);
        $stmt_corso->execute();
        $stmt_corso->close();
    }
}

//READ - Chiamo info utenti e corsi
$studenti = null;
$professori = null;
$corsi = null;
