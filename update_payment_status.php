<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

$orderId   = $_POST["order_id"];
$status    = $_POST["status"];        // success / failed
$paymentId = $_POST["payment_id"] ?? null;   // may be empty for failed

if ($status === "success") {
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = 'paid',
           status = 'ordered',
            razorpay_payment_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$paymentId, $orderId]);

} else {
    // FAILED PAYMENT
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = 'failed'
        status = 'pending'
        WHERE id = ?
    ");
    $stmt->execute([$orderId]);
}

echo "ok";
