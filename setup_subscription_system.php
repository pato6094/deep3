<?php
/**
 * Script per configurare il sistema di gestione abbonamenti
 * Esegui questo script UNA VOLTA per aggiungere le colonne necessarie
 */

require_once 'config/database.php';

echo "<h2>Setup Sistema Gestione Abbonamenti</h2>\n";

try {
    // 1. Aggiungi colonna next_billing_date
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN next_billing_date DATETIME NULL AFTER subscription_end");
        echo "✓ Colonna 'next_billing_date' aggiunta con successo<br>\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "• Colonna 'next_billing_date' già esistente<br>\n";
        } else {
            throw $e;
        }
    }
    
    // 2. Aggiungi colonna last_status_check
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_status_check DATETIME NULL AFTER next_billing_date");
        echo "✓ Colonna 'last_status_check' aggiunta con successo<br>\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "• Colonna 'last_status_check' già esistente<br>\n";
        } else {
            throw $e;
        }
    }
    
    // 3. Aggiungi colonna grace_period_until
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN grace_period_until DATETIME NULL AFTER last_status_check");
        echo "✓ Colonna 'grace_period_until' aggiunta con successo<br>\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "• Colonna 'grace_period_until' già esistente<br>\n";
        } else {
            throw $e;
        }
    }
    
    // 4. Aggiungi colonna custom_name alla tabella deeplinks se non esiste
    try {
        $pdo->exec("ALTER TABLE deeplinks ADD COLUMN custom_name VARCHAR(50) NULL UNIQUE AFTER title");
        echo "✓ Colonna 'custom_name' aggiunta alla tabella deeplinks<br>\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "• Colonna 'custom_name' già esistente nella tabella deeplinks<br>\n";
        } else {
            throw $e;
        }
    }
    
    // 5. Aggiungi colonna title alla tabella deeplinks se non esiste
    try {
        $pdo->exec("ALTER TABLE deeplinks ADD COLUMN title VARCHAR(255) NULL AFTER user_id");
        echo "✓ Colonna 'title' aggiunta alla tabella deeplinks<br>\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "• Colonna 'title' già esistente nella tabella deeplinks<br>\n";
        } else {
            throw $e;
        }
    }
    
    // 6. Aggiorna gli utenti Premium esistenti con next_billing_date
    $stmt = $pdo->prepare("
        UPDATE users 
        SET next_billing_date = DATE_ADD(subscription_end, INTERVAL -1 DAY)
        WHERE subscription_status = 'active' 
        AND subscription_end IS NOT NULL 
        AND next_billing_date IS NULL
    ");
    $updated = $stmt->execute();
    $affected_rows = $stmt->rowCount();
    
    if ($affected_rows > 0) {
        echo "✓ Aggiornati $affected_rows utenti Premium con next_billing_date<br>\n";
    } else {
        echo "• Nessun utente Premium da aggiornare<br>\n";
    }
    
    // 7. Mostra statistiche attuali
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN subscription_status = 'active' THEN 1 END) as active_subs,
            COUNT(CASE WHEN next_billing_date IS NOT NULL THEN 1 END) as with_billing_date,
            COUNT(CASE WHEN grace_period_until IS NOT NULL AND grace_period_until > NOW() THEN 1 END) as in_grace_period
        FROM users
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<br><h3>Statistiche Attuali:</h3>\n";
    echo "• Utenti totali: {$stats['total_users']}<br>\n";
    echo "• Abbonamenti attivi: {$stats['active_subs']}<br>\n";
    echo "• Con data di rinnovo: {$stats['with_billing_date']}<br>\n";
    echo "• In periodo di grazia: {$stats['in_grace_period']}<br>\n";
    
    echo "<br><h3>Prossimi Passi:</h3>\n";
    echo "1. Configura un cron job per eseguire 'cron_check_subscriptions.php' ogni giorno<br>\n";
    echo "2. Esempio cron: <code>0 2 * * * /usr/bin/php /path/to/cron_check_subscriptions.php</code><br>\n";
    echo "3. Oppure esegui manualmente: <a href='cron_check_subscriptions.php?key=deeplink_cron_2025'>Controlla Abbonamenti</a><br>\n";
    echo "4. Testa il sistema con un abbonamento di prova<br>\n";
    
    echo "<br><strong>✅ Setup completato con successo!</strong><br>\n";
    
} catch (Exception $e) {
    echo "<br><strong>❌ Errore durante il setup:</strong> " . $e->getMessage() . "<br>\n";
}
?>