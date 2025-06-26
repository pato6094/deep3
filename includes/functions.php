<?php
session_start();

// Funzione per generare deeplink da link originale
function generate_deeplink($url) {
    if (strpos($url, 'youtube.com/watch') !== false) {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query['v'])) {
            return "youtube://watch?v=" . $query['v'];
        }
    }
    elseif (preg_match('#youtube\.com/@([\w\d]+)#', $url, $matches)) {
        $username = $matches[1];
        return "youtube://www.youtube.com/@" . $username;
    }
    elseif (strpos($url, 'instagram.com') !== false) {
        $path = trim(parse_url($url, PHP_URL_PATH), '/');
        $parts = explode('/', $path);

        if ($parts[0] === 'p' && isset($parts[1])) {
            $postId = $parts[1];
            return "instagram://media?id=" . $postId;
        } else {
            $username = $parts[0];
            return "instagram://user?username=" . $username;
        }
    }
    elseif (strpos($url, 'twitch.tv') !== false) {
        $parts = explode('/', rtrim(parse_url($url, PHP_URL_PATH), '/'));
        $username = end($parts);
        return "twitch://stream/" . $username;
    }
    elseif (strpos($url, 'amazon.') !== false) {
        $url_no_proto = preg_replace("#^https?://#", "", $url);
        return "amazon://" . $url_no_proto;
    }
    return $url;
}

// Funzione per verificare se l'utente è loggato
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Funzione per verificare se l'utente è admin
function is_admin($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user && $user['is_admin'] == 1;
}

