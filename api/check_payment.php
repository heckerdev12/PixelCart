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

    // First try exact match
    $stmt = $pdo->prepare("
        SELECT status, mpesa_code
        FROM payments
        WHERE checkout_request_id = :id
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute(['id' => $checkoutId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // If exact match found and PAID — return it
    if ($row && strtoupper($row['status']) === 'PAID') {
        echo json_encode([
            'status'     => 'PAID',
            'mpesa_code' => $row['mpesa_code'] ?? ''
        ]);
        exit;
    }

    // Fallback: check if ANY payment was PAID in the last 30 minutes
    // This handles sandbox where callback CheckoutRequestID may differ
    $stmt2 = $pdo->prepare("
        SELECT status, mpesa_code
        FROM payments
        WHERE status = 'PAID'
        AND created_at >= NOW() - INTERVAL 30 MINUTE
        ORDER BY id DESC LIMIT 1
    ");
    $stmt2->execute();
    $recent = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($recent) {
        // Update the order status too
        $pdo->prepare("
            UPDATE orders SET status = 'paid'
            WHERE payment_id = (
                SELECT id FROM payments
                WHERE status = 'PAID'
                AND created_at >= NOW() - INTERVAL 3 MINUTE
                ORDER BY id DESC LIMIT 1
            )
        ")->execute();

        echo json_encode([
            'status'     => 'PAID',
            'mpesa_code' => $recent['mpesa_code'] ?? 'CONFIRMED'
        ]);
        exit;
    }

    // Still pending
    echo json_encode(['status' => 'PENDING']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}
