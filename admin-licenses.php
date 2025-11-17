<?php
/**
 * FURY OF SPARTA - ADMIN DASHBOARD
 * Modern Admin Interface for License Management
 */

session_start();

// Configuration
define('ADMIN_PASSWORD', 'spartanadmin2025'); // CHANGE THIS PASSWORD!

// Use persistent volume directory if available (Railway), otherwise use local directory
$data_dir = is_dir('/data') && is_writable('/data') ? '/data' : __DIR__;
define('LICENSE_DB_FILE', $data_dir . '/licenses.json');
define('LOG_FILE', $data_dir . '/license_log.txt');

// Authentication Check
if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>⚔️ Fury of Sparta Dashboard - Login</title>
            <link rel="stylesheet" href="loginstyle.css">
        </head>
        <body>
            <div class="particles">
                <div class="particle" style="left: 10%; width: 3px; height: 3px; animation-delay: 0s;"></div>
                <div class="particle" style="left: 20%; width: 2px; height: 2px; animation-delay: 1s;"></div>
                <div class="particle" style="left: 30%; width: 4px; height: 4px; animation-delay: 2s;"></div>
                <div class="particle" style="left: 40%; width: 2px; height: 2px; animation-delay: 3s;"></div>
                <div class="particle" style="left: 50%; width: 3px; height: 3px; animation-delay: 4s;"></div>
                <div class="particle" style="left: 60%; width: 2px; height: 2px; animation-delay: 5s;"></div>
                <div class="particle" style="left: 70%; width: 4px; height: 4px; animation-delay: 0.5s;"></div>
                <div class="particle" style="left: 80%; width: 2px; height: 2px; animation-delay: 1.5s;"></div>
                <div class="particle" style="left: 90%; width: 3px; height: 3px; animation-delay: 2.5s;"></div>
            </div>
            
            <div class="login-container">
                <div class="logo">⚔️</div>
                <h1 class="title">Fury of Sparta</h1>
                <p class="subtitle">Admin Dashboard</p>
                
                <form method="post">
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Enter Admin Password" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="login-btn">🔐 Access Dashboard</button>
                </form>
                
                <div class="security-notice">
                    🛡️ Secure admin access - All activities are logged
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// Logout Handler
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Utility Functions
function loadLicenses() {
    if (!file_exists(LICENSE_DB_FILE)) return [];
    $data = file_get_contents(LICENSE_DB_FILE);
    return json_decode($data, true) ?: [];
}

function saveLicenses($licenses) {
    return file_put_contents(LICENSE_DB_FILE, json_encode($licenses, JSON_PRETTY_PRINT));
}

function generateLicenseKey() {
    return 'FOS-' . strtoupper(bin2hex(random_bytes(6))) . '-' . date('Y');
}

function logActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] ADMIN: $message\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

// AJAX Handler for real-time operations
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $licenses = loadLicenses();
    
    switch ($_POST['ajax_action']) {
        case 'toggle_status':
            $key = $_POST['license_key'];
            if (isset($licenses[$key])) {
                $current_status = $licenses[$key]['status'];
                $licenses[$key]['status'] = ($current_status === 'active') ? 'suspended' : 'active';
                saveLicenses($licenses);
                logActivity("License status changed: $key from $current_status to {$licenses[$key]['status']}");
                echo json_encode(['success' => true, 'new_status' => $licenses[$key]['status']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'License not found']);
            }
            exit();
            
        case 'extend_license':
            $key = $_POST['license_key'];
            $months = intval($_POST['months']);
            if (isset($licenses[$key]) && $months > 0) {
                $current_expires = $licenses[$key]['expires'];
                $new_expires = date('Y-m-d', strtotime($current_expires . " +$months months"));
                $licenses[$key]['expires'] = $new_expires;
                saveLicenses($licenses);
                logActivity("License extended: $key by $months months (new expiry: $new_expires)");
                echo json_encode(['success' => true, 'new_expiry' => $new_expires]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
            }
            exit();
    }
}

