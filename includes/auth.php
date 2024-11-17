<?php
//Avvio sessione
use JetBrains\PhpStorm\NoReturn;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

//Funzione per controllare se utente è loggato
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

//Funzione per controllare il ruolo
function hasRole($role): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

//Funzione di Logout
#[NoReturn] function logout(): void {
    session_unset();
    session_destroy();
    header("location: login.php");
    exit();
}