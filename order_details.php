<?php
session_start();
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    $_SESSION["redirect_after_login"] = "my_orders.php";
    header("Location: login.php");
    exit();
}

$userId = $_SESSION["user_id"];

// Get order ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid order ID");
}
$orderId = (int)$_GET['id'];

// Fetch order details along with user address
$sqlOrder = "
    SELECT o.*, ua.contact_name, ua.contact_mobile, ua.address_line1, ua.address_line2, ua.city, ua.state, ua.pincode 
    FROM orders o
    LEFT JOIN user_addresses ua ON o.address_id = ua.id
    WHERE o.id = ? AND o.user_id = ?
";
$stmtOrder = $conn->prepare($sqlOrder);
$stmtOrder->execute([$orderId, $userId]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found");
}

// Fetch order items with product and variant info (schema-safe)
$dbName = $_ENV['DB_NAME'] ?? $conn->query('select database()')->fetchColumn();
$colsStmt = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = 'order_items'");
$colsStmt->execute([$dbName]);
$cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
$hasVariantId = in_array('variant_id', $cols);

if ($hasVariantId) {
    $sqlItems = "
        SELECT 
            oi.*, 
            i.name AS product_name,
            v.weight_value AS variant_weight,
            v.weight_unit AS variant_unit,
            v.price AS variant_price,
            v.old_price AS variant_old_price,
            v.discount AS variant_discount,
            (
                SELECT COALESCE(compressed_path, image_path) FROM item_images
                WHERE item_images.item_id = i.id
                ORDER BY is_primary DESC, sort_order ASC LIMIT 1
            ) AS product_image
        FROM order_items oi
        LEFT JOIN items i ON oi.item_id = i.id
        LEFT JOIN item_variants v ON oi.variant_id = v.id
        WHERE oi.order_id = ?
    ";
} else {
    // No variant_id on order_items — try to infer variant by matching price
    $hasVariantPrice = in_array('variant_price', $cols);
    if ($hasVariantPrice) {
        $priceExpr = "COALESCE(oi.variant_price, oi.discounted_price, oi.original_price)";
    } else {
        $priceExpr = "COALESCE(oi.discounted_price, oi.original_price)";
    }

    $sqlItems = "
        SELECT 
            oi.*, 
            i.name AS product_name,
            (
                SELECT weight_value FROM item_variants 
                WHERE item_variants.item_id = oi.item_id 
                AND item_variants.price = " . $priceExpr . "
                LIMIT 1
            ) AS variant_weight,
            (
                SELECT weight_unit FROM item_variants 
                WHERE item_variants.item_id = oi.item_id 
                AND item_variants.price = " . $priceExpr . "
                LIMIT 1
            ) AS variant_unit,
            (
                SELECT price FROM item_variants 
                WHERE item_variants.item_id = oi.item_id 
                AND item_variants.price = " . $priceExpr . "
                LIMIT 1
            ) AS variant_price,
            NULL AS variant_old_price,
            NULL AS variant_discount,
            (
                SELECT COALESCE(compressed_path, image_path) FROM item_images
                WHERE item_images.item_id = i.id
                ORDER BY is_primary DESC, sort_order ASC LIMIT 1
            ) AS product_image
        FROM order_items oi
        LEFT JOIN items i ON oi.item_id = i.id
        WHERE oi.order_id = ?
    ";
}

