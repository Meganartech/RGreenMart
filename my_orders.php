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
$sql = "
    SELECT 
        o.id AS order_id,
        o.order_date,
        o.overall_total,
        o.payment_status,
        o.status,
        oi.id AS order_item_id,
        i.name AS product_name,
        i.image AS product_image
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
