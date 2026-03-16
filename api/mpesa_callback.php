<?php
// ══════════════════════════════════════
//  mpesa_callback.php
//  Safaricom calls this after payment
//  Saves result to MySQL via XAMPP
// ══════════════════════════════════════

// Log everything — helpful during testing
$log_file = __DIR__ . '/callback_log.txt';

$raw = file_get_contents('php://input');
file_put_contents($log_file, date('Y-m-d H:i:s') . "\n" . $raw . "\n\n", FILE_APPEND);

$data = json_decode($raw, true);

// ── Always respond 200 to Safaricom immediately ──
// If you don't, they retry the callback multiple times
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

// ── Parse the callback ──
$body = $data['Body']['stkCallback'] ?? null;

if (!$body) {
    file_put_contents($log_file, "ERROR: No stkCallback in body\n\n", FILE_APPEND);
    exit;
}

$resultCode = $body['ResultCode'];
$resultDesc = $body['ResultDesc'];
$merchantRequestID  = $body['MerchantRequestID']  ?? '';
$checkoutRequestID  = $body['CheckoutRequestID']  ?? '';

// ── Payment successful ──
// ResultCode 0    = real success (production)
// ResultCode 1037 = sandbox "no response" — treated as success for demo/testing
$sandboxSuccess = ($resultCode === 1037);
$realSuccess    = ($resultCode === 0);

if ($realSuccess || $sandboxSuccess) {
    $items = $body['CallbackMetadata']['Item'] ?? [];

    // Extract payment details (only available on real success)
    $amount          = $realSuccess ? getCallbackValue($items, 'Amount')            : 'SANDBOX';
    $mpesaCode       = $realSuccess ? getCallbackValue($items, 'MpesaReceiptNumber') : 'SANDBOX-' . strtoupper(substr($checkoutRequestID, -6));
    $transactionDate = $realSuccess ? getCallbackValue($items, 'TransactionDate')   : date('YmdHis');
    $phoneNumber     = $realSuccess ? getCallbackValue($items, 'PhoneNumber')       : '254708374149';

    // ── Save to MySQL ──
    savePayment([
        'checkout_request_id' => $checkoutRequestID,
        'merchant_request_id' => $merchantRequestID,
        'mpesa_code'          => $mpesaCode,
        'phone'               => $phoneNumber,
        'amount'              => $amount,
        'transaction_date'    => $transactionDate,
        'status'              => 'PAID'
    ]);

    file_put_contents(
        $log_file,
        "✅ PAYMENT SUCCESS | Code: $mpesaCode | Phone: $phoneNumber | Amount: $amount\n\n",
        FILE_APPEND
    );
} else {
    // Payment failed or cancelled
    savePayment([
        'checkout_request_id' => $checkoutRequestID,
        'merchant_request_id' => $merchantRequestID,
        'mpesa_code'          => null,
        'phone'               => null,
        'amount'              => 0,
        'transaction_date'    => date('YmdHis'),
        'status'              => 'FAILED - ' . $resultDesc
    ]);

    file_put_contents(
        $log_file,
        "❌ PAYMENT FAILED | Reason: $resultDesc\n\n",
        FILE_APPEND
    );
}

// ── Save payment to database ──
function savePayment($payment)
{
    $host = 'localhost';
    $db   = 'pixelcart_db';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if a PENDING row already exists — if yes UPDATE it, if no INSERT
        $check = $pdo->prepare("
            SELECT id FROM payments
            WHERE checkout_request_id = :id
            ORDER BY id DESC LIMIT 1
        ");
        $check->execute(['id' => $payment['checkout_request_id']]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // UPDATE the existing row
            $stmt = $pdo->prepare("
                UPDATE payments SET
                    mpesa_code       = :mpesa_code,
                    phone            = :phone,
                    amount           = :amount,
                    transaction_date = :transaction_date,
                    status           = :status
                WHERE id = :id
            ");
            $stmt->execute([
                'mpesa_code'       => $payment['mpesa_code'],
                'phone'            => $payment['phone'],
                'amount'           => $payment['amount'],
                'transaction_date' => $payment['transaction_date'],
                'status'           => $payment['status'],
                'id'               => $existing['id']
            ]);
        } else {
            // INSERT new row (fallback if STK PHP didn't save it)
            $stmt = $pdo->prepare("
                INSERT INTO payments
                    (checkout_request_id, merchant_request_id, mpesa_code, phone, amount, transaction_date, status, created_at)
                VALUES
                    (:checkout_request_id, :merchant_request_id, :mpesa_code, :phone, :amount, :transaction_date, :status, NOW())
            ");
            $stmt->execute($payment);
        }
    } catch (PDOException $e) {
        $log_file = __DIR__ . '/callback_log.txt';
        file_put_contents($log_file, "DB ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND);
    }
}

// ── Helper: extract value from Safaricom callback items ──
function getCallbackValue($items, $name)
{
    foreach ($items as $item) {
        if ($item['Name'] === $name) {
            return $item['Value'];
        }
    }
    return null;
}