$stmtItems = $conn->prepare($sqlItems);
$stmtItems->execute([$orderId]);
$orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Aggregate distinct variant weights present in this order for a brief summary
$variantWeightsArr = [];
foreach ($orderItems as $it) {
    $w = trim((string)($it['variant_weight'] ?? $it['variant_weight_value'] ?? ''));
    $u = trim((string)($it['variant_unit'] ?? $it['variant_weight_unit'] ?? ''));
    if ($w !== '') {
        $entry = trim($w . ' ' . $u);
        if (!empty($entry)) $variantWeightsArr[$entry] = true;
    }
}
$variantWeightsSummary = implode(', ', array_keys($variantWeightsArr));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order #<?= $order['id'] ?> Details</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="/toast.js"></script>
<style>
.payment_badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    transition: transform 0.2s, box-shadow 0.2s;
}
.paid { background: linear-gradient(135deg, #a7f3d0, #49b771ff); color: #065f46; }
.pending { background: linear-gradient(135deg, #fef08a, #eab308); color: #78350f; }
.failed { background: linear-gradient(135deg, #fecaca, #dc2626); color: #7f1d1d; }
.payment_badge:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    /* Shipment badge styles */
    .shipment_badge { display:inline-block; padding:6px 12px; border-radius:9999px; font-size:13px; font-weight:600; color:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
    .shipment_badge.not_shipped { background: linear-gradient(135deg,#e5e7eb,#9ca3af); color:#1f2937; }
    .shipment_badge.shipped { background: linear-gradient(135deg,#bfdbfe,#3b82f6); }
    .shipment_badge.in_transit { background: linear-gradient(135deg,#fde68a,#f59e0b); color:#78350f; }
    .shipment_badge.delivered { background: linear-gradient(135deg,#a7f3d0,#22c55e); color:#065f46; }
    .shipment_badge.cancelled { background: linear-gradient(135deg,#fecaca,#dc2626); color:#7f1d1d; }
    .shipment_badge.error { background: linear-gradient(135deg,#fecaca,#f97316); color:#7f1d1d; }</style>
</head>
<body>
<?php include "includes/header.php"; ?>

<div class="bg-white p-8 m-5 rounded-2xl shadow-xl">
    <h1 class="text-3xl font-bold mb-6 text-green-800">Order #<?= $order['id'] ?></h1>

    <!-- Order Info -->
    <?php
    // Fetch shipment info if available
    $stmtS = $conn->prepare('SELECT * FROM shipments WHERE order_id = ? LIMIT 1');
    $stmtS->execute([$orderId]);
    $shipment = $stmtS->fetch(PDO::FETCH_ASSOC);

    // Best-effort: fetch live shipment details / tracking from Shiprocket for a richer UI
    $liveShipment = null; // GET /shipments/{id}
    $liveTracking = null; // track by AWB
    $displayStatus = $shipment['status'] ?? null;
    $latestEvent = null;
    if ($shipment) {
        try {
            require_once __DIR__ . '/api/shiprocket.php';
            $client = shiprocketClient();

            if (!empty($shipment['shipment_id'])) {
                try {
                    $resp = $client->request('GET', '/shipments/' . urlencode($shipment['shipment_id']));
                    $liveShipment = $resp;
                    if (is_array($resp)) {
                        if (!empty($resp['status'])) $displayStatus = $resp['status'];
                        elseif (!empty($resp['data']['status'])) $displayStatus = $resp['data']['status'];
                        elseif (!empty($resp['shipment']['status'])) $displayStatus = $resp['shipment']['status'];
                    }
                } catch (Throwable $e) {
                    $liveShipment = ['error' => $e->getMessage()];
                }
            }

            if (!empty($shipment['awb'])) {
                try {
                    $tr = $client->trackAwb($shipment['awb']);
                    $liveTracking = $tr;
                    // extract timeline / tracking events if available
                    $events = [];
                    $data = $tr['data'] ?? $tr;
                    if (isset($data['trackings']) && is_array($data['trackings'])) {
                        foreach ($data['trackings'] as $t) {
                            if (isset($t['tracking_data']) && is_array($t['tracking_data'])) $events = array_merge($events, $t['tracking_data']);
                            if (isset($t['tracking_details']) && is_array($t['tracking_details'])) $events = array_merge($events, $t['tracking_details']);
                            if (isset($t['timeline']) && is_array($t['timeline'])) $events = array_merge($events, $t['timeline']);
                        }
                    } elseif (isset($data['tracking_data']) && is_array($data['tracking_data'])) {
                        $events = $data['tracking_data'];
                    } elseif (isset($data['data']) && is_array($data['data'])) {
                        $events = $data['data'];
                    }

                    if (!empty($events)) {
                        usort($events, function($a,$b){
                            $ta = strtotime($a['date'] ?? $a['time'] ?? $a['datetime'] ?? $a['created_at'] ?? 0);
                            $tb = strtotime($b['date'] ?? $b['time'] ?? $b['datetime'] ?? $b['created_at'] ?? 0);
                            return $tb - $ta;
                        });
                        $latestEvent = $events[0];
                        $displayStatus = $latestEvent['status'] ?? $latestEvent['title'] ?? $displayStatus;
                    }
                } catch (Throwable $e) {
                    $liveTracking = ['error' => $e->getMessage()];
                }
            }
        } catch (Throwable $e) {
            // ignore failures and continue with stored info
        }
    }

    // Classify badge color
    $badgeClass = 'shipped';
    if (!empty($displayStatus)) {
        $k = strtolower($displayStatus);
        if (strpos($k,'deliv') !== false || strpos($k,'delivered') !== false) $badgeClass = 'delivered';
        elseif (strpos($k,'out for') !== false || strpos($k,'outfor') !== false || strpos($k,'out') !== false) $badgeClass = 'in_transit';
        elseif (strpos($k,'transit') !== false) $badgeClass = 'in_transit';
        elseif (strpos($k,'cancel') !== false) $badgeClass = 'cancelled';
        elseif (strpos($k,'error') !== false || (!empty($liveShipment['error']) || !empty($liveTracking['error']))) $badgeClass = 'error';
        else $badgeClass = 'shipped';
    }
    $displayStatus = $displayStatus ?? 'Unknown';
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="space-y-2">
            <p><span class="font-semibold">Order Date:</span> <?= date("d M Y, h:i A", strtotime($order['order_date'])) ?></p>
            <p><span class="font-semibold">Name:</span> <?= htmlspecialchars($order['contact_name']) ?></p>
            <p><span class="font-semibold">Mobile:</span> <?= htmlspecialchars($order['contact_mobile']) ?></p>
            <p><span class="font-semibold">Address:</span> <?= htmlspecialchars($order['address_line1']) ?> <?= htmlspecialchars($order['address_line2']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> - <?= htmlspecialchars($order['pincode']) ?></p>
            <?php if(!empty($variantWeightsSummary)): ?>
                <p><span class="font-semibold">Weights in order:</span> <?= htmlspecialchars($variantWeightsSummary) ?></p>
            <?php endif; ?>
        </div>
        <div class="space-y-2">
            <p><span class="font-semibold">Payment Status:</span> 
                <span class="payment_badge <?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span>
            </p>
             
            <?php if($order['payment_status'] === 'paid'): ?>
            <p class="flex items-center space-x-2">
                <span class="font-semibold">Payment ID:</span> 
                <code id="paymentId" class="bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($order['razorpay_payment_id']) ?></code>
                <i class="fa-regular fa-copy cursor-pointer text-gray-600 hover:text-gray-800" title="Copy Payment ID" onclick="copyPaymentId()"></i>
            </p>
            <?php endif; ?>
              <p><span class="font-semibold">Order Status:</span> 
                <span ><?= ucfirst($order['status']) ?></span>
            </p>

            <?php if ($shipment): ?>
                <div class="mt-4 p-3 bg-gray-50 rounded">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">Shipment</h3>
                        <span class="shipment_badge <?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars(ucfirst($displayStatus)) ?></span>
                    </div>

                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                        <p><strong>AWB:</strong> <?= htmlspecialchars($shipment['awb'] ?? '-') ?></p>
                        <p><strong>Courier:</strong> <?= htmlspecialchars($shipment['courier_code'] ?? '-') ?></p>
                    </div>

                    <?php if (!empty($latestEvent)): ?>
                        <div class="mt-3 p-3 bg-white rounded border">
                            <div class="text-sm text-gray-700"><strong>Latest:</strong> <?= htmlspecialchars($latestEvent['description'] ?? $latestEvent['status'] ?? $latestEvent['title'] ?? '') ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($latestEvent['date'] ?? $latestEvent['time'] ?? $latestEvent['datetime'] ?? '') ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($shipment['label_url']): ?>
                        <p class="mt-2"><a href="<?= htmlspecialchars($shipment['label_url']) ?>" target="_blank" class="text-indigo-600">Open Label</a></p>
                    <?php endif; ?>

                    <p class="mt-2"><a href="/track_shipment.php?order_id=<?= $order['id'] ?>" class="text-indigo-600">View full tracking timeline</a></p>
                </div>
            <?php else: ?>
                <div class="mt-4 p-3 bg-yellow-50 rounded">Not shipped yet</div>
            <?php endif; ?>
            
  <?php if ($order['status'] === 'cancelled'): ?>
<div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg space-y-2">

    <p class="text-red-700">
        <span class="font-semibold">Cancelled At:</span>
        <?= date("d M Y, h:i A", strtotime($order['cancelled_at'])) ?>
    </p>

    <p class="text-red-700">
        <span class="font-semibold">Cancellation Reason:</span>
        <?= htmlspecialchars($order['cancellation_reason']) ?>
    </p>

    <p class="text-red-700">
        <span class="font-semibold">Cancelled By:</span>
        <?= htmlspecialchars($order['cancelled_by']) ?>
    </p>

    <p class="text-red-700">
        <span class="font-semibold">Refund Status:</span>
        <?= htmlspecialchars($order['refund_status']) ?>
    </p>

    <?php if (!empty($order['refund_payment_id'])): ?>
        <div class="text-red-700 flex items-center gap-2">
            <span class="font-semibold">Refund Payment ID:</span>
            <span id="refundId"><?= htmlspecialchars($order['refund_payment_id']) ?></span>

            <!-- Copy Button -->
            <button 
                onclick="copyRefundId()" 
                class="px-2 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition">
                Copy
            </button>
        </div>
    <?php endif; ?>

</div>
<?php endif; ?>

        </div>
    </div>

    <!-- Items -->
    <h2 class="text-2xl font-semibold text-green-700 mb-4">Items</h2>
    <div class="space-y-4">
        <?php foreach ($orderItems as $item): ?>
        <div class="flex flex-col md:flex-row items-center md:items-start border p-4 rounded-xl shadow-sm hover:shadow-md transition">
            <?php
                $img = $item['product_image'] ?? null;
                $imgSrc = $img ? 'admin/' . ltrim($img, '/') : 'images/default.jpg';
            ?>
            <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES) ?>" 
                 alt="<?= htmlspecialchars($item['product_name']) ?>" 
                 class="w-24 h-24 md:w-28 md:h-28 object-cover rounded-lg mb-3 md:mb-0 md:mr-6">
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-green-800"><?= htmlspecialchars($item['product_name']) ?></h3>
                <p class="text-gray-600">
                    <?php if(!empty($item['variant_weight'])): ?>
                        <strong>Weight:</strong> <?= htmlspecialchars($item['variant_weight']) ?> <?= htmlspecialchars($item['variant_unit']) ?>
                        &nbsp;|&nbsp;
                    <?php endif; ?>
                    <strong>Unit Price:</strong> ₹<?= number_format($item['variant_price'] ?? $item['discounted_price'] ?? 0,2) ?>
                    <?php if(!empty($item['original_price']) && $item['original_price'] > ($item['variant_price'] ?? $item['discounted_price'] ?? 0)): ?>
                        &nbsp; <span style="text-decoration:line-through; color:#888;">₹<?= number_format($item['original_price'],2) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($item['discount_percentage'])): ?>
                        &nbsp;<span style="color:#d97706;">(<?= $item['discount_percentage'] ?>% OFF)</span>
                    <?php endif; ?>
                </p>
                <p class="text-gray-600">
                    <strong>Quantity:</strong> <?= $item['quantity'] ?> &nbsp;|&nbsp; <strong>Amount:</strong> ₹<?= number_format($item['amount'],2) ?>
                </p>
            </div>
        </div>
        <?php endforeach; ?>
        
    </div>

    <!-- Totals -->
    <div class="mt-8 border-t pt-6 flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="space-y-2">
            <p><span class="font-semibold">Subtotal:</span> ₹<?= number_format($order['subtotal'],2) ?></p>
            <p><span class="font-semibold">Shipping Charge:</span> ₹<?= number_format($order['shipping_charge'],2) ?></p>
            <p class="text-xl font-bold"><span class="font-semibold">Overall Total:</span> ₹<?= number_format($order['overall_total'],2) ?></p>
            <?php if(!empty($order['coupon_code'])): ?>
            <p><span class="font-semibold">Coupon:</span> <?= htmlspecialchars($order['coupon_code']) ?> (-₹<?= number_format($order['coupon_discount_amount'],2) ?>)</p>
            <?php endif; ?>
        </div>
        <div>
             <?php if($order['payment_status'] === 'paid'): ?>
        <a href="download_bill.php?order_id=<?= $order['id'] ?>" 
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"
           target="_blank">Download Invoice</a>
          <?php endif; ?> 
<?php if ($order['payment_status'] === 'paid' && $order['status'] !== 'cancelled'): ?>
    <button onclick="openCancelModal()" 
        class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
        Cancel Order
    </button>
<?php endif; ?>
  
   </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script>
function copyPaymentId() {
    const paymentId = document.getElementById('paymentId').innerText;
    navigator.clipboard.writeText(paymentId).then(() => {
        showToast('Payment ID copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
function openCancelModal() {
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}

function confirmCancelOrder() {
    let orderId = <?= $order['id'] ?>;
    let reason = document.getElementById("cancelReason").value.trim();

    if (reason.length < 3) {
        showToast("Please enter a valid reason!", { background: "#e63946", color: "#fff" });
        return;
    }

    fetch("/api/user/cancel_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `order_id=${orderId}&reason=${encodeURIComponent(reason)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast("Order Cancelled Successfully!", {
                background: "#dc2626",
                color: "#fff"
            });
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast("Error: " + data.message, {
                background: "#e63946",
                color: "#fff"
            });
        }
    })
    .catch(err => {
        console.error(err);
        showToast("Something went wrong");
    });

    closeCancelModal();
}


</script>

<!-- Cancel Order Modal -->
<div id="cancelModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-xl w-80">

        <h2 class="text-xl font-bold text-red-700 mb-3">Cancel Order</h2>

        <label class="block text-left font-semibold text-gray-700 mb-1">Reason for cancellation:</label>
        <textarea id="cancelReason" 
                  class="w-full border rounded p-2 text-sm mb-4"
                  placeholder="Enter reason..."></textarea>

        <div class="flex justify-between">
            <button onclick="closeCancelModal()" 
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400 transition">
                No
            </button>

            <button onclick="confirmCancelOrder()" 
                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                Yes, Cancel
            </button>
        </div>

    </div>
</div>


</body>
</html>
