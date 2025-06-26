<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Recupera informazioni utente
$stmt = $pdo->prepare("
    SELECT subscription_status, subscription_id, next_billing_date, grace_period_until
    FROM users 
    WHERE id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Se l'utente clicca "Ho pagato", aggiorna la data di rinnovo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    if ($user['subscription_id']) {
        // Aggiorna la data di prossimo rinnovo
        if (update_billing_date($pdo, $user_id)) {
            $success = 'Pagamento confermato! Il tuo abbonamento è stato rinnovato.';
            
            // Ricarica i dati utente
            $stmt->execute([':user_id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'Errore nell\'aggiornamento. Contatta il supporto.';
        }
    } else {
        $error = 'Nessun abbonamento trovato.';
    }
}

$is_in_grace_period = $user['grace_period_until'] && strtotime($user['grace_period_until']) > time();
$has_active_subscription = has_active_subscription($pdo, $user_id);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Pagamento - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .payment-check-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .payment-check-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .status-active {
            color: #28a745;
            font-weight: 600;
        }
        .status-grace {
            color: #ffc107;
            font-weight: 600;
        }
        .status-expired {
            color: #dc3545;
            font-weight: 600;
        }
        .paypal-instructions {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
            font-size: 0.9rem;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="payment-check-container">
        <div class="payment-check-card">
            <h2 style="color: #333; margin-bottom: 2rem;">Conferma Pagamento Manuale</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($has_active_subscription && !$is_in_grace_period): ?>
                <div style="margin-bottom: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                    <h3 class="status-active">Abbonamento Attivo</h3>
                    <p style="color: #666;">
                        Il tuo abbonamento Premium è attivo.
                        <?php if ($user['next_billing_date']): ?>
                            <br>Prossimo rinnovo previsto: <strong><?= date('d/m/Y', strtotime($user['next_billing_date'])) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                
                <a href="dashboard.php" class="btn btn-primary">
                    Vai alla Dashboard
                </a>
                
            <?php elseif ($is_in_grace_period): ?>
                <div style="margin-bottom: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <h3 class="status-grace">Periodo di Grazia</h3>
                    <p style="color: #666;">
                        Il tuo abbonamento non è stato rinnovato automaticamente.
                        <br>Scadenza periodo di grazia: <strong><?= date('d/m/Y H:i', strtotime($user['grace_period_until'])) ?></strong>
                    </p>
                </div>
                
                <div class="paypal-instructions">
                    <strong>📋 Hai già pagato su PayPal?</strong><br>
                    Se hai già effettuato il pagamento ricorrente su PayPal ma il sistema non l'ha rilevato automaticamente, 
                    clicca il pulsante qui sotto per confermare manualmente il pagamento.
                    <br><br>
                    <strong>Come verificare su PayPal:</strong><br>
                    1. Vai su PayPal → Attività<br>
                    2. Cerca "DeepLink Pro" negli ultimi pagamenti<br>
                    3. Se vedi il pagamento, clicca "Ho Pagato" qui sotto
                </div>
                
                <form method="POST" style="margin-bottom: 1rem;">
                    <button type="submit" name="confirm_payment" class="btn btn-success" 
                            onclick="return confirm('Confermi di aver già pagato su PayPal?')">
                        ✅ Ho Pagato su PayPal
                    </button>
                </form>
                
                <div style="margin-top: 1rem;">
                    <a href="pricing.php" class="btn btn-primary" style="margin-right: 1rem;">
                        Rinnova Abbonamento
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        Dashboard
                    </a>
                </div>
                
            <?php else: ?>
                <div style="margin-bottom: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
                    <h3 class="status-expired">Abbonamento Scaduto</h3>
                    <p style="color: #666;">
                        Il tuo abbonamento Premium è scaduto. 
                        Rinnova per continuare a usare le funzionalità Premium.
                    </p>
                </div>
                
                <a href="pricing.php" class="btn btn-primary">
                    Rinnova Abbonamento
                </a>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
                <small style="color: #666;">
                    <strong>💡 Suggerimento:</strong> Aggiungi questa pagina ai preferiti per confermare facilmente i pagamenti futuri.
                </small>
            </div>
        </div>
    </div>
</body>
</html>