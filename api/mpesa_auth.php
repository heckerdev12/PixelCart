<?php
// ══════════════════════════════════════
//  mpesa_auth.php
//  Gets access token from Safaricom
// ══════════════════════════════════════

define('CONSUMER_KEY',    'YOUR_CONSUMER_KEY_HERE');
define('CONSUMER_SECRET', 'YOUR_CONSUMER_SECRET_HERE');

// Sandbox URL — swap for live when going to production
define('MPESA_ENV', 'sandbox');
define('AUTH_URL',  'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

function getMpesaToken()
{
    $credentials = base64_encode(CONSUMER_KEY . ':' . CONSUMER_SECRET);

    $ch = curl_init(AUTH_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // OK for sandbox/local

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        return ['success' => true, 'token' => $data['access_token']];
    }

    return ['success' => false, 'error' => $response];
}

// ── Quick test: hit this file directly in browser ──
// http://localhost/PixelCart/api/mpesa_auth.php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    echo json_encode(getMpesaToken(), JSON_PRETTY_PRINT);
}
