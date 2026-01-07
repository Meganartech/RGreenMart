<?php
session_start();

// Only admin can view
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

// Check order ID
if (!isset($_GET['id'])) {
    die("Order ID missing!");
}
$order_id = intval($_GET['id']);

/* ----------------------------------------------------
   FETCH ORDER + USER + ADDRESS DETAILS
-----------------------------------------------------*/

$sql = "
    SELECT o.*, 
           u.name AS user_name, u.mobile AS user_mobile, u.email AS user_email,
           a.contact_name, a.contact_mobile, a.address_line1, a.address_line2,
           a.city, a.state, a.pincode, a.landmark
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN user_addresses a ON o.address_id = a.id
    WHERE o.id = :id
";

$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found");
}

/* ----------------------------------------------------
   FETCH ORDER ITEMS
-----------------------------------------------------*/

// Fetch items with optional variant info (schema-safe)
$dbName = $_ENV['DB_NAME'] ?? $conn->query('select database()')->fetchColumn();
$colsStmt = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = 'order_items'");
$colsStmt->execute([$dbName]);
$cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
$hasVariantId = in_array('variant_id', $cols);

if ($hasVariantId) {
    $item_sql = "
        SELECT oi.*, it.name AS item_name,
               v.weight_value AS variant_weight,
               v.weight_unit AS variant_unit,
               v.price AS variant_price,
               v.old_price AS variant_old_price,
               v.discount AS variant_discount
        FROM order_items oi
        JOIN items it ON oi.item_id = it.id
        LEFT JOIN item_variants v ON oi.variant_id = v.id
        WHERE oi.order_id = :id
    ";
} else {
    $item_sql = "
        SELECT oi.*, it.name AS item_name,
               NULL AS variant_weight,
               NULL AS variant_unit,
               NULL AS variant_price,
               NULL AS variant_old_price,
               NULL AS variant_discount
        FROM order_items oi
        JOIN items it ON oi.item_id = it.id
        WHERE oi.order_id = :id
    ";
}

