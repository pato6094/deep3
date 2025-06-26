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
    SELECT name, email, subscription_status, subscription_start, subscription_end, 
           subscription_id, created_at, next_billing_date, grace_period_until, cancellation_requested
    FROM users 
    WHERE id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: auth/logout.php');
    exit;
}

$has_subscription = has_active_subscription($pdo, $user_id);
$is_in_grace_period = $user['grace_period_until'] && strtotime($user['grace_period_until']) > time();

// Gestione richiesta cancellazione abbonamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_subscription'])) {
    if ($user['subscription_id'] && $has_subscription) {
        try {
            // Marca l'abbonamento per la cancellazione alla scadenza
            $stmt = $pdo->prepare("
                UPDATE users 
                SET cancellation_requested = 1
                WHERE id = :user_id
            ");
            
            if ($stmt->execute([':user_id' => $user_id])) {
                // Reindirizza alla pagina di cancellazione PayPal
                header('Location: cancel_subscription_guide.php');
                exit;
            } else {
                $error = 'Errore durante la richiesta di cancellazione.';
            }
        } catch (Exception $e) {
            $error = 'Errore: ' . $e->getMessage();
        }
    } else {
        $error = 'Nessun abbonamento attivo trovato.';
    }
}

// Gestione cambio password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Tutti i campi password sono obbligatori.';
    } elseif (strlen($new_password) < 6) {
        $error = 'La nuova password deve essere di almeno 6 caratteri.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Le nuove password non coincidono.';
    } else {
        // Verifica password attuale
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $stored_password = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $stored_password)) {
            $error = 'Password attuale non corretta.';
        } else {
            // Aggiorna password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
            
            if ($stmt->execute([':password' => $hashed_password, ':user_id' => $user_id])) {
                $success = 'Password cambiata con successo!';
            } else {
                $error = 'Errore durante il cambio password.';
            }
        }
    }
}

