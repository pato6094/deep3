<?php
// Database configuration
$host = 'localhost';
$dbname = 'buonafor_deep';
$username = 'buonafor_dee';
$password = 'Pianeta123!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Aggiungi le nuove colonne se non esistono
    try {
        // Colonna per tracciare la prossima data di rinnovo
        $pdo->exec("ALTER TABLE users ADD COLUMN next_billing_date DATETIME NULL AFTER subscription_end");
    } catch (PDOException $e) {
        // Colonna già esistente, ignora l'errore
    }
    
    try {
        // Colonna per tracciare l'ultimo controllo dello stato abbonamento
        $pdo->exec("ALTER TABLE users ADD COLUMN last_status_check DATETIME NULL AFTER next_billing_date");
    } catch (PDOException $e) {
        // Colonna già esistente, ignora l'errore
    }
    
    try {
        // Colonna per tracciare se l'abbonamento è in periodo di grazia
        $pdo->exec("ALTER TABLE users ADD COLUMN grace_period_until DATETIME NULL AFTER last_status_check");
    } catch (PDOException $e) {
        // Colonna già esistente, ignora l'errore
    }
    
    try {
        // Colonna per tracciare se l'utente ha richiesto la cancellazione
        $pdo->exec("ALTER TABLE users ADD COLUMN cancellation_requested TINYINT(1) DEFAULT 0 AFTER grace_period_until");
    } catch (PDOException $e) {
        // Colonna già esistente, ignora l'errore
    }
    
} catch (PDOException $e) {
    die("Errore connessione DB: " . $e->getMessage());
}
?>