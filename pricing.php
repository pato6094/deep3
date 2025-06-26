<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prezzi - DeepLink Generator</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">DeepLink Pro</a>
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
                <h1>Scegli il Piano Perfetto</h1>
                <p>Inizia gratis e passa a Premium quando sei pronto per di più</p>
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
                            Inizia Gratis
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-secondary" style="width: 100%;">
                            Piano Attuale
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
                                Piano Attivo
                            </div>
                        <?php else: ?>
                            <div id="paypal-button-container-P-7RV70051U1318953DNBNLR3Q"></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="auth/register.php" class="btn btn-primary" style="width: 100%;">
                            Registrati per Iniziare
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FAQ -->
            <div class="card" style="margin-top: 4rem;">
                <h2>Domande Frequenti</h2>
                
                <div style="margin-top: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">Cosa sono i deeplink?</h3>
                    <p style="color: #666; margin-bottom: 2rem;">
                        I deeplink sono collegamenti speciali che aprono direttamente le app mobili invece del browser, 
                        offrendo un'esperienza utente migliore e più fluida.
                    </p>
                    
                    <h3 style="color: #333; margin-bottom: 1rem;">Quali piattaforme supportate?</h3>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Supportiamo YouTube, Instagram, Twitch, Amazon e molte altre piattaforme popolari. 
                        Aggiungiamo costantemente nuove piattaforme.
                    </p>
                    
                    <h3 style="color: #333; margin-bottom: 1rem;">Cosa succede se cancello l'abbonamento?</h3>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Se cancelli l'abbonamento da PayPal, continuerai ad avere accesso alle funzionalità Premium 
                        fino alla fine del periodo già pagato. Dopo 3 giorni dalla scadenza, il tuo account 
                        tornerà automaticamente al piano gratuito.
                    </p>
                    
                    <h3 style="color: #333; margin-bottom: 1rem;">Come gestisco il mio abbonamento?</h3>
                    <p style="color: #666;">
                        Puoi gestire il tuo abbonamento direttamente dal tuo account PayPal. 
                        Vai su PayPal → Impostazioni → Pagamenti automatici per modificare o cancellare l'abbonamento.
                    </p>
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
</body>
</html>