<?php
// process_order_debug.php — create Payment + Order for COD/normal path
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }
    $user_id = (int)$_SESSION['user_id'];

    $raw = file_get_contents('php://input');
    if (!$raw) throw new Exception('Empty request body');
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $customer = $payload['customerInfo'] ?? [];
    $items    = $payload['items']        ?? [];
    $subtotal = (float)($payload['subtotal'] ?? 0);
    $shipping = (float)($payload['shipping'] ?? 0);
    $total    = (float)($payload['total'] ?? ($subtotal + $shipping));

    $payment_method   = $customer['paymentMethod'] ?? 'cod';
    $shipping_address = trim(($customer['address'] ?? ''));
    $shipping_city    = trim(($customer['city'] ?? ''));
    $shipping_zip     = trim(($customer['zipCode'] ?? ''));
    $shipping_country = trim(($customer['country'] ?? ''));
    $customer_notes   = trim(($customer['notes'] ?? ''));

    if (empty($items)) throw new Exception('No items in order');

    if (!$conn->begin_transaction()) {
        throw new Exception('Unable to start DB transaction: '.$conn->error);
    }

    // Recalculate total from DB
    $calc_total = 0.0;
    $order_items = [];
    $product_stmt = $conn->prepare("SELECT id, price FROM products WHERE id = ?");
    if (!$product_stmt) throw new Exception('Prepare product failed: '.$conn->error);
    foreach ($items as $row) {
        $pid = (int)($row['product_id'] ?? $row['id'] ?? 0);
        $qty = (int)($row['quantity'] ?? 1);
        if ($pid <= 0 || $qty <= 0) throw new Exception('Invalid product or quantity');

        $product_stmt->bind_param("i", $pid);
        if (!$product_stmt->execute()) throw new Exception('Product fetch failed: '.$product_stmt->error);
        $res = $product_stmt->get_result();
        if ($res->num_rows === 0) throw new Exception("Product not found: $pid");
        $prod = $res->fetch_assoc();
        $unit = (float)$prod['price'];
        $calc_total += $unit * $qty;
        $order_items[] = ['product_id'=>$pid,'quantity'=>$qty,'price'=>$unit];
    }
    $product_stmt->close();
    $calc_total += $shipping;
    if (abs($calc_total - $total) > 0.01) {
        $total = $calc_total;
    }

    // Create payment for COD — mark as 'pending' now
    $transaction_id = 'COD-' . bin2hex(random_bytes(8));
    $payment_status = 'pending';
    $payment_stmt = $conn->prepare("INSERT INTO payments (user_id, transaction_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?)");
    if (!$payment_stmt) throw new Exception('Prepare payment failed: '.$conn->error);
    $payment_stmt->bind_param("isdss", $user_id, $transaction_id, $total, $payment_method, $payment_status);
    if (!$payment_stmt->execute()) {
        throw new Exception('Payment insert failed: '.$payment_stmt->error);
    }
    $payment_id = $payment_stmt->insert_id;
    $payment_stmt->close();

    // Create order
    $status = 'pending';
    $order_stmt = $conn->prepare("INSERT INTO orders
        (user_id, payment_id, total_amount, status, payment_method,
         shipping_address, shipping_city, shipping_zip, shipping_country, customer_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$order_stmt) throw new Exception('Prepare order failed: '.$conn->error);
    $order_stmt->bind_param(
        "iidsssssss",
        $user_id, $payment_id, $total, $status, $payment_method,
        $shipping_address, $shipping_city, $shipping_zip, $shipping_country, $customer_notes
    );
    if (!$order_stmt->execute()) {
        throw new Exception('Order insert failed: '.$order_stmt->error);
    }
    $order_id = $order_stmt->insert_id;
    $order_stmt->close();

    // Insert order items
    $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    if (!$item_stmt) throw new Exception('Prepare order_items failed: '.$conn->error);
    foreach ($order_items as $oi) {
        $item_stmt->bind_param("iiid", $order_id, $oi['product_id'], $oi['quantity'], $oi['price']);
        if (!$item_stmt->execute()) {
            throw new Exception('Order item insert failed: '.$item_stmt->error);
        }
    }
    $item_stmt->close();

    if (!$conn->commit()) throw new Exception('Commit failed: '.$conn->error);

    echo json_encode(['success' => true, 'orderId' => $order_id, 'paymentId' => $payment_id]);
} catch (Throwable $e) {
    @$conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
