<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verifica se l'admin è loggato
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Recupera utenti che hanno richiesto la cancellazione
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.subscription_start,
        u.subscription_end,
        u.subscription_status,
        u.cancellation_requested,
        u.created_at,
        -- Calcola quando è stato premuto il bottone (approssimativo basato su quando subscription_end è stato impostato)
        CASE 
            WHEN u.cancellation_requested = 1 THEN 
                COALESCE(u.updated_at, u.subscription_start)
            ELSE NULL 
        END as cancellation_date,
        -- Stato futuro dell'abbonamento
        CASE 
            WHEN u.cancellation_requested = 1 AND u.subscription_end > NOW() THEN 'Scadrà il'
            WHEN u.cancellation_requested = 1 AND u.subscription_end <= NOW() THEN 'Scaduto - Diventerà FREE'
            ELSE 'Attivo'
        END as future_status,
        -- Giorni rimanenti
        CASE 
            WHEN u.cancellation_requested = 1 AND u.subscription_end > NOW() THEN 
                DATEDIFF(u.subscription_end, NOW())
            ELSE 0
        END as days_remaining
    FROM users u
    WHERE u.subscription_id IS NOT NULL
    AND (u.cancellation_requested = 1 OR u.subscription_status IN ('cancelled', 'expired'))
    ORDER BY 
        CASE 
            WHEN u.cancellation_requested = 1 AND u.subscription_end > NOW() THEN 1
            WHEN u.cancellation_requested = 1 AND u.subscription_end <= NOW() THEN 2
            ELSE 3
        END,
        u.subscription_end DESC
