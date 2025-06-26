<?php
/**
 * Funzioni helper per la gestione abbonamenti senza webhook
 */

/**
 * Controlla se un utente dovrebbe essere in periodo di grazia
 */
function should_be_in_grace_period($user_data) {
    if ($user_data['subscription_status'] !== 'active') {
        return false;
    }
    
    if (!$user_data['next_billing_date']) {
        return false;
    }
    
    // Se la data di rinnovo è passata e non è già in periodo di grazia
    $billing_date = strtotime($user_data['next_billing_date']);
    $now = time();
    
    return $billing_date < $now && 
           (!$user_data['grace_period_until'] || strtotime($user_data['grace_period_until']) < $now);
}

/**
 * Controlla se un utente dovrebbe essere downgraded
 */
function should_be_downgraded($user_data) {
    if ($user_data['subscription_status'] !== 'active') {
        return false;
    }
    
    if (!$user_data['grace_period_until']) {
        return false;
    }
    
    // Se il periodo di grazia è scaduto
    return strtotime($user_data['grace_period_until']) < time();
}

/**
 * Simula il controllo dello stato abbonamento
 * Questa funzione viene chiamata ogni volta che l'utente accede al sito
 */
function simulate_subscription_check($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT subscription_status, next_billing_date, grace_period_until, last_status_check
            FROM users 
            WHERE id = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['subscription_status'] !== 'active') {
            return;
        }
        
        $needs_update = false;
        $updates = [];
        
        // Controlla se dovrebbe essere in periodo di grazia
        if (should_be_in_grace_period($user)) {
            $grace_until = date('Y-m-d H:i:s', strtotime('+3 days'));
            $updates['grace_period_until'] = $grace_until;
            $needs_update = true;
            
            error_log("Utente $user_id messo in periodo di grazia fino al $grace_until");
        }
        
        // Controlla se dovrebbe essere downgraded
        if (should_be_downgraded($user)) {
            $updates['subscription_status'] = 'expired';
            $updates['grace_period_until'] = null;
            $updates['next_billing_date'] = null;
            $needs_update = true;
            
            error_log("Utente $user_id downgraded per mancato rinnovo");
        }
        
        // Aggiorna sempre last_status_check
        $updates['last_status_check'] = date('Y-m-d H:i:s');
        $needs_update = true;
        
        if ($needs_update) {
            $set_clauses = [];
            $params = [':user_id' => $user_id];
            
            foreach ($updates as $field => $value) {
                $set_clauses[] = "$field = :$field";
                $params[":$field"] = $value;
            }
            
            $sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = :user_id";
            $update_stmt = $pdo->prepare($sql);
            $update_stmt->execute($params);
        }
        
    } catch (Exception $e) {
        error_log("Errore controllo abbonamento per utente $user_id: " . $e->getMessage());
    }
}

/**
 * Invia notifica email di scadenza (da implementare)
 */
function send_expiry_notification($email, $name, $days_remaining) {
    // Implementa l'invio email
    $subject = "Il tuo abbonamento DeepLink Pro scade tra $days_remaining giorni";
    $message = "Ciao $name,\n\nIl tuo abbonamento Premium scadrà tra $days_remaining giorni...";
    
    // mail($email, $subject, $message);
    error_log("Notifica scadenza inviata a $email ($days_remaining giorni rimanenti)");
}

/**
 * Genera link per conferma pagamento manuale
 */
function generate_payment_confirmation_link() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    
    return "$protocol://$host$path/manual_payment_check.php";
}
?>