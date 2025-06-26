<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!is_logged_in()) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Processa automaticamente gli abbonamenti scaduti quando l'utente accede
process_expired_subscriptions($pdo);

$deeplink_url = "";
$error = "";
$success = "";

// Statistiche utente
$monthly_count = count_monthly_deeplinks($pdo, $user_id);
$has_subscription = has_active_subscription($pdo, $user_id);
$can_create = can_create_deeplink($pdo, $user_id);

// Statistiche totali click (solo per utenti PRO)
$total_clicks = $has_subscription ? get_total_clicks($pdo, $user_id) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    if (!$can_create) {
        $error = "Hai raggiunto il limite mensile di 5 deeplink. Passa al piano Premium per deeplink illimitati!";
    } else {
        $original_url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
        $title = trim($_POST['title'] ?? '');
        $custom_name = trim($_POST['custom_name'] ?? '');

        if (filter_var($original_url, FILTER_VALIDATE_URL)) {
            // Validazione del titolo
            if (empty($title)) {
                $error = "Il titolo è obbligatorio per identificare il deeplink.";
            } else {
                $deeplink = generate_deeplink($original_url);
                $id = substr(hash('sha256', $original_url . $user_id . time()), 0, 8);

                // Validazione del nome personalizzato per utenti premium
                if ($has_subscription && !empty($custom_name)) {
                    // Verifica che il nome personalizzato sia valido (solo lettere, numeri, trattini)
                    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $custom_name)) {
                        $error = "Il nome personalizzato può contenere solo lettere, numeri, trattini e underscore.";
                    } elseif (strlen($custom_name) < 3 || strlen($custom_name) > 20) {
                        $error = "Il nome personalizzato deve essere tra 3 e 20 caratteri.";
                    } else {
                        // Verifica che il nome personalizzato non sia già in uso
                        $stmt = $pdo->prepare("SELECT id FROM deeplinks WHERE custom_name = :custom_name");
                        $stmt->execute([':custom_name' => $custom_name]);
                        if ($stmt->fetch()) {
                            $error = "Il nome personalizzato è già in uso. Scegline un altro.";
                        } else {
                            $id = $custom_name; // Usa il nome personalizzato come ID
                        }
                    }
                }

                if (empty($error)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO deeplinks (id, original_url, deeplink, user_id, title, custom_name, created_at) 
                        VALUES (:id, :original_url, :deeplink, :user_id, :title, :custom_name, NOW())
                    ");
                    
                    if ($stmt->execute([
                        ':id' => $id,
                        ':original_url' => $original_url,
                        ':deeplink' => $deeplink,
                        ':user_id' => $user_id,
                        ':title' => $title,
                        ':custom_name' => $has_subscription && !empty($custom_name) ? $custom_name : null
                    ])) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                        $base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                        
                        // Se ha un nome personalizzato, usa l'URL personalizzato
                        if ($has_subscription && !empty($custom_name)) {
                            $deeplink_url = "$base_url/$custom_name";
                        } else {
                            $deeplink_url = "$base_url/redirect.php?id=$id";
                        }
                        
                        $success = "Deeplink creato con successo!";
                    } else {
                        $error = "Errore durante la creazione del deeplink.";
                    }
                }
            }
        } else {
            $error = "Inserisci un URL valido.";
        }
    }
}

