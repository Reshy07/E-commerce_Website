<?php
// payment_success.php
session_start();
require_once 'db_connect.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // -----------------------------------------------------------------
    // 1. Get data from eSewa callback (base64 JSON in ?data=…)
    // -----------------------------------------------------------------
    $data = $_GET['data'] ?? null;
    if (!$data) {
        throw new Exception("No data received from eSewa");
    }

    $decodedData = json_decode(base64_decode($data), true);
    if (!$decodedData) {
        throw new Exception("Failed to decode eSewa response data");
    }

    $transaction_uuid = $decodedData['transaction_uuid'] ?? null;
    $status           = $decodedData['status']           ?? null;
    $total_amount     = $decodedData['total_amount']     ?? null;
    $transaction_code = $decodedData['transaction_code'] ?? null;

    if (!$transaction_uuid) {
        throw new Exception("Missing transaction UUID");
    }
    if ($status !== 'COMPLETE') {
        throw new Exception("Payment not completed. Status: " . $status);
    }

    // -----------------------------------------------------------------
    // 2. Update DB (payment → completed, order → completed)
    // -----------------------------------------------------------------
    $conn->begin_transaction();

    // Find payment record
    $stmt = $conn->prepare("SELECT id FROM payments WHERE transaction_id = ?");
    $stmt->bind_param("s", $transaction_uuid);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$row = $result->fetch_assoc()) {
        $stmt->close();
        throw new Exception("Payment record not found for transaction: " . $transaction_uuid);
    }
    $payment_id = $row['id'];
    $stmt->close();

    // Mark payment as completed
    $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $stmt->close();

    // Mark order as completed
    $stmt = $conn->prepare("UPDATE orders SET status = 'completed' WHERE payment_id = ?");
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
        <title>Payment Successful - BotaniQ</title>
        <style>
            body{
                margin:0;padding:0;background:#EEF0E5;font-family:'Segoe UI',Arial,sans-serif;
            }
            .success-box{
                max-width:500px;margin:50px auto;padding:30px;
                text-align:center;border:1px solid #ddd;border-radius:10px;
                background:#e6ffe6;
            }
            .success-box img{
                width:120px;margin-bottom:20px;
            }
            .success-box h2{
                color:green;margin-bottom:15px;
            }
            .success-box p{
                margin:8px 0;
            }
            .success-box a{
                display:inline-block;margin-top:20px;
                color:#3FA636;text-decoration:none;font-weight:500;
            }
        </style>
    </head>
    <body>
        <div class="success-box">
            <img src="https://www.esewa.com.np/images/esewa-logo.png" alt="eSewa">
            <h2>Payment Successful</h2>
            <p>Your payment has been processed.</p>

            <p><strong>Amount Paid:</strong> NPR. <?php echo number_format($total_amount, 2); ?></p>
            <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($transaction_uuid); ?></p>
            <?php if ($transaction_code): ?>
                <p><strong>eSewa Transaction Code:</strong> <?php echo htmlspecialchars($transaction_code); ?></p>
            <?php endif; ?>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($status); ?></p>

            <p>Please don’t refresh the page or navigate away while we redirect you.</p>
            <p><a href="e-commerce.php">Click here if not redirected automatically</a></p>
        </div>

    </body>
    </html>
    <?php

} catch (Throwable $e) {
    // ---------------------------------------------------------------
    // 4. Rollback on any error and show a clean error page
    // ---------------------------------------------------------------
    if (isset($conn)) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }

    // Simple error UI (you can reuse your existing payment_failed style)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Error - BotaniQ</title>
        <style>
            body{font-family:'Segoe UI',Arial,sans-serif;background:#EEF0E5;color:#163020;margin:0;padding:20px;}
            .container{max-width:600px;margin:0 auto;background:#fff;padding:40px;border-radius:8px;
                       box-shadow:0 2px 10px rgba(0,0,0,.1);text-align:center;}
            .error-icon{font-size:4rem;color:#d32f2f;margin-bottom:20px;}
            h1{color:#d32f2f;}
            .btn{display:inline-block;padding:12px 25px;background:#20674b;color:#fff;
                  text-decoration:none;border-radius:4px;margin:10px;}
            .btn:hover{background:#163020;}
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-icon">Warning</div>
            <h1>Payment Error</h1>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <p>If the problem persists, please contact support.</p>
            <a href="e-commerce.php" class="btn">Return to Shop</a>
        </div>
    </body>
    </html>
    <?php
} finally {
    if (isset($conn)) $conn->close();
}
?>