// POST Actions Handler - CORRECTION MAJEURE ICI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $licenses = loadLicenses(); // Charger les licences UNE SEULE FOIS
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_license':
                $new_key = generateLicenseKey();
                
                $licenses[$new_key] = [
                    'status' => 'active',
                    'expires' => $_POST['expires'],
                    'max_machines' => (int)$_POST['max_machines'],
                    'machines' => [],
                    'created' => date('Y-m-d H:i:s'),
                    'client_info' => trim($_POST['client_info'] ?? '')
                ];
                
                if (saveLicenses($licenses)) {
                    logActivity("New license created: $new_key for client: {$_POST['client_info']}");
                    $_SESSION['success_message'] = "✅ New license created: <strong>$new_key</strong>";
                } else {
                    $_SESSION['error_message'] = "❌ Failed to create license";
                }
                break;

            case 'update_license':
                $key = $_POST['license_key'];
                
                if (isset($licenses[$key])) {
                    // MODIFICATION DIRECTE - NE PAS CRÉER DE NOUVELLE LICENCE
                    $licenses[$key]['status'] = $_POST['status'];
                    $licenses[$key]['expires'] = $_POST['expires'];
                    $licenses[$key]['max_machines'] = (int)$_POST['max_machines'];
                    $licenses[$key]['client_info'] = trim($_POST['client_info'] ?? '');
                    
                    if (saveLicenses($licenses)) {
                        logActivity("License updated: $key");
                        $_SESSION['success_message'] = "✅ License updated: <strong>$key</strong>";
                    } else {
                        $_SESSION['error_message'] = "❌ Failed to update license";
                    }
                } else {
                    $_SESSION['error_message'] = "❌ License not found";
                }
                break;

            case 'delete_license':
                $key = $_POST['license_key'];
                
                if (isset($licenses[$key])) {
                    $client_info = $licenses[$key]['client_info'] ?? '';
                    unset($licenses[$key]); // SUPPRESSION DÉFINITIVE
                    
                    if (saveLicenses($licenses)) {
                        logActivity("License deleted: $key (Client: $client_info)");
                        $_SESSION['success_message'] = "🗑️ License permanently deleted: <strong>$key</strong>";
                    } else {
                        $_SESSION['error_message'] = "❌ Failed to delete license";
                    }
                } else {
                    $_SESSION['error_message'] = "❌ License not found";
                }
                break;
        }
    }
    
    // Redirection POST-Redirect-GET
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . ($_GET['tab'] ?? 'licenses'));
    exit();
}

// Retrieve messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Load data for display
$licenses = loadLicenses();

// Calculate stats with proper status detection
$stats = [
    'total' => count($licenses),
    'active' => 0,
    'expired' => 0,
    'suspended' => 0,
    'total_machines' => 0
];

foreach ($licenses as $license) {
    $stats['total_machines'] += count($license['machines']);
    
    // Determine actual status
    if ($license['status'] === 'suspended') {
        $stats['suspended']++;
    } elseif (strtotime($license['expires']) < time()) {
        $stats['expired']++;
    } elseif ($license['status'] === 'active') {
        $stats['active']++;
    }
}

