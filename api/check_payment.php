<?php
// ══════════════════════════════════════
//  check_payment.php
//  Polled by checkout.html every 4s
//  Returns payment status from DB
// ══════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$checkoutId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!$checkoutId) {
    echo json_encode(['status' => 'ERROR', 'message' => 'No checkout ID provided']);
    exit;
}

$host = 'localhost';
$db   = 'pixelcart_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT status, mpesa_code
        FROM payments
        WHERE checkout_request_id = :id
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute(['id' => $checkoutId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Not yet saved — still PENDING
        echo json_encode(['status' => 'PENDING']);
        exit;
    }

    $status = strtoupper($row['status']);

    if ($status === 'PAID') {
        // Also update the order status
        $stmt2 = $pdo->prepare("
            UPDATE orders
            SET status = 'paid'
            WHERE payment_id = (
                SELECT id FROM payments WHERE checkout_request_id = :id LIMIT 1
            )
        ");
        $stmt2->execute(['id' => $checkoutId]);

        echo json_encode([
            'status'     => 'PAID',
            'mpesa_code' => $row['mpesa_code'] ?? ''
        ]);
    } elseif (strpos($status, 'FAILED') !== false) {
        echo json_encode(['status' => 'FAILED']);
    } else {
        // PENDING
        echo json_encode(['status' => 'PENDING']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}
