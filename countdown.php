<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    http_response_code(404);
    echo "Link non trovato";
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT deeplink, original_url, title, user_id, created_at FROM deeplinks WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo "Link non trovato";
    exit;
}

// Verifica se il link è scaduto (solo per utenti free)
$user_has_subscription = false;
if ($row['user_id']) {
    $user_has_subscription = has_active_subscription($pdo, $row['user_id']);
}

if (is_deeplink_expired($row['created_at'], $user_has_subscription)) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Link Scaduto - DeepLink Pro</title>
        <link rel="stylesheet" href="assets/style.css">
        <style>
            .expired-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .expired-card {
                background: white;
                border-radius: 20px;
                padding: 3rem;
                text-align: center;
                max-width: 500px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            .expired-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class="expired-container">
            <div class="expired-card">
                <div class="expired-icon">⏰</div>
                <h2 style="color: #dc3545; margin-bottom: 1rem;">Link Scaduto</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Questo deeplink è scaduto. I link gratuiti durano 5 giorni dalla creazione.
                </p>
                <?php if ($row['title']): ?>
                <div style="margin-bottom: 1rem;">
                    <strong>Titolo:</strong><br>
                    <span style="color: #333;"><?= htmlspecialchars($row['title']) ?></span>
                </div>
                <?php endif; ?>
                <div style="margin-bottom: 2rem;">
                    <strong>URL originale:</strong><br>
                    <span style="color: #666; word-break: break-all;"><?= htmlspecialchars($row['original_url']) ?></span>
                </div>
                <a href="pricing.php" class="btn btn-primary" style="margin-right: 1rem;">
                    Passa a Premium
                </a>
                <a href="index.php" class="btn btn-secondary">
                    Torna alla Home
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Determina il tipo di app dal deeplink
$app_name = "l'app";
$app_icon = "📱";

if (strpos($row['deeplink'], 'youtube://') !== false) {
    $app_name = "YouTube";
    $app_icon = "📺";
} elseif (strpos($row['deeplink'], 'instagram://') !== false) {
    $app_name = "Instagram";
    $app_icon = "📷";
} elseif (strpos($row['deeplink'], 'twitch://') !== false) {
    $app_name = "Twitch";
    $app_icon = "🎮";
} elseif (strpos($row['deeplink'], 'amazon://') !== false) {
    $app_name = "Amazon";
    $app_icon = "🛒";
}

// Usa il titolo se disponibile, altrimenti usa il nome dell'app
$display_title = $row['title'] ? htmlspecialchars($row['title']) : "Apertura " . htmlspecialchars($app_name);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $display_title ?> - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .countdown-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
        }
        .countdown-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        .countdown-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            animation: loading 3s ease-in-out;
        }
        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        .app-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .countdown-number {
            font-size: 3rem;
            font-weight: 700;
            color: #667eea;
            margin: 1rem 0;
            animation: countdown 1s infinite;
        }
        @keyframes countdown {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .loading-dots {
            display: inline-block;
            position: relative;
            width: 80px;
            height: 20px;
            margin-top: 1rem;
        }
        .loading-dots div {
            position: absolute;
            top: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            animation: loading-dots 1.2s linear infinite;
        }
        .loading-dots div:nth-child(1) { left: 8px; animation-delay: 0s; }
        .loading-dots div:nth-child(2) { left: 32px; animation-delay: -0.4s; }
        .loading-dots div:nth-child(3) { left: 56px; animation-delay: -0.8s; }
        @keyframes loading-dots {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
        .fallback-link {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            display: none;
        }
        .deeplink-title {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        /* BOTTONI MIGLIORATI */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        
        .btn-app {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 160px;
            justify-content: center;
        }
        
        .btn-app:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-browser {
            background: rgba(255, 255, 255, 0.1);
            color: #333;
            border: 2px solid #ddd;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 160px;
            justify-content: center;
        }
        
        .btn-browser:hover {
            background: #f8f9fa;
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .countdown-container {
                padding: 1rem;
            }
            
            .countdown-card {
                padding: 2rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-app,
            .btn-browser {
                width: 100%;
                max-width: 280px;
            }
        }
        
        /* ANIMAZIONI MIGLIORATE */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-message {
            color: #28a745;
            font-weight: 600;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="countdown-container">
        <div class="countdown-card">
            <div class="app-icon"><?= $app_icon ?></div>
            <?php if ($row['title']): ?>
                <div class="deeplink-title"><?= htmlspecialchars($row['title']) ?></div>
            <?php endif; ?>
            <h2 style="color: #333; margin-bottom: 1rem;" id="main-title">
                Sto aprendo <?= htmlspecialchars($app_name) ?> per te!
            </h2>
            <div class="countdown-number" id="countdown">3</div>
            <p style="color: #666;" id="status-text">
                Preparazione del deeplink in corso...
            </p>
            <div class="loading-dots" id="loading-dots">
                <div></div>
                <div></div>
                <div></div>
            </div>
            
            <!-- BOTTONI SEMPRE VISIBILI -->
            <div class="action-buttons" id="action-buttons">
                <a href="<?= htmlspecialchars($row['deeplink']) ?>" class="btn-app" id="app-button">
                    📱 Apri <?= htmlspecialchars($app_name) ?>
                </a>
                <a href="<?= htmlspecialchars($row['original_url']) ?>" class="btn-browser" target="_blank" id="browser-button">
                    🌐 Apri nel Browser
                </a>
            </div>
            
            <div class="fallback-link" id="fallback" style="display: none;">
                <p style="color: #666; margin-bottom: 1rem;" class="success-message">
                    ✅ Apertura completata!
                </p>
                <p style="color: #666; font-size: 0.9rem;">
                    Se l'app non si è aperta automaticamente, usa i pulsanti qui sopra.
                </p>
            </div>
        </div>
    </div>

    <script>
        let countdown = 3;
        const countdownElement = document.getElementById('countdown');
        const fallbackElement = document.getElementById('fallback');
        const mainTitle = document.getElementById('main-title');
        const statusText = document.getElementById('status-text');
        const loadingDots = document.getElementById('loading-dots');
        const actionButtons = document.getElementById('action-buttons');
        const appButton = document.getElementById('app-button');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                
                // Aggiorna il contatore dei click
                fetch('update_click.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: '<?= $id ?>'
                    })
                });
                
                // Prova ad aprire l'app automaticamente
                window.location.href = '<?= htmlspecialchars($row['deeplink']) ?>';
                
                // Aggiorna l'interfaccia dopo il countdown
                setTimeout(() => {
                    // Nascondi countdown e loading
                    countdownElement.style.display = 'none';
                    loadingDots.style.display = 'none';
                    
                    // Aggiorna testi
                    mainTitle.textContent = 'Apertura in corso...';
                    statusText.textContent = 'Tentativo di apertura automatica dell\'app';
                    
                    // Mostra il messaggio di fallback
                    fallbackElement.style.display = 'block';
                    fallbackElement.classList.add('fade-in');
                    
                    // Evidenzia il bottone dell'app
                    appButton.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                    appButton.innerHTML = '✅ Apri <?= htmlspecialchars($app_name) ?>';
                    
                }, 1000);
            }
        }, 1000);
        
        // Gestione click sui bottoni
        appButton.addEventListener('click', function(e) {
            // Aggiorna il contatore dei click se non è già stato fatto
            fetch('update_click.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: '<?= $id ?>'
                })
            });
        });
        
        // Feedback visivo per il bottone browser
        document.getElementById('browser-button').addEventListener('click', function() {
            this.style.background = 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)';
            this.style.color = 'white';
            this.style.borderColor = 'transparent';
            this.innerHTML = '✅ Apertura Browser...';
        });
    </script>
</body>
</html>