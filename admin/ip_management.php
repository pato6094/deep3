<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/ip_restrictions.php';

// Verifica se l'admin è loggato
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Gestione azioni admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_blacklist'])) {
        $ip = trim($_POST['ip_address']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // Aggiungi IP alla blacklist (questo richiederebbe modifica del file di config)
            $success = "IP $ip aggiunto alla blacklist (richiede modifica manuale del file di configurazione)";
        } else {
            $error = "Indirizzo IP non valido";
        }
    }
    
    if (isset($_POST['cleanup_logs'])) {
        $days = (int)$_POST['cleanup_days'];
        $deleted = cleanup_old_ip_logs($pdo, $days);
        $success = "Eliminati $deleted record più vecchi di $days giorni";
    }
}

// Ottieni statistiche IP
$ip_stats = get_ip_statistics($pdo, 30);

// Statistiche generali
$general_stats_stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT ip_address) as unique_ips,
        COUNT(CASE WHEN action = 'registration' THEN 1 END) as total_registrations,
        COUNT(CASE WHEN action = 'blocked' THEN 1 END) as total_blocked,
        COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h_attempts
    FROM ip_registration_log
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$general_stats = $general_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Top IP bloccati
$blocked_ips_stmt = $pdo->query("
    SELECT 
        ip_address,
        COUNT(*) as blocked_count,
        MAX(created_at) as last_attempt
    FROM ip_registration_log 
    WHERE action = 'blocked' 
    AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY ip_address
    ORDER BY blocked_count DESC
    LIMIT 10
");
$blocked_ips = $blocked_ips_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione IP - Admin Panel</title>
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

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
        }

        .nav-icon {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: rgba(15, 23, 42, 0.3);
            backdrop-filter: blur(10px);
            min-height: 100vh;
        }

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
            background: linear-gradient(90deg, #10b981, #059669);
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
            min-width: 600px;
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

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(239, 68, 68, 0.4);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: #ffffff;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            background: rgba(255, 255, 255, 0.15);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #4ade80;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .config-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .config-info h3 {
            color: #93c5fd;
            margin-bottom: 1rem;
        }

        .config-info ul {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
        }

        .ip-address {
            font-family: 'Monaco', 'Menlo', monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .status-blocked {
            color: #f87171;
            font-weight: 600;
        }

        .status-allowed {
            color: #4ade80;
            font-weight: 600;
        }

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
                    <a href="cancellations.php" class="nav-link">
                        <span class="nav-icon">⚠️</span>
                        <span>Cancellazioni</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="ip_management.php" class="nav-link active">
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
                <h1 class="page-title">🛡️ Gestione IP e Sicurezza</h1>
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

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Configurazione Attuale -->
            <div class="config-info">
                <h3>⚙️ Configurazione Restrizioni IP Attuale</h3>
                <ul>
                    <li><strong>Stato:</strong> <?= $IP_RESTRICTION_CONFIG['enabled'] ? '✅ Attivo' : '❌ Disattivato' ?></li>
                    <li><strong>Max registrazioni/ora:</strong> <?= $IP_RESTRICTION_CONFIG['max_registrations_per_hour'] ?></li>
                    <li><strong>Max registrazioni/giorno:</strong> <?= $IP_RESTRICTION_CONFIG['max_registrations_per_day'] ?></li>
                    <li><strong>Periodo blocco:</strong> <?= $IP_RESTRICTION_CONFIG['block_period_hours'] ?> ore</li>
                    <li><strong>Log attività:</strong> <?= $IP_RESTRICTION_CONFIG['log_attempts'] ? '✅ Attivo' : '❌ Disattivato' ?></li>
                </ul>
            </div>

            <!-- Statistiche Generali -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">🌐</div>
                    </div>
                    <div class="stat-number"><?= number_format($general_stats['unique_ips'] ?? 0) ?></div>
                    <div class="stat-label">IP Unici (30 giorni)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">✅</div>
                    </div>
                    <div class="stat-number"><?= number_format($general_stats['total_registrations'] ?? 0) ?></div>
                    <div class="stat-label">Registrazioni Completate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">🚫</div>
                    </div>
                    <div class="stat-number"><?= number_format($general_stats['total_blocked'] ?? 0) ?></div>
                    <div class="stat-label">Tentativi Bloccati</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">📅</div>
                    </div>
                    <div class="stat-number"><?= number_format($general_stats['last_24h_attempts'] ?? 0) ?></div>
                    <div class="stat-label">Attività Ultime 24h</div>
                </div>
            </div>

            <!-- Top IP Bloccati -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🚫 Top IP Bloccati (Ultimi 7 giorni)</h2>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Indirizzo IP</th>
                                <th>Tentativi Bloccati</th>
                                <th>Ultimo Tentativo</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($blocked_ips)): ?>
                                <?php foreach ($blocked_ips as $ip): ?>
                                <tr>
                                    <td>
                                        <span class="ip-address"><?= htmlspecialchars($ip['ip_address']) ?></span>
                                    </td>
                                    <td>
                                        <span style="color: #f87171; font-weight: 600;">
                                            <?= number_format($ip['blocked_count']) ?> tentativi
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.7);">
                                            <?= date('d/m/Y H:i', strtotime($ip['last_attempt'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (is_ip_blacklisted($ip['ip_address'])): ?>
                                            <span class="status-blocked">🚫 Blacklisted</span>
                                        <?php elseif (is_ip_whitelisted($ip['ip_address'])): ?>
                                            <span class="status-allowed">✅ Whitelisted</span>
                                        <?php else: ?>
                                            <span style="color: #fbbf24;">⚠️ Monitorato</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 3rem;">
                                        <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                                        <div>Nessun IP bloccato negli ultimi 7 giorni</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Statistiche Dettagliate IP -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📊 Statistiche IP Dettagliate (Ultimi 30 giorni)</h2>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Indirizzo IP</th>
                                <th>Registrazioni</th>
                                <th>Tentativi Bloccati</th>
                                <th>Totale Attività</th>
                                <th>Ultima Attività</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ip_stats)): ?>
                                <?php foreach ($ip_stats as $stat): ?>
                                <tr>
                                    <td>
                                        <span class="ip-address"><?= htmlspecialchars($stat['ip_address']) ?></span>
                                    </td>
                                    <td>
                                        <span style="color: #4ade80; font-weight: 600;">
                                            <?= number_format($stat['registrations']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: #f87171; font-weight: 600;">
                                            <?= number_format($stat['blocked_attempts']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= number_format($stat['total_attempts']) ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.7);">
                                            <?= date('d/m/Y H:i', strtotime($stat['last_activity'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (is_ip_blacklisted($stat['ip_address'])): ?>
                                            <span class="status-blocked">🚫 Blacklisted</span>
                                        <?php elseif (is_ip_whitelisted($stat['ip_address'])): ?>
                                            <span class="status-allowed">✅ Whitelisted</span>
                                        <?php elseif ($stat['blocked_attempts'] > 0): ?>
                                            <span style="color: #fbbf24;">⚠️ Sospetto</span>
                                        <?php else: ?>
                                            <span style="color: #4ade80;">✅ Normale</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 3rem;">
                                        <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                                        <div>Nessuna attività IP registrata</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Azioni Admin -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <!-- Aggiungi IP alla Blacklist -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🚫 Blocca IP</h2>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="ip_address" class="form-label">Indirizzo IP</label>
                            <input type="text" id="ip_address" name="ip_address" class="form-input" 
                                   placeholder="192.168.1.100" required>
                        </div>
                        
                        <button type="submit" name="add_to_blacklist" class="btn btn-danger">
                            🚫 Aggiungi alla Blacklist
                        </button>
                    </form>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(245, 158, 11, 0.1); border-radius: 8px; font-size: 0.875rem; color: #fbbf24;">
                        <strong>⚠️ Nota:</strong> Per modificare permanentemente la blacklist, 
                        è necessario modificare il file <code>config/ip_restrictions.php</code>
                    </div>
                </div>

                <!-- Pulizia Log -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🧹 Pulizia Log</h2>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="cleanup_days" class="form-label">Elimina log più vecchi di (giorni)</label>
                            <input type="number" id="cleanup_days" name="cleanup_days" class="form-input" 
                                   value="30" min="1" max="365" required>
                        </div>
                        
                        <button type="submit" name="cleanup_logs" class="btn btn-primary"
                                onclick="return confirm('Sei sicuro di voler eliminare i log più vecchi?')">
                            🧹 Pulisci Log
                        </button>
                    </form>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px; font-size: 0.875rem; color: #93c5fd;">
                        <strong>💡 Suggerimento:</strong> Pulisci regolarmente i log per mantenere 
                        le performance del database ottimali.
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