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
    
    // Aggiungi la colonna registration_ip per tracciare l'IP di registrazione
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN registration_ip VARCHAR(45) NULL AFTER email");
    } catch (PDOException $e) {
        // Colonna già esistente, ignora l'errore
    }
    
    // Crea tabella per il log delle registrazioni IP
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ip_registration_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action ENUM('registration', 'blocked', 'attempt') NOT NULL,
                details TEXT,
                user_id INT NULL,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_created (ip_address, created_at),
                INDEX idx_action_created (action, created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
    } catch (PDOException $e) {
        // Tabella già esistente o errore, ignora
        error_log("Errore creazione tabella ip_registration_log: " . $e->getMessage());
    }
    
} catch (PDOException $e) {
    die("Errore connessione DB: " . $e->getMessage());
}
?>