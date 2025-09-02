<?php
// process_order.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/db_connect.php"; // must define $conn = new mysqli(...)

// Enable error reporting for debugging (comment out in production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }
    $user_id = (int)$_SESSION['user_id'];

    // Read request
    $raw = file_get_contents("php://input");
    if (!$raw) {
        throw new Exception("Empty request body");
    }
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $customer = $payload['customerInfo'] ?? [];
    $items    = $payload['items'] ?? [];
    $shipping = (float)($payload['shipping'] ?? 0);
    $tax      = (float)($payload['tax'] ?? 0);

    if (empty($items)) {
        throw new Exception("No items in order");
    }

    // Recalculate totals
    $calcSubtotal = 0.0;
    foreach ($items as $row) {
        $qty   = (int)($row['quantity'] ?? 0);
        $price = (float)preg_replace('/[^0-9.]/', '', (string)($row['price'] ?? '0'));
        if ($qty <= 0 || $price <= 0) {
            throw new Exception("Invalid cart line: " . json_encode($row));
        }
        $calcSubtotal += $price * $qty;
    }
    $totalAmount = $calcSubtotal + $shipping + $tax;

    // Generate unique transaction id
    $transaction_uuid = uniqid("order_");
    $payment_method   = $customer['paymentMethod'] ?? 'cod';
    $payment_status   = "pending";
    $order_status     = "pending";

    // Shipping info
    $shipping_address = trim((string)($customer['address'] ?? ''));
    $shipping_city    = trim((string)($customer['city'] ?? ''));
    $shipping_zip     = trim((string)($customer['zipCode'] ?? ''));
    $shipping_country = trim((string)($customer['country'] ?? ''));
    $customer_notes   = trim((string)($customer['notes'] ?? ''));

    // Start DB transaction
    $conn->begin_transaction();

    // 1. Insert payment
    $stmt = $conn->prepare("
        INSERT INTO payments (user_id, transaction_id, amount, payment_method, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isdss", $user_id, $transaction_uuid, $totalAmount, $payment_method, $payment_status);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2. Insert order
    $stmt = $conn->prepare("
        INSERT INTO orders (user_id, payment_id, total_amount, status, payment_method,
                            shipping_address, shipping_city, shipping_zip, shipping_country, customer_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iidsssssss",
        $user_id, $payment_id, $totalAmount, $order_status, $payment_method,
        $shipping_address, $shipping_city, $shipping_zip, $shipping_country, $customer_notes
    );
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    // 3. Insert order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_name, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items as $row) {
        $pname = trim((string)($row['name'] ?? ''));
        $qty   = (int)($row['quantity'] ?? 0);
        $price = (float)preg_replace('/[^0-9.]/', '', (string)($row['price'] ?? '0'));
        $stmt->bind_param("isid", $order_id, $pname, $qty, $price);
        $stmt->execute();
    }
    $stmt->close();

    // Commit
    $conn->commit();

    echo json_encode([
        "success"   => true,
        "orderId"   => $order_id,
        "paymentId" => $payment_id,
        "transactionId" => $transaction_uuid,
        "message"   => "Order created successfully"
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>