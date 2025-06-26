<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prezzi - DeepLink Pro</title>
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
                    <?php if (is_logged_in()): ?>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="auth/logout.php">Logout</a>
                    <?php else: ?>
                        <a href="auth/login.php">Login</a>
                        <a href="auth/register.php">Registrati</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="hero">
                <h1>Scegli il Piano Perfetto per Te</h1>
                <p>Inizia gratis e passa a Premium quando sei pronto per sbloccare tutto il potenziale</p>
            </div>

            <div class="pricing-grid">
                <!-- Piano Gratuito -->
                <div class="pricing-card">
                    <h3>Piano Gratuito</h3>
                    <div class="price">€0</div>
                    <div class="price-period">per sempre</div>
                    
                    <ul class="features">
                        <li>5 deeplink al mese</li>
                        <li>Supporto per tutti i social</li>
                        <li>Dashboard personale</li>
                        <li>Cronologia deeplink</li>
                        <li>Link scadono dopo 5 giorni</li>
                    </ul>
                    
                    <?php if (!is_logged_in()): ?>
                        <a href="auth/register.php" class="btn btn-secondary" style="width: 100%;">
                            🚀 Inizia Gratis
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-secondary" style="width: 100%;">
                            📊 Piano Attuale
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Piano Premium -->
                <div class="pricing-card featured">
                    <h3>Piano Premium</h3>
                    <div class="price">€9.99</div>
                    <div class="price-period">al mese</div>
                    
                    <ul class="features">
                        <li>Deeplink illimitati</li>
                        <li>Link permanenti (non scadono mai)</li>
                        <li>URL personalizzati</li>
                        <li>Analytics avanzate</li>
                        <li>Supporto prioritario</li>
                        <li>API personalizzata</li>
                        <li>Nessuna pubblicità</li>
                    </ul>
                    
                    <?php if (is_logged_in()): ?>
                        <?php if (has_active_subscription($pdo, $_SESSION['user_id'])): ?>
                            <div class="btn btn-success" style="width: 100%; cursor: default;">
                                ✅ Piano Attivo
                            </div>
                        <?php else: ?>
                            <div id="paypal-button-container-P-7RV70051U1318953DNBNLR3Q"></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="auth/register.php" class="btn btn-primary" style="width: 100%;">
                            🚀 Registrati per Iniziare
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FAQ -->
            <div class="card" style="margin-top: 4rem;">
                <h2>❓ Domande Frequenti</h2>
                
                <div style="margin-top: 2rem;">
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #ffffff; margin-bottom: 1rem;">🔗 Cosa sono i deeplink?</h3>
                        <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                            I deeplink sono collegamenti speciali che aprono direttamente le app mobili invece del browser, 
                            offrendo un'esperienza utente migliore e più fluida per i tuoi contenuti.
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #ffffff; margin-bottom: 1rem;">📱 Quali piattaforme supportate?</h3>
                        <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                            Supportiamo YouTube, Instagram, Twitch, Amazon e molte altre piattaforme popolari. 
                            Aggiungiamo costantemente nuove piattaforme basandoci sui feedback degli utenti.
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #ffffff; margin-bottom: 1rem;">❌ Cosa succede se cancello l'abbonamento?</h3>
                        <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                            Se cancelli l'abbonamento da PayPal, continuerai ad avere accesso alle funzionalità Premium 
                            fino alla fine del periodo già pagato. Dopo 1 mese esatto dalla sottoscrizione, il tuo account 
                            tornerà automaticamente al piano gratuito.
                        </p>
                    </div>
                    
                    <div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem;">⚙️ Come gestisco il mio abbonamento?</h3>
                        <p style="color: rgba(255, 255, 255, 0.7);">
                            Puoi gestire il tuo abbonamento direttamente dal tuo account PayPal. 
                            Vai su PayPal → Impostazioni → Pagamenti automatici per modificare o cancellare l'abbonamento.
                        </p>
                    </div>
                </div>
            </div>

            <!-- CTA finale -->
            <div style="margin-top: 4rem; text-align: center;">
                <div class="card" style="background: rgba(102, 126, 234, 0.1); border-color: rgba(102, 126, 234, 0.3);">
                    <h2 style="font-size: 2rem; margin-bottom: 1rem;">Pronto a potenziare i tuoi link?</h2>
                    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                        Unisciti a migliaia di professionisti che usano DeepLink Pro
                    </p>
                    <?php if (!is_logged_in()): ?>
                        <a href="auth/register.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1.25rem 2.5rem;">
                            🚀 Inizia Gratis Ora
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php if (is_logged_in() && !has_active_subscription($pdo, $_SESSION['user_id'])): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=AQJDnagVff_mI2EtgXHdsCD_hduUKkOwKnGn2goqziCThEKDgzGDV3UWbza5b6Bz5w-kz4Ba-qqwxWyr&vault=true&intent=subscription" data-sdk-integration-source="button-factory"></script>
    <script>
        paypal.Buttons({
            style: {
                shape: 'rect',
                color: 'gold',
                layout: 'vertical',
                label: 'subscribe'
            },
            createSubscription: function(data, actions) {
                return actions.subscription.create({
                    plan_id: 'P-7RV70051U1318953DNBNLR3Q',
                    application_context: {
                        brand_name: "DeepLink Pro",
                        locale: "it-IT",
                        shipping_preference: "NO_SHIPPING",
                        user_action: "SUBSCRIBE_NOW",
                        payment_method: {
                            payer_selected: "PAYPAL",
                            payee_preferred: "IMMEDIATE_PAYMENT_REQUIRED"
                        },
                        return_url: "<?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http' ?>://<?= $_SERVER['HTTP_HOST'] ?><?= dirname($_SERVER['PHP_SELF']) ?>/payment_success.php?user_id=<?= $_SESSION['user_id'] ?>",
                        cancel_url: "<?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http' ?>://<?= $_SERVER['HTTP_HOST'] ?><?= dirname($_SERVER['PHP_SELF']) ?>/pricing.php?cancelled=1"
                    }
                });
            },
            onApprove: function(data, actions) {
                // Invia i dati dell'abbonamento al server
                fetch('process_subscription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        subscriptionID: data.subscriptionID,
                        user_id: <?= $_SESSION['user_id'] ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reindirizza alla pagina di successo
                        window.location.href = 'payment_success.php?first_payment=1';
                    } else {
                        alert('Errore nell\'attivazione dell\'abbonamento: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    alert('Errore di comunicazione con il server');
                });
            },
            onError: function(err) {
                console.error('Errore PayPal:', err);
                alert('Errore durante il pagamento. Riprova più tardi.');
            }
        }).render('#paypal-button-container-P-7RV70051U1318953DNBNLR3Q');
    </script>
    <?php endif; ?>

    <!-- Floating elements -->
    <div style="position: fixed; top: 10%; right: 5%; width: 120px; height: 120px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border-radius: 50%; filter: blur(50px); pointer-events: none; z-index: -1;"></div>
    <div style="position: fixed; bottom: 30%; left: 5%; width: 80px; height: 80px; background: linear-gradient(135deg, rgba(255, 119, 198, 0.1), rgba(120, 219, 255, 0.1)); border-radius: 50%; filter: blur(30px); pointer-events: none; z-index: -1;"></div>
</body>
</html>