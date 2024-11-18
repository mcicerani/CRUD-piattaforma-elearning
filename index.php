<?php
global $conn;
require_once './includes/config.php';
require_once './includes/auth.php';

//Controlla se utente è loggato
if (isLoggedIn()) {
    //Se loggato, reindirizza alla pagina in base al role
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
    $role = $_POST['role'];
    $password_hashed = password_hash($password, PASSWORD_DEFAULT); //Criptazione password

    //Query per inserire nuovo utente
    $stmt = $conn->prepare('INSERT into utenti (nome, email, password, role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $nome, $email, $password_hashed, $role);
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

            //Reindirizza in base al role
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
?>

<!-- HTML Login & Registrazione -->
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/style.css">
    <script src="./js/index.js" defer></script> <!-- Importa il JS alla fine del body -->
    <title>Login & Registrazione</title>
</head>
<body>
<div class="head">
    <!-- Puoi aggiungere un'intestazione qui -->
</div>
<div class="content-container">
    <div id="form-container">
        <!-- I form vengono inseriti dinamicamente qui -->
    </div>
</div>
</body>
</html>