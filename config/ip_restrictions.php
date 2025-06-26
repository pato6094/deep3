<?php
// Configurazione restrizioni IP per registrazioni

// CONFIGURAZIONE PRINCIPALE
$IP_RESTRICTION_CONFIG = [
    // Abilita/disabilita le restrizioni IP
    'enabled' => true,
    
    // Numero massimo di registrazioni per IP nelle ultime 24 ore
    'max_registrations_per_day' => 3,
    
    // Numero massimo di registrazioni per IP nelle ultime ore
    'max_registrations_per_hour' => 1,
    
    // Periodo di blocco in ore dopo aver raggiunto il limite
    'block_period_hours' => 24,
    
    // Lista di IP sempre bloccati (blacklist)
    'blacklisted_ips' => [
        // '192.168.1.100',
        // '10.0.0.50',
    ],
    
    // Lista di IP sempre permessi (whitelist) - bypassa tutte le restrizioni
    'whitelisted_ips' => [
        // '127.0.0.1',
        // '::1',
    ],
    
    // Blocca registrazioni da proxy/VPN noti (richiede servizio esterno)
    'block_proxies' => false,
    
    // Paesi bloccati (codici ISO 2 lettere) - richiede servizio di geolocalizzazione
    'blocked_countries' => [
        // 'CN', 'RU', 'KP'
    ],
    
    // Log delle attività sospette
    'log_attempts' => true,
    
    // Messaggio di errore personalizzato
    'error_message' => 'Troppe registrazioni da questo indirizzo IP. Riprova più tardi.',
];

// Funzione per ottenere l'IP reale del client
function get_client_ip() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Funzione per verificare se un IP è in whitelist
function is_ip_whitelisted($ip) {
    global $IP_RESTRICTION_CONFIG;
    return in_array($ip, $IP_RESTRICTION_CONFIG['whitelisted_ips']);
}

// Funzione per verificare se un IP è in blacklist
function is_ip_blacklisted($ip) {
    global $IP_RESTRICTION_CONFIG;
    return in_array($ip, $IP_RESTRICTION_CONFIG['blacklisted_ips']);
}

// Funzione per verificare le restrizioni IP
function check_ip_registration_limits($pdo, $ip) {
    global $IP_RESTRICTION_CONFIG;
    
    // Se le restrizioni sono disabilitate, permetti sempre
    if (!$IP_RESTRICTION_CONFIG['enabled']) {
        return ['allowed' => true];
    }
    
    // Se l'IP è in whitelist, permetti sempre
    if (is_ip_whitelisted($ip)) {
        return ['allowed' => true];
    }
    
    // Se l'IP è in blacklist, blocca sempre
    if (is_ip_blacklisted($ip)) {
        log_ip_attempt($pdo, $ip, 'blocked', 'IP in blacklist');
        return [
            'allowed' => false, 
            'reason' => 'IP bloccato',
            'message' => 'Il tuo indirizzo IP è stato bloccato.'
        ];
    }
    
    try {
        // Verifica registrazioni nelle ultime 24 ore
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM ip_registration_log 
            WHERE ip_address = :ip 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND action = 'registration'
        ");
        $stmt->execute([':ip' => $ip]);
        $daily_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($daily_count >= $IP_RESTRICTION_CONFIG['max_registrations_per_day']) {
            log_ip_attempt($pdo, $ip, 'blocked', 'Limite giornaliero raggiunto');
            return [
                'allowed' => false,
                'reason' => 'daily_limit',
                'message' => $IP_RESTRICTION_CONFIG['error_message'],
                'retry_after' => 24 * 3600 // 24 ore in secondi
            ];
        }
        
        // Verifica registrazioni nell'ultima ora
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM ip_registration_log 
            WHERE ip_address = :ip 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND action = 'registration'
        ");
        $stmt->execute([':ip' => $ip]);
        $hourly_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($hourly_count >= $IP_RESTRICTION_CONFIG['max_registrations_per_hour']) {
            log_ip_attempt($pdo, $ip, 'blocked', 'Limite orario raggiunto');
            return [
                'allowed' => false,
                'reason' => 'hourly_limit',
                'message' => 'Troppe registrazioni nell\'ultima ora. Riprova tra un\'ora.',
                'retry_after' => 3600 // 1 ora in secondi
            ];
        }
        
        return ['allowed' => true];
        
    } catch (Exception $e) {
        error_log("Errore verifica IP restrictions: " . $e->getMessage());
        // In caso di errore, permetti la registrazione per non bloccare utenti legittimi
        return ['allowed' => true];
    }
}

// Funzione per loggare un tentativo di registrazione
function log_ip_registration($pdo, $ip, $user_id = null) {
    log_ip_attempt($pdo, $ip, 'registration', 'Registrazione completata', $user_id);
}

// Funzione per loggare tentativi sospetti
function log_ip_attempt($pdo, $ip, $action, $details = '', $user_id = null) {
    global $IP_RESTRICTION_CONFIG;
    
    if (!$IP_RESTRICTION_CONFIG['log_attempts']) {
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ip_registration_log (ip_address, action, details, user_id, user_agent, created_at) 
            VALUES (:ip, :action, :details, :user_id, :user_agent, NOW())
        ");
        
        $stmt->execute([
            ':ip' => $ip,
            ':action' => $action,
            ':details' => $details,
            ':user_id' => $user_id,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Errore log IP attempt: " . $e->getMessage());
    }
}

// Funzione per ottenere statistiche IP (per admin)
function get_ip_statistics($pdo, $days = 7) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ip_address,
                COUNT(CASE WHEN action = 'registration' THEN 1 END) as registrations,
                COUNT(CASE WHEN action = 'blocked' THEN 1 END) as blocked_attempts,
                COUNT(*) as total_attempts,
                MAX(created_at) as last_activity
            FROM ip_registration_log 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY ip_address
            HAVING total_attempts > 1
            ORDER BY total_attempts DESC, last_activity DESC
            LIMIT 50
        ");
        
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore get IP statistics: " . $e->getMessage());
        return [];
    }
}

// Funzione per pulire vecchi log (da chiamare periodicamente)
function cleanup_old_ip_logs($pdo, $days_to_keep = 30) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM ip_registration_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute([':days' => $days_to_keep]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Errore cleanup IP logs: " . $e->getMessage());
        return 0;
    }
}
?>