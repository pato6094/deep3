<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$deeplink_url = "";
$error = "";

// Se l'utente è loggato, reindirizza alla dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_url = filter_var($_POST['url'], FILTER_SANITIZE_URL);

    if (filter_var($original_url, FILTER_VALIDATE_URL)) {
        $deeplink = generate_deeplink($original_url);
        $id = substr(hash('sha256', $original_url), 0, 6);

        // Inserimento nel DB senza user_id per utenti non registrati
        $stmt = $pdo->prepare("REPLACE INTO deeplinks (id, original_url, deeplink, created_at) VALUES (:id, :original_url, :deeplink, NOW())");
        $stmt->execute([
            ':id' => $id,
            ':original_url' => $original_url,
            ':deeplink' => $deeplink
        ]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $deeplink_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/redirect.php?id=$id";
    } else {
        $error = "Inserisci un URL valido.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeepLink Pro - Converti URL in Deeplink Professionali</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🚀 DeepLink Pro</a>
                <div class="nav-links">
                    <a href="pricing.php">Prezzi</a>
                    <a href="auth/login.php">Login</a>
                    <a href="auth/register.php" class="btn btn-primary">Inizia Gratis</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="hero">
                <h1>Il tuo sito dovrebbe fare di più che sembrare bello</h1>
                <p>
                    DeepLink Pro unisce marketer, designer e sviluppatori per creare, gestire e 
                    ottimizzare esperienze web di impatto con deeplink professionali
                </p>
            </div>

            <div style="max-width: 700px; margin: 0 auto;">
                <div class="card">
                    <h2>✨ Prova Gratuita</h2>
                    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                        Genera il tuo primo deeplink gratuitamente. Registrati per funzionalità avanzate e analytics!
                    </p>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="url">URL da convertire</label>
                            <input type="url" id="url" name="url" class="form-control" 
                                   placeholder="https://www.youtube.com/watch?v=..." required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            🚀 Genera Deeplink
                        </button>
                    </form>
                    
                    <?php if ($deeplink_url): ?>
                        <div class="result">
                            <strong>🎉 Il tuo deeplink è pronto!</strong><br>
                            <a href="<?= htmlspecialchars($deeplink_url) ?>" target="_blank" style="word-break: break-all;">
                                <?= htmlspecialchars($deeplink_url) ?>
                            </a>
                            <div class="expiry-info" style="margin-top: 1rem;">
                                ⏰ <strong>Attenzione:</strong> I link gratuiti scadono dopo 5 giorni. 
                                <a href="auth/register.php" style="color: #fbbf24;">Registrati</a> per creare fino a 5 link al mese!
                            </div>
                        </div>
                        
                        <div class="alert alert-info" style="margin-top: 1rem;">
                            <strong>🚀 Vuoi di più?</strong> 
                            <a href="auth/register.php" style="color: #93c5fd;">Registrati</a> 
                            per creare fino a 5 deeplink al mese gratuitamente!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Caratteristiche -->
            <div style="margin-top: 6rem;">
                <h2 style="text-align: center; color: white; margin-bottom: 3rem; font-size: 2.5rem; font-weight: 700;">
                    Perché Scegliere DeepLink Pro?
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
                    <div class="card">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🚀</div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem;">Veloce e Affidabile</h3>
                        <p style="color: rgba(255, 255, 255, 0.7);">
                            Genera deeplink in millisecondi con la nostra tecnologia ottimizzata per le performance.
                        </p>
                    </div>
                    
                    <div class="card">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📱</div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem;">Multi-Piattaforma</h3>
                        <p style="color: rgba(255, 255, 255, 0.7);">
                            Supporto completo per YouTube, Instagram, Twitch, Amazon e molte altre app popolari.
                        </p>
                    </div>
                    
                    <div class="card">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem;">Analytics Avanzate</h3>
                        <p style="color: rgba(255, 255, 255, 0.7);">
                            Traccia le performance dei tuoi deeplink con statistiche dettagliate e insights actionable.
                        </p>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div style="margin-top: 6rem; text-align: center;">
                <div class="card" style="background: rgba(102, 126, 234, 0.1); border-color: rgba(102, 126, 234, 0.3);">
                    <h2 style="font-size: 2rem; margin-bottom: 1rem;">Pronto a iniziare?</h2>
                    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem; font-size: 1.1rem;">
                        Unisciti a migliaia di professionisti che usano DeepLink Pro per ottimizzare le loro campagne
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="auth/register.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1.25rem 2.5rem;">
                            🚀 Inizia Gratis
                        </a>
                        <a href="pricing.php" class="btn btn-secondary" style="font-size: 1.1rem; padding: 1.25rem 2.5rem;">
                            📊 Vedi i Prezzi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Floating elements for visual appeal -->
    <div style="position: fixed; top: 20%; left: 10%; width: 100px; height: 100px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border-radius: 50%; filter: blur(40px); pointer-events: none; z-index: -1;"></div>
    <div style="position: fixed; bottom: 20%; right: 10%; width: 150px; height: 150px; background: linear-gradient(135deg, rgba(255, 119, 198, 0.1), rgba(120, 219, 255, 0.1)); border-radius: 50%; filter: blur(60px); pointer-events: none; z-index: -1;"></div>
</body>
</html>