<?php
session_start();
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Admin login required']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$reason = trim($_POST['reason'] ?? '');

if (!$orderId || strlen($reason) < 3) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

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
$done = $stmt->execute([$reason, $orderId]);

echo json_encode(['success' => $done]);
