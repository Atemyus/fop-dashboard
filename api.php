<?php
/**
 * FURY OF SPARTA - API ENDPOINT
 * License verification for MetaTrader EA
 */

// CORS Headers (permetti richieste da qualsiasi origine)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Configurazione
define('LICENSE_DB_FILE', __DIR__ . '/licenses.json');
define('LOG_FILE', __DIR__ . '/license_log.txt');

// ========== FUNZIONI UTILITY ==========

function loadLicenses() {
    if (!file_exists(LICENSE_DB_FILE)) return [];
    $data = file_get_contents(LICENSE_DB_FILE);
    return json_decode($data, true) ?: [];
}

function saveLicenses($licenses) {
    return file_put_contents(LICENSE_DB_FILE, json_encode($licenses, JSON_PRETTY_PRINT));
}

function logActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] API: $message\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

function sendResponse($success, $message, $data = []) {
    $response = [
        'status' => $success ? 'success' : 'error',
        'message' => $message,
        'timestamp' => time()
    ];

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

// ========== GESTIONE RICHIESTE ==========

// 1. Estrai parametri (GET o POST)
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'check_license';
$licenseKey = isset($_REQUEST['license_key']) ? trim($_REQUEST['license_key']) : '';
$machineFingerprint = isset($_REQUEST['machine_fingerprint']) ? trim($_REQUEST['machine_fingerprint']) : '';
$accountNumber = isset($_REQUEST['account_number']) ? trim($_REQUEST['account_number']) : '';
$accountServer = isset($_REQUEST['account_server']) ? trim($_REQUEST['account_server']) : '';
$eaVersion = isset($_REQUEST['ea_version']) ? trim($_REQUEST['ea_version']) : '';
$symbol = isset($_REQUEST['symbol']) ? trim($_REQUEST['symbol']) : '';

// Log richiesta
logActivity("Request from IP: " . $_SERVER['REMOTE_ADDR'] . " | Action: $action | License: " . substr($licenseKey, 0, 8) . "***");

// ========== VALIDAZIONE BASE ==========

if (empty($licenseKey)) {
    sendResponse(false, 'License key is required');
}

if (strlen($licenseKey) < 15) {
    sendResponse(false, 'Invalid license key format');
}

// ========== CARICA DATABASE LICENZE ==========

$licenses = loadLicenses();

if (empty($licenses)) {
    logActivity("WARNING: License database is empty or missing");
    sendResponse(false, 'License system temporarily unavailable');
}

// ========== VERIFICA LICENZA ==========

if (!isset($licenses[$licenseKey])) {
    logActivity("FAILED: License key not found - $licenseKey");
    sendResponse(false, 'Invalid license key');
}

$license = $licenses[$licenseKey];

// ========== CHECK STATUS ==========

if ($license['status'] !== 'active') {
    logActivity("FAILED: License suspended - $licenseKey");
    sendResponse(false, 'License is ' . $license['status']);
}

// ========== CHECK EXPIRY ==========

$expiryDate = strtotime($license['expires']);
$today = strtotime(date('Y-m-d'));

if ($expiryDate < $today) {
    logActivity("FAILED: License expired - $licenseKey (Expired: {$license['expires']})");
    sendResponse(false, 'License expired on ' . $license['expires']);
}

// ========== GESTIONE MACHINE BINDING ==========

$maxMachines = (int)$license['max_machines'];
$currentMachines = $license['machines'];
$machineCount = count($currentMachines);

// Cerca se questa macchina è già registrata
$machineFound = false;
$machineIndex = -1;

foreach ($currentMachines as $idx => $machine) {
    if ($machine['fingerprint'] === $machineFingerprint) {
        $machineFound = true;
        $machineIndex = $idx;
        break;
    }
}

// Se macchina nuova e limite raggiunto
if (!$machineFound && $machineCount >= $maxMachines) {
    logActivity("FAILED: Max machines reached - $licenseKey (Max: $maxMachines)");
    sendResponse(false, "Maximum number of machines reached ($maxMachines/$maxMachines)");
}

// ========== REGISTRA/AGGIORNA MACCHINA ==========

if ($machineFound) {
    // Aggiorna last_seen
    $licenses[$licenseKey]['machines'][$machineIndex]['last_seen'] = date('Y-m-d H:i:s');
    $licenses[$licenseKey]['machines'][$machineIndex]['account_number'] = $accountNumber;
    $licenses[$licenseKey]['machines'][$machineIndex]['account_server'] = $accountServer;
    $licenses[$licenseKey]['machines'][$machineIndex]['symbol'] = $symbol;

    logActivity("SUCCESS: Machine updated - $licenseKey | Fingerprint: $machineFingerprint");
} else {
    // Registra nuova macchina
    $licenses[$licenseKey]['machines'][] = [
        'fingerprint' => $machineFingerprint,
        'account_number' => $accountNumber,
        'account_server' => $accountServer,
        'symbol' => $symbol,
        'ea_version' => $eaVersion,
        'first_seen' => date('Y-m-d H:i:s'),
        'last_seen' => date('Y-m-d H:i:s')
    ];

    logActivity("SUCCESS: New machine registered - $licenseKey | Fingerprint: $machineFingerprint");
}

// Salva modifiche
saveLicenses($licenses);

// ========== CALCOLA GIORNI RIMANENTI ==========

$daysRemaining = ceil(($expiryDate - $today) / 86400);

// ========== RISPOSTA DI SUCCESSO ==========

sendResponse(true, 'License valid', [
    'valid' => true,
    'expires' => $license['expires'],
    'days_remaining' => $daysRemaining,
    'machines_used' => count($licenses[$licenseKey]['machines']),
    'machines_max' => $maxMachines,
    'client_info' => $license['client_info'] ?? '',
    'warning' => ($daysRemaining <= 7) ? 'License expires soon!' : null
]);