");
$stmt->execute();
$cancellation_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiche cancellazioni
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN cancellation_requested = 1 AND subscription_end > NOW() THEN 1 END) as pending_cancellations,
        COUNT(CASE WHEN cancellation_requested = 1 AND subscription_end <= NOW() THEN 1 END) as completed_cancellations,
        COUNT(CASE WHEN subscription_status = 'cancelled' THEN 1 END) as total_cancelled,
        COUNT(CASE WHEN cancellation_requested = 1 AND subscription_end > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as cancellations_this_week
    FROM users 
    WHERE subscription_id IS NOT NULL
");
$cancellation_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Cancellazioni - Admin Panel</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .admin-header .logo {
            color: white;
        }
        .cancellation-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .cancellation-stat-card {
            background: linear-gradient(135deg, #fd7e14 0%, #f39c12 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        .status-cancelled {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .days-remaining {
            font-weight: 600;
            color: #fd7e14;
        }
        .days-remaining.urgent {
            color: #dc3545;
        }
        .cancellation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .cancellation-table th {
            text-align: left;
            padding: 1rem;
            color: #666;
            font-weight: 600;
            border-bottom: 2px solid #eee;
            background: #f8f9fa;
        }
        .cancellation-table td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .cancellation-table tr:hover {
            background: #f8f9fa;
        }
        .user-info {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        .user-email {
            font-size: 0.875rem;
            color: #666;
        }
        .date-info {
            font-size: 0.875rem;
            color: #666;
        }
        .priority-high {
            border-left: 4px solid #dc3545;
        }
        .priority-medium {
            border-left: 4px solid #ffc107;
        }
        .priority-low {
            border-left: 4px solid #28a745;
        }
        .nav-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
        }
        .nav-tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }
        .nav-tab.active {
            color: #dc3545;
            border-bottom-color: #dc3545;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <header class="header admin-header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🛡️ Admin Panel - DeepLink Pro</a>
                <div class="nav-links">
                    <a href="index.php">Dashboard</a>
                    <a href="cancellations.php" style="color: #ffc107;">Cancellazioni</a>
                    <span>Admin: <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                    <a href="logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <!-- Statistiche Cancellazioni -->
            <div class="cancellation-stats">
                <div class="cancellation-stat-card">
                    <div class="stat-number"><?= $cancellation_stats['pending_cancellations'] ?></div>
                    <div class="stat-label">Cancellazioni in Attesa</div>
                </div>
                <div class="cancellation-stat-card">
                    <div class="stat-number"><?= $cancellation_stats['completed_cancellations'] ?></div>
                    <div class="stat-label">Cancellazioni Completate</div>
                </div>
                <div class="cancellation-stat-card">
                    <div class="stat-number"><?= $cancellation_stats['total_cancelled'] ?></div>
                    <div class="stat-label">Totale Cancellati</div>
                </div>
                <div class="cancellation-stat-card">
                    <div class="stat-number"><?= $cancellation_stats['cancellations_this_week'] ?></div>
                    <div class="stat-label">Questa Settimana</div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="showTab('pending')">
                    🕐 In Attesa (<?= $cancellation_stats['pending_cancellations'] ?>)
                </button>
                <button class="nav-tab" onclick="showTab('completed')">
                    ✅ Completate (<?= $cancellation_stats['completed_cancellations'] ?>)
                </button>
                <button class="nav-tab" onclick="showTab('all')">
                    📋 Tutte (<?= count($cancellation_users) ?>)
                </button>
            </div>

            <!-- Tab Content: Cancellazioni in Attesa -->
            <div id="pending-tab" class="tab-content active">
                <div class="card">
                    <h2>🕐 Cancellazioni in Attesa di Scadenza</h2>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Utenti che hanno richiesto la cancellazione ma hanno ancora accesso Premium
                    </p>
                    
                    <div style="overflow-x: auto;">
                        <table class="cancellation-table">
                            <thead>
                                <tr>
                                    <th>Utente</th>
                                    <th>Primo Abbonamento</th>
                                    <th>Cancellazione Richiesta</th>
                                    <th>Stato Attuale</th>
                                    <th>Scadenza</th>
                                    <th>Giorni Rimanenti</th>
                                    <th>Stato Futuro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $pending_users = array_filter($cancellation_users, function($user) {
                                    return $user['cancellation_requested'] == 1 && strtotime($user['subscription_end']) > time();
                                });
                                ?>
                                <?php if (!empty($pending_users)): ?>
                                    <?php foreach ($pending_users as $user): ?>
                                    <?php 
                                        $days_remaining = $user['days_remaining'];
                                        $priority_class = $days_remaining <= 3 ? 'priority-high' : ($days_remaining <= 7 ? 'priority-medium' : 'priority-low');
                                    ?>
                                    <tr class="<?= $priority_class ?>">
                                        <td>
                                            <div class="user-info"><?= htmlspecialchars($user['name']) ?></div>
                                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= $user['subscription_start'] ? date('d/m/Y H:i', strtotime($user['subscription_start'])) : 'N/A' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= $user['cancellation_date'] ? date('d/m/Y H:i', strtotime($user['cancellation_date'])) : 'N/A' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-pending">
                                                ⚠ Premium (Cancellazione Richiesta)
                                            </span>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= date('d/m/Y H:i', strtotime($user['subscription_end'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="days-remaining <?= $days_remaining <= 3 ? 'urgent' : '' ?>">
                                                <?= $days_remaining ?> giorni
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-expired">
                                                🔄 Diventerà FREE
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #666; padding: 2rem;">
                                            Nessuna cancellazione in attesa
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Cancellazioni Completate -->
            <div id="completed-tab" class="tab-content">
                <div class="card">
                    <h2>✅ Cancellazioni Completate</h2>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Utenti che hanno completato il processo di cancellazione e sono tornati al piano FREE
                    </p>
                    
                    <div style="overflow-x: auto;">
                        <table class="cancellation-table">
                            <thead>
                                <tr>
                                    <th>Utente</th>
                                    <th>Primo Abbonamento</th>
                                    <th>Cancellazione Richiesta</th>
                                    <th>Data Scadenza</th>
                                    <th>Stato Attuale</th>
                                    <th>Durata Abbonamento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $completed_users = array_filter($cancellation_users, function($user) {
                                    return ($user['cancellation_requested'] == 1 && strtotime($user['subscription_end']) <= time()) 
                                           || $user['subscription_status'] == 'cancelled';
                                });
                                ?>
                                <?php if (!empty($completed_users)): ?>
                                    <?php foreach ($completed_users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info"><?= htmlspecialchars($user['name']) ?></div>
                                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= $user['subscription_start'] ? date('d/m/Y H:i', strtotime($user['subscription_start'])) : 'N/A' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= $user['cancellation_date'] ? date('d/m/Y H:i', strtotime($user['cancellation_date'])) : 'N/A' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= date('d/m/Y H:i', strtotime($user['subscription_end'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-cancelled">
                                                ✅ FREE (Cancellato)
                                            </span>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?php 
                                                if ($user['subscription_start'] && $user['subscription_end']) {
                                                    $start = new DateTime($user['subscription_start']);
                                                    $end = new DateTime($user['subscription_end']);
                                                    $interval = $start->diff($end);
                                                    echo $interval->days . ' giorni';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #666; padding: 2rem;">
                                            Nessuna cancellazione completata
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Tutte le Cancellazioni -->
            <div id="all-tab" class="tab-content">
                <div class="card">
                    <h2>📋 Tutte le Cancellazioni</h2>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Cronologia completa di tutte le cancellazioni di abbonamento
                    </p>
                    
                    <div style="overflow-x: auto;">
                        <table class="cancellation-table">
                            <thead>
                                <tr>
                                    <th>Utente</th>
                                    <th>Primo Abbonamento</th>
                                    <th>Cancellazione Richiesta</th>
                                    <th>Stato Attuale</th>
                                    <th>Scadenza/Scaduto</th>
                                    <th>Stato Futuro</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($cancellation_users)): ?>
                                    <?php foreach ($cancellation_users as $user): ?>
                                    <?php 
                                        $is_active = $user['cancellation_requested'] == 1 && strtotime($user['subscription_end']) > time();
                                        $is_expired = strtotime($user['subscription_end']) <= time();
                                        $priority_class = $is_active ? ($user['days_remaining'] <= 3 ? 'priority-high' : 'priority-medium') : 'priority-low';
                                    ?>
                                    <tr class="<?= $priority_class ?>">
                                        <td>
                                            <div class="user-info"><?= htmlspecialchars($user['name']) ?></div>
                                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= $user['subscription_start'] ? date('d/m/Y H:i', strtotime($user['subscription_start'])) : 'N/A' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= $user['cancellation_date'] ? date('d/m/Y H:i', strtotime($user['cancellation_date'])) : 'N/A' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($is_active): ?>
                                                <span class="status-badge status-pending">
                                                    ⚠ Premium (Cancellazione Richiesta)
                                                </span>
                                            <?php elseif ($user['subscription_status'] == 'cancelled'): ?>
                                                <span class="status-badge status-cancelled">
                                                    ✅ FREE (Cancellato)
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-expired">
                                                    ❌ Scaduto
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= date('d/m/Y H:i', strtotime($user['subscription_end'])) ?>
                                                <?php if ($is_active): ?>
                                                    <br><small style="color: #fd7e14;">(<?= $user['days_remaining'] ?> giorni)</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($is_active): ?>
                                                <span class="status-badge status-expired">
                                                    🔄 Diventerà FREE
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-cancelled">
                                                    ✅ FREE
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?php if ($is_active && $user['days_remaining'] <= 3): ?>
                                                    <span style="color: #dc3545; font-weight: 600;">⚠ Scade presto</span>
                                                <?php elseif ($is_active): ?>
                                                    <span style="color: #ffc107;">🕐 In attesa</span>
                                                <?php else: ?>
                                                    <span style="color: #28a745;">✅ Completato</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #666; padding: 2rem;">
                                            Nessuna cancellazione trovata
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Informazioni Aggiuntive -->
            <div class="card">
                <h2>ℹ️ Informazioni sul Sistema di Cancellazione</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div>
                        <h3 style="color: #333; margin-bottom: 1rem;">🔄 Processo di Cancellazione</h3>
                        <ol style="color: #666; line-height: 1.6;">
                            <li>Utente clicca "Elimina Abbonamento" nel profilo</li>
                            <li>Sistema marca <code>cancellation_requested = 1</code></li>
                            <li>Utente viene reindirizzato a PayPal per completare</li>
                            <li>Dopo 1 mese esatto, account diventa automaticamente FREE</li>
                        </ol>
                    </div>
                    
                    <div>
                        <h3 style="color: #333; margin-bottom: 1rem;">📊 Legenda Stati</h3>
                        <ul style="list-style: none; padding: 0; color: #666;">
                            <li style="margin-bottom: 0.5rem;">
                                <span class="status-badge status-pending">⚠ Premium (Cancellazione Richiesta)</span>
                                <br><small>Ancora attivo, scadrà presto</small>
                            </li>
                            <li style="margin-bottom: 0.5rem;">
                                <span class="status-badge status-cancelled">✅ FREE (Cancellato)</span>
                                <br><small>Cancellazione completata</small>
                            </li>
                            <li>
                                <span class="status-badge status-active">✓ Premium Attivo</span>
                                <br><small>Abbonamento normale</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showTab(tabName) {
            // Nascondi tutti i tab content
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Rimuovi active da tutti i tab buttons
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostra il tab selezionato
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Attiva il button corrispondente
            event.target.classList.add('active');
        }
    </script>
</body>
</html>