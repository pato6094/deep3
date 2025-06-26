<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email e password sono obbligatori.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            header('Location: ../dashboard.php');
            exit;
        } else {
            $error = 'Email o password non corretti.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DeepLink Pro</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
                        <h2>Accedi al tuo Account</h2>
                        <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 0;">
                            Benvenuto! Inserisci le tue credenziali per continuare
                        </p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   placeholder="la-tua-email@esempio.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="La tua password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            🚀 Accedi
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <p style="color: rgba(255, 255, 255, 0.7);">
                            Non hai un account? 
                            <a href="register.php" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                Registrati qui
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