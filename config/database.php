<?php
// Database configuration
$host = 'localhost';
$dbname = 'buonafor_deep';
$username = 'buonafor_dee';
$password = 'Pianeta123!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Aggiungi la colonna per tracciare se l'utente ha richiesto la cancellazione
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN cancellation_requested TINYINT(1) DEFAULT 0 AFTER subscription_end");
    } catch (PDOException $e) {
        // Colonna già esistente, ignora l'errore
    }
    
    // Aggiungi la colonna updated_at per tracciare quando viene premuto il bottone
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    } catch (PDOException $e) {
        // Colonna già esistente, ignora l'errore
    }
    
} catch (PDOException $e) {
    die("Errore connessione DB: " . $e->getMessage());
}
?>