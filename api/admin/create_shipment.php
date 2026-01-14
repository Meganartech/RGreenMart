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
               u.email AS user_email,
               u.mobile AS user_mobile
        FROM orders o
        LEFT JOIN user_addresses ua ON o.address_id = ua.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');

    // Validate phone - try order address, then user's mobile; normalize different formats
    $normalize_phone = function($p) {
        $p = preg_replace('/\D+/', '', (string)$p);
        if (!$p) return null;
        if (strlen($p) > 10) $p = substr($p, -10);
        return preg_match('/^[6-9][0-9]{9}$/', $p) ? $p : null;
    };

    $phoneSources = [
        $order['contact_mobile'] ?? '',
        $order['user_mobile'] ?? ''
    ];

    $phone = null;
    foreach ($phoneSources as $src) {
        $candidate = $normalize_phone($src);
        if ($candidate) { $phone = $candidate; break; }
    }

    if (!$phone) {
        throw new Exception('Invalid phone number (tried: ' . json_encode(array_values(array_filter($phoneSources))) . ')');
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
        'payment_method' => (isset($order['payment_method']) && strtolower($order['payment_method']) === 'cod') ? 'COD' : 'Prepaid',
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
    // Use courier info from the orders table (courier_company_id, courier_name) if present
    $orderCourierId = !empty($order['courier_company_id']) ? trim($order['courier_company_id']) : null;
    $orderCourierName = !empty($order['courier_name']) ? trim($order['courier_name']) : null;
    $autoAssign = isset($_POST['auto_assign']) ? intval($_POST['auto_assign']) : 1;

    if ($autoAssign) {
        try {
            $assignPayload = ['shipment_id' => $shipmentId];
            if ($orderCourierId) { $assignPayload['courier_id'] = $orderCourierId; $assignPayload['courier_company_id'] = $orderCourierId; }
            $assignResp = $client->assignAwb($assignPayload);
            if (!empty($assignResp['data']['awb'])) {
                $awb = $assignResp['data']['awb'];
                $courier = $assignResp['data']['courier_code'] ?? $assignResp['data']['courier_name'] ?? $orderCourierName;
                $awb_status = 'assigned';
            } elseif (!empty($assignResp['awb'])) {
                $awb = $assignResp['awb'];
                $courier = $assignResp['courier_code'] ?? $orderCourierName;
                $awb_status = 'assigned';
            } elseif (!empty($assignResp['data'][0]['awb'])) {
                $awb = $assignResp['data'][0]['awb'];
                $courier = $assignResp['data'][0]['courier_code'] ?? $orderCourierName;
                $awb_status = 'assigned';
            } else {
                $awb_status = 'pending';
            }
        } catch (Throwable $e) {
            $awb_status = 'pending (assign error: ' . $e->getMessage() . ')';
        }
    } else {
        $awb_status = 'not_assigned_manual';
    }
// Persist shipment reference on orders table (backwards compatible)
// Try to fetch label (if AWB assigned) and persist AWB/courier details back into orders table
$label_url = null;
$label_path = null;
if (!empty($awb)) {
    try {
        $labelResp = $client->getLabel(['shipment_id' => $shipmentId, 'awb' => $awb]);
        // common response shapes
        if (!empty($labelResp['label_url'])) $label_url = $labelResp['label_url'];
        elseif (!empty($labelResp['data'][0]['label_url'])) $label_url = $labelResp['data'][0]['label_url'];
        elseif (!empty($labelResp['data'][0]['label_download_url'])) $label_url = $labelResp['data'][0]['label_download_url'];
        elseif (!empty($labelResp['data']['label_download_url'])) $label_url = $labelResp['data']['label_download_url'];
        $label_raw = $labelResp;
    } catch (Throwable $e) {
        // ignore label fetch errors
        $label_raw = ['error' => $e->getMessage()];
    }
}

try {
    $conn->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS awb VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS courier_code VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS label_url VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS awb_status VARCHAR(100) DEFAULT NULL");
} catch (Exception $e) {
    // ignore permissions errors
}

$updOrder = $conn->prepare('UPDATE orders SET shipment_id = ?, awb = ?, courier_code = ?, courier_name = ?, courier_company_id = ?, awb_status = ?, label_url = ? WHERE id = ?');
$updOrder->execute([$shipmentId, $awb, $courier, $orderCourierName, $orderCourierId, $awb_status, $label_url, $orderId]);

// Ensure shipments table exists columns (safe add)
try {
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS order_id INT DEFAULT NULL");
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS shipment_id VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS awb VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS courier_code VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS label_url VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS label_path VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS status VARCHAR(100) DEFAULT NULL");
    $conn->exec("ALTER TABLE shipments ADD COLUMN IF NOT EXISTS raw_response LONGTEXT DEFAULT NULL");
} catch (Exception $e) {
    // ignore perms
}

// Insert or update shipments record for this order
$stmt2 = $conn->prepare('SELECT id FROM shipments WHERE order_id = ? LIMIT 1');
$stmt2->execute([$orderId]);
$existing = $stmt2->fetch(PDO::FETCH_ASSOC);
$raw = json_encode(['create' => $createResp ?? null, 'assign' => $assignResp ?? null, 'label' => $labelResp ?? null]);

if ($existing) {
    $upd = $conn->prepare('UPDATE shipments SET shipment_id = ?, awb = ?, courier_code = ?, status = ?, label_url = ?, label_path = ?, raw_response = ? WHERE order_id = ?');
    $upd->execute([$shipmentId, $awb, $courier, $awb_status, $label_url, $label_path, $raw, $orderId]);
} else {
    $ins = $conn->prepare('INSERT INTO shipments (order_id, shipment_id, awb, courier_code, status, label_url, label_path, raw_response) VALUES (?,?,?,?,?,?,?,?)');
    $ins->execute([$orderId, $shipmentId, $awb, $courier, $awb_status, $label_url, $label_path, $raw]);
}

echo json_encode([
    'success' => true,
    'shipment_id' => $shipmentId,
    'awb' => $awb,
    'courier' => $courier,
    'awb_status' => $awb_status,
    'label_url' => $label_url,
    'label_path' => $label_path
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
