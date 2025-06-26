<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Recupera informazioni utente
$stmt = $pdo->prepare("
    SELECT name, email, subscription_status, subscription_end, subscription_id, cancellation_requested
    FROM users 
    WHERE id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['subscription_id']) {
    header('Location: profile.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completa Cancellazione Abbonamento - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .cancellation-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fd7e14 0%, #f39c12 100%);
        }
        .cancellation-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .step-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
            text-align: left;
        }
        .step-number {
            background: #fd7e14;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 1rem;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            color: #856404;
        }
        .paypal-button {
            background: #0070ba;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin: 1rem;
            transition: background-color 0.3s ease;
        }
        .paypal-button:hover {
            background: #005ea6;
            color: white;
        }
    </style>
</head>
<body>
    <div class="cancellation-container">
        <div class="cancellation-card">
            <div style="font-size: 4rem; margin-bottom: 1rem;">⚠️</div>
            <h2 style="color: #fd7e14; margin-bottom: 1rem;">Completa la Cancellazione su PayPal</h2>
            <p style="color: #666; margin-bottom: 2rem;">
                Per completare la disdetta dell'abbonamento e evitare addebiti futuri, 
                devi cancellare l'abbonamento anche su PayPal.
            </p>
            
            <div class="warning-box">
                <strong>📋 Stato Attuale:</strong><br>
                ✅ Cancellazione richiesta sul nostro sistema<br>
                ⏳ <strong>Devi completare la cancellazione su PayPal</strong><br>
                🗓️ Accesso Premium fino al: <strong><?= date('d/m/Y', strtotime($user['subscription_end'])) ?></strong>
            </div>
            
            <h3 style="color: #333; margin-bottom: 1rem;">Come Cancellare su PayPal:</h3>
            
            <div class="step-card">
                <div style="display: flex; align-items: flex-start;">
                    <span class="step-number">1</span>
                    <div>
                        <strong>Accedi al tuo account PayPal</strong><br>
                        <small style="color: #666;">Clicca il pulsante qui sotto per andare direttamente alla pagina PayPal</small>
                    </div>
                </div>
            </div>
            
            <div class="step-card">
                <div style="display: flex; align-items: flex-start;">
                    <span class="step-number">2</span>
                    <div>
                        <strong>Vai su "Impostazioni" → "Pagamenti automatici"</strong><br>
                        <small style="color: #666;">Troverai questa sezione nel menu delle impostazioni del tuo account</small>
                    </div>
                </div>
            </div>
            
            <div class="step-card">
                <div style="display: flex; align-items: flex-start;">
                    <span class="step-number">3</span>
                    <div>
                        <strong>Cerca "DeepLink Pro" e clicca "Cancella"</strong><br>
                        <small style="color: #666;">ID Abbonamento: <?= htmlspecialchars($user['subscription_id']) ?></small>
                    </div>
                </div>
            </div>
            
            <div class="step-card">
                <div style="display: flex; align-items: flex-start;">
                    <span class="step-number">4</span>
                    <div>
                        <strong>Conferma la cancellazione</strong><br>
                        <small style="color: #666;">PayPal ti chiederà di confermare. Una volta confermato, non ci saranno più addebiti</small>
                    </div>
                </div>
            </div>
            
            <div style="margin: 2rem 0;">
                <a href="https://www.paypal.com/myaccount/autopay/" target="_blank" class="paypal-button">
                    🔗 Vai su PayPal per Cancellare
                </a>
            </div>
            
            <div class="warning-box">
                <strong>⚠️ IMPORTANTE:</strong><br>
                • Se NON cancelli su PayPal, continuerai ad essere addebitato ogni mese<br>
                • Dopo 1 mese esatto dalla sottoscrizione, il tuo account tornerà automaticamente al piano FREE<br>
                • Puoi continuare a usare le funzionalità Premium fino alla scadenza del periodo già pagato
            </div>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
                <h3 style="color: #333; margin-bottom: 1rem;">Cosa Succede Dopo:</h3>
                <ul style="text-align: left; color: #666; margin-bottom: 2rem;">
                    <li><strong>Oggi:</strong> Continui ad avere accesso Premium</li>
                    <li><strong>Fino al <?= date('d/m/Y', strtotime($user['subscription_end'])) ?>:</strong> Tutte le funzionalità Premium attive</li>
                    <li><strong>Dal <?= date('d/m/Y', strtotime($user['subscription_end'] . ' +1 day')) ?>:</strong> Account automaticamente downgraded a piano FREE</li>
                    <li><strong>Piano FREE:</strong> 5 deeplink al mese, link scadono dopo 5 giorni</li>
                </ul>
                
                <div style="margin-top: 2rem;">
                    <a href="profile.php" class="btn btn-secondary" style="margin-right: 1rem;">
                        Torna al Profilo
                    </a>
                    <a href="dashboard.php" class="btn btn-primary">
                        Vai alla Dashboard
                    </a>
                </div>
                
                <p style="color: #666; font-size: 0.9rem; margin-top: 1rem;">
                    <strong>Hai cambiato idea?</strong> Puoi sempre riattivare l'abbonamento Premium dalla pagina dei prezzi.
                </p>
            </div>
        </div>
    </div>
</body>
</html>