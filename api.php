<?php
/**
 * FURY OF SPARTA - API ENDPOINT v2.0
 * License verification with AUTO-REGISTRATION
 *
 * Features:
 * - Auto-registers valid license formats
 * - Machine fingerprint tracking
 * - Expiry date validation
 * - Multi-machine support
 */

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('LICENSE_DB_FILE', __DIR__ . '/licenses.json');
define('LOG_FILE', __DIR__ . '/license_log.txt');

// ========== UTILITY FUNCTIONS ==========

function loadLicenses() {
    if (!file_exists(LICENSE_DB_FILE)) {
        // Create empty license file
        file_put_contents(LICENSE_DB_FILE, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    $data = file_get_contents(LICENSE_DB_FILE);
    return json_decode($data, true) ?: [];
}

function saveLicenses($licenses) {
    return file_put_contents(LICENSE_DB_FILE, json_encode($licenses, JSON_PRETTY_PRINT));
}

function logActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    @file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

function sendResponse($success, $message, $data = [], $httpCode = null) {
    if ($httpCode) {
        http_response_code($httpCode);
    }

    $response = [
        'status' => $success ? 'success' : 'error',
        'message' => $message,
        'timestamp' => time()
    ];

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    logActivity(($success ? "✅ SUCCESS" : "❌ FAILED") . ": $message");
    exit();
}

// ========== LICENSE VALIDATION ==========

/**
 * Validates license key format
 * Valid formats:
 * - FOS-XXXXXXXXXXXX-2025
 * - FOS-XXXXXXXXXXXX-2026
 * Minimum 15 characters, must start with FOS-
 */
function isValidLicenseFormat($key) {
    // Basic length check
    if (strlen($key) < 15) {
        return false;
    }

    // Must start with FOS-
    if (strpos($key, 'FOS-') !== 0) {
        return false;
    }

    // Must end with valid year (2025-2029)
    $lastFour = substr($key, -4);
    if (!in_array($lastFour, ['2025', '2026', '2027', '2028', '2029'])) {
        return false;
    }

    // Check format: FOS-{12+ alphanumeric}-{year}
    if (!preg_match('/^FOS-[A-F0-9]{12,}-202[5-9]$/', $key)) {
        return false;
    }

    return true;
}

/**
 * Auto-registers a new valid license key
 */
function autoRegisterLicense($licenseKey) {
    $licenses = loadLicenses();

    // Skip if already exists
    if (isset($licenses[$licenseKey])) {
        return false;
    }

    // Validate format first
    if (!isValidLicenseFormat($licenseKey)) {
        return false;
    }

    // Extract year from license
    $year = substr($licenseKey, -4);
    $expiryDate = ($year + 1) . '-12-31'; // Expires end of next year

    // Create new license entry
    $licenses[$licenseKey] = [
        'status' => 'active',
        'expires' => $expiryDate,
        'max_machines' => 3,
        'client_info' => 'Auto-registered',
        'created_at' => date('Y-m-d H:i:s'),
        'machines' => []
    ];

    // Save to database
    if (saveLicenses($licenses)) {
        logActivity("🆕 AUTO-REGISTERED: New license added - $licenseKey (Expires: $expiryDate)");
        return true;
    }

    return false;
}

// ========== MAIN REQUEST HANDLER ==========

// Extract parameters (support both GET and POST)
$action = $_REQUEST['action'] ?? 'check_license';
$licenseKey = trim($_REQUEST['license_key'] ?? '');
$machineFingerprint = trim($_REQUEST['machine_fingerprint'] ?? '');
$accountNumber = trim($_REQUEST['account_number'] ?? '');
$accountServer = trim($_REQUEST['account_server'] ?? '');
$eaVersion = trim($_REQUEST['ea_version'] ?? '');
$symbol = trim($_REQUEST['symbol'] ?? '');

// Log incoming request
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
logActivity("📥 REQUEST from $clientIP | License: " . substr($licenseKey, 0, 8) . "*** | Machine: " . substr($machineFingerprint, 0, 8) . "***");

// ========== VALIDATION ==========

if (empty($licenseKey)) {
    sendResponse(false, 'License key is required', [], 400);
}

if (strlen($licenseKey) < 15) {
    sendResponse(false, 'Invalid license key format (too short)', [], 400);
}

// ========== AUTO-REGISTRATION CHECK ==========

$licenses = loadLicenses();

// If license doesn't exist AND format is valid, auto-register it
if (!isset($licenses[$licenseKey])) {
    if (isValidLicenseFormat($licenseKey)) {
        logActivity("🔍 License not found, attempting auto-registration: $licenseKey");

        if (autoRegisterLicense($licenseKey)) {
            // Reload licenses after registration
            $licenses = loadLicenses();
            logActivity("✅ Auto-registration successful: $licenseKey");
        } else {
            logActivity("❌ Auto-registration failed: $licenseKey");
            sendResponse(false, 'Invalid license key format', [], 403);
        }
    } else {
        logActivity("❌ Invalid license format: $licenseKey");
        sendResponse(false, 'Invalid license key format', [], 403);
    }
}

// At this point, license must exist
if (!isset($licenses[$licenseKey])) {
    sendResponse(false, 'License system error', [], 500);
}

$license = $licenses[$licenseKey];

// ========== STATUS CHECK ==========

if ($license['status'] !== 'active') {
    sendResponse(false, 'License is ' . $license['status'], [], 403);
}

// ========== EXPIRY CHECK ==========

$expiryDate = strtotime($license['expires']);
$today = time();

if ($expiryDate < $today) {
    sendResponse(false, 'License expired on ' . $license['expires'], [], 403);
}

// ========== MACHINE BINDING ==========

$maxMachines = (int)$license['max_machines'];
$currentMachines = $license['machines'] ?? [];
$machineCount = count($currentMachines);

// Find if this machine is already registered
$machineFound = false;
$machineIndex = -1;

foreach ($currentMachines as $idx => $machine) {
    if ($machine['fingerprint'] === $machineFingerprint) {
        $machineFound = true;
        $machineIndex = $idx;
        break;
    }
}

// Check machine limit
if (!$machineFound && $machineCount >= $maxMachines) {
    logActivity("❌ Machine limit reached for $licenseKey ($machineCount/$maxMachines)");
    sendResponse(false, "Maximum machines limit reached ($maxMachines)", [
        'machines_used' => $machineCount,
        'machines_max' => $maxMachines
    ], 403);
}

// ========== REGISTER/UPDATE MACHINE ==========

if ($machineFound) {
    // Update existing machine
    $licenses[$licenseKey]['machines'][$machineIndex]['last_seen'] = date('Y-m-d H:i:s');
    $licenses[$licenseKey]['machines'][$machineIndex]['account_number'] = $accountNumber;
    $licenses[$licenseKey]['machines'][$machineIndex]['account_server'] = $accountServer;
    $licenses[$licenseKey]['machines'][$machineIndex]['symbol'] = $symbol;
    $licenses[$licenseKey]['machines'][$machineIndex]['ea_version'] = $eaVersion;

    logActivity("🔄 Updated machine for $licenseKey | Account: $accountNumber");
} else {
    // Register new machine
    $licenses[$licenseKey]['machines'][] = [
        'fingerprint' => $machineFingerprint,
        'account_number' => $accountNumber,
        'account_server' => $accountServer,
        'symbol' => $symbol,
        'ea_version' => $eaVersion,
        'first_seen' => date('Y-m-d H:i:s'),
        'last_seen' => date('Y-m-d H:i:s'),
        'ip_address' => $clientIP
    ];

    logActivity("🆕 Registered new machine for $licenseKey | Account: $accountNumber | IP: $clientIP");
}

// Save changes
saveLicenses($licenses);

// ========== CALCULATE REMAINING DAYS ==========

$daysRemaining = ceil(($expiryDate - $today) / 86400);

// ========== SUCCESS RESPONSE ==========

$responseData = [
    'valid' => true,
    'expires' => $license['expires'],
    'days_remaining' => $daysRemaining,
    'machines_used' => count($licenses[$licenseKey]['machines']),
    'machines_max' => $maxMachines,
    'client_info' => $license['client_info'] ?? ''
];

// Add warning if expiring soon
if ($daysRemaining <= 7) {
    $responseData['warning'] = "License expires in $daysRemaining days!";
}

if ($daysRemaining <= 30) {
    $responseData['renewal_notice'] = "Consider renewing your license";
}

sendResponse(true, 'License valid', $responseData, 200);