// Statistiche utente
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_deeplinks,
        SUM(clicks) as total_clicks,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as deeplinks_last_30_days
    FROM deeplinks 
    WHERE user_id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impostazioni Profilo - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        .subscription-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .subscription-card.premium {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        }
        .subscription-card.grace-period {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }
        .subscription-card.cancellation-pending {
            border-color: #fd7e14;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 15px;
            padding: 1.5rem;
            background: #f8f9fa;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(220, 53, 69, 0.4);
        }
        .btn-warning {
            background: linear-gradient(135deg, #fd7e14 0%, #f39c12 100%);
            color: white;
        }
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(253, 126, 20, 0.4);
        }
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-mini {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        .stat-mini-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        .stat-mini-label {
            font-size: 0.875rem;
            color: #666;
        }
        .paypal-info {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #004085;
        }
        .grace-period-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #856404;
        }
        .cancellation-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #856404;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">DeepLink Pro</a>
                <div class="nav-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="profile.php">Profilo</a>
                    <a href="auth/logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <div class="profile-header">
        <div class="container">
            <div class="profile-avatar">👤</div>
            <h1 style="text-align: center; margin-bottom: 0.5rem;"><?= htmlspecialchars($user['name']) ?></h1>
            <p style="text-align: center; opacity: 0.9;"><?= htmlspecialchars($user['email']) ?></p>
            <p style="text-align: center; opacity: 0.8; font-size: 0.9rem;">
                Membro dal <?= date('d/m/Y', strtotime($user['created_at'])) ?>
            </p>
        </div>
    </div>

    <main class="main" style="padding-top: 0;">
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Statistiche Rapide -->
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="stat-mini-number"><?= $stats['total_deeplinks'] ?? 0 ?></div>
                    <div class="stat-mini-label">Deeplink Totali</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-number"><?= $has_subscription ? ($stats['total_clicks'] ?? 0) : '🔒' ?></div>
                    <div class="stat-mini-label">Click Totali</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-number"><?= $stats['deeplinks_last_30_days'] ?? 0 ?></div>
                    <div class="stat-mini-label">Ultimi 30 giorni</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-number">
                        <?= $has_subscription ? ($is_in_grace_period ? 'GRACE' : ($user['cancellation_requested'] ? 'ENDING' : 'PRO')) : 'FREE' ?>
                    </div>
                    <div class="stat-mini-label">Piano Attuale</div>
                </div>
            </div>

            <!-- Stato Abbonamento -->
            <div class="subscription-card <?= $has_subscription ? ($user['cancellation_requested'] ? 'cancellation-pending' : ($is_in_grace_period ? 'grace-period' : 'premium')) : '' ?>">
                <h2>📋 Stato Abbonamento</h2>
                
                <?php if ($has_subscription && !$is_in_grace_period && !$user['cancellation_requested']): ?>
                    <div style="margin: 1rem 0;">
                        <span style="background: #28a745; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                            ✓ Premium Attivo
                        </span>
                    </div>
                    <p style="color: #155724; margin-bottom: 1rem;">
                        Il tuo abbonamento Premium è attivo e si rinnova automaticamente.
                        <?php if ($user['next_billing_date']): ?>
                            <br>Prossimo rinnovo previsto: <strong><?= date('d/m/Y', strtotime($user['next_billing_date'])) ?></strong>
                        <?php endif; ?>
                    </p>
                    <ul style="color: #155724; margin-bottom: 1.5rem;">
                        <li>✓ Deeplink illimitati</li>
                        <li>✓ URL personalizzati</li>
                        <li>✓ Statistiche avanzate</li>
                        <li>✓ Link permanenti</li>
                    </ul>
                    
                    <!-- BOTTONE ELIMINA ABBONAMENTO -->
                    <form method="POST" style="margin-bottom: 1rem;">
                        <button type="submit" name="cancel_subscription" class="btn btn-warning"
                                onclick="return confirm('⚠️ ATTENZIONE: Vuoi davvero cancellare l\'abbonamento?\n\n• Continuerai ad avere accesso Premium fino alla scadenza del periodo già pagato\n• Dopo 1 mese esatto dalla sottoscrizione, l\'account tornerà FREE\n• Dovrai completare la cancellazione anche su PayPal\n\nConfermi la cancellazione?')">
                            🗑️ Elimina Abbonamento
                        </button>
                    </form>
                    
                <?php elseif ($user['cancellation_requested'] && $has_subscription): ?>
                    <div style="margin: 1rem 0;">
                        <span style="background: #fd7e14; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                            ⚠ Cancellazione Richiesta
                        </span>
                    </div>
                    <p style="color: #856404; margin-bottom: 1rem;">
                        Hai richiesto la cancellazione dell'abbonamento. 
                        Continuerai ad avere accesso Premium fino al <strong><?= date('d/m/Y', strtotime($user['subscription_end'])) ?></strong>.
                    </p>
                    
                    <div class="cancellation-warning">
                        <strong>📋 Prossimi Passi:</strong><br>
                        1. ✅ Cancellazione richiesta sul nostro sistema<br>
                        2. ⏳ <strong>Completa la cancellazione su PayPal</strong> per evitare addebiti futuri<br>
                        3. 🗓️ Il tuo account tornerà automaticamente al piano FREE il <?= date('d/m/Y', strtotime($user['subscription_end'])) ?>
                        
                        <div style="margin-top: 1rem; text-align: center;">
                            <a href="https://www.paypal.com/myaccount/autopay/" target="_blank" 
                               class="btn btn-primary" style="margin-right: 1rem;">
                                Cancella su PayPal
                            </a>
                            <small style="display: block; margin-top: 0.5rem; color: #666;">
                                Vai su PayPal → Impostazioni → Pagamenti automatici → Trova "DeepLink Pro" → Cancella
                            </small>
                        </div>
                    </div>
                    
                <?php elseif ($is_in_grace_period): ?>
                    <div style="margin: 1rem 0;">
                        <span style="background: #ffc107; color: #212529; padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                            ⚠ Periodo di Grazia
                        </span>
                    </div>
                    <p style="color: #856404; margin-bottom: 1rem;">
                        Il tuo abbonamento non è stato rinnovato automaticamente. 
                        Hai accesso alle funzionalità Premium fino al <strong><?= date('d/m/Y H:i', strtotime($user['grace_period_until'])) ?></strong>.
                    </p>
                    
                    <div class="grace-period-warning">
                        <strong>⏰ Azione Richiesta:</strong> 
                        Il tuo abbonamento PayPal potrebbe essere stato cancellato o il pagamento non è andato a buon fine. 
                        Controlla il tuo account PayPal o rinnova l'abbonamento per continuare a usare le funzionalità Premium.
                    </div>
                    
                    <div style="margin-top: 1rem;">
                        <a href="pricing.php" class="btn btn-primary" style="margin-right: 1rem;">
                            Rinnova Abbonamento
                        </a>
                        <a href="payment_success.php" class="btn btn-secondary">
                            Ho già pagato
                        </a>
                    </div>
                    
                <?php else: ?>
                    <div style="margin: 1rem 0;">
                        <span style="background: #6c757d; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                            Piano Gratuito
                        </span>
                    </div>
                    <p style="color: #666; margin-bottom: 1rem;">
                        Stai utilizzando il piano gratuito con alcune limitazioni.
                    </p>
                    <ul style="color: #666; margin-bottom: 1.5rem;">
                        <li>• 5 deeplink al mese</li>
                        <li>• Link scadono dopo 5 giorni</li>
                        <li>• Statistiche limitate</li>
                    </ul>
                    
                    <a href="pricing.php" class="btn btn-success">
                        🚀 Diventa Premium
                    </a>
                <?php endif; ?>
                
                <?php if ($user['subscription_id'] && !$user['cancellation_requested']): ?>
                    <div class="paypal-info">
                        <strong>💳 Gestione Abbonamento:</strong> Il tuo abbonamento si rinnova automaticamente ogni mese tramite PayPal. 
                        Puoi anche gestire l'abbonamento direttamente dal tuo account PayPal.
                        <br><br>
                        <a href="https://www.paypal.com/myaccount/autopay/" target="_blank" style="color: #004085; text-decoration: underline;">
                            Visualizza su PayPal →
                        </a>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                        <small style="color: #666;">
                            <strong>ID Abbonamento:</strong> <?= htmlspecialchars($user['subscription_id']) ?><br>
                            <?php if ($user['subscription_start']): ?>
                                <strong>Inizio:</strong> <?= date('d/m/Y', strtotime($user['subscription_start'])) ?><br>
                            <?php endif; ?>
                            <?php if ($user['subscription_end']): ?>
                                <strong>Scadenza attuale:</strong> <?= date('d/m/Y', strtotime($user['subscription_end'])) ?><br>
                            <?php endif; ?>
                            <?php if ($user['next_billing_date']): ?>
                                <strong>Prossimo rinnovo:</strong> <?= date('d/m/Y', strtotime($user['next_billing_date'])) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cambio Password -->
            <div class="card">
                <h2>🔒 Sicurezza Account</h2>
                <p style="color: #666; margin-bottom: 2rem;">Cambia la tua password per mantenere il tuo account sicuro</p>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password">Password Attuale</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nuova Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               minlength="6" required>
                        <small style="color: #666;">Minimo 6 caratteri</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Conferma Nuova Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               minlength="6" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        Cambia Password
                    </button>
                </form>
            </div>

            <!-- Informazioni Account -->
            <div class="card">
                <h2>ℹ️ Informazioni Account</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                    <div>
                        <h3 style="color: #333; margin-bottom: 1rem;">Dettagli Personali</h3>
                        <p><strong>Nome:</strong> <?= htmlspecialchars($user['name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <p><strong>Registrato:</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></p>
                    </div>
                    
                    <div>
                        <h3 style="color: #333; margin-bottom: 1rem;">Utilizzo</h3>
                        <p><strong>Deeplink creati:</strong> <?= $stats['total_deeplinks'] ?? 0 ?></p>
                        <?php if ($has_subscription): ?>
                            <p><strong>Click totali:</strong> <?= $stats['total_clicks'] ?? 0 ?></p>
                        <?php endif; ?>
                        <p><strong>Attività recente:</strong> <?= $stats['deeplinks_last_30_days'] ?? 0 ?> deeplink negli ultimi 30 giorni</p>
                    </div>
                </div>
            </div>

            <!-- Zona Pericolosa -->
            <div class="danger-zone">
                <h2 style="color: #dc3545;">⚠️ Zona Pericolosa</h2>
                <p style="color: #666; margin-bottom: 1.5rem;">
                    Le azioni in questa sezione sono irreversibili. Procedi con cautela.
                </p>
                
                <div style="border: 1px solid #dc3545; border-radius: 8px; padding: 1rem; background: white;">
                    <h3 style="color: #dc3545; margin-bottom: 0.5rem;">Elimina Account</h3>
                    <p style="color: #666; margin-bottom: 1rem; font-size: 0.9rem;">
                        Questa azione eliminerà permanentemente il tuo account e tutti i tuoi deeplink. 
                        Non sarà possibile recuperare i dati.
                    </p>
                    <button class="btn btn-danger" onclick="alert('Funzionalità in arrivo. Contatta il supporto per eliminare l\'account.')">
                        Elimina Account
                    </button>
                </div>
            </div>
        </div>
    </main>
</body>
</html>