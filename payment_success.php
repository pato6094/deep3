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

// Se viene chiamata questa pagina, significa che il pagamento è andato a buon fine
// Aggiorna la data di prossimo rinnovo
if (update_billing_date($pdo, $user_id)) {
    $success_message = 'Pagamento ricevuto con successo! Il tuo abbonamento Premium è stato rinnovato.';
    
    // Log per debug
    error_log("Pagamento confermato per utente $user_id - Data rinnovo aggiornata");
} else {
    $error_message = 'Errore nell\'aggiornamento dell\'abbonamento. Contatta il supporto.';
}

// Recupera informazioni utente aggiornate
$stmt = $pdo->prepare("
    SELECT subscription_status, subscription_end, next_billing_date 
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
        .billing-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <?php if ($success_message): ?>
                <div class="success-icon">✅</div>
                <h2 style="color: #28a745; margin-bottom: 1rem;">Pagamento Confermato!</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    <?= htmlspecialchars($success_message) ?>
                </p>
                
                <?php if ($user): ?>
                <div class="billing-info">
                    <h3 style="color: #333; margin-bottom: 1rem;">Dettagli Abbonamento</h3>
                    <p><strong>Stato:</strong> 
                        <span style="color: #28a745;">✓ Premium Attivo</span>
                    </p>
                    <?php if ($user['next_billing_date']): ?>
                    <p><strong>Prossimo rinnovo:</strong> 
                        <?= date('d/m/Y', strtotime($user['next_billing_date'])) ?>
                    </p>
                    <?php endif; ?>
                    <p><strong>Scadenza attuale:</strong> 
                        <?= date('d/m/Y', strtotime($user['subscription_end'])) ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 2rem;">
                    <a href="dashboard.php" class="btn btn-primary" style="margin-right: 1rem;">
                        Vai alla Dashboard
                    </a>
                    <a href="profile.php" class="btn btn-secondary">
                        Gestisci Profilo
                    </a>
                </div>
                
            <?php else: ?>
                <div class="success-icon" style="color: #dc3545;">❌</div>
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
</body>
</html>