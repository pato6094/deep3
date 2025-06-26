<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/ip_restrictions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Ottieni IP del client
    $client_ip = get_client_ip();
    
    // Verifica restrizioni IP PRIMA di qualsiasi altra validazione
    $ip_check = check_ip_registration_limits($pdo, $client_ip);
    
    if (!$ip_check['allowed']) {
        $error = $ip_check['message'];
        
        // Log del tentativo bloccato
        log_ip_attempt($pdo, $client_ip, 'blocked', $ip_check['reason']);
    } else {
        // Validazione normale
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Tutti i campi sono obbligatori.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email non valida.';
        } elseif (strlen($password) < 6) {
            $error = 'La password deve essere di almeno 6 caratteri.';
        } elseif ($password !== $confirm_password) {
            $error = 'Le password non coincidono.';
        } else {
            // Verifica se l'email esiste già
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            
            if ($stmt->fetch()) {
                $error = 'Email già registrata.';
                // Log tentativo con email duplicata
                log_ip_attempt($pdo, $client_ip, 'attempt', 'Email già registrata: ' . $email);
            } else {
                // Registrazione utente
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, created_at) 
                    VALUES (:name, :email, :password, NOW())
                ");
                
                if ($stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password' => $hashed_password
                ])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Log registrazione completata con successo
                    log_ip_registration($pdo, $client_ip, $user_id);
                    
                    $success = 'Registrazione completata! Ora puoi effettuare il login.';
                } else {
                    $error = 'Errore durante la registrazione.';
                    log_ip_attempt($pdo, $client_ip, 'attempt', 'Errore database durante registrazione');
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - DeepLink Pro</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .ip-info {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .ip-warning {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }
        
        .rate-limit-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 1rem;
            font-size: 0.8rem;
            color: #93c5fd;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="../index.php" class="logo">🚀 DeepLink Pro</a>
                <div class="nav-links">
                    <a href="../auth/login.php">Login</a>
                    <a href="../auth/register.php">Registrati</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div style="max-width: 500px; margin: 0 auto;">
                <div class="card">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🚀</div>
                        <h2>Crea il tuo Account</h2>
                        <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 0;">
                            Registrati per iniziare a creare deeplink personalizzati e accedere alle analytics
                        </p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                        
                        <?php if (strpos($error, 'Troppe registrazioni') !== false): ?>
                            <div class="ip-info ip-warning">
                                <strong>🛡️ Protezione Anti-Spam Attiva</strong><br>
                                Per proteggere il nostro servizio, limitiamo il numero di registrazioni per indirizzo IP.<br>
                                <small>Se hai bisogno di registrare più account, contatta il supporto.</small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Nome Completo</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                   placeholder="Il tuo nome completo" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   placeholder="la-tua-email@esempio.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Minimo 6 caratteri" required>
                            <small style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">
                                Minimo 6 caratteri
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Conferma Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="Ripeti la password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            🚀 Registrati
                        </button>
                    </form>
                    
                    <!-- Informazioni sui limiti di registrazione -->
                    <div class="rate-limit-info">
                        <strong>🛡️ Protezione Anti-Spam:</strong> Per la sicurezza del servizio, limitiamo le registrazioni a 
                        <?= $IP_RESTRICTION_CONFIG['max_registrations_per_hour'] ?> all'ora e 
                        <?= $IP_RESTRICTION_CONFIG['max_registrations_per_day'] ?> al giorno per IP.
                    </div>
                    
                    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <p style="color: rgba(255, 255, 255, 0.7);">
                            Hai già un account? 
                            <a href="login.php" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                Accedi qui
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Floating elements -->
    <div style="position: fixed; top: 20%; left: 10%; width: 100px; height: 100px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border-radius: 50%; filter: blur(40px); pointer-events: none; z-index: -1;"></div>
    <div style="position: fixed; bottom: 20%; right: 10%; width: 150px; height: 150px; background: linear-gradient(135deg, rgba(255, 119, 198, 0.1), rgba(120, 219, 255, 0.1)); border-radius: 50%; filter: blur(60px); pointer-events: none; z-index: -1;"></div>
</body>
</html>