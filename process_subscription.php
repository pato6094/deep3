<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['subscriptionID']) || !isset($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti']);
    exit;
}

$subscription_id = $input['subscriptionID'];
$user_id = $input['user_id'];

// Verifica che l'utente corrisponda alla sessione
if ($user_id != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Utente non valido']);
    exit;
}

try {
    // Calcola le date per il nuovo abbonamento
    $subscription_start = date('Y-m-d H:i:s');
    $subscription_end = date('Y-m-d H:i:s', strtotime('+1 month'));
    $next_billing_date = date('Y-m-d H:i:s', strtotime('+1 month'));
    
    // Aggiorna lo stato dell'abbonamento dell'utente con le nuove colonne
    $stmt = $pdo->prepare("
        UPDATE users 
        SET subscription_status = 'active', 
            subscription_id = :subscription_id,
            subscription_start = :subscription_start,
            subscription_end = :subscription_end,
            next_billing_date = :next_billing_date,
            grace_period_until = NULL,
            last_status_check = NOW()
        WHERE id = :user_id
    ");
    
    $result = $stmt->execute([
        ':subscription_id' => $subscription_id,
        ':subscription_start' => $subscription_start,
        ':subscription_end' => $subscription_end,
        ':next_billing_date' => $next_billing_date,
        ':user_id' => $user_id
    ]);
    
    if ($result) {
        // Log per debug
        error_log("Abbonamento attivato per utente $user_id - Prossimo rinnovo: $next_billing_date");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Abbonamento attivato',
            'next_billing' => $next_billing_date
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore database']);
    }
} catch (Exception $e) {
    error_log("Errore attivazione abbonamento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore server']);
}
?>