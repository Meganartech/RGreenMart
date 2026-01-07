<?php
session_start();
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

if (!isset($_SESSION["user_id"])) {
    $_SESSION["redirect_after_login"] = "my_orders.php";
    header("Location: login.php");
    exit();
}

$userId = $_SESSION["user_id"];

// Fetch all orders of the user
// Detect if order_items table has variant columns to avoid SQL errors
$dbName = $_ENV['DB_NAME'] ?? $conn->query('select database()')->fetchColumn();
$colsStmt = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = 'order_items'");
$colsStmt->execute([$dbName]);
$cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
$selectExtra = '';
$hasVariantWeightValue = in_array('variant_weight_value', $cols);
$hasVariantWeightUnit = in_array('variant_weight_unit', $cols);
$hasVariantPrice = in_array('variant_price', $cols);
$hasDiscount = in_array('discount_percentage', $cols) || in_array('discount', $cols);
if ($hasVariantWeightValue) $selectExtra .= 'oi.variant_weight_value,';
if ($hasVariantWeightUnit) $selectExtra .= 'oi.variant_weight_unit,';
if ($hasVariantPrice) $selectExtra .= 'oi.variant_price,';

// Build items summary subquery (product name + optional variant weight/unit + qty + unit price + optional discount)
$hasVariantId = in_array('variant_id', $cols);
if ($hasVariantId) {
    $itemsSub = "(SELECT GROUP_CONCAT(CONCAT(i.name, ' (', COALESCE(v.weight_value, ''), ' ', COALESCE(v.weight_unit, ''), ')', ' x', oi.quantity, ' @ Rs ', FORMAT(COALESCE(";
    if ($hasVariantPrice) {
        $itemsSub .= "oi.variant_price, oi.discounted_price";
    } else {
        $itemsSub .= "oi.discounted_price, oi.original_price";
    }
    $itemsSub .= "),2)";
    if ($hasDiscount) {
        $itemsSub .= ", ' (', oi.discount_percentage, '% off)'";
    }
    $itemsSub .= ") SEPARATOR ' || ') FROM order_items oi JOIN items i ON i.id = oi.item_id LEFT JOIN item_variants v ON v.id = oi.variant_id WHERE oi.order_id = o.id) AS items_summary";
} else {
    // No variant_id on order_items — attempt to find a matching variant by price using LEFT JOIN
    // Build a price expression that avoids referencing oi.variant_price when it does not exist
    if ($hasVariantPrice) {
        $priceExpr = "COALESCE(oi.variant_price, oi.discounted_price, oi.original_price)";
    } else {
        $priceExpr = "COALESCE(oi.discounted_price, oi.original_price)";
    }

    $itemsSub = "(SELECT GROUP_CONCAT(CONCAT(i.name, ' (', COALESCE(iv.weight_value, ''), ' ', COALESCE(iv.weight_unit, ''), ') x', oi.quantity, ' @ Rs ', FORMAT(" . $priceExpr . ",2)";
    if ($hasDiscount) {
        $itemsSub .= ", ' (', oi.discount_percentage, '% off)'";
    }
    $itemsSub .= ") SEPARATOR ' || ') FROM order_items oi JOIN items i ON i.id = oi.item_id LEFT JOIN item_variants iv ON iv.item_id = oi.item_id AND iv.price = " . $priceExpr . " WHERE oi.order_id = o.id) AS items_summary";
}

// Build variant weights subquery (aggregated per order)
if ($hasVariantWeightValue) {
    $variantWeightsSub = "(SELECT GROUP_CONCAT(DISTINCT CONCAT(COALESCE(oi.variant_weight_value, ''), ' ', COALESCE(oi.variant_weight_unit, '')) SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) AS variant_weights";
} else {
    $variantWeightsSub = "(SELECT GROUP_CONCAT(DISTINCT CONCAT(COALESCE(iv.weight_value, ''), ' ', COALESCE(iv.weight_unit, '')) SEPARATOR ', ') FROM order_items oi LEFT JOIN item_variants iv ON iv.item_id = oi.item_id AND iv.price = " . $priceExpr . " WHERE oi.order_id = o.id) AS variant_weights";
}

