<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// NOTE: Ensure your dbconf.php correctly sets up the $conn PDO object.
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

/* ---------------------------------------
   HANDLE POST REQUESTS (UPDATE & DELETE)
---------------------------------------- */
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $message = '';
    $message_class = '';

    try {
        // 1. Get associated image paths from item_images BEFORE deleting DB record
        $stmt = $conn->prepare("SELECT image_path, compressed_path FROM item_images WHERE item_id=?");
        $stmt->execute([$id]);
        $imgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Delete database record
        $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
        $stmt->execute([$id]);

        // 3. Delete item_images rows for this item
        $stmt = $conn->prepare("DELETE FROM item_images WHERE item_id=?");
        $stmt->execute([$id]);

        // 4. Delete image files from disk (stored under /admin/Uploads/...)
        foreach ($imgs as $imgRow) {
            foreach (['image_path', 'compressed_path'] as $p) {
                if (empty($imgRow[$p])) continue;
                $full = $_SERVER['DOCUMENT_ROOT'] . "/admin/" . ltrim($imgRow[$p], "/");
                if (file_exists($full)) {
                    @unlink($full);
                }
            }
        }

        // 5. Attempt to remove the item's upload directory if empty
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/admin/Uploads/" . $id;
        if (is_dir($uploadDir)) {
            // Recursive delete if not empty
            $it = new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) rmdir($file->getRealPath()); else unlink($file->getRealPath());
            }
            @rmdir($uploadDir);
        }

        $message = 'Item deleted successfully.';
        $message_class = 'success';
    } catch (Exception $e) {
        $message = 'Error deleting item: ' . $e->getMessage();
        $message_class = 'error';
    }

    // If AJAX request, return message
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo "<div id='server-message' class='" . ($message_class==='success'?'success':'error') . "'>$message</div>";
        exit;
    }
}




/* ---------------------------------------
   AJAX Endpoints
---------------------------------------- */