// Funzione per verificare se l'utente ha un abbonamento attivo (AGGIORNATA)
function has_active_subscription($pdo, $user_id) {
    // Prima controlla se ci sono abbonamenti scaduti da processare
    check_expired_subscriptions($pdo);
    
    $stmt = $pdo->prepare("
        SELECT subscription_status, subscription_end, next_billing_date, grace_period_until 
        FROM users 
        WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return false;
    
    // Se è in periodo di grazia, considera ancora attivo
    if ($user['grace_period_until'] && strtotime($user['grace_period_until']) > time()) {
        return true;
    }
    
    return $user['subscription_status'] === 'active' && 
           ($user['subscription_end'] === null || strtotime($user['subscription_end']) > time());
}

// Funzione per controllare abbonamenti scaduti (NUOVA)
function check_expired_subscriptions($pdo) {
    try {
        // Trova utenti con abbonamento attivo ma che hanno superato la next_billing_date
        $stmt = $pdo->prepare("
            SELECT id, subscription_id, next_billing_date, last_status_check
            FROM users 
            WHERE subscription_status = 'active' 
            AND next_billing_date IS NOT NULL 
            AND next_billing_date < NOW()
            AND (last_status_check IS NULL OR last_status_check < DATE_SUB(NOW(), INTERVAL 1 DAY))
        ");
        $stmt->execute();
        $expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
            
            // Log per debug
            error_log("Utente {$user['id']} in periodo di grazia fino al $grace_period");
        }
        
        // Trova utenti il cui periodo di grazia è scaduto
        $stmt = $pdo->prepare("
            SELECT id 
            FROM users 
            WHERE subscription_status = 'active' 
            AND grace_period_until IS NOT NULL 
            AND grace_period_until < NOW()
        ");
        $stmt->execute();
        $grace_expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
            
            // Log per debug
            error_log("Utente {$user['id']} downgraded a piano gratuito per mancato rinnovo");
        }
        
    } catch (Exception $e) {
        error_log("Errore controllo abbonamenti scaduti: " . $e->getMessage());
    }
}

// Funzione per aggiornare la data di rinnovo dopo un pagamento (NUOVA)
function update_billing_date($pdo, $user_id) {
    try {
        $next_billing = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET next_billing_date = :next_billing,
                grace_period_until = NULL,
                last_status_check = NOW(),
                subscription_status = 'active'
            WHERE id = :user_id
        ");
        
        return $stmt->execute([
            ':next_billing' => $next_billing,
            ':user_id' => $user_id
        ]);
    } catch (Exception $e) {
        error_log("Errore aggiornamento data billing: " . $e->getMessage());
        return false;
    }
}

// Funzione per contare i deeplink dell'utente nel mese corrente
function count_monthly_deeplinks($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM deeplinks 
        WHERE user_id = :user_id 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

// Funzione per verificare se l'utente può creare più deeplink
function can_create_deeplink($pdo, $user_id) {
    if (has_active_subscription($pdo, $user_id)) {
        return true; // Utenti premium illimitati
    }
    
    $monthly_count = count_monthly_deeplinks($pdo, $user_id);
    return $monthly_count < 5; // Limite di 5 per utenti gratuiti
}

// Funzione per verificare se un deeplink è scaduto
function is_deeplink_expired($created_at, $user_has_subscription) {
    if ($user_has_subscription) {
        return false; // I link degli utenti premium non scadono mai
    }
    
    $created_timestamp = strtotime($created_at);
    $expiry_timestamp = $created_timestamp + (5 * 24 * 60 * 60); // 5 giorni
    
    return time() > $expiry_timestamp;
}

// Funzione per calcolare i giorni rimanenti prima della scadenza
function get_days_until_expiry($created_at, $user_has_subscription) {
    if ($user_has_subscription) {
        return null; // I link degli utenti premium non scadono mai
    }
    
    $created_timestamp = strtotime($created_at);
    $expiry_timestamp = $created_timestamp + (5 * 24 * 60 * 60); // 5 giorni
    $days_remaining = ceil(($expiry_timestamp - time()) / (24 * 60 * 60));
    
    return max(0, $days_remaining);
}

// Funzione per ottenere il totale dei click dell'utente
function get_total_clicks($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT SUM(clicks) as total_clicks 
        FROM deeplinks 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_clicks'] ?? 0;
}

// Funzione per ottenere le statistiche dettagliate di un deeplink
function get_deeplink_stats($pdo, $deeplink_id, $user_id) {
    $stmt = $pdo->prepare("
        SELECT id, original_url, clicks, created_at,
               DATE(created_at) as creation_date
        FROM deeplinks 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([
        ':id' => $deeplink_id,
        ':user_id' => $user_id
    ]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Funzione per ottenere i click giornalieri di un deeplink (per grafici futuri)
function get_daily_clicks($pdo, $deeplink_id, $user_id, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, clicks
        FROM deeplinks 
        WHERE id = :id AND user_id = :user_id
        AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ORDER BY created_at DESC
    ");
    $stmt->execute([
        ':id' => $deeplink_id,
        ':user_id' => $user_id,
        ':days' => $days
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Funzione per ottenere le performance mensili dell'utente
function get_monthly_performance($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(created_at) as month,
            YEAR(created_at) as year,
            COUNT(*) as deeplinks_created,
            SUM(clicks) as total_clicks,
            AVG(clicks) as avg_clicks
        FROM deeplinks 
        WHERE user_id = :user_id
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Funzioni Admin (AGGIORNATE)
function get_admin_stats($pdo) {
    // Controlla abbonamenti scaduti prima di calcolare le statistiche
    check_expired_subscriptions($pdo);
    
    $stats = [];
    
    // Utenti totali
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Utenti premium (inclusi quelli in periodo di grazia)
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE subscription_status = 'active' 
        OR (grace_period_until IS NOT NULL AND grace_period_until > NOW())
    ");
    $stats['premium_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Utenti gratuiti
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE subscription_status IN ('free', 'expired', 'cancelled')
        AND (grace_period_until IS NULL OR grace_period_until <= NOW())
    ");
    $stats['free_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Deeplink totali
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM deeplinks");
    $stats['total_deeplinks'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Click totali
    $stmt = $pdo->query("SELECT SUM(clicks) as total FROM deeplinks");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_clicks'] = $result['total'] ?? 0;
    
    // Nuovi utenti oggi
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()");
    $stats['new_users_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nuovi utenti questa settimana
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['new_users_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nuovi utenti questo mese
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stats['new_users_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Click oggi
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM click_logs 
        WHERE DATE(clicked_at) = CURDATE()
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['clicks_today'] = $result ? $result['total'] : 0;
    
    // Deeplink creati oggi
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM deeplinks WHERE DATE(created_at) = CURDATE()");
    $stats['deeplinks_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Media click per deeplink
    $avg_clicks = $stats['total_deeplinks'] > 0 ? $stats['total_clicks'] / $stats['total_deeplinks'] : 0;
    $stats['avg_clicks_per_deeplink'] = $avg_clicks;
    
    return $stats;
}
?>