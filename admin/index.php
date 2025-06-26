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

$success = '';
$error = '';

// Gestione upgrade utente a PRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_status = 'active',
                subscription_start = NOW(),
                subscription_end = DATE_ADD(NOW(), INTERVAL 1 YEAR),
                cancellation_requested = 0
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([':user_id' => $user_id])) {
            $success = "Utente aggiornato a Premium con successo!";
        } else {
            $error = "Errore durante l'aggiornamento dell'utente.";
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Gestione downgrade utente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['downgrade_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_status = 'free',
                subscription_start = NULL,
                subscription_end = NULL,
                subscription_id = NULL,
                cancellation_requested = 0
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([':user_id' => $user_id])) {
            $success = "Utente riportato al piano gratuito con successo!";
        } else {
            $error = "Errore durante il downgrade dell'utente.";
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Statistiche generali
$stats = get_admin_stats($pdo);

// Lista utenti con paginazione
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE :search OR u.email LIKE :search OR u.registration_ip LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter !== 'all') {
    $where_conditions[] = "u.subscription_status = :filter";
    $params[':filter'] = $filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Conta totale utenti
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u $where_clause");
$count_stmt->execute($params);
$total_users = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = max(1, ceil($total_users / $per_page));

// Recupera utenti CON IP di registrazione
$query = "
    SELECT u.*, 
           COUNT(d.id) AS total_deeplinks,
           COALESCE(SUM(d.clicks), 0) AS total_clicks
    FROM users u
    LEFT JOIN deeplinks d ON u.id = d.user_id
    $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT :per_page OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

try {
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Errore query utenti: " . $e->getMessage());
    $users = [];
    $error = "Errore nel caricamento degli utenti.";
}

// Statistiche cancellazioni per il widget
$cancellation_stats_stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN cancellation_requested = 1 AND subscription_end > NOW() THEN 1 END) as pending_cancellations,
        COUNT(CASE WHEN cancellation_requested = 1 AND subscription_end <= NOW() THEN 1 END) as completed_cancellations
    FROM users 
    WHERE subscription_id IS NOT NULL
");
$cancellation_stats = $cancellation_stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DeepLink Pro</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
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
            background: linear-gradient(90deg, #667eea, #764ba2);
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

        .stat-change {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
        }

        .stat-change.positive {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .stat-change.negative {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
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

        /* FILTERS */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: #ffffff;
            font-size: 0.875rem;
            min-width: 200px;
            transition: all 0.3s ease;
        }

        .filter-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
            background: rgba(255, 255, 255, 0.15);
        }

        .filter-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: #ffffff;
            font-size: 0.875rem;
            min-width: 150px;
        }

        .filter-select option {
            background: #1e293b;
            color: #ffffff;
        }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
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
            min-width: 1000px;
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

        .status-free {
            background: rgba(107, 114, 128, 0.2);
            color: #9ca3af;
        }

        .status-active {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .status-expired {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .status-cancellation-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        /* ALERTS */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.3);
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .pagination .current {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            border-color: transparent;
        }

        /* USER ACTIONS */
        .user-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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

        /* IP ADDRESS STYLING */
        .ip-address {
            font-family: 'Monaco', 'Menlo', monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #93c5fd;
            border: 1px solid rgba(147, 197, 253, 0.2);
        }

        .ip-unknown {
            color: rgba(255, 255, 255, 0.4);
            font-style: italic;
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

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-input,
            .filter-select {
                min-width: auto;
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 800px;
            }

            .user-actions {
                flex-direction: column;
                gap: 0.25rem;
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

        /* QUICK ACTION CARD */
        .quick-action-card {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .quick-action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .quick-action-card:hover::before {
            left: 100%;
        }

        .quick-action-card h3 {
            color: #ffffff;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .quick-action-card p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1rem;
        }

        .quick-action-card a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .quick-action-card a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
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
                    <a href="index.php" class="nav-link active">
                        <span class="nav-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="cancellations.php" class="nav-link">
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
                    <a href="ip_management.php" class="nav-link">
                        <span class="nav-icon">🛡️</span>
                        <span>Gestione IP</span>
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
                <h1 class="page-title">Admin Dashboard</h1>
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

            <!-- Quick Action: Cancellazioni -->
            <?php if ($cancellation_stats['pending_cancellations'] > 0): ?>
            <div class="quick-action-card">
                <h3>⚠️ Attenzione: <?= $cancellation_stats['pending_cancellations'] ?> Cancellazioni in Attesa</h3>
                <p>Ci sono utenti che hanno richiesto la cancellazione dell'abbonamento</p>
                <a href="cancellations.php">Visualizza Cancellazioni →</a>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">👥</div>
                        <div class="stat-change positive">+12%</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                    <div class="stat-label">Utenti Totali</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">💎</div>
                        <div class="stat-change positive">+8%</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['premium_users']) ?></div>
                    <div class="stat-label">Utenti Premium</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">🔗</div>
                        <div class="stat-change positive">+24%</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_deeplinks']) ?></div>
                    <div class="stat-label">Deeplink Totali</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">📈</div>
                        <div class="stat-change positive">+18%</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_clicks']) ?></div>
                    <div class="stat-label">Click Totali</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">⚠️</div>
                        <?php if ($cancellation_stats['pending_cancellations'] > 0): ?>
                            <div class="stat-change negative">Attenzione</div>
                        <?php else: ?>
                            <div class="stat-change positive">OK</div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-number"><?= $cancellation_stats['pending_cancellations'] ?></div>
                    <div class="stat-label">Cancellazioni in Attesa</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">🆕</div>
                        <div class="stat-change positive">Oggi</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['new_users_today']) ?></div>
                    <div class="stat-label">Nuovi Utenti</div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- User Management -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">👥 Gestione Utenti</h2>
                    <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">
                        Totale: <?= number_format($total_users) ?> utenti
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="filters">
                    <input type="text" name="search" placeholder="Cerca per nome, email o IP..." 
                           value="<?= htmlspecialchars($search) ?>" class="filter-input">
                    
                    <select name="filter" class="filter-select">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tutti gli utenti</option>
                        <option value="free" <?= $filter === 'free' ? 'selected' : '' ?>>Solo Gratuiti</option>
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Solo Premium</option>
                        <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Scaduti</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">Filtra</button>
                    <a href="index.php" class="btn btn-secondary">Reset</a>
                </form>

                <!-- Users Table -->
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Utente</th>
                                <th>Piano</th>
                                <th>IP Registrazione</th>
                                <th>Deeplinks</th>
                                <th>Click</th>
                                <th>Registrato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
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
                                        <?php if ($user['cancellation_requested'] == 1 && $user['subscription_status'] == 'active'): ?>
                                            <span class="status-badge status-cancellation-pending">
                                                ⚠ Premium (Cancellazione Richiesta)
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?= $user['subscription_status'] ?>">
                                                <?php
                                                switch($user['subscription_status']) {
                                                    case 'active': echo '💎 Premium'; break;
                                                    case 'expired': echo '⏰ Scaduto'; break;
                                                    case 'cancelled': echo '❌ Cancellato'; break;
                                                    default: echo '🆓 Gratuito';
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($user['subscription_end']): ?>
                                            <div style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.5); margin-top: 0.25rem;">
                                                Scade: <?= date('d/m/Y', strtotime($user['subscription_end'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['registration_ip'])): ?>
                                            <span class="ip-address" title="IP di registrazione">
                                                <?= htmlspecialchars($user['registration_ip']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="ip-address ip-unknown" title="IP non disponibile">
                                                Non disponibile
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #ffffff;">
                                            <?= number_format($user['total_deeplinks']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #4ade80;">
                                            <?= number_format($user['total_clicks']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.7);">
                                            <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-actions">
                                            <?php if ($user['subscription_status'] !== 'active'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="upgrade_user" 
                                                            class="btn btn-success btn-sm"
                                                            onclick="return confirm('Vuoi davvero aggiornare questo utente a Premium?')">
                                                        💎 Upgrade PRO
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="downgrade_user" 
                                                            class="btn btn-warning btn-sm"
                                                            onclick="return confirm('Vuoi davvero riportare questo utente al piano gratuito?')">
                                                        ⬇️ Downgrade
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 3rem;">
                                        <div style="font-size: 3rem; margin-bottom: 1rem;">👤</div>
                                        <div>Nessun utente trovato</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>">« Precedente</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>">Successiva »</a>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; color: rgba(255, 255, 255, 0.6); margin-top: 1rem; font-size: 0.875rem;">
                    Pagina <?= $page ?> di <?= $total_pages ?> (<?= number_format($total_users) ?> utenti totali)
                </div>
                <?php endif; ?>
            </div>

            <!-- Detailed Statistics -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📊 Statistiche Dettagliate</h2>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem; font-size: 1.125rem;">📈 Crescita Utenti</h3>
                        <div style="space-y: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <span style="color: rgba(255, 255, 255, 0.8);">Oggi:</span>
                                <span style="color: #4ade80; font-weight: 600;"><?= number_format($stats['new_users_today']) ?> nuovi utenti</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <span style="color: rgba(255, 255, 255, 0.8);">Questa settimana:</span>
                                <span style="color: #4ade80; font-weight: 600;"><?= number_format($stats['new_users_week']) ?> nuovi utenti</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0;">
                                <span style="color: rgba(255, 255, 255, 0.8);">Questo mese:</span>
                                <span style="color: #4ade80; font-weight: 600;"><?= number_format($stats['new_users_month']) ?> nuovi utenti</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem; font-size: 1.125rem;">⚡ Performance</h3>
                        <div style="space-y: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <span style="color: rgba(255, 255, 255, 0.8);">Click oggi:</span>
                                <span style="color: #667eea; font-weight: 600;"><?= number_format($stats['clicks_today']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <span style="color: rgba(255, 255, 255, 0.8);">Deeplink oggi:</span>
                                <span style="color: #667eea; font-weight: 600;"><?= number_format($stats['deeplinks_today']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0;">
                                <span style="color: rgba(255, 255, 255, 0.8);">Media click/deeplink:</span>
                                <span style="color: #667eea; font-weight: 600;"><?= number_format($stats['avg_clicks_per_deeplink'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 style="color: #ffffff; margin-bottom: 1rem; font-size: 1.125rem;">⚠️ Cancellazioni</h3>
                        <div style="space-y: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <span style="color: rgba(255, 255, 255, 0.8);">In attesa:</span>
                                <span style="color: #fbbf24; font-weight: 600;"><?= number_format($cancellation_stats['pending_cancellations']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <span style="color: rgba(255, 255, 255, 0.8);">Completate:</span>
                                <span style="color: #f87171; font-weight: 600;"><?= number_format($cancellation_stats['completed_cancellations']) ?></span>
                            </div>
                            <div style="padding: 0.75rem 0;">
                                <a href="cancellations.php" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                    Visualizza dettagli →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
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