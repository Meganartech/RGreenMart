<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        $id = htmlspecialchars($_POST['id']);
        $name = htmlspecialchars($_POST['name']);
        $price = htmlspecialchars($_POST['price']);
        $discount = htmlspecialchars($_POST['discount']);
        $category = htmlspecialchars($_POST['category']);
        $pieces = htmlspecialchars($_POST['pieces']);
        $brand = htmlspecialchars($_POST['brand']);

        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Base upload directory
            $baseDir = 'Uploads/';
            // Sanitize brand name for folder
            $brandDir = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $brand);
            $uploadDir = $baseDir . $brandDir . '/';

            // Create brand folder if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Unique file name
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $imagePath = $uploadDir . $imageName;

            // Move file
            move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
        } else {
            // Keep existing image if no new one uploaded
            $stmt = $conn->prepare("SELECT image FROM items WHERE id = ?");
            $stmt->execute([$id]);
            $imagePath = $stmt->fetchColumn();
        }

        // Update query
        $stmt = $conn->prepare("UPDATE items SET name = ?, price = ?, discount = ?, category = ?, pieces = ?, image = ?, brand = ? WHERE id = ?");
        $stmt->execute([$name, $price, $discount, $category, $pieces, $imagePath, $brand, $id]);
    } elseif (isset($_POST['delete'])) {
        $id = htmlspecialchars($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// Fetch all items
$crackers = $conn->query("SELECT * FROM items")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Crackers</title>
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
        .admin-main {
            margin-left: 16rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="admin-container flex">
        <?php require_once './common/admin_sidebar.php'; ?>
        <main class="admin-main flex-1 p-6">
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
                                <th class="p-3 text-left">Pieces</th>
                                <th class="p-3 text-left">Brand</th>                                
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
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($cracker['pieces']); ?></td>
                                    <td class="p-3 border-b"><?php echo htmlspecialchars($cracker['brand']); ?></td>
                                    <td class="p-3 border-b">
                                        <?php 
                                            if (!empty($cracker['image']) && file_exists($cracker['image'])) {
                                        ?>
                                            <img src="<?php echo htmlspecialchars($cracker['image']); ?>" alt="Image" class="w-12 h-12 object-cover rounded">
                                        <?php } else { ?>
                                            <span class="text-gray-400">No Image</span>
                                        <?php } ?>
                                    </td>
                                    <td class="p-3 border-b">
                                        <button type="button" class="bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 transition-colors" data-bs-toggle="modal" data-bs-target="#updateModal"
                                            data-id="<?php echo $cracker['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($cracker['name']); ?>"
                                            data-price="<?php echo htmlspecialchars($cracker['price']); ?>"
                                            data-discount="<?php echo htmlspecialchars($cracker['discount']); ?>"
                                            data-category="<?php echo htmlspecialchars($cracker['category']); ?>"
                                            data-pieces="<?php echo htmlspecialchars($cracker['pieces']); ?>"
                                            data-brand="<?php echo htmlspecialchars($cracker['brand']); ?>"
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
                                        <label for="modal_pieces" class="block text-sm font-medium text-gray-700">Pieces</label>
                                        <input type="text" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" name="pieces" id="modal_pieces" >
                                    </div>
                                    <div>
                                        <label for="modal_brand" class="block text-sm font-medium text-gray-700">Brand</label>
                                        <input type="text" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" name="brand" id="modal_brand" required>
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
                <!-- End Update Modal -->

            </div>
        </main>
    </div>

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
                const pieces = button.getAttribute('data-pieces');
                const brand = button.getAttribute('data-brand');
                const image = button.getAttribute('data-image');

                document.getElementById('modal_id').value = id;
                document.getElementById('modal_name').value = name;
                document.getElementById('modal_price').value = price;
                document.getElementById('modal_discount').value = discount;
                document.getElementById('modal_category').value = category;
                document.getElementById('modal_pieces').value = pieces;
                document.getElementById('modal_brand').value = brand;
                document.getElementById('current_image').textContent = image || 'No image';
            });
        });
    </script>
</body>
</html>