// Recupera gli ultimi deeplink dell'utente con statistiche
$stmt = $pdo->prepare("
    SELECT id, original_url, title, custom_name, clicks, created_at 
    FROM deeplinks 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([':user_id' => $user_id]);
$recent_deeplinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 deeplink più cliccati (solo per utenti PRO)
$top_deeplinks = [];
if ($has_subscription) {
    $stmt = $pdo->prepare("
        SELECT id, original_url, title, custom_name, clicks, created_at 
        FROM deeplinks 
        WHERE user_id = :user_id AND clicks > 0
        ORDER BY clicks DESC 
        LIMIT 5
    ");
    $stmt->execute([':user_id' => $user_id]);
    $top_deeplinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Funzione helper per generare l'URL del deeplink
function generate_deeplink_url($link) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    
    if ($link['custom_name']) {
        return "$base_url/" . $link['custom_name'];
    } else {
        return "$base_url/redirect.php?id=" . $link['id'];
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* TITOLI BIANCHI */
        .card h2 {
            color: #ffffff !important;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .card h3 {
            color: #ffffff !important;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        /* RESPONSIVE TABLES */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .stats-table {
            min-width: 800px; /* Larghezza minima per evitare compressione eccessiva */
        }
        
        /* AZIONI TABELLA - BOTTONI SEMPRE SULLA STESSA LINEA */
        .table-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: nowrap; /* IMPEDISCE IL WRAP */
            min-width: 200px; /* Larghezza minima per contenere tutti i bottoni */
        }
        
        .table-actions .btn {
            white-space: nowrap;
            flex-shrink: 0; /* IMPEDISCE LA COMPRESSIONE */
            min-width: auto;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        
        .table-actions .copy-btn,
        .table-actions .delete-btn {
            flex-shrink: 0; /* IMPEDISCE LA COMPRESSIONE */
            white-space: nowrap;
            min-width: 60px;
        }
        
        /* MIGLIORAMENTI RESPONSIVE */
        @media (max-width: 1200px) {
            .stats-table {
                min-width: 900px;
            }
            
            .table-actions {
                min-width: 180px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-table {
                min-width: 700px;
                font-size: 0.875rem;
            }
            
            .stats-table th,
            .stats-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .table-actions {
                min-width: 160px;
                gap: 0.25rem;
            }
            
            .table-actions .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
                min-width: 50px;
            }
            
            .url-cell {
                max-width: 200px;
            }
            
            .deeplink-title {
                font-size: 0.875rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-table {
                min-width: 600px;
                font-size: 0.8rem;
            }
            
            .table-actions {
                flex-direction: column;
                gap: 0.25rem;
                min-width: 80px;
            }
            
            .table-actions .btn {
                width: 100%;
                padding: 0.25rem;
                font-size: 0.65rem;
            }
        }
        
        /* FORM RESPONSIVE */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* URL PREVIEW RESPONSIVE */
        .url-preview {
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            word-break: break-all;
        }
        
        .url-preview.active {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            color: #93c5fd;
        }
        
        /* BADGES RESPONSIVE */
        .custom-name-badge,
        .custom-url-badge {
            display: inline-block;
            margin-left: 0.5rem;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .custom-name-badge,
            .custom-url-badge {
                display: block;
                margin-left: 0;
                margin-top: 0.5rem;
                width: fit-content;
            }
        }
        
        /* STATISTICHE RESPONSIVE */
        .usage-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .usage-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .usage-stats {
                grid-template-columns: 1fr;
            }
        }
        
        /* TIPS GRID RESPONSIVE */
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .tips-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        /* EXPIRY INFO RESPONSIVE */
        .expiry-info {
            background: rgba(255, 193, 7, 0.15);
            backdrop-filter: blur(10px);
            color: #fbbf24;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            font-size: 0.875rem;
            margin-top: 1rem;
            border: 1px solid rgba(255, 193, 7, 0.3);
            font-weight: 500;
            line-height: 1.5;
            position: relative;
            overflow: hidden;
        }
        
        .expiry-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(251, 191, 36, 0.4), transparent);
        }
        
        .expiry-info a {
            color: #fbbf24;
            text-decoration: underline;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .expiry-info a:hover {
            color: #ffffff;
            text-decoration: none;
        }
        
        /* PERFORMANCE RANK RESPONSIVE */
        .performance-rank {
            position: absolute;
            top: -10px;
            right: -10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .performance-rank {
                position: static;
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🚀 DeepLink Pro</a>
                <div class="nav-links">
                    <span>Ciao, <?= htmlspecialchars($_SESSION['user_name']) ?>!</span>
                    <a href="profile.php">Profilo</a>
                    <?php if (!$has_subscription): ?>
                        <a href="pricing.php" class="btn btn-success" style="padding: 0.5rem 1rem;">Upgrade Premium</a>
                    <?php endif; ?>
                    <a href="auth/logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <!-- Statistiche -->
            <div class="usage-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $monthly_count ?></div>
                    <div class="stat-label">Deeplink questo mese</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $has_subscription ? '∞' : (5 - $monthly_count) ?></div>
                    <div class="stat-label"><?= $has_subscription ? 'Illimitati' : 'Rimanenti' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $has_subscription ? $total_clicks : '🔒' ?></div>
                    <div class="stat-label"><?= $has_subscription ? 'Click Totali' : 'Solo PRO' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $has_subscription ? 'PRO' : 'FREE' ?></div>
                    <div class="stat-label">Piano attuale</div>
                </div>
            </div>

            <!-- Generatore Deeplink -->
            <div class="card">
                <h2>✨ Genera Nuovo Deeplink</h2>
                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                    Supportiamo YouTube, Instagram, Twitch, Amazon e molti altri servizi
                    <?php if ($has_subscription): ?>
                        <br><span style="color: #4ade80; font-weight: 600;">✓ Premium: URL personalizzati e link permanenti!</span>
                    <?php endif; ?>
                </p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <?php if (!$can_create): ?>
                    <div class="alert alert-info">
                        Hai raggiunto il limite mensile. <a href="pricing.php" style="color: #93c5fd;">Passa a Premium</a> per deeplink illimitati!
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title">Titolo del Deeplink *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               placeholder="Es: Video YouTube interessante, Post Instagram..." 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               <?= !$can_create ? 'disabled' : '' ?> required>
                        <small style="color: rgba(255, 255, 255, 0.6);">Inserisci un titolo per identificare facilmente questo deeplink</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="url">URL da convertire *</label>
                            <input type="url" id="url" name="url" class="form-control" 
                                   placeholder="https://www.youtube.com/watch?v=..." 
                                   value="<?= htmlspecialchars($_POST['url'] ?? '') ?>"
                                   <?= !$can_create ? 'disabled' : '' ?> required>
                        </div>

                        <?php if ($has_subscription): ?>
                        <div class="form-group">
                            <label for="custom_name">Nome Personalizzato (Premium) 
                                <span class="custom-name-badge">PRO</span>
                            </label>
                            <input type="text" id="custom_name" name="custom_name" class="form-control" 
                                   placeholder="mio-link-speciale" 
                                   value="<?= htmlspecialchars($_POST['custom_name'] ?? '') ?>"
                                   pattern="[a-zA-Z0-9\-_]+" 
                                   minlength="3" maxlength="20"
                                   oninput="updateUrlPreview()"
                                   <?= !$can_create ? 'disabled' : '' ?>>
                            <small style="color: rgba(255, 255, 255, 0.6);">Opzionale. Solo lettere, numeri, trattini e underscore (3-20 caratteri)</small>
                            <div id="url-preview" class="url-preview">
                                <?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http" ?>://<?= $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) ?>/tuo-nome-personalizzato
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" <?= !$can_create ? 'disabled' : '' ?>>
                        🚀 Genera Deeplink
                    </button>
                </form>
                
                <?php if ($deeplink_url): ?>
                    <div class="result">
                        <strong>🎉 Il tuo deeplink è pronto!</strong>
                        <?php if ($has_subscription && !empty($_POST['custom_name'])): ?>
                            <span class="custom-url-badge">URL PERSONALIZZATO</span>
                        <?php endif; ?>
                        <br>
                        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                            <a href="<?= htmlspecialchars($deeplink_url) ?>" target="_blank" style="word-break: break-all;">
                                <?= htmlspecialchars($deeplink_url) ?>
                            </a>
                            <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($deeplink_url) ?>', this)">
                                Copia
                            </button>
                        </div>
                        <?php if (!$has_subscription): ?>
                            <div class="expiry-info">
                                ⏰ <strong>Attenzione:</strong> Questo link scadrà tra 5 giorni. 
                                <a href="pricing.php">Passa a Premium</a> per link permanenti e URL personalizzati!
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Deeplink per Click (Solo PRO) -->
            <?php if ($has_subscription && !empty($top_deeplinks)): ?>
            <div class="card">
                <h2>🏆 Top Deeplink per Click</h2>
                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">I tuoi deeplink più performanti</p>
                
                <div class="table-container">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Posizione</th>
                                <th>Titolo</th>
                                <th>URL Originale</th>
                                <th>Click</th>
                                <th>Data Creazione</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_deeplinks as $index => $link): ?>
                            <tr>
                                <td>
                                    <div class="performance-rank" style="position: static; width: 30px; height: 30px; font-size: 0.8rem;">
                                        #<?= $index + 1 ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="deeplink-title">
                                        <?= htmlspecialchars($link['title'] ?? '') ?>
                                        <?php if ($link['custom_name']): ?>
                                            <span class="custom-url-badge">CUSTOM</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="url-cell">
                                        <div class="url-text">
                                            <?= htmlspecialchars(substr($link['original_url'], 0, 60)) ?><?= strlen($link['original_url']) > 60 ? '...' : '' ?>
                                        </div>
                                        <div class="url-domain">
                                            <?= parse_url($link['original_url'], PHP_URL_HOST) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="click-badge active">
                                        <?= $link['clicks'] ?>
                                    </div>
                                </td>
                                <td class="date-cell">
                                    <?= date('d/m/Y H:i', strtotime($link['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="<?= generate_deeplink_url($link) ?>" target="_blank" 
                                           class="btn btn-secondary btn-sm">
                                            Apri
                                        </a>
                                        <button class="copy-btn" onclick="copyToClipboard('<?= generate_deeplink_url($link) ?>', this)">
                                            Copia
                                        </button>
                                        <button class="delete-btn" onclick="deleteDeeplink('<?= $link['id'] ?>', this)">
                                            Elimina
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif (!$has_subscription): ?>
            <div class="card">
                <h2>🏆 Top Deeplink per Click</h2>
                <div class="alert alert-info">
                    <strong>Funzionalità Premium</strong><br>
                    Le statistiche dettagliate sui click sono disponibili solo per gli utenti Premium.
                    <a href="pricing.php" style="color: #93c5fd;">Passa a Premium</a> per sbloccare questa funzionalità!
                </div>
            </div>
            <?php endif; ?>

            <!-- Deeplink Recenti con Statistiche -->
            <?php if (!empty($recent_deeplinks)): ?>
            <div class="card">
                <h2>📊 I tuoi Deeplink Recenti</h2>
                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                    Cronologia completa dei tuoi deeplink
                    <?php if (!$has_subscription): ?>
                        <span style="color: #f39c12;">(Statistiche click disponibili solo per utenti Premium)</span>
                    <?php endif; ?>
                </p>
                
                <div class="table-container">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Titolo</th>
                                <th>URL Originale</th>
                                <th>Click</th>
                                <th>Data Creazione</th>
                                <th>Stato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_deeplinks as $link): ?>
                            <?php 
                                $is_expired = is_deeplink_expired($link['created_at'], $has_subscription);
                                $days_remaining = get_days_until_expiry($link['created_at'], $has_subscription);
                            ?>
                            <tr id="deeplink-row-<?= $link['id'] ?>">
                                <td>
                                    <div class="deeplink-title">
                                        <?= htmlspecialchars($link['title'] ?? '') ?>
                                        <?php if ($link['custom_name']): ?>
                                            <span class="custom-url-badge">CUSTOM</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="url-cell">
                                        <div class="url-text">
                                            <?= htmlspecialchars(substr($link['original_url'], 0, 60)) ?><?= strlen($link['original_url']) > 60 ? '...' : '' ?>
                                        </div>
                                        <div class="url-domain">
                                            <?= parse_url($link['original_url'], PHP_URL_HOST) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($has_subscription): ?>
                                        <div class="click-badge <?= $link['clicks'] > 0 ? 'active' : '' ?>">
                                            <?= $link['clicks'] ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="click-badge" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.5);">
                                            🔒
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="date-cell">
                                    <?= date('d/m/Y H:i', strtotime($link['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($has_subscription): ?>
                                        <span style="color: #4ade80; font-weight: 600;">✓ Permanente</span>
                                    <?php elseif ($is_expired): ?>
                                        <span style="color: #f87171; font-weight: 600;">✗ Scaduto</span>
                                    <?php elseif ($days_remaining <= 1): ?>
                                        <span style="color: #fbbf24; font-weight: 600;">⚠ Scade oggi</span>
                                    <?php else: ?>
                                        <span style="color: rgba(255, 255, 255, 0.6);">⏰ <?= $days_remaining ?> giorni</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <?php if (!$is_expired): ?>
                                            <a href="<?= generate_deeplink_url($link) ?>" target="_blank" 
                                               class="btn btn-secondary btn-sm">
                                                Apri
                                            </a>
                                            <button class="copy-btn" onclick="copyToClipboard('<?= generate_deeplink_url($link) ?>', this)">
                                                Copia
                                            </button>
                                        <?php else: ?>
                                            <span style="color: rgba(255, 255, 255, 0.5); font-size: 0.875rem;">Link scaduto</span>
                                        <?php endif; ?>
                                        <button class="delete-btn" onclick="deleteDeeplink('<?= $link['id'] ?>', this)">
                                            Elimina
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Suggerimenti per migliorare le performance -->
            <div class="card tips-card">
                <h2>💡 Suggerimenti per Migliorare le Performance</h2>
                <div class="tips-grid">
                    <div class="tip-item">
                        <div class="tip-icon">📱</div>
                        <div class="tip-content">
                            <h3>Condividi sui Social</h3>
                            <p>I deeplink funzionano meglio quando condivisi direttamente sui social media</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">⏰</div>
                        <div class="tip-content">
                            <h3>Timing Ottimale</h3>
                            <p>Condividi i tuoi deeplink negli orari di maggiore attività del tuo pubblico</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">🎯</div>
                        <div class="tip-content">
                            <h3>Contenuto Rilevante</h3>
                            <p>Assicurati che il contenuto linkato sia interessante per il tuo target</p>
                        </div>
                    </div>
                    <?php if ($has_subscription): ?>
                    <div class="tip-item">
                        <div class="tip-icon">🔗</div>
                        <div class="tip-content">
                            <h3>URL Personalizzati</h3>
                            <p>Usa nomi personalizzati per creare URL più puliti e memorabili come: tuosito.com/mio-link</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="tip-item">
                        <div class="tip-icon">📊</div>
                        <div class="tip-content">
                            <h3>Funzionalità Premium</h3>
                            <p><a href="pricing.php" style="color: #93c5fd;">Passa a Premium</a> per URL personalizzati e statistiche dettagliate</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(function() {
                const originalText = button.textContent;
                button.textContent = 'Copiato!';
                button.classList.add('copied');
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Errore nella copia: ', err);
                // Fallback per browser più vecchi
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                const originalText = button.textContent;
                button.textContent = 'Copiato!';
                button.classList.add('copied');
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            });
        }

        function deleteDeeplink(id, button) {
            if (!confirm('Sei sicuro di voler eliminare questo deeplink? Questa azione non può essere annullata.')) {
                return;
            }

            const originalText = button.textContent;
            button.textContent = 'Eliminando...';
            button.disabled = true;

            fetch('delete_deeplink.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Rimuovi la riga dalla tabella con animazione
                    const row = document.getElementById('deeplink-row-' + id);
                    if (row) {
                        row.style.transition = 'opacity 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                        }, 300);
                    }
                    
                    // Mostra messaggio di successo
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success';
                    alert.textContent = 'Deeplink eliminato con successo!';
                    alert.style.position = 'fixed';
                    alert.style.top = '20px';
                    alert.style.right = '20px';
                    alert.style.zIndex = '9999';
                    document.body.appendChild(alert);
                    
                    setTimeout(() => {
                        alert.remove();
                    }, 3000);
                } else {
                    alert('Errore: ' + data.message);
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                alert('Errore durante l\'eliminazione del deeplink');
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        <?php if ($has_subscription): ?>
        function updateUrlPreview() {
            const customName = document.getElementById('custom_name').value;
            const preview = document.getElementById('url-preview');
            const protocol = '<?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http" ?>';
            const host = '<?= $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) ?>';
            
            if (customName && customName.length >= 3) {
                preview.textContent = `${protocol}://${host}/${customName}`;
                preview.classList.add('active');
            } else {
                preview.textContent = `${protocol}://${host}/tuo-nome-personalizzato`;
                preview.classList.remove('active');
            }
        }

        // Inizializza l'anteprima URL al caricamento della pagina
        document.addEventListener('DOMContentLoaded', function() {
            updateUrlPreview();
        });
        <?php endif; ?>
    </script>
</body>
</html>