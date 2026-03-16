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

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            AUTH_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPGET,        true);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Add this temporarily to debug
    if (empty($response)) {
        return [
            'success'  => false,
            'error'    => "Empty response. HTTP Code: $httpCode. Curl error: $error"
        ];
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
