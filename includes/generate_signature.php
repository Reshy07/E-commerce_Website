<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$secretKey = "8gBm/:&EnhH.1/q"; // your eSewa secret

$total_amount = sprintf("%.2f", $_POST['total_amount'] ?? 0);
$transaction_uuid = $_POST['transaction_uuid'] ?? '';
$product_code     = $_POST['product_code'] ?? 'EPAYTEST';

if (empty($total_amount) || empty($transaction_uuid) || empty($product_code)) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$string_to_sign = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code={$product_code}";
$signature = base64_encode(hash_hmac('sha256', $string_to_sign, $secretKey, true));

echo json_encode([
    'success' => true,
    'signature' => $signature
]);
