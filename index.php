<?php
global $conn;
require_once './includes/config.php';
require_once './includes/auth.php';

// Funzione per controllare se l'utente è loggato e reindirizzarlo
function redirectIfLoggedIn(): void
{
    if (isLoggedIn()) {
        // Reindirizza l'utente alla pagina corretta in base al suo ruolo
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
}

// Funzione per gestire la registrazione
function registerUser($nome, $email, $password, $role): string
{
    global $conn;
    $password_hashed = password_hash($password, PASSWORD_DEFAULT); // Criptazione della password

    // Query per inserire il nuovo utente
    $stmt = $conn->prepare('INSERT INTO utenti (nome, email, password, role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $nome, $email, $password_hashed, $role);
    if ($stmt->execute()) {
        return 'Registrazione avvenuta con successo!';
    } else {
        return 'Errore durante la registrazione!';
    }
}

// Funzione per gestire il login
function loginUser($email, $password) {
    global $conn;

    // Query per recuperare i dati dell'utente dal DB
    $stmt = $conn->prepare("SELECT * FROM utenti WHERE email=?");
    $stmt->bind_param('s', $email); // Associa il parametro $email come stringa
    $stmt->execute();
    $result = $stmt->get_result();

    // Se la query recupera l'utente, lo associa come array
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Salva i dati utente nella sessione
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Reindirizza in base al ruolo
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } elseif ($user['role'] === 'professore') {
                header('Location: professore.php');
            } elseif ($user['role'] === 'studente') {
                header('Location: studente.php');
            }
            exit();
        } else {
            return 'Credenziali errate.';
        }
    } else {
        return 'Utente non trovato.';
    }
}

// Chiamata alla funzione per controllare se l'utente è loggato
redirectIfLoggedIn();

// Variabili per i messaggi di errore e successo
$success_message = '';
$error_message = '';

// Gestione della registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Registrazione con i dati del form
    $success_message = registerUser($_POST['nome'], $_POST['email'], $_POST['password'], $_POST['role']);
}

// Gestione del login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Login con i dati del form
    $error_message = loginUser($_POST['email'], $_POST['password']);
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