// Return item variants as JSON for modal editing
if (isset($_GET['action']) && $_GET['action'] === 'fetch_variants' && isset($_GET['id'])) {
    $itemId = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT id, weight_value, weight_unit, price, old_price, discount, stock, status FROM item_variants WHERE item_id = ? ORDER BY id ASC");
    $stmt->execute([$itemId]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* ---------------------------------------
   FETCH DATA
---------------------------------------- */
// Fetch all items with category and brand names plus variant aggregates
$items = $conn->query(" 
    SELECT items.*, categories.name AS category_name, brands.name AS brand_name,
        (
            SELECT COALESCE(compressed_path, image_path) FROM item_images
            WHERE item_images.item_id = items.id
            ORDER BY is_primary DESC, sort_order ASC LIMIT 1
        ) AS thumb_image,
        (SELECT MIN(price) FROM item_variants WHERE item_variants.item_id = items.id) AS min_price,
        (SELECT MAX(price) FROM item_variants WHERE item_variants.item_id = items.id) AS max_price,
        (SELECT COALESCE(SUM(stock),0) FROM item_variants WHERE item_variants.item_id = items.id) AS total_stock,
        (SELECT COUNT(*) FROM item_variants WHERE item_variants.item_id = items.id) AS variants_count
    FROM items
    LEFT JOIN categories ON items.category_id=categories.id
    LEFT JOIN brands ON items.brand_id=brands.id
")->fetchAll(PDO::FETCH_ASSOC);

// Add preview of up to two variants per item
foreach ($items as &$it) {
    $stmt = $conn->prepare("SELECT id, price, old_price FROM item_variants WHERE item_id = ? ORDER BY id ASC LIMIT 2");
    $stmt->execute([$it['id']]);
    $it['variant_preview'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($it);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Items</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="admin-container flex">
    <?php // Assuming './common/admin_sidebar.php' exists and is correctly structured ?>
    <?php require_once './common/admin_sidebar.php'; ?>

    <main class="p-6 flex-1">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-7xl mx-auto overflow-x-auto">
            <h2 class="text-2xl font-bold text-indigo-600 mb-6">Manage Items</h2>
<!-- Message Container -->
<div id="message-container" class="mb-4"></div>

            <table class="w-full border-collapse bg-white rounded-lg shadow-sm">
                <thead>
                    <tr class="bg-indigo-500 text-white text-sm">
                        <th class="p-2">Name</th>
                        <th class="p-2">Price Range</th>
                        <th class="p-2">Total Stock</th>
                        <th class="p-2">Variants</th>
                        <th class="p-2">Category</th>
                        <th class="p-2">Brand</th>
                        <th class="p-2">Packaging</th>
                        <th class="p-2">Form</th>
                        <th class="p-2">Origin</th>
                        <th class="p-2">Grade</th>
                        <th class="p-2">Purity</th>
                        <th class="p-2">Flavor</th>
                        <th class="p-2">Shelf Life</th>
                        <th class="p-2">Description</th>
                        <th class="p-2">Nutrition</th>
                        <th class="p-2">Expiry Info</th>
                        <th class="p-2">Tags</th>
                        <th class="p-2">Storage</th>
                        <th class="p-2">Image</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr class="hover:bg-gray-50 text-sm">
                        <td class="p-2 border"><?= htmlspecialchars($item['name']) ?></td>
                        <td class="p-2 border"><?php if ($item['min_price'] !== null) { echo '₹' . $item['min_price']; if ($item['max_price'] !== null && $item['max_price'] != $item['min_price']) echo ' - ₹' . $item['max_price']; } else { echo 'N/A'; } ?></td>
                        <td class="p-2 border"><?= $item['total_stock'] ?></td>
                        <td class="p-2 border">
                            <?= $item['variants_count'] ?>
                            <div class="text-xs text-gray-600 mt-1">
                                <?php foreach($item['variant_preview'] as $vp): ?>
                                    <div>₹<?= $vp['price'] ?><?php if ($vp['old_price']) echo ' (old ₹' . $vp['old_price'] . ')'; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="p-2 border" data-category-id="<?= $item['category_id'] ?>"><?= htmlspecialchars($item['category_name'] ?? '') ?></td>
                        <td class="p-2 border" data-brand-id="<?= $item['brand_id'] ?>"><?= htmlspecialchars($item['brand_name'] ?? '') ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['packaging_type']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['product_form']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['origin']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['grade']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['purity']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['flavor']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['shelf_life']) ?></td>
                        <td class="p-2 border description-cell"><?= htmlspecialchars($item['description']) ?></td>
                        <td class="p-2 border nutrition-cell"><?= htmlspecialchars($item['nutrition']) ?></td>
                        <td class="p-2 border expiry-info-cell"><?= htmlspecialchars($item['expiry_info']) ?></td>
                        <td class="p-2 border tags-cell"><?= htmlspecialchars($item['tags']) ?></td>
                        <td class="p-2 border storage-instructions-cell"><?= htmlspecialchars($item['storage_instructions']) ?></td>
                        <td class="p-2 border image-cell">
                            <?php
                                $shown = false;
                                if (!empty($item['thumb_image'])) {
                                    $thumbFull = $_SERVER['DOCUMENT_ROOT'] . "/admin/" . ltrim($item['thumb_image'], '/');
                                    if (file_exists($thumbFull)) {
                                        echo '<img src="/admin/' . htmlspecialchars($item['thumb_image'], ENT_QUOTES) . '" class="w-12 h-12 object-cover rounded">';
                                        $shown = true;
                                    }
                                }
                                if (!$shown && !empty($item['image']) && file_exists($item['image'])) {
                                    echo '<img src="' . htmlspecialchars($item['image'], ENT_QUOTES) . '" class="w-12 h-12 object-cover rounded">';
                                    $shown = true;
                                }
                                if (!$shown) echo 'No Image';
                            ?>
                        </td>
                        <td class="p-2 border">
                           <a href="edit_item.php?id=<?= $item['id'] ?>"
   class="bg-indigo-600 text-white px-3 py-1 rounded inline-block">
   Edit
</a>
 <form method="POST" class="inline-block delete-form" data-item-name="<?= htmlspecialchars($item['name']) ?>">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" name="delete" class="bg-red-600 text-white px-3 py-1 rounded delete-btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>



</body>
</html>