<?php
session_start();
require_once $_SERVER["DOCUMENT_DOCUMENT"] . "/dbconf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$reason  = trim($_POST['reason'] ?? '');

if (!$orderId || strlen($reason) < 3) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// ---------------------------------------------
// USER CANCEL FUNCTION
// ---------------------------------------------
function cancelOrderByUser($conn, $orderId, $reason, $userId)
{
    $sql = "
        UPDATE orders 
        SET status = 'cancelled', 
            cancellation_reason = ?, 
            cancelled_at = NOW(),
            cancelled_by = 'user',
            refund_status = 'initiated'
        WHERE id = ? AND user_id = ?
    ";

    $stmt = $conn->prepare($sql);
    return $stmt->execute([$reason, $orderId, $userId]);
}

// ---------------------------------------------
// ADMIN CANCEL FUNCTION
// ---------------------------------------------
function cancelOrderByAdmin($conn, $orderId, $reason)
{
    $sql = "
        UPDATE orders 
        SET status = 'cancelled',
            cancellation_reason = ?,
            cancelled_at = NOW(),
            cancelled_by = 'admin',
            refund_status = 'initiated'
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);
    return $stmt->execute([$reason, $orderId]);
}

// ---------------------------------------------
// MAIN EXECUTION LOGIC
// ---------------------------------------------
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($isAdmin) {
    // Admin cancelling order
    $done = cancelOrderByAdmin($conn, $orderId, $reason);
} else {
    // Normal user cancelling their order
    $done = cancelOrderByUser($conn, $orderId, $reason, $_SESSION['user_id']);
}

echo json_encode(['success' => $done]);
?>
