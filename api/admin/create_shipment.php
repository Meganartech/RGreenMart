<?php
/************************************************************
 * CREATE SHIPMENT – KYC SAFE & JSON RESPONSE
 ************************************************************/

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../../dbconf.php';
require_once __DIR__ . '/../shiprocket.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$orderId = intval($_POST['order_id'] ?? 0);
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'order_id is required']);
    exit;
}

try {
    // Fetch order and user/address details
    $stmt = $conn->prepare("
        SELECT o.*,
               ua.contact_name, ua.contact_mobile, ua.address_line1, ua.address_line2,
               ua.city, ua.state, ua.pincode,
               u.email AS user_email
        FROM orders o
        LEFT JOIN user_addresses ua ON o.address_id = ua.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');

    // Validate phone
    $phone = preg_replace('/[^0-9]/', '', $order['contact_mobile'] ?? '');
    if (strlen($phone) > 10) $phone = substr($phone, -10);
    if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        throw new Exception('Invalid phone number');
    }

    // Billing address
    $billingAddress = trim(($order['address_line1'] ?? '') . ' ' . ($order['address_line2'] ?? ''));
    if (strlen($billingAddress) < 5 || !$order['city'] || !$order['state'] || !$order['pincode']) {
        throw new Exception('Incomplete address');
    }

    $state = ucwords(strtolower(trim($order['state'])));
    $pickupLocation = trim($_ENV['SHIPROCKET_PICKUP_LOC_NAME'] ?? '');
    if (!$pickupLocation) throw new Exception('Pickup location missing');

    // Fetch order items
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) throw new Exception('No items found');

    $orderItems = [];
    $subTotal = 0;
    $totalWeight = 0;

    foreach ($items as $it) {
        $price = $it['variant_price'] ?? $it['discounted_price'] ?? $it['original_price'] ?? 0;
        if ($price <= 0) throw new Exception('Invalid item price');

        $qty = max(1, (int)$it['quantity']);
        $weight = max(0.5, floatval($it['variant_weight_value'] ?? $it['weight'] ?? 0.5));

        $subTotal += ($price * $qty);
        $totalWeight += ($weight * $qty);

        $orderItems[] = [
            'name' => $it['name'] ?? 'Item',
            'sku'  => 'SKU' . $it['item_id'],
            'units' => $qty,
            'selling_price' => round($price, 2)
        ];
    }

    // Customer name split
    $fullName = trim($order['contact_name'] ?? 'Customer');
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName  = $nameParts[1] ?? 'NA';

    // Shiprocket payload
    $payload = [
        'order_id'   => 'ERP-' . $order['id'] . '-' . time(),
        'order_date' => date('Y-m-d H:i:s'),
        'pickup_location' => $pickupLocation,
        'shipping_is_billing' => 1, // no separate shipping
        'billing_first_name' => $firstName,
        'billing_last_name'  => $lastName,
        'billing_customer_name' => $fullName,
        'billing_email' => $order['user_email'],
        'billing_phone' => $phone,
        'billing_address' => $billingAddress,
        'billing_city' => $order['city'],
        'billing_state' => $state,
        'billing_pincode' => (string)$order['pincode'],
        'billing_country' => 'India',
        'payment_method' => ($order['payment_status'] === 'paid') ? 'Prepaid' : 'COD',
        'order_items' => $orderItems,
        'weight' => max(0.5, round($totalWeight, 2)),
        'length' => 10,
        'breadth' => 10,
        'height' => 5,
        'sub_total' => round($subTotal, 2)
    ];

    $client = shiprocketClient();

    // Step 1: Create order (KYC not required)
    $createResp = $client->createOrder($payload);
    $shipmentId = $createResp['shipment_id'] ?? null;
    if (!$shipmentId) throw new Exception('shipment_id missing');

    $awb = null;
    $courier = null;
    $awb_status = 'pending';

    // Step 2: Try assign AWB (catch KYC errors)
    try {
        $assignResp = $client->assignAwb(['shipment_id' => $shipmentId]);
        if (!empty($assignResp['data']['awb'])) {
            $awb = $assignResp['data']['awb'];
            $courier = $assignResp['data']['courier_code'] ?? null;
            $awb_status = 'assigned';
        }
    } catch (Throwable $e) {
        $awb_status = 'pending (KYC required)';
    }
$stmt = $conn->prepare("
    UPDATE orders 
    SET shipment_id = ?
    WHERE id = ?
");
$stmt->execute([$shipmentId, $orderId]);
    echo json_encode([
        'success' => true,
        'shipment_id' => $shipmentId,
        'awb' => $awb,
        'courier' => $courier,
        'awb_status' => $awb_status
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
