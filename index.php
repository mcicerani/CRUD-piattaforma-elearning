<?php
global $conn;
require './includes/config.php';
require_once './includes/auth.php';

//Controlla se utente è loggato
if (isLoggedIn()) {
    //Se loggato, reindirizza alla pagina in base al ruolo
    if (hasRole('admin')) {
        header('Location: admin.php');
        exit();
    } elseif (hasRole('professore')) {
        header('Location: professore.php');
        exit();
    } elseif (hasRole('studente')) {
        header('Location: studente.php');
        exit();
    }
}

//Gestione della registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $ruolo = $_POST['ruolo'];
    $password_hashed = password_hash($password, PASSWORD_DEFAULT); //Criptazione password

    //Query per inserire nuovo utente
    $stmt = $conn->prepare('INSERT into utenti (nome, email, password, ruolo) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $name, $email, $password, $ruolo);
    if ($stmt->execute()) {
        $success_message = 'Registrazione avvenuta con successo!';
    } else {
        $error_message = "Errore durante la registrazione!";
    }
}

//Gestione del login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    //Query per recuperare dati utente dal db
    $stmt = $conn->prepare("SELECT * FROM utenti WHERE email=?");
    $stmt->bind_param('s',$email); //associa il parametro $email come stringa
    $stmt->execute();
    $result = $stmt->get_result();

    //Se la query recupera l'utente lo associa a user come array dei dati
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            //Salva i dati utente nella sessione
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            //Reindirizza in base al ruolo
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } elseif ($user['role'] === 'professore') {
                header('Location: professore.php');
            } elseif ($user['role'] === 'studente') {
                header('Location: studente.php');
            }
            exit();
        } else {
            $error_message = "Credenziali errate.";
        }
    } else {
        $error_message = "Utente non trovato.";
    }
}