$stmt_items = $conn->prepare($item_sql);
$stmt_items->execute(['id' => $order_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order #<?= $order_id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="/toast.js"></script>
    <style>
        .admin-main { margin-left: 3rem; }
  
    
</style>
</head>
<body class="bg-gray-100 min-h-screen">

<div class="admin-container flex">

    <?php require_once './common/admin_sidebar.php'; ?>

    <main class="admin-main flex-1 p-6">
        <div class="container mx-auto max-w-5xl">

            <!-- Order Header -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-indigo-600">Order #<?= $order['id'] ?></h2>

                    <!-- Cancel Button (Only if not cancelled) -->
                    <?php if ($order['status'] !== 'cancelled'): ?>
                    <button onclick="openAdminCancelModal(<?= $order['id'] ?>)"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Cancel Order
                    </button>
                    <?php endif; ?>
                </div>

                <hr class="my-4">

                <!-- Order Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">

                    <p><span class="font-semibold">User Name:</span> <?= $order['user_name'] ?></p>
                    <p><span class="font-semibold">User Mobile:</span> <?= $order['user_mobile'] ?></p>
                    <p><span class="font-semibold">User Email:</span> <?= $order['user_email'] ?></p>

                    <p><span class="font-semibold">Order Date:</span> <?= $order['order_date'] ?></p>

                    <p>
                        <span class="font-semibold">Payment Status:</span>
                        <?php if ($order['payment_status'] === 'paid'): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">Paid</span>
                        <?php elseif ($order['payment_status'] === 'pending'): ?>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Pending</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm">Failed</span>
                        <?php endif; ?>
                    </p>

                    <p>
                        <span class="font-semibold">Order Status:</span>
                        <span class="px-2 py-1 rounded-full text-sm 
                            <?= $order['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : '' ?>
                            <?= $order['status'] === 'delivered' ? 'bg-green-100 text-green-800' : '' ?>
                            <?= $order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </p>

                </div>

                <!-- Address -->
                <h3 class="text-xl font-semibold mt-6 mb-2 text-gray-800">Delivery Address</h3>
                <div class="text-gray-700 leading-6">
                    <?= $order['contact_name'] ?> (<?= $order['contact_mobile'] ?>)<br>
                    <?= $order['address_line1'] ?><br>
                    <?= $order['address_line2'] ? $order['address_line2'] . '<br>' : '' ?>
                    <?= $order['city'] ?>, <?= $order['state'] ?> - <?= $order['pincode'] ?><br>
                    <?= $order['landmark'] ?>
                </div>

                <!-- Totals -->
                <h3 class="text-xl font-semibold mt-6 mb-2 text-gray-800">Bill Summary</h3>
                <p><span class="font-semibold">Subtotal:</span> ₹<?= $order['subtotal'] ?></p>
                <p><span class="font-semibold">Packing Charge:</span> ₹<?= $order['packing_charge'] ?></p>
                <p><span class="font-semibold">Net Total:</span> ₹<?= $order['net_total'] ?></p>
                <p><span class="font-semibold">Overall Total:</span> ₹<?= $order['overall_total'] ?></p>

                <?php if ($order['coupon_code']): ?>
                <p><span class="font-semibold">Coupon:</span> <?= $order['coupon_code'] ?> (-₹<?= $order['coupon_discount_amount'] ?>)</p>
                <?php endif; ?>
<?php
$pdfPath = $_SERVER["DOCUMENT_ROOT"] . "/bills/estimate_" . $order['enquiry_no'] . ".pdf";
$pdfExists = file_exists($pdfPath);
?>

<h3 class="text-xl font-semibold mt-6 mb-2 text-gray-800">Estimate PDF</h3>

<?php if ($pdfExists): ?>
    <a href="/bills/estimate_<?= $order['enquiry_no'] ?>.pdf" target="_blank"
        class="px-3 py-1 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition inline-block mr-2">
        Open
    </a>

    <a href="/bills/estimate_<?= $order['enquiry_no'] ?>.pdf" download
        class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 transition inline-block">
        Download
    </a>
<?php else: ?>
    <span class="px-3 py-1 text-gray-500">No PDF Available</span>
<?php endif; ?>

                <!-- Razorpay -->
                <h3 class="text-xl font-semibold mt-6 mb-2 text-gray-800">Payment Details</h3>
            <!-- Toast Box -->
<div id="copyToast">Copied to clipboard!</div>

<p>
    <b>Order ID: </b> 
    <span id="orderId"><?= $order['razorpay_order_id'] ?></span>
    <i class="fa-regular fa-copy copy-icon" onclick="copyText('orderId', this)"></i>
</p>

<p>
    <b>Payment ID: </b> 
    <span id="paymentId"><?= $order['razorpay_payment_id'] ?></span>
    <i class="fa-regular fa-copy copy-icon" onclick="copyText('paymentId', this)"></i>
</p>

<p>
    <b>Signature: </b> 
    <span id="signature"><?= $order['razorpay_signature'] ?></span>
    <i class="fa-regular fa-copy copy-icon" onclick="copyText('signature', this)"></i>
</p>

            </div>

            <!-- Order Items -->
            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">#</th>
                            <th class="px-6 py-3">Product</th>
                            <th class="px-6 py-3">Variant</th>
                            <th class="px-6 py-3">Orig Price</th>
                            <th class="px-6 py-3">Discount %</th>
                            <th class="px-6 py-3">Final Price</th>
                            <th class="px-6 py-3">Qty</th>
                            <th class="px-6 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php $i=1; $total=0; foreach($items as $it): $total += $it['amount']; ?>
                        <tr>
                            <td class="px-6 py-4"><?= $i++ ?></td>
                            <td class="px-6 py-4">
                                <?= $it['item_name'] ?>
                                <?php if (!empty($it['variant_weight'])): ?><div class="text-sm text-gray-600">Variant: <?= htmlspecialchars($it['variant_weight']) ?> <?= htmlspecialchars($it['variant_unit']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-6 py-4">₹<?= number_format($it['original_price'],2) ?></td>
                            <td class="px-6 py-4"><?= $it['discount_percentage'] ?>%</td>
                            <td class="px-6 py-4">₹<?= number_format($it['variant_price'] ?? $it['discounted_price'] ?? 0,2) ?></td>
                            <td class="px-6 py-4"><?= $it['quantity'] ?></td>
                            <td class="px-6 py-4 font-semibold">₹<?= number_format($it['amount'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-100 font-semibold">
                            <td colspan="6" class="px-6 py-4 text-right">Total:</td>
                            <td class="px-6 py-4">₹<?= $total ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<!-- ADMIN CANCEL MODAL -->
<div id="adminCancelModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-96 shadow-lg">
        <h2 class="text-xl font-bold mb-4">Cancel Order</h2>

        <input type="hidden" id="adminCancelOrderId">

        <label class="block font-semibold mb-1">Reason:</label>
        <textarea id="adminCancelReason" class="w-full p-2 border rounded" rows="3"></textarea>

        <div class="text-right mt-4">
            <button onclick="closeAdminCancelModal()" class="px-3 py-1 bg-gray-400 text-white rounded">Close</button>
            <button onclick="confirmAdminCancelOrder()" class="px-3 py-1 bg-red-600 text-white rounded">Cancel Order</button>
        </div>
    </div>
</div>

<script>
function openAdminCancelModal(id) {
    document.getElementById("adminCancelOrderId").value = id;
    document.getElementById("adminCancelModal").classList.remove("hidden");
}
function closeAdminCancelModal() {
    document.getElementById("adminCancelModal").classList.add("hidden");
}
function confirmAdminCancelOrder() {
    let id = document.getElementById("adminCancelOrderId").value;
    let reason = document.getElementById("adminCancelReason").value.trim();

    if (reason.length < 3) {
        alert("Enter valid reason");
        return;
    }

    fetch("/api/admin/cancel_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `order_id=${id}&reason=${encodeURIComponent(reason)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert("Order cancelled!");
            location.reload();
        } else {
            alert(data.message || "Error");
        }
    })
    .catch(() => alert("Something went wrong"));

    closeAdminCancelModal();
}
    function copyText(elementId, icon) {
        const text = document.getElementById(elementId).innerText;

        navigator.clipboard.writeText(text).then(() => {

        showToast("text copied to clipboard", { background: "#20aeaeff", color: "#fff" });
        });
    }

    
</script>

</body>
</html>
