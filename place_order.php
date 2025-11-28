<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'vendor/autoload.php';
require_once __DIR__ . "/includes/env.php";
session_start();

// DB Config
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'diwali_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // PROCESS FORM
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill'])) {

        // Sanitize Input
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerMobile = trim($_POST['customer_mobile'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerState = trim($_POST['customer_state'] ?? '');
        $customerCity = trim($_POST['customer_city'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $itemsBought = json_decode($_POST['items_bought'] ?? '[]', true);
        $orderedDateTime = $_POST['ordered_date_time'] ?? date('Y-m-d H:i:s');

        // Basic Validation
        if (!$customerName || !$customerMobile || !$customerEmail || !$customerState || !$customerCity || !$customerAddress) {
            die("<p style='color:red;'>Error: Required fields missing.</p>");
        }
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            die("<p style='color:red;'>Invalid email.</p>");
        }
        if (!is_array($itemsBought) || empty($itemsBought)) {
            die("<p style='color:red;'>No items found in order.</p>");
        }

        // Generate Incrementing Order Number
        try {
            $conn->beginTransaction();
            $stmt = $conn->query("SELECT last_enquiry_number FROM settings LIMIT 1 FOR UPDATE");
            $row = $stmt->fetch();
            $enquiryNumber = ($row['last_enquiry_number'] ?? 1000) + 1;
            $conn->exec("UPDATE settings SET last_enquiry_number = $enquiryNumber");
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            die("Error generating enquiry number.");
        }

        // Calculate totals
        $subtotal = 0;
        $netTotal = 0;
        $packingpercent = 3;

        foreach ($itemsBought as $item) {
            $grossPrice = $item['grossPrice'];
            $discount = $item['discount'] ?? 0;

            $discounted = $grossPrice - ($grossPrice * ($discount / 100));
            $subtotal += $grossPrice * $item['quantity'];
            $netTotal += $discounted * $item['quantity'];
        }

        $packingcharge = ($netTotal * $packingpercent) / 100;
        $overallTotal = $netTotal + $packingcharge;

        // Insert order
        $stmt = $conn->prepare("
            INSERT INTO orders (enquiry_no, name, mobile, email, state, city, address, subtotal, packing_charge, net_total, overall_total, order_date, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
        ");
        $stmt->execute([$enquiryNumber, $customerName, $customerMobile, $customerEmail, $customerState, $customerCity, $customerAddress, $subtotal, $packingcharge, $netTotal, $overallTotal, $orderedDateTime]);

        $orderId = $conn->lastInsertId();

        // Save items
        $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, product_name, price, discount, discounted_price, qty, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($itemsBought as $item) {
            $grossPrice = $item['grossPrice'];
            $discount = $item['discount'];
            $discountedPrice = $grossPrice - ($grossPrice * ($discount / 100));
            $amount = $discountedPrice * $item['quantity'];

            $stmtItem->execute([$orderId, $item['name'], $grossPrice, $discount, $discountedPrice, $item['quantity'], $amount]);
        }

        $_SESSION['order_id'] = $orderId;

        // Redirect to razorpay checkout
        header("Location: payment.php?order_id=$orderId");
        exit;
    }

} catch (Exception $e) {
    die("<p style='color:red;'>Server error: " . $e->getMessage() . "</p>");
}
?>