$sql = "
    SELECT 
        o.id AS order_id,
        o.order_date,
        o.overall_total,
        o.payment_status,
        o.status,
        oi.id AS order_item_id," . $selectExtra . "
        i.name AS product_name,
        (
            SELECT COALESCE(compressed_path, image_path) FROM item_images
            WHERE item_images.item_id = i.id
            ORDER BY is_primary DESC, sort_order ASC LIMIT 1
        ) AS product_image,
        " . $variantWeightsSub . ",
        " . $itemsSub . "
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN items i ON oi.item_id = i.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <!-- FontAwesome Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .orders-container {
            margin: 20px;
            min-height: 50vh;
            padding: 20px;
        }
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left: 6px solid #16a34a;
        }
        .headingh2{
            font-size: 2em;
            font-weight: 700;
            color: #14532d; /* dark green */
            margin-bottom: 15px;
        }
        .order-card img {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 15px;
        }
        .order-info {
            flex: 1;
        }
        .order-info h3 {
            margin: 0;
            font-size: 18px;
            color: #166534;
        }
        .order-info p {
            margin: 4px 0;
            color: #444;
            font-size: 14px;
        }
        .payment_badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 9999px; /* fully rounded pill */
            font-size: 13px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .delivered { background: linear-gradient(135deg, #a7f3d0, #22c55e); color: #065f46; }
        .ordered { background: linear-gradient(135deg, #a7f3d0, #22c55e); color: #065f46; }
        .pending { background: linear-gradient(135deg, #fef08a, #eab308); color: #78350f; }
        .cancelled { background: linear-gradient(135deg, #fecaca, #dc2626); color: #7f1d1d; }
        .payment_badge:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
<?php include "includes/header.php"; ?>

<div class="orders-container">
    <h2 class="headingh2">My Orders</h2>

    <?php if (empty($orders)) { ?>
        <p>You have no orders yet.</p>
    <?php } else { ?>
        <?php foreach ($orders as $row) { ?>
            <a href="order_details.php?id=<?= $row['order_id'] ?>" style="text-decoration:none;">
                <div class="order-card">
                    <img src="/admin/<?= $row['product_image'] ?>" onerror="this.src='images/default.jpg';" alt="Product Image">

                    <div class="order-info">
                        <h3><?= htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if(!empty($row['variant_weights'])): ?>
                            <p style="margin:2px 0; color:#555; font-size:13px;">Variants: <?= htmlspecialchars($row['variant_weights']) ?></p>
                        <?php elseif(!empty($row['variant_weight_value'])): ?>
                            <p style="margin:2px 0; color:#555; font-size:13px;">Variant: <?= htmlspecialchars($row['variant_weight_value']) ?> <?= htmlspecialchars($row['variant_weight_unit']) ?></p>
                        <?php endif; ?>
                        <?php if(!empty($row['items_summary'])): ?>
                            <p style="margin:2px 0; color:#555; font-size:13px;">Items: <?= htmlspecialchars(mb_strimwidth($row['items_summary'], 0, 140, '...')) ?></p>
                        <?php endif; ?>
                        <p style="margin:2px 0; color:#333; font-size:14px;">Total: ₹<?= number_format($row['overall_total'], 2) ?></p>
                        <p><b>Order ID:</b> <?= $row['order_id'] ?></p>
                        <p><b>Date:</b> <?= date("d M Y", strtotime($row['order_date'])) ?></p>
                        <p><b>Total:</b> ₹<?= number_format($row['overall_total'], 2) ?></p>

                        <span class="payment_badge <?= $row['status'] ?>">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </div>

                    <i class="fa-solid fa-chevron-right chevron"></i>
                </div>
            </a>
        <?php } ?>
    <?php } ?>
</div>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>

","newString":"$stmtItems = $conn->prepare($sqlItems);
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
$variantWeightsSummary = implode(', ', array_keys($variantWeightsArr));"},{"explanation":"Add variant summary UI under address block in order_details.php","filePath":"c:\xampp\htdocs\Ecommerce\order_details.php","oldString":"            <p><span class=\"font-semibold\">Address:</span> <?= htmlspecialchars($order['address_line1']) ?> <?= htmlspecialchars($order['address_line2']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> - <?= htmlspecialchars($order['pincode']) ?></p>","newString":"            <p><span class=\"font-semibold\">Address:</span> <?= htmlspecialchars($order['address_line1']) ?> <?= htmlspecialchars($order['address_line2']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> - <?= htmlspecialchars($order['pincode']) ?></p>
            <?php if(!empty($variantWeightsSummary)): ?>
                <p><span class=\"font-semibold\">Variants in order:</span> <?= htmlspecialchars($variantWeightsSummary) ?></p>
            <?php endif; ?>

$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <!-- FontAwesome Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .orders-container {
            margin: 20px;
            min-height: 50vh;
            padding: 20px;
        }
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left: 6px solid #16a34a;
        }
        .headingh2{
            font-size: 2em;
            font-weight: 700;
            color: #14532d; /* dark green */
            margin-bottom: 15px;
        }
        .order-card img {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 15px;
        }
        .order-info {
            flex: 1;
        }
        .order-info h3 {
            margin: 0;
            font-size: 18px;
            color: #166534;
        }
        .order-info p {
            margin: 4px 0;
            color: #444;
            font-size: 14px;
        }
        .payment_badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 9999px; /* fully rounded pill */
            font-size: 13px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .delivered { background: linear-gradient(135deg, #a7f3d0, #22c55e); color: #065f46; }
        .ordered { background: linear-gradient(135deg, #a7f3d0, #22c55e); color: #065f46; }
        .pending { background: linear-gradient(135deg, #fef08a, #eab308); color: #78350f; }
        .cancelled { background: linear-gradient(135deg, #fecaca, #dc2626); color: #7f1d1d; }
        .payment_badge:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
<?php include "includes/header.php"; ?>

<div class="orders-container">
    <h2 class="headingh2">My Orders</h2>

    <?php if (empty($orders)) { ?>
        <p>You have no orders yet.</p>
    <?php } else { ?>
        <?php foreach ($orders as $row) { ?>
            <a href="order_details.php?id=<?= $row['order_id'] ?>" style="text-decoration:none;">
                <div class="order-card">
                    <img src="/admin/<?= $row['product_image'] ?>" onerror="this.src='images/default.jpg';" alt="Product Image">

                    <div class="order-info">
                        <h3><?= htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if(!empty($row['variant_weights'])): ?>
                            <p style="margin:2px 0; color:#555; font-size:13px;">Variants: <?= htmlspecialchars($row['variant_weights']) ?></p>
                        <?php elseif(!empty($row['variant_weight_value'])): ?>
                            <p style="margin:2px 0; color:#555; font-size:13px;">Variant: <?= htmlspecialchars($row['variant_weight_value']) ?> <?= htmlspecialchars($row['variant_weight_unit']) ?></p>
                        <?php endif; ?>
                        <?php if(!empty($row['items_summary'])): ?>
                            <p style="margin:2px 0; color:#555; font-size:13px;">Items: <?= htmlspecialchars(mb_strimwidth($row['items_summary'], 0, 140, '...')) ?></p>
                        <?php endif; ?>
                        <p style="margin:2px 0; color:#333; font-size:14px;">Total: ₹<?= number_format($row['overall_total'], 2) ?></p>
                        <p><b>Order ID:</b> <?= $row['order_id'] ?></p>
                        <p><b>Date:</b> <?= date("d M Y", strtotime($row['order_date'])) ?></p>
                        <p><b>Total:</b> ₹<?= number_format($row['overall_total'], 2) ?></p>

                        <span class="payment_badge <?= $row['status'] ?>">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </div>

                    <i class="fa-solid fa-chevron-right chevron"></i>
                </div>
            </a>
        <?php } ?>
    <?php } ?>
</div>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>
