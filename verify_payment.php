<?php
// Set up error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

// --- 1. Load Environment Variables ---
require_once __DIR__ . "./includes/env.php"; 
require_once __DIR__ . "/admin/config.php"; 

use Razorpay\Api\Api;

session_start();

// DB Config (Get configuration from environment variables)
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'diwali_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

// --- 2. Initialize Database Connection ($conn) ---
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// --- 3. Initialize Razorpay API ---
$razorpayKeyId = $_ENV['RAZORPAY_KEY_ID'] ?? null;
$razorpayKeySecret = $_ENV['RAZORPAY_KEY_SECRET'] ?? null;

if (!$razorpayKeyId || !$razorpayKeySecret) {
    die("Configuration Error: Razorpay keys are missing.");
}

$api = new Api($razorpayKeyId, $razorpayKeySecret);

// --- 4. Get Data from URL and Database ---
$orderId = $_GET['order_id'] ?? null;
$paymentId = $_GET['payment_id'] ?? null;
$signature = $_GET['signature'] ?? null;

// Basic validation (same as before)
if (!$orderId || !$paymentId || !$signature || !isset($_SESSION['order_id']) || $orderId != $_SESSION['order_id']) {
    die("Invalid payment request parameters or session mismatch.");
}

// Fetch the order details, including the Razorpay Order ID
$stmt = $conn->prepare("SELECT * FROM orders WHERE id=?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Error: Order ID $orderId not found in database.");
}

$attributes = [
    'razorpay_order_id' => $order['razorpay_order_id'],
    'razorpay_payment_id' => $paymentId,
    'razorpay_signature' => $signature
];

// --- 5. Debug Output ---
echo "<h2>DEBUG INFO (DO NOT SHOW IN PRODUCTION)</h2>";
echo "<p>DB Razorpay Order ID: " . htmlspecialchars($order['razorpay_order_id']) . "</p>";
echo "<p>Received Payment ID: " . htmlspecialchars($paymentId) . "</p>";
echo "<p>Received Signature: " . htmlspecialchars($signature) . "</p>";
echo "<p>Attempting verification...</p>";
// --- End Debug Output ---

// --- 6. Verify Signature and Update DB ---
try {
    $api->utility->verifyPaymentSignature($attributes);

    // FIX APPLIED: Mapped $paymentId to razorpay_payment_id and added razorpay_signature
    $conn->prepare("
        UPDATE orders 
        SET 
            payment_status='paid', 
            razorpay_payment_id=?, 
            razorpay_signature=? 
        WHERE id=?
    ")->execute([$paymentId, $signature, $orderId]);

    // Clear session data
    unset($_SESSION['order_id']);

    header("Location: pdf_generation.php?order_id=$orderId");
    exit;

} catch(\Razorpay\Api\Errors\SignatureVerificationError $e){
    // Update status to FAILED and record the payment ID and signature
    $errorMessage = "Payment Verification Failed: Invalid Signature. Debug: " . $e->getMessage();
    
    $conn->prepare("
        UPDATE orders 
        SET 
            payment_status='failed', 
            razorpay_payment_id=?, 
            razorpay_signature=? 
        WHERE id=?
    ")->execute([$paymentId, $signature, $orderId]);
         
    die($errorMessage);
    
} catch(Exception $e){
    // General error handler
    $conn->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")->execute([$orderId]);
    die("Payment Verification Failed: An unexpected error occurred: " . $e->getMessage());
}
?>