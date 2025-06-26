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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #ffffff;
            overflow-x: hidden;
        }

        /* LAYOUT PRINCIPALE */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.5rem;
        }

        .sidebar-nav {
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
        }

        .nav-icon {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: rgba(15, 23, 42, 0.3);
            backdrop-filter: blur(10px);
            min-height: 100vh;
        }

        /* HEADER */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #ffffff;
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        /* CARDS */
        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        /* TABS */
        .nav-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 2rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px 12px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-tab {
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
            position: relative;
            backdrop-filter: blur(10px);
        }

        .nav-tab:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            transform: translateY(-2px);
        }

        .nav-tab.active {
            background: rgba(255, 255, 255, 0.15);
            color: #f59e0b;
            border-bottom-color: #f59e0b;
            box-shadow: 0 -2px 10px rgba(245, 158, 11, 0.2);
        }

        .nav-tab.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        /* TAB CONTENT */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* TABLE */
        .table-container {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .table th {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
            vertical-align: middle;
        }

        .table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        /* STATUS BADGES */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .status-expired {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .status-cancelled {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .status-active {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
            color: #ffffff;
        }

        .user-details h4 {
            font-weight: 600;
            color: #ffffff;
            margin: 0 0 0.25rem 0;
            font-size: 0.875rem;
        }

        .user-details p {
            color: rgba(255, 255, 255, 0.6);
            margin: 0;
            font-size: 0.75rem;
        }

        .date-info {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .days-remaining {
            font-weight: 600;
            color: #f59e0b;
        }

        .days-remaining.urgent {
            color: #f87171;
        }

        .priority-high {
            border-left: 4px solid #f87171;
        }

        .priority-medium {
            border-left: 4px solid #fbbf24;
        }

        .priority-low {
            border-left: 4px solid #4ade80;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .nav-tabs {
                flex-direction: column;
                gap: 0;
            }

            .nav-tab {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 0;
            }

            .nav-tab.active {
                border-bottom-color: #f59e0b;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .content-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* MOBILE MENU BUTTON */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.5rem;
            color: #ffffff;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>

    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <span>🛡️</span>
                    <div>
                        <div>DeepLink Pro</div>
                        <div class="sidebar-subtitle">Admin Dashboard</div>
                    </div>
                </a>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="index.php" class="nav-link">
                        <span class="nav-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="cancellations.php" class="nav-link active">
                        <span class="nav-icon">⚠️</span>
                        <span>Cancellazioni</span>
                        <?php if ($cancellation_stats['pending_cancellations'] > 0): ?>
                            <span style="background: #f59e0b; color: #000; padding: 0.125rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700; margin-left: auto;">
                                <?= $cancellation_stats['pending_cancellations'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../dashboard.php" class="nav-link">
                        <span class="nav-icon">👤</span>
                        <span>Dashboard Utente</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <span class="nav-icon">🚪</span>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <h1 class="page-title">⚠️ Gestione Cancellazioni</h1>
                <div class="admin-info">
                    <div class="admin-avatar">
                        <?= strtoupper(substr($_SESSION['admin_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
                        <div style="font-size: 0.75rem; opacity: 0.7;">Administrator</div>
                    </div>
                </div>
            </div>

            <!-- Statistiche Cancellazioni -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">🕐</div>
                    </div>
                    <div class="stat-number"><?= $cancellation_stats['pending_cancellations'] ?></div>
                    <div class="stat-label">Cancellazioni in Attesa</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">✅</div>
                    </div>
                    <div class="stat-number"><?= $cancellation_stats['completed_cancellations'] ?></div>
                    <div class="stat-label">Cancellazioni Completate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">📊</div>
                    </div>
                    <div class="stat-number"><?= $cancellation_stats['total_cancelled'] ?></div>
                    <div class="stat-label">Totale Cancellati</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">📅</div>
                    </div>
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
                    <div class="card-header">
                        <h2 class="card-title">🕐 Cancellazioni in Attesa di Scadenza</h2>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">
                            Utenti che hanno richiesto la cancellazione ma hanno ancora accesso Premium
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="table">
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
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                                                    <p><?= htmlspecialchars($user['email']) ?></p>
                                                </div>
                                            </div>
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
                                        <td colspan="7" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 3rem;">
                                            <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                                            <div>Nessuna cancellazione in attesa</div>
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
                    <div class="card-header">
                        <h2 class="card-title">✅ Cancellazioni Completate</h2>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">
                            Utenti che hanno completato il processo di cancellazione e sono tornati al piano FREE
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="table">
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
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                                                    <p><?= htmlspecialchars($user['email']) ?></p>
                                                </div>
                                            </div>
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
                                        <td colspan="6" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 3rem;">
                                            <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                                            <div>Nessuna cancellazione completata</div>
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
                    <div class="card-header">
                        <h2 class="card-title">📋 Tutte le Cancellazioni</h2>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">
                            Cronologia completa di tutte le cancellazioni di abbonamento
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="table">
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
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                                                    <p><?= htmlspecialchars($user['email']) ?></p>
                                                </div>
                                            </div>
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
                                                    <br><small style="color: #f59e0b;">(<?= $user['days_remaining'] ?> giorni)</small>
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
                                                    <span style="color: #f87171; font-weight: 600;">⚠ Scade presto</span>
                                                <?php elseif ($is_active): ?>
                                                    <span style="color: #fbbf24;">🕐 In attesa</span>
                                                <?php else: ?>
                                                    <span style="color: #4ade80;">✅ Completato</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 3rem;">
                                            <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                                            <div>Nessuna cancellazione trovata</div>
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
                <div class="card-header">
                    <h2 class="card-title">ℹ️ Informazioni sul Sistema di Cancellazione</h2>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem; font-size: 1.125rem;">🔄 Processo di Cancellazione</h3>
                        <ol style="color: rgba(255, 255, 255, 0.8); line-height: 1.6; padding-left: 1.5rem;">
                            <li>Utente clicca "Elimina Abbonamento" nel profilo</li>
                            <li>Sistema marca <code style="background: rgba(255, 255, 255, 0.1); padding: 0.25rem 0.5rem; border-radius: 4px;">cancellation_requested = 1</code></li>
                            <li>Utente viene reindirizzato a PayPal per completare</li>
                            <li>Dopo 1 mese esatto, account diventa automaticamente FREE</li>
                        </ol>
                    </div>
                    
                    <div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem; font-size: 1.125rem;">📊 Legenda Stati</h3>
                        <div style="space-y: 1rem;">
                            <div style="margin-bottom: 1rem;">
                                <span class="status-badge status-pending">⚠ Premium (Cancellazione Richiesta)</span>
                                <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.6); margin-top: 0.25rem;">Ancora attivo, scadrà presto</div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <span class="status-badge status-cancelled">✅ FREE (Cancellato)</span>
                                <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.6); margin-top: 0.25rem;">Cancellazione completata</div>
                            </div>
                            <div>
                                <span class="status-badge status-active">✓ Premium Attivo</span>
                                <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.6); margin-top: 0.25rem;">Abbonamento normale</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

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

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 1024 && 
                !sidebar.contains(event.target) && 
                !menuBtn.contains(event.target) && 
                sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('open');
            }
        });
    </script>
</body>
</html>