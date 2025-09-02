<?php
// payment_failed.php
session_start();
require_once __DIR__ . "/db_connect.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get data from eSewa callback
    $data = $_GET['data'] ?? null;
    
    if (!$data) {
        throw new Exception("No data received from eSewa");
    }
    
    // Decode the base64 encoded JSON data from eSewa
    $decodedData = json_decode(base64_decode($data), true);
    
    if (!$decodedData) {
        throw new Exception("Failed to decode eSewa response data");
    }
    
    $transaction_uuid = $decodedData['transaction_uuid'] ?? null;
    $status = $decodedData['status'] ?? null;
    $total_amount = $decodedData['total_amount'] ?? null;
    $transaction_code = $decodedData['transaction_code'] ?? null;
    
    if (!$transaction_uuid) {
        throw new Exception("Missing transaction UUID");
    }

    // Start transaction
    $conn->begin_transaction();

    // Find payment by transaction_id
    $stmt = $conn->prepare("SELECT id FROM payments WHERE transaction_id = ?");
    $stmt->bind_param("s", $transaction_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $payment_id = $row['id'];
        $stmt->close();
        
        // Update payment status
        $stmt = $conn->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $stmt->close();

        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = 'failed' WHERE payment_id = ?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Failed - BotaniQ</title>
            <style>
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background-color: #EEF0E5;
                    color: #163020;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .failed-icon {
                    font-size: 4rem;
                    color: #d32f2f;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #d32f2f;
                    margin-bottom: 20px;
                }
                .details {
                    background: #f9f9f9;
                    padding: 20px;
                    border-radius: 6px;
                    margin: 20px 0;
                    text-align: left;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 25px;
                    background-color: #20674b;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    margin: 10px;
                }
                .btn:hover {
                    background-color: #163020;
                }
                .btn-secondary {
                    background-color: #666;
                }
                .btn-secondary:hover {
                    background-color: #555;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="failed-icon">✗</div>
                <h1>Payment Failed</h1>
                <p>Unfortunately, your payment could not be processed.</p>
                
                <div class="details">
                    <h3>Transaction Details:</h3>
                    <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($transaction_uuid); ?></p>
                    <?php if ($transaction_code): ?>
                    <p><strong>eSewa Transaction Code:</strong> <?php echo htmlspecialchars($transaction_code); ?></p>
                    <?php endif; ?>
                    <p><strong>Amount:</strong> Rs. <?php echo htmlspecialchars($total_amount); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($status); ?></p>
                </div>
                
                <p>Please try again or contact our support team if the problem persists.</p>
                
                <a href="../checkout.php" class="btn">Try Again</a>
                <a href="../e-commerce.php" class="btn btn-secondary">Continue Shopping</a>
            </div>
        </body>
        </html>
        <?php
        
    } else {
        $stmt->close();
        throw new Exception("Payment record not found for transaction: " . $transaction_uuid);
    }

} catch (Throwable $e) {
    if (isset($conn)) { 
        try { $conn->rollback(); } catch (Throwable $ignored) {} 
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Error - BotaniQ</title>
        <style>
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                background-color: #EEF0E5;
                color: #163020;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
            }
            .error-icon {
                font-size: 4rem;
                color: #d32f2f;
                margin-bottom: 20px;
            }
            h1 {
                color: #d32f2f;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 12px 25px;
                background-color: #20674b;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin: 10px;
            }
            .btn:hover {
                background-color: #163020;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-icon">⚠</div>
            <h1>Payment Error</h1>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <p>If you continue to experience issues, please contact our support team.</p>
            <a href="../e-commerce.php" class="btn">Return to Shop</a>
        </div>
    </body>
    </html>
    <?php
} finally {
    if (isset($conn)) $conn->close();
}
?>