$active_tab = $_GET['tab'] ?? 'licenses';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚔️ Fury of Sparta Dashboard</title>
    <link rel="stylesheet" href="bodystyle.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="?logout=1" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
                🚪 Logout
            </a>
            <h1>⚔️ Fury of Sparta</h1>
            <p>License Management Dashboard</p>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?= $success_message ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <?= $error_message ?>
        </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Licenses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active'] ?></div>
                <div class="stat-label">Active Licenses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['expired'] ?></div>
                <div class="stat-label">Expired Licenses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['suspended'] ?></div>
                <div class="stat-label">Suspended Licenses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_machines'] ?></div>
                <div class="stat-label">Active Machines</div>
            </div>
        </div>
        
        <!-- Tabs Container -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab <?= $active_tab === 'licenses' ? 'active' : '' ?>" onclick="showTab('licenses')">
                    📋 License Management
                </button>
                <button class="tab <?= $active_tab === 'add' ? 'active' : '' ?>" onclick="showTab('add')">
                    ➕ Create License
                </button>
                <button class="tab <?= $active_tab === 'logs' ? 'active' : '' ?>" onclick="showTab('logs')">
                    📊 Activity Logs
                </button>
            </div>
            
            <!-- Licenses Tab -->
            <div id="licenses" class="tab-content <?= $active_tab === 'licenses' ? 'active' : '' ?>">
                <div class="table-container">
                    <table class="license-table">
                        <thead>
                            <tr>
                                <th>License Key</th>
                                <th>Status</th>
                                <th>Expiry Date</th>
                                <th>Machines</th>
                                <th>Client Info</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($licenses)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
                                    🔍 No licenses found. Create your first license using the "Create License" tab.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($licenses as $key => $license): 
                                // Determine actual status for display
                                $is_expired = strtotime($license['expires']) < time();
                                $actual_status = $license['status'];
                                if ($is_expired && $actual_status === 'active') {
                                    $actual_status = 'expired';
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: #D4AF37;"><?= htmlspecialchars($key) ?></strong>
                                    <div style="font-size: 0.8rem; color: rgba(255,255,255,0.6); margin-top: 4px;">
                                        Created: <?= $license['created'] ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $actual_status ?>">
                                        <?= strtoupper($actual_status) ?>
                                    </span>
                                    <?php if ($is_expired && $license['status'] === 'active'): ?>
                                    <div style="font-size: 0.8rem; color: #dc3545; margin-top: 4px;">
                                        ⚠️ Should be expired
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $license['expires'] ?>
                                    <div style="font-size: 0.8rem; margin-top: 4px;">
                                        <?php 
                                        $days_left = ceil((strtotime($license['expires']) - time()) / (60 * 60 * 24));
                                        if ($days_left < 0) {
                                            echo '<span style="color: #dc3545;">Expired ' . abs($days_left) . ' days ago</span>';
                                        } elseif ($days_left <= 30) {
                                            echo '<span style="color: #ffc107;">⏰ ' . $days_left . ' days left</span>';
                                        } else {
                                            echo '<span style="color: #28a745;">✅ ' . $days_left . ' days left</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= count($license['machines']) ?>/<?= $license['max_machines'] ?></strong>
                                    <?php if (!empty($license['machines'])): ?>
                                    <div class="machine-info">
                                        <?php foreach(array_slice($license['machines'], 0, 2) as $machine): ?>
                                        <div class="machine-item">
                                            🖥️ <?= substr($machine['fingerprint'], 0, 8) ?>...
                                            <div style="font-size: 0.8rem;">
                                                Account: <?= $machine['account_number'] ?>
                                                <br>Last: <?= date('M j, H:i', strtotime($machine['last_seen'])) ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (count($license['machines']) > 2): ?>
                                        <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">
                                            +<?= count($license['machines']) - 2 ?> more machines
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($license['client_info'] ?: 'No info provided') ?>
                                </td>
                                <td>
                                    <div class="quick-actions">
                                        <button type="button" class="btn btn-primary" 
                                                onclick="editLicense('<?= htmlspecialchars($key) ?>')"
                                                data-tooltip="Edit license details">
                                            ✏️ Edit
                                        </button>
                                        
                                        <button type="button" class="btn <?= $license['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>"
                                                onclick="toggleStatus('<?= htmlspecialchars($key) ?>')"
                                                data-tooltip="<?= $license['status'] === 'active' ? 'Suspend' : 'Activate' ?> license">
                                            <?= $license['status'] === 'active' ? '⏸ Suspend' : '▶️ Activate' ?>
                                        </button>
                                        
                                        <button type="button" class="btn btn-info"
                                                onclick="extendLicense('<?= htmlspecialchars($key) ?>')"
                                                data-tooltip="Extend expiry date">
                                            📅 Extend
                                        </button>
                                        
                                        <button type="button" class="btn btn-danger"
                                                onclick="confirmDeleteLicense('<?= htmlspecialchars($key) ?>')"
                                                data-tooltip="Permanently delete license">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Add License Tab -->
            <div id="add" class="tab-content <?= $active_tab === 'add' ? 'active' : '' ?>">
                <h2 style="color: #D4AF37; margin-bottom: 25px;">➕ Create New License</h2>
                <form method="post" onsubmit="return validateAddForm(this)">
                    <input type="hidden" name="action" value="add_license">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>📅 Expiry Date</label>
                            <input type="date" name="expires" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>🖥️ Maximum Machines</label>
                            <select name="max_machines" required>
                                <option value="1">1 Machine (Single License)</option>
                                <option value="2">2 Machines (Dual License)</option>
                                <option value="3">3 Machines (Triple License)</option>
                                <option value="5">5 Machines (Multi License)</option>
                                <option value="10">10 Machines (Business)</option>
                                <option value="25">25 Machines (Enterprise)</option>
                                <option value="100">100 Machines (Corporate)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>👤 Client Information</label>
                        <textarea name="client_info" rows="3" placeholder="Client name, email, company, notes..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="font-size: 1.2rem; padding: 18px 36px;">
                        🔑 Generate License Key
                    </button>
                </form>
            </div>
            
            <!-- Logs Tab -->
            <div id="logs" class="tab-content <?= $active_tab === 'logs' ? 'active' : '' ?>">
                <h2 style="color: #D4AF37; margin-bottom: 25px;">📊 System Activity Logs</h2>
                <div class="logs-container">
                    <pre><?php 
                    if (file_exists(LOG_FILE)) {
                        $logs = file_get_contents(LOG_FILE);
                        $lines = explode("\n", $logs);
                        $recent_lines = array_slice($lines, -200);
                        echo htmlspecialchars(implode("\n", array_reverse($recent_lines)));
                    } else {
                        echo "📝 No activity logs available yet.\nLogs will appear here when licenses are created or accessed.";
                    }
                    ?></pre>
                </div>
                <div style="margin-top: 20px;">
                    <button type="button" class="btn btn-info" onclick="location.reload()">
                        🔄 Refresh Logs
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit License Modal -->
    <div id="editModal" class="modal" onclick="closeModalOnOutsideClick(event, 'editModal')">
        <div class="modal-content" onclick="event.stopPropagation()">
            <h3>✏️ Edit License</h3>
            <form method="post" id="editForm" onsubmit="return validateEditForm(this)">
                <input type="hidden" name="action" value="update_license">
                <input type="hidden" name="license_key" id="edit_license_key">
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">✅ Active</option>
                        <option value="suspended">⏸ Suspended</option>
                        <option value="expired">❌ Expired</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expires" id="edit_expires">
                </div>
                
                <div class="form-group">
                    <label>Maximum Machines</label>
                    <input type="number" name="max_machines" id="edit_max_machines" min="1" max="1000">
                </div>
                
                <div class="form-group">
                    <label>Client Information</label>
                    <textarea name="client_info" id="edit_client_info" rows="3"></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('editModal')">❌ Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Extend License Modal -->
    <div id="extendModal" class="modal" onclick="closeModalOnOutsideClick(event, 'extendModal')">
        <div class="modal-content" onclick="event.stopPropagation()">
            <h3>📅 Extend License</h3>
            <div id="extend_license_info" style="margin-bottom: 20px; padding: 15px; background: rgba(212,175,55,0.1); border-radius: 8px;"></div>
            <div class="form-group">
                <label>Extend by:</label>
                <select id="extend_months">
                    <option value="1">1 Month</option>
                    <option value="3">3 Months</option>
                    <option value="6">6 Months</option>
                    <option value="12">1 Year</option>
                    <option value="24">2 Years</option>
                </select>
            </div>
            <div style="text-align: center; margin-top: 30px;">
                <button type="button" class="btn btn-success" onclick="confirmExtend()">📅 Extend License</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('extendModal')">❌ Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Hidden delete form -->
    <form id="deleteForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="delete_license">
        <input type="hidden" name="license_key" id="delete_license_key">
    </form>
    
    <!-- Pass licenses data to JavaScript -->
    <script>
        window.licensesData = <?= json_encode($licenses) ?>;
    </script>
    <script src="dashboard.js"></script>
</body>
</html>