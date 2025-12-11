<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

$sql = "
SELECT 
    o.id,
    o.enquiry_no,
    o.overall_total,
    o.payment_status,
    o.order_date,
    o.status,
    o.cancelled_by,
    o.cancelled_at,
    o.cancellation_reason,

    u.name AS user_name,
    u.mobile AS user_mobile,

    ua.contact_name,
    ua.contact_mobile

FROM orders o
LEFT JOIN users u ON o.user_id = u.id
LEFT JOIN user_addresses ua ON o.address_id = ua.id
ORDER BY o.id DESC
";

$stmt = $conn->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$billsDir = realpath(__DIR__ . '/../bills');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Orders List</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Poppins', sans-serif;
    }

    .admin-main {
        margin-left: 3rem;
    }
    </style>
</head>

<body class="bg-gray-100">
    <div class="admin-container flex">
        <?php require_once './common/admin_sidebar.php'; ?>
        <main class="admin-main flex-1 p-6">
            <div
                class="container mx-auto max-w-6xl p-6 bg-white rounded-lg shadow-lg mt-10 min-h-[80vh] overflow-y-auto">
                <h2 class="text-2xl font-bold text-indigo-600 mb-6">Orders</h2>

                <div class="mb-4">
                    <input type="text" id="searchInput" placeholder="Search by order ID or enquiry number..."
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="overflow-x-auto bg-white shadow-md rounded-lg">
                    <table class="w-full border-collapse bg-white rounded-lg shadow-sm" id="ordersTable">
                        <thead>
                            <tr class="bg-indigo-500 text-white">
                                <th class="p-3 text-left">Order ID</th>
                                <th class="p-3 text-left">Name</th>
                                <th class="p-3 text-left">Mobile</th>
                                <th class="p-3 text-left">Overall Total (₹)</th>
                                <th class="p-3 text-left">Order Status</th>
                                <th class="p-3 text-left">Payment Status</th>
                                <th class="p-3 text-left">Order Date</th>
                                <th class="p-3 text-left">Enquiry No</th>
                                <th class="p-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                            $enquiryNo = $order['enquiry_no'];
                            $pdfFile = $billsDir . "/estimate_{$enquiryNo}.pdf";
                            $pdfExists = file_exists($pdfFile);
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-3 border-b"><?= $order['id'] ?></td>
                                <td class="p-3 border-b"><?= htmlspecialchars($order['contact_name']) ?> </td>
                                <td class="p-3 border-b"><?= htmlspecialchars($order['contact_mobile']) ?></td>
                                <td class="p-3 border-b font-medium">₹<?= number_format($order['overall_total'],2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($order['payment_status'] === 'paid'): ?>
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                                    <?php elseif ($order['payment_status'] === 'pending'): ?>
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                    <?php else: ?>
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
    <?php if ($order['status'] === 'pending'): ?>
        <span class="px-2 inline-flex text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">Pending</span>

    <?php elseif ($order['status'] === 'ordered'): ?>
        <span class="px-2 inline-flex text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">ordered</span>

    <?php elseif ($order['status'] === 'shipped'): ?>
        <span class="px-2 inline-flex text-xs font-semibold bg-indigo-100 text-indigo-800 rounded-full">Shipped</span>

    <?php elseif ($order['status'] === 'delivered'): ?>
        <span class="px-2 inline-flex text-xs font-semibold bg-green-100 text-green-800 rounded-full">Delivered</span>

    <?php elseif ($order['status'] === 'cancelled'): ?>
        <span class="px-2 inline-flex text-xs font-semibold bg-red-100 text-red-800 rounded-full">Cancelled</span>

    <?php else: ?>
        <span class="px-2 inline-flex text-xs font-semibold bg-gray-100 text-gray-800 rounded-full">Unknown</span>
    <?php endif; ?>
</td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $order['order_date'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $enquiryNo ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="view_order.php?id=<?= $order['id'] ?>"
                                        class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">View</a>

                                    <?php if ($order['status'] !== 'cancelled'): ?>
                                    <button onclick="openAdminCancelModal(<?= $order['id'] ?>)"
                                        class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                        Cancel
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($pdfExists): ?>
                                    <a href="../bills/estimate_<?= $enquiryNo ?>.pdf" target="_blank"
                                        class="px-3 py-1 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">Open</a>
                                    <a href="../bills/estimate_<?= $enquiryNo ?>.pdf" download
                                        class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Download</a>
                                    <?php else: ?>
                                    <span class="px-3 py-1 text-gray-400">No PDF</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
<!-- Admin Cancel Modal -->
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
        function openAdminCancelModal(orderId) {
    document.getElementById("adminCancelOrderId").value = orderId;
    document.getElementById("adminCancelModal").classList.remove("hidden");
}

function closeAdminCancelModal() {
    document.getElementById("adminCancelModal").classList.add("hidden");
}
function confirmAdminCancelOrder() {
    let orderId = document.getElementById("adminCancelOrderId").value;
    let reason = document.getElementById("adminCancelReason").value.trim();

    if (reason.length < 3) {
        alert("Please enter a valid cancellation reason.");
        return;
    }
fetch("/api/admin/cancel_order.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `order_id=${orderId}&reason=${encodeURIComponent(reason)}`
})
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("Order cancelled successfully!");
            location.reload();
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Something went wrong!");
    });

    closeAdminCancelModal();
}

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#ordersTable tbody tr');
            rows.forEach(row => {
                const orderId = row.cells[0].textContent.toLowerCase();
                const enquiryNo = row.cells[6].textContent.toLowerCase();
                row.style.display = orderId.includes(filter) || enquiryNo.includes(filter) ?
                    '' : 'none';
            });
        });
    });
    </script>

</body>

</html>