<?php
//Connessione al database
$host = "localhost";
$user = "root";
$password = "root";
$db_name = "crud_db";

$conn = new mysqli($host, $user, $password, $db_name);

//Controllo errori di connessione
if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

//echo "Connessione riuscita";
