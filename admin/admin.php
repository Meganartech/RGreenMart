
<?php
session_start();
require_once __DIR__ . '../includes/env.php';
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'diwali_db';
$username = $_ENV['DB_USER']?? 'root';
$password = $password = $_ENV['DB_PASS']??"";
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $name = htmlspecialchars($_POST['name']);
        $price = htmlspecialchars($_POST['price']);
        $discount = htmlspecialchars($_POST['discount']);
        $category = htmlspecialchars($_POST['category']);
        $stock = htmlspecialchars($_POST['stock']);

        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $imagePath = $uploadDir . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
        }

        $stmt = $conn->prepare("INSERT INTO items (name, price, discount, category, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $discount, $category, $stock, $imagePath]);
    } elseif (isset($_POST['update'])) {
        $id = htmlspecialchars($_POST['id']);
        $name = htmlspecialchars($_POST['name']);
        $price = htmlspecialchars($_POST['price']);
        $discount = htmlspecialchars($_POST['discount']);
        $category = htmlspecialchars($_POST['category']);
        $stock = htmlspecialchars($_POST['stock']);

        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'Uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $imagePath = $uploadDir . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
        } else {
            $stmt = $conn->prepare("SELECT image FROM items WHERE id = ?");
            $stmt->execute([$id]);
            $imagePath = $stmt->fetchColumn();
        }

        $stmt = $conn->prepare("UPDATE items SET name = ?, price = ?, discount = ?, category = ?, stock = ?, image = ? WHERE id = ?");
        $stmt->execute([$name, $price, $discount, $category, $stock, $imagePath, $id]);
    } elseif (isset($_POST['delete'])) {
        $id = htmlspecialchars($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
    } elseif (isset($_POST['update_settings'])) {
        $gstRate = htmlspecialchars($_POST['gst_rate']);
        $discount = htmlspecialchars($_POST['discount']);
        $stmt = $conn->prepare("UPDATE settings SET gst_rate = ?, discount = ? WHERE id = 1");
        $stmt->execute([$gstRate, $discount]);
    } elseif (isset($_POST['delete_admin'])) {
        $id = htmlspecialchars($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// Fetch data from database
$crackers = $conn->query("SELECT * FROM items")->fetchAll(PDO::FETCH_ASSOC);
$settings = $conn->query("SELECT gst_rate, discount FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT id, username FROM admin_users")->fetchAll(PDO::FETCH_ASSOC);
$orders = $conn->query("SELECT * FROM orders ORDER BY ordered_date_time DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get list of PDF files
$billsDir = __DIR__ . '../bills';
$pdfFiles = glob($billsDir . '/*.pdf');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Crackers and Admins</title>
        <meta name="keywords" content="Deepavali crackers sale 2025, Buy crackers online Deepavali 2025, Diwali crackers offer 2025, Deepavali discount crackers online, Diwali crackers shop near me, Deepavali crackers combo offer 2025, Wholesale Diwali crackers online, Sivakasi crackers online shopping, Diwali crackers home delivery 2025, Best price Diwali crackers online, Cheapest Deepavali crackers online 2025, Eco-friendly Diwali crackers online 2025, Diwali crackers gift box sale 2025, Online cracker booking for Deepavali 2025, Buy Sivakasi crackers for Deepavali 2025, Buy crackers online Chennai Deepavali 2025, Diwali crackers sale Coimbatore 2025, Deepavali crackers shop Madurai 2025, Tirunelveli Deepavali crackers online, Salem Diwali crackers discount 2025, Deepavali crackers gift pack 2025, Green crackers for Diwali 2025, Cheap Diwali crackers online 2025, Buy Diwali crackers online Tamil Nadu 2025, Standard Fireworks Diwali crackers 2025, Ayyan Fireworks branded crackers online, Sony Fireworks crackers sale 2025, Sri Kaliswari branded crackers Deepavali 2025, RGreenMart crackers sale 2025, Trichy branded crackers discount Diwali 2025">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .modal-content {
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            background-color: #4f46e5;
            color: white;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        .btn-close {
            filter: invert(1);
        }
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="admin-container">
        <?php require_once 'common/admin_sidebar.php'; ?>
        <main class="admin-main">
            <div class="container mx-auto max-w-4xl p-6 bg-white rounded-lg shadow-lg mt-10">
                <h2 class="text-2xl font-bold text-indigo-600 mb-6">Manage Crackers</h2>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse bg-white rounded-lg shadow-sm">
                        <thead>
                            <tr class="bg-indigo-500 text-white">
                                <th class="p-3 text-left">ID</th>
                                <th class="p-3 text-left">Name</th>
                                <th class="p-3 text-left">Price</th>
                                <th class="p-3 text-left">Category</th>
                                <th class="p-3 text-left">Stock</th>
                                <th class="p-3 text-left">Image</th>
                                <th class="p-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($crackers as $cracker): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($cracker['id']); ?></td>
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($cracker['name']); ?></td>
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($cracker['price']); ?></td>
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($cracker['category']); ?></td>
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($cracker['stock']); ?></td>
                                    <td class="p-3 border-b">
                                        <?php if (!empty($cracker['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($cracker['image']); ?>" alt="Image" class="w-12 h-12 object-cover rounded">
                                        <?php else: ?>
                                            <span class="text-gray-400">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 border-b">
                                        <button type="button" class="bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 transition-colors" data-bs-toggle="modal" data-bs-target="#updateModal"
                                            data-id="<?php echo $cracker['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($cracker['name']); ?>"
                                            data-price="<?php echo htmlspecialchars($cracker['price']); ?>"
                                            data-discount="<?php echo htmlspecialchars($cracker['discount']); ?>"
                                            data-category="<?php echo htmlspecialchars($cracker['category']); ?>"
                                            data-stock="<?php echo htmlspecialchars($cracker['stock']); ?>"
                                            data-image="<?php echo htmlspecialchars($cracker['image'] ?? ''); ?>">
                                            Edit
                                        </button>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="id" value="<?php echo $cracker['id']; ?>">
                                            <button type="submit" name="delete" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition-colors">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="text-xl font-semibold text-indigo-600 mt-8 mb-4">Add New Cracker</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="text" name="name" placeholder="Name" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="number" name="price" placeholder="Price" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="number" name="discount" placeholder="Discount (%)" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="category" placeholder="Category" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="number" name="stock" placeholder="Stock" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="file" name="image" accept="image/*" class="w-full p-3 border rounded-lg">
                    <button type="submit" name="add" class="w-full bg-indigo-600 text-white p-3 rounded-lg hover:bg-indigo-700 transition-colors">Add</button>
                </form>

                <!-- Update Modal -->
                <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="updateModalLabel">Update Cracker</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" enctype="multipart/form-data" id="updateForm" class="space-y-4">
                                    <input type="hidden" name="id" id="modal_id">
                                    <div>
                                        <label for="modal_name" class="block text-sm font-medium text-gray-700">Name</label>
                                        <input type="text" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" name="name" id="modal_name" required>
                                    </div>
                                    <div>
                                        <label for="modal_price" class="block text-sm font-medium text-gray-700">Price</label>
                                        <input type="number" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" name="price" id="modal_price" required>
                                    </div>
                                    <div>
                                        <label for="modal_discount" class="block text-sm font-medium text-gray-700">Discount (%)</label>
                                        <input type="number" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" name="discount" id="modal_discount">
                                    </div>
                                    <div>
                                        <label for="modal_category" class="block text-sm font-medium text-gray-700">Category</label>
                                        <input type="text" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" name="category" id="modal_category" required>
                                    </div>
                                    <div>
                                        <label for="modal_stock" class="block text-sm font-medium text-gray-700">Stock</label>
                                        <input type="number" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" name="stock" id="modal_stock" required>
                                    </div>
                                    <div>
                                        <label for="modal_image" class="block text-sm font-medium text-gray-700">Image</label>
                                        <input type="file" class="w-full p-3 border rounded-lg" name="image" id="modal_image" accept="image/*">
                                        <small class="text-gray-500">Current image: <span id="current_image"></span></small>
                                    </div>
                                    <button type="submit" name="update" class="w-full bg-indigo-600 text-white p-3 rounded-lg hover:bg-indigo-700 transition-colors">Update</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-indigo-600 mt-8 mb-4">Manage Orders</h2>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse bg-white rounded-lg shadow-sm">
                        <thead>
                            <tr class="bg-indigo-500 text-white">
                                <th class="p-3 text-left">Order ID</th>
                                <th class="p-3 text-left">Customer Name</th>
                                <th class="p-3 text-left">Ordered Date & Time</th>
                                <th class="p-3 text-left">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($order['ordered_date_time']); ?></td>
                                    <td class="p-3 border-b">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2 class="text-2xl font-bold text-indigo-600 mt-8 mb-4">List of Enquiries</h2>
                <div class="mb-4">
                    <input type="text" id="searchInput" placeholder="Search by enquiry number (e.g., 1234)..." class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse bg-white rounded-lg shadow-sm" id="enquiriesTable">
                        <thead>
                            <tr class="bg-indigo-500 text-white">
                                <th class="p-3 text-left">Filename</th>
                                <th class="p-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pdfFiles as $file): ?>
                                <?php $filename = basename($file); ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($filename); ?></td>
                                    <td class="p-3 border-b">
                                        <a href="bills/<?php echo htmlspecialchars($filename); ?>" target="_blank" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition-colors">Open</a>
                                        <a href="bills/<?php echo htmlspecialchars($filename); ?>" download class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition-colors">Download</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2 class="text-2xl font-bold text-indigo-600 mt-8 mb-4">Update Settings</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="gst_rate" class="block text-sm font-medium text-gray-700">GST Rate (%):</label>
                        <input type="number" name="gst_rate" id="gst_rate" value="<?php echo htmlspecialchars($settings['gst_rate']); ?>" step="0.01" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="discount" class="block text-sm font-medium text-gray-700">Discount (%):</label>
                        <input type="number" name="discount" id="discount" value="<?php echo htmlspecialchars($settings['discount']); ?>" step="0.01" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <button type="submit" name="update_settings" class="w-full bg-indigo-600 text-white p-3 rounded-lg hover:bg-indigo-700 transition-colors">Update Settings</button>
                </form>
            </div>
        </main>
    </div>

    <?php require_once '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const updateModal = document.getElementById('updateModal');
            updateModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const price = button.getAttribute('data-price');
                const discount = button.getAttribute('data-discount');
                const category = button.getAttribute('data-category');
                const stock = button.getAttribute('data-stock');
                const image = button.getAttribute('data-image');

                document.getElementById('modal_id').value = id;
                document.getElementById('modal_name').value = name;
                document.getElementById('modal_price').value = price;
                document.getElementById('modal_discount').value = discount;
                document.getElementById('modal_category').value = category;
                document.getElementById('modal_stock').value = stock;
                document.getElementById('current_image').textContent = image || 'No image';
            });

            document.getElementById('searchInput').addEventListener('keyup', function() {
                const filter = this.value.toLowerCase();
                const rows = document.querySelectorAll('#enquiriesTable tbody tr');
                rows.forEach(row => {
                    const filename = row.querySelector('td:first-child').textContent.toLowerCase();
                    row.style.display = filename.includes(filter) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html> 