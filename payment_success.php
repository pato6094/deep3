<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$is_first_payment = isset($_GET['first_payment']);

// Verifica se l'utente nell'URL corrisponde a quello loggato (per sicurezza)
if (isset($_GET['user_id']) && $_GET['user_id'] != $user_id) {
    $error_message = 'Errore di sicurezza: utente non corrispondente.';
} else {
    // Se viene chiamata questa pagina, significa che il pagamento è andato a buon fine
    // Aggiorna la data di scadenza dell'abbonamento
    try {
        $subscription_end = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_end = :subscription_end,
                cancellation_requested = 0
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([':subscription_end' => $subscription_end, ':user_id' => $user_id])) {
            if ($is_first_payment) {
                $success_message = 'Benvenuto in DeepLink Pro Premium! Il tuo abbonamento è stato attivato con successo.';
            } else {
                $success_message = 'Pagamento ricevuto con successo! Il tuo abbonamento Premium è stato rinnovato.';
            }
            
            // Log per debug
            error_log("Pagamento confermato per utente $user_id - Scadenza aggiornata a $subscription_end" . ($is_first_payment ? " (primo pagamento)" : " (rinnovo)"));
        } else {
            $error_message = 'Errore nell\'aggiornamento dell\'abbonamento. Contatta il supporto.';
        }
    } catch (Exception $e) {
        $error_message = 'Errore del server: ' . $e->getMessage();
    }
}

// Recupera informazioni utente aggiornate
$stmt = $pdo->prepare("
    SELECT subscription_status, subscription_end, cancellation_requested
    FROM users 
    WHERE id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Confermato - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #28a745;
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dc3545;
        }
        .billing-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
        }
        .premium-features {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-radius: 10px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="<?= $success_message ? 'success-container' : 'error-container' ?>">
        <div class="success-card">
            <?php if ($success_message): ?>
                <div class="success-icon">✅</div>
                <h2 style="color: #28a745; margin-bottom: 1rem;">
                    <?= $is_first_payment ? 'Benvenuto in Premium!' : 'Pagamento Confermato!' ?>
                </h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    <?= htmlspecialchars($success_message) ?>
                </p>
                
                <?php if ($is_first_payment): ?>
                <div class="premium-features">
                    <h3 style="color: #155724; margin-bottom: 1rem;">🎉 Ora hai accesso a:</h3>
                    <ul style="color: #155724; text-align: left; margin: 0;">
                        <li>✓ Deeplink illimitati ogni mese</li>
                        <li>✓ URL personalizzati (es: tuosito.com/mio-link)</li>
                        <li>✓ Link permanenti che non scadono mai</li>
                        <li>✓ Statistiche dettagliate sui click</li>
                        <li>✓ Supporto prioritario</li>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if ($user): ?>
                <div class="billing-info">
                    <h3 style="color: #333; margin-bottom: 1rem;">Dettagli Abbonamento</h3>
                    <p><strong>Stato:</strong> 
                        <span style="color: #28a745;">✓ Premium Attivo</span>
                    </p>
                    <p><strong>Scadenza attuale:</strong> 
                        <?= date('d/m/Y', strtotime($user['subscription_end'])) ?>
                    </p>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                        <small style="color: #666;">
                            <strong>💡 Importante:</strong> PayPal rinnoverà automaticamente il tuo abbonamento ogni mese. 
                            Ogni volta che PayPal elabora un pagamento, verrai reindirizzato a questa pagina per confermare il rinnovo.
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 2rem;">
                    <a href="dashboard.php" class="btn btn-primary" style="margin-right: 1rem;">
                        <?= $is_first_payment ? 'Inizia a Creare Deeplink' : 'Vai alla Dashboard' ?>
                    </a>
                    <a href="profile.php" class="btn btn-secondary">
                        Gestisci Profilo
                    </a>
                </div>
                
            <?php else: ?>
                <div class="error-icon">❌</div>
                <h2 style="color: #dc3545; margin-bottom: 1rem;">Errore Pagamento</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    <?= htmlspecialchars($error_message) ?>
                </p>
                
                <div style="margin-top: 2rem;">
                    <a href="pricing.php" class="btn btn-primary" style="margin-right: 1rem;">
                        Riprova Pagamento
                    </a>
                    <a href="profile.php" class="btn btn-secondary">
                        Contatta Supporto
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($success_message): ?>
    <script>
        // Auto-redirect alla dashboard dopo 10 secondi per i primi pagamenti
        <?php if ($is_first_payment): ?>
        setTimeout(function() {
            window.location.href = 'dashboard.php';
        }, 10000);
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>