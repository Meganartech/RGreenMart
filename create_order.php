<?php
session_start();
header("Content-Type: application/json");
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";
require_once "vendor/autoload.php";   // Razorpay PHP SDK

use Razorpay\Api\Api;

// USER LOGIN CHECK
$user_id = $_SESSION["user_id"] ?? 0;
if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit;
}

// GET RAW JSON
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$address_id = $data["address_id"] ?? 0;
$cartItems  = $data["cart"] ?? [];

if (!$address_id || empty($cartItems)) {
    echo json_encode(["success" => false, "message" => "Invalid address or empty cart"]);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. NEW ENQUIRY NUMBER
    $stmt = $conn->query("SELECT last_enquiry_number FROM settings LIMIT 1 FOR UPDATE");
    $row = $stmt->fetch();
    $enquiryNumber = ($row['last_enquiry_number'] ?? 1000) + 1;

    $conn->exec("UPDATE settings SET last_enquiry_number = $enquiryNumber");

    // 2. FETCH ADDRESS
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id=? AND user_id=?");
    $stmt->execute([$address_id, $user_id]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {
        throw new Exception("Invalid address");
    }

    // 3. GET TOTALS FROM FRONTEND (CORRECT FIX)
    $subtotal       = (float) ($data["subtotal"] ?? 0);
    $packing_charge = (float) ($data["packing_charge"] ?? 0);
    $net_total      = (float) ($data["net_total"] ?? 0);
    $overall_total  = (float) ($data["overall_total"] ?? 0);

    // 4. INSERT ORDER
    $stmt = $conn->prepare("INSERT INTO orders 
        (enquiry_no, user_id, address_id, subtotal, packing_charge, net_total, overall_total, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

    $stmt->execute([
        $enquiryNumber,
        $user_id,
        $address_id,
        $subtotal,
        $packing_charge,
        $net_total,
        $overall_total
    ]);

    $orderId = $conn->lastInsertId();

    // 5. INSERT ORDER ITEMS
    $stmt = $conn->prepare("INSERT INTO order_items 
        (order_id, item_id, original_price, discount_percentage, discounted_price, quantity, amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($cartItems as $item) {
        $qty = $item["quantity"] ?? 1;

        $stmt->execute([
            $orderId,
            $item["id"],
            $item["oldamt"],             // original price
            $item["discountRate"],       // discount %
            $item["price"],              // discounted price
            $qty,
            $item["price"] * $qty        // line total
        ]);
    }

    // 6. CREATE RAZORPAY ORDER
    $api = new Api($_ENV['RAZORPAY_KEY_ID'], $_ENV['RAZORPAY_KEY_SECRET']);

    $razorpayOrder = $api->order->create([
        "receipt"  => "ORDER_" . $orderId,
        "amount"   => round($overall_total * 100), // PAY EXACT OVERALL TOTAL
        "currency" => "INR"
    ]);

    $razorpayOrderId = $razorpayOrder["id"];

    // SAVE razorpay_order_id
    $stmt = $conn->prepare("UPDATE orders SET razorpay_order_id = ? WHERE id = ?");
    $stmt->execute([$razorpayOrderId, $orderId]);

    $conn->commit();

    // 7. USER DETAILS FOR PREFILL
    $stmt = $conn->prepare("SELECT name, email, mobile FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "order_id" => $orderId,
        "razorpay_order_id" => $razorpayOrderId,
        "key" => $_ENV['RAZORPAY_KEY_ID'],
        "amount" => round($overall_total * 100),
        "prefill" => [
            "name" => $user["name"],
            "email" => $user["email"],
            "mobile" => $user["mobile"]
        ]
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
