<?php
/**
 * Script CRON per controllare abbonamenti scaduti
 * Da eseguire giornalmente: 0 2 * * * /usr/bin/php /path/to/cron_check_subscriptions.php
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Verifica che lo script sia eseguito da CLI o con una chiave segreta
$secret_key = 'deeplink_cron_2025'; // Cambia questa chiave!

if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
        http_response_code(403);
        die('Accesso negato');
    }
}

echo "=== CONTROLLO ABBONAMENTI SCADUTI ===\n";
echo "Data/Ora: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Trova utenti con abbonamento attivo ma che hanno superato la next_billing_date
    $stmt = $pdo->prepare("
        SELECT id, name, email, subscription_id, next_billing_date, last_status_check
        FROM users 
        WHERE subscription_status = 'active' 
        AND next_billing_date IS NOT NULL 
        AND next_billing_date < NOW()
        AND (grace_period_until IS NULL OR grace_period_until < NOW())
        AND (last_status_check IS NULL OR last_status_check < DATE_SUB(NOW(), INTERVAL 1 DAY))
    ");
    $stmt->execute();
    $expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Utenti con abbonamento scaduto da processare: " . count($expired_users) . "\n";
    
    foreach ($expired_users as $user) {
        // Imposta periodo di grazia di 3 giorni
        $grace_period = date('Y-m-d H:i:s', strtotime('+3 days'));
        
        $update_stmt = $pdo->prepare("
            UPDATE users 
            SET grace_period_until = :grace_period,
                last_status_check = NOW()
            WHERE id = :user_id
        ");
        $update_stmt->execute([
            ':grace_period' => $grace_period,
            ':user_id' => $user['id']
        ]);
        
        echo "- Utente {$user['name']} ({$user['email']}) in periodo di grazia fino al $grace_period\n";
    }
    
    // 2. Trova utenti il cui periodo di grazia è scaduto
    $stmt = $pdo->prepare("
        SELECT id, name, email, subscription_id
        FROM users 
        WHERE subscription_status = 'active' 
        AND grace_period_until IS NOT NULL 
        AND grace_period_until < NOW()
    ");
    $stmt->execute();
    $grace_expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nUtenti con periodo di grazia scaduto: " . count($grace_expired_users) . "\n";
    
    foreach ($grace_expired_users as $user) {
        // Downgrade a piano gratuito
        $downgrade_stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_status = 'expired',
                grace_period_until = NULL,
                next_billing_date = NULL
            WHERE id = :user_id
        ");
        $downgrade_stmt->execute([':user_id' => $user['id']]);
        
        echo "- Utente {$user['name']} ({$user['email']}) downgraded a piano gratuito\n";
        
        // Qui potresti inviare una email di notifica all'utente
        // send_downgrade_notification($user['email'], $user['name']);
    }
    
    // 3. Statistiche finali
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN subscription_status = 'active' THEN 1 END) as active_subs,
            COUNT(CASE WHEN grace_period_until IS NOT NULL AND grace_period_until > NOW() THEN 1 END) as grace_period,
            COUNT(CASE WHEN subscription_status IN ('expired', 'cancelled') THEN 1 END) as expired_subs
        FROM users 
        WHERE subscription_id IS NOT NULL
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== STATISTICHE ABBONAMENTI ===\n";
    echo "Abbonamenti attivi: {$stats['active_subs']}\n";
    echo "In periodo di grazia: {$stats['grace_period']}\n";
    echo "Scaduti/Cancellati: {$stats['expired_subs']}\n";
    
    echo "\n=== CONTROLLO COMPLETATO ===\n";
    
} catch (Exception $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
    error_log("Errore cron abbonamenti: " . $e->getMessage());
}

// Funzione per inviare notifica di downgrade (da implementare)
function send_downgrade_notification($email, $name) {
    // Implementa l'invio email qui
    // mail($email, "Abbonamento DeepLink Pro scaduto", $message);
}
?>