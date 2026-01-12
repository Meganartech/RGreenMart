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
    $shippingcharge = (float) ($data["shipping_charge"] ?? 0);
    $overall_total  = (float) ($data["overall_total"] ?? 0);

    // Check minimum order amount from settings
    $minRow = $conn->query("SELECT minimum_order FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $minimumOrder = isset($minRow['minimum_order']) ? (float)$minRow['minimum_order'] : 0;

    if ($overall_total < $minimumOrder) {
        echo json_encode(["success" => false, "message" => "Minimum order amount is ₹" . number_format($minimumOrder,2)]);
        $conn->rollBack();
        exit;
    }

    // 4. INSERT ORDER
$stmt = $conn->prepare("
    INSERT INTO orders (
        enquiry_no,
        user_id,
        address_id,
        subtotal,
        shipping_charge,
        overall_total,
         courier_company_id,
        courier_name,
        estimated_delivery_days,
        etd,
        status,
        created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,  'pending', NOW()
    )
");

$stmt->execute([
    $enquiryNumber,
    $user_id,
    $address_id,
    $subtotal,
    $data['shipping_charge'] ?? 0,                
    $overall_total,
    $data['courier_company_id'] ?? null,
    $data['courier_name'] ?? null,
    $data['courier_eta'] ?? null,
    $data['courier_etd'] ?? null
]);



    $orderId = $conn->lastInsertId();

    // 5. INSERT ORDER ITEMS
    // Check which columns exist in order_items table (to safely support variant columns)
    $colsStmt = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = 'order_items'");
    $dbName = $_ENV['DB_NAME'] ?? (parse_url($_ENV['DATABASE_URL'] ?? '')['path'] ?? null);
    if (!$dbName) {
        // Try connection DB name from PDO
        $dbName = $conn->query('select database()')->fetchColumn();
    }
    $colsStmt->execute([$dbName]);
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

    $hasVariantId = in_array('variant_id', $cols);
    $hasVariantWeightValue = in_array('variant_weight_value', $cols);
    $hasVariantWeightUnit = in_array('variant_weight_unit', $cols);
    $hasVariantPrice = in_array('variant_price', $cols);

    // Build insert SQL dynamically
    $baseCols = ['order_id','item_id','original_price','discount_percentage','discounted_price','quantity','amount'];
    if ($hasVariantId) $baseCols[] = 'variant_id';
    if ($hasVariantWeightValue) $baseCols[] = 'variant_weight_value';
    if ($hasVariantWeightUnit) $baseCols[] = 'variant_weight_unit';
    if ($hasVariantPrice) $baseCols[] = 'variant_price';

    $placeholders = implode(', ', array_fill(0, count($baseCols), '?'));
    $insertSql = 'INSERT INTO order_items (' . implode(',', $baseCols) . ') VALUES (' . $placeholders . ')';
    $stmt = $conn->prepare($insertSql);

    foreach ($cartItems as $item) {
        $qty = $item["quantity"] ?? $item['qty'] ?? 1;
        $params = [
            $orderId,
            $item["id"],
            $item["oldamt"] ?? 0,
            $item["discountRate"] ?? 0,
            $item["price"] ?? 0,
            $qty,
            ($item["price"] ?? 0) * $qty
        ];

        if ($hasVariantId) $params[] = $item['variant_id'] ?? null;
        if ($hasVariantWeightValue) $params[] = $item['variant_weight'] ?? ($item['weight_value'] ?? null);
        if ($hasVariantWeightUnit) $params[] = $item['variant_unit'] ?? ($item['weight_unit'] ?? null);
        if ($hasVariantPrice) $params[] = $item['variant_price'] ?? ($item['price'] ?? 0);

        $stmt->execute($params);
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
