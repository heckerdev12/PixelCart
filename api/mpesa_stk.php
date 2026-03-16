<?php
// ══════════════════════════════════════
//  mpesa_stk.php
//  Triggers STK Push + saves order to DB
// ══════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once 'mpesa_auth.php';

define('SHORTCODE',    '174379');
define('PASSKEY',      'bfb279f9aa9b6f0bbde9fcd2eb5befc93dde975e4b1a3caec22f6e72deb53b3f');
define('CALLBACK_URL', 'https://julene-lineable-concurringly.ngrok-free.dev/PixelCart/api/mpesa_callback.php');
define('STK_URL',      'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');

// DB config
define('DB_HOST', 'localhost');
define('DB_NAME', 'pixelcart_db');
define('DB_USER', 'root');
define('DB_PASS', '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$phone   = isset($body['phone'])  ? sanitizePhone($body['phone'])  : null;
$amount  = isset($body['amount']) ? intval($body['amount'])        : null;
$name    = isset($body['name'])   ? trim($body['name'])            : '';
$email   = isset($body['email'])  ? trim($body['email'])           : '';
$address = isset($body['address']) ? trim($body['address'])         : '';
$items   = isset($body['items'])  ? $body['items']                 : [];

if (!$phone || !$amount) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Phone and amount required']);
    exit;
}

// Get token
$auth = getMpesaToken();
if (!$auth['success']) {
    echo json_encode(['success' => false, 'error' => 'Auth failed: ' . $auth['error']]);
    exit;
}

// Build STK payload
$timestamp = date('YmdHis');
$password  = base64_encode(SHORTCODE . PASSKEY . $timestamp);

$payload = [
    'BusinessShortCode' => SHORTCODE,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerPayBillOnline',
    'Amount'            => $amount,
    'PartyA'            => $phone,
    'PartyB'            => SHORTCODE,
    'PhoneNumber'       => $phone,
    'CallBackURL'       => CALLBACK_URL,
    'AccountReference'  => 'PixelCart',
    'TransactionDesc'   => 'Laptop Purchase - PixelCart'
];

$ch = curl_init(STK_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Authorization: Bearer ' . $auth['token'], 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT,        30);

$response = curl_exec($ch);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

$data = json_decode($response, true);

if (isset($data['ResponseCode']) && $data['ResponseCode'] === '0') {
    $checkoutRequestId  = $data['CheckoutRequestID'];
    $merchantRequestId  = $data['MerchantRequestID'];

    // Save pending order to database
    saveOrder($checkoutRequestId, $merchantRequestId, $phone, $amount, $name, $email, $address, $items);

    echo json_encode([
        'success'           => true,
        'message'           => 'STK Push sent. Check your phone.',
        'CheckoutRequestID' => $checkoutRequestId
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error'   => $data['errorMessage'] ?? 'STK Push failed',
        'raw'     => $data
    ]);
}

/* ── Save order + items to DB ── */
function saveOrder($checkoutId, $merchantId, $phone, $amount, $name, $email, $address, $items)
{
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Insert into payments as PENDING
        $stmt = $pdo->prepare("
            INSERT INTO payments
                (checkout_request_id, merchant_request_id, phone, amount, status, created_at)
            VALUES
                (:checkout_request_id, :merchant_request_id, :phone, :amount, 'PENDING', NOW())
        ");
        $stmt->execute([
            'checkout_request_id' => $checkoutId,
            'merchant_request_id' => $merchantId,
            'phone'               => $phone,
            'amount'              => $amount
        ]);
        $paymentId = $pdo->lastInsertId();

        // 2. Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders
                (payment_id, customer_name, phone, email, address, total, status, created_at)
            VALUES
                (:payment_id, :name, :phone, :email, :address, :total, 'pending', NOW())
        ");
        $stmt->execute([
            'payment_id' => $paymentId,
            'name'       => $name,
            'phone'      => $phone,
            'email'      => $email,
            'address'    => $address,
            'total'      => $amount
        ]);
        $orderId = $pdo->lastInsertId();

        // 3. Insert order items
        foreach ($items as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, qty, price)
                VALUES (:order_id, :product_id, :qty, :price)
            ");
            $stmt->execute([
                'order_id'   => $orderId,
                'product_id' => $item['id']    ?? 'unknown',
                'qty'        => $item['qty']   ?? 1,
                'price'      => $item['price'] ?? 0
            ]);
        }
    } catch (PDOException $e) {
        error_log('DB Error in mpesa_stk.php: ' . $e->getMessage());
    }
}

/* ── Format phone ── */
function sanitizePhone($phone)
{
    $phone = preg_replace('/\D/', '', $phone);
    if (substr($phone, 0, 1) === '0') $phone = '254' . substr($phone, 1);
    if (substr($phone, 0, 3) !== '254') $phone = '254' . $phone;
    return $phone;
}
