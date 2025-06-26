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
        
        // 📸 GESTIONE POST INSTAGRAM - Supporto generico per /p/
        if ($parts[0] === 'p' && isset($parts[1])) {
            $postId = $parts[1];
            
            // Rimuove eventuali parametri dall'ID del post (come ?img_index=1)
            $postId = preg_replace('/[^a-zA-Z0-9_-].*$/', '', $postId);
            
            // FORMATO UNIVERSALE: Il più compatibile con tutte le versioni di Instagram
            return "instagram://media?id=" . $postId;
        }
        // 🎬 GESTIONE REEL INSTAGRAM - Supporto generico per /reel/
        elseif ($parts[0] === 'reel' && isset($parts[1])) {
            $reelId = $parts[1];
            
            // Rimuove eventuali parametri dall'ID del reel (come ?igsh=...)
            $reelId = preg_replace('/[^a-zA-Z0-9_-].*$/', '', $reelId);
            
            // FORMATO UNIVERSALE: Il più compatibile per i reel
            return "instagram://reel?id=" . $reelId;
        }
        // 📺 GESTIONE STORIES INSTAGRAM
        elseif ($parts[0] === 'stories' && isset($parts[1])) {
            $username = $parts[1];
            
            // Se c'è uno story ID specifico
            if (isset($parts[2])) {
                $storyId = $parts[2];
                return "instagram://story?story_id=" . $storyId . "&username=" . $username;
            }
            
            return "instagram://story-camera?entrypoint=story_camera";
        }
        // 👤 GESTIONE PROFILI UTENTE INSTAGRAM
        else {
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
        // 🛒 GESTIONE AMAZON MIGLIORATA - Supporto per dispositivi mobili
        
        // Estrai il dominio Amazon (amazon.com, amazon.it, amazon.co.uk, etc.)
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        
        // Cerca l'ASIN (Amazon Standard Identification Number) nell'URL
        $asin = null;
        
        // Pattern per trovare ASIN in diversi formati di URL Amazon
        $patterns = [
            '/\/dp\/([A-Z0-9]{10})/',           // /dp/ASIN
            '/\/gp\/product\/([A-Z0-9]{10})/',  // /gp/product/ASIN
            '/\/product\/([A-Z0-9]{10})/',      // /product/ASIN
            '/\/exec\/obidos\/ASIN\/([A-Z0-9]{10})/', // Formato vecchio
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches)) {
                $asin = $matches[1];
                break;
            }
        }
        
        // Se non troviamo ASIN nel path, cerca nei parametri query
        if (!$asin && $query) {
            parse_str($query, $params);
            if (isset($params['asin'])) {
                $asin = $params['asin'];
            } elseif (isset($params['ASIN'])) {
                $asin = $params['ASIN'];
            }
        }
        
        // Se abbiamo trovato un ASIN, usa il formato deeplink ottimizzato
        if ($asin && preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            // FORMATO UNIVERSALE per Amazon - Il più compatibile
            return "amazon://product/" . $asin;
        }
        
        // Se è una ricerca Amazon
        if (strpos($path, '/s') === 0 && $query) {
            parse_str($query, $params);
            if (isset($params['k'])) {
                $searchTerm = urlencode($params['k']);
                return "amazon://search?k=" . $searchTerm;
            }
        }
        
        // Se è una categoria Amazon
        if (preg_match('/\/([^\/]+)\/b\//', $path, $matches)) {
            $category = $matches[1];
            return "amazon://category/" . $category;
        }
        
        // Fallback: usa il formato generico (meno affidabile ma meglio di niente)
        $cleanUrl = preg_replace('#^https?://#', '', $url);
        return "amazon://" . $cleanUrl;
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

// Funzione per verificare se l'utente ha un abbonamento attivo
function has_active_subscription($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT subscription_status, subscription_end, cancellation_requested
        FROM users 
        WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return false;
    
    // Se ha richiesto la cancellazione, è ancora attivo fino alla scadenza
    if ($user['cancellation_requested'] && $user['subscription_end'] && strtotime($user['subscription_end']) > time()) {
        return true;
    }
    
    return $user['subscription_status'] === 'active' && 
           ($user['subscription_end'] === null || strtotime($user['subscription_end']) > time());
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

// Funzioni Admin
function get_admin_stats($pdo) {
    $stats = [];
    
    // Utenti totali
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Utenti premium (inclusi quelli con cancellazione richiesta ma ancora attivi)
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE subscription_status = 'active'
        AND (cancellation_requested = 0 OR (cancellation_requested = 1 AND subscription_end > NOW()))
    ");
    $stats['premium_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Utenti gratuiti
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE subscription_status IN ('free', 'expired', 'cancelled')
        OR (subscription_status = 'active' AND cancellation_requested = 1 AND subscription_end <= NOW())
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

// Funzione per processare automaticamente gli abbonamenti scaduti
function process_expired_subscriptions($pdo) {
    try {
        // Trova utenti con abbonamento attivo che hanno richiesto cancellazione e sono scaduti
        $stmt = $pdo->prepare("
            SELECT id, name, email
            FROM users 
            WHERE subscription_status = 'active' 
            AND cancellation_requested = 1
            AND subscription_end IS NOT NULL 
            AND subscription_end < NOW()
        ");
        $stmt->execute();
        $expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expired_users as $user) {
            // Downgrade a piano gratuito
            $downgrade_stmt = $pdo->prepare("
                UPDATE users 
                SET subscription_status = 'cancelled',
                    cancellation_requested = 0
                WHERE id = :user_id
            ");
            $downgrade_stmt->execute([':user_id' => $user['id']]);
            
            error_log("Utente {$user['id']} ({$user['name']}) downgraded per cancellazione richiesta");
        }
        
        return count($expired_users);
        
    } catch (Exception $e) {
        error_log("Errore processamento abbonamenti scaduti: " . $e->getMessage());
        return 0;
    }
}
?>