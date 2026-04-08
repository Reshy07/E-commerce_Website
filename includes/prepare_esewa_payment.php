<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    $raw = file_get_contents('php://input');
    if (!$raw) {
        throw new Exception('Empty request body');
    }

    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $order_id = (int)($payload['orderId'] ?? 0);
    $transaction_uuid = trim((string)($payload['transaction_uuid'] ?? ''));
    $user_id = (int)$_SESSION['user_id'];

    if ($order_id <= 0 || $transaction_uuid === '') {
        throw new Exception('Missing order details');
    }

    $stmt = $conn->prepare("SELECT o.total_amount, o.user_id, p.transaction_id
        FROM orders o
        INNER JOIN payments p ON o.payment_id = p.id
        WHERE o.id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$row = $result->fetch_assoc()) {
        $stmt->close();
        throw new Exception('Order not found');
    }
    $stmt->close();

    if ((int)$row['user_id'] !== $user_id) {
        throw new Exception('Unauthorized order access');
    }

    if ((string)$row['transaction_id'] !== $transaction_uuid) {
        throw new Exception('Transaction mismatch');
    }

    $total_value = (float)$row['total_amount'];
    $delivery_charge_value = 100.00;
    if ($total_value < $delivery_charge_value) {
        $delivery_charge_value = 0.00;
    }
    $amount_value = $total_value - $delivery_charge_value;

    $total_amount = number_format($total_value, 2, '.', '');
    $amount = number_format($amount_value, 2, '.', '');
    $delivery_charge = number_format($delivery_charge_value, 2, '.', '');
    $product_code = 'EPAYTEST';
    $secret_key = '8gBm/:&EnhH.1/q';

    $string_to_sign = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code={$product_code}";
    $signature = base64_encode(hash_hmac('sha256', $string_to_sign, $secret_key, true));

    echo json_encode([
        'success' => true,
        'amount' => $amount,
        'total_amount' => $total_amount,
        'product_delivery_charge' => $delivery_charge,
        'tax_amount' => '0',
        'product_service_charge' => '0',
        'transaction_uuid' => $transaction_uuid,
        'product_code' => $product_code,
        'signature' => $signature
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
