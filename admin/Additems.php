<?php
// Set a higher execution time for file uploads and processing
set_time_limit(300);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Assume admin check is correct
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}
// Assume dbconf.php includes a PDO connection instance named $conn
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";


// ----------------- Function to Delete Directory Recursively -----------------
function deleteDirectory($dir) {
    // ... (This function is not directly used in the upload logic but kept for completeness)
    if (!is_dir($dir)) {
        return false;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// ----------------- Function to Compress Image -----------------
function compressImage($source, $destination, $quality = 25) {
    $info = getimagesize($source);
    if ($info === false) {
        return false;
    }

    $image = false;
    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            // For PNG, $quality is interpreted differently (0-9). 
            // We'll map 25 (JPEG scale) to a suitable PNG compression level (e.g., 7-9).
            $png_quality = max(0, min(9, round(($quality / 100) * 9)));
            $image = imagecreatefrompng($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }

    if ($image === false) {
        return false;
    }

    // Ensure destination directory exists
    $destDir = dirname($destination);
    if (!is_dir($destDir)) {
        // Use 0755 for better permissions on most web hosts
        if (!mkdir($destDir, 0755, true)) {
            imagedestroy($image);
            return false;
        }
    }

    // Save compressed image
    $result = false;
    switch ($info['mime']) {
        case 'image/jpeg':
        case 'image/webp':
            // Save as JPEG for compression consistency, even if source was WebP (common practice)
            $result = imagejpeg($image, $destination, $quality);
            break;
        case 'image/png':
            // Save PNG with calculated quality
            $result = imagepng($image, $destination, $png_quality);
            break;
    }
    
    imagedestroy($image);
    return $result;
}


// ----------------- Single Upload Logic -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_upload'])) {
    
    // START DEBUGGING OUTPUT
    echo "<h2>--- SERVER-SIDE DEBUGGING START ---</h2>";
    echo "<h3>\$_POST Data:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    echo "<h3>\$_FILES Data:</h3>";
    echo "<pre>" . print_r($_FILES, true) . "</pre>";

    // START DB TRANSACTION
    $conn->beginTransaction();
    $item_id = null; // Initialize $item_id for use in the error handler
    $success = false;

    try {
        // BASIC INFO & Input Sanitization
        $name = trim($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $brand_id = intval($_POST['brand_id']);
        $status = isset($_POST['status']) ? intval($_POST['status']) : 1;



        // Item meta
        $packaging_type = trim($_POST['Packaging_type']);
        $product_form = trim($_POST['product_form']);
        $origin = trim($_POST['origin']);
        $grade = trim($_POST['grade']);
        $purity = trim($_POST['puriy']);
        $flavor = trim($_POST['floavour']);
        $description = trim($_POST['description']);
        $nutrition = trim($_POST['nutrition']);
        $shelf_life = trim($_POST['self_life']);
        $storage_instructions = trim($_POST['storage_instruction']);
        $expiry_info = isset($_POST['expiry_info']) ? trim($_POST['expiry_info']) : null;
        $tags = isset($_POST['tags']) ? trim($_POST['tags']) : null;


        // ----------------- Insert item (main row) -----------------
        $sql = "INSERT INTO items 
            (name, category_id, brand_id, status, packaging_type, product_form, origin, grade, purity, flavor, description, nutrition, shelf_life, storage_instructions, expiry_info, tags) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $name, $category_id, $brand_id, $status, $packaging_type, $product_form, $origin, $grade, $purity, $flavor, $description, $nutrition, $shelf_life, $storage_instructions, $expiry_info, $tags
        ]);

        $item_id = $conn->lastInsertId();
        echo "<h3>Main Item Inserted: Item ID = $item_id</h3>";


        // ----------------- Handle multiple images -----------------
        $orderArray = isset($_POST['order']) ? $_POST['order'] : [];
        $insertedImageIds = []; // Array to store IDs of inserted images

        // CRITICAL CHECK: Check if files array is available and if any file name exists
        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
             echo "<h3>Image Upload Status: SKIPPED</h3>";
             if (!empty($orderArray)) {
                 echo "<p class='text-red-600'>WARNING: \$_POST['order'] array received but \$_FILES['images'] is empty. File input modification failed!</p>";
             }
        } else {
            // Files exist in $_FILES, now proceed with ordering and insertion
            $files = $_FILES['images'];
            echo "<h3>Image Upload Status: STARTING</h3>";
            
            // Fall back to sequential upload if order array is empty (shouldn't happen with JS)
            if (empty($orderArray)) {
                $orderArray = array_keys($files['name']);
            }
            
            $primaryIndex = intval($_POST['primary_index']); 
            
            // Use a flat Uploads directory (no per-item subfolders)
            // Store files as: Uploads/<uniq> and Uploads/compressed/<uniq>
            $uploadDir = "Uploads/";

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Failed to create upload directory: $uploadDir");
                }
            }

            echo "<h4>Processing " . count($orderArray) . " images...</h4>";

            // Loop according to the client-side drag-drop order ('order' array)
            foreach ($orderArray as $sortOrder => $fileIndex) {

                // Safety check: ensure the index is valid and upload was successful
                if (!isset($files['tmp_name'][$fileIndex]) || $files['error'][$fileIndex] !== UPLOAD_ERR_OK) {
                    echo "<p class='text-yellow-500'>Skipping file at index $fileIndex (Error Code: " . $files['error'][$fileIndex] . ").</p>";
                    continue; 
                }

                $tmpPath = $files['tmp_name'][$fileIndex];
                $originalName = $files['name'][$fileIndex];

                $filename = uniqid() . "_" . preg_replace("/[^A-Za-z0-9\._-]/", "_", basename($originalName));

                    $finalPath = $uploadDir . $filename;

                    // Ensure compressed subfolder exists
                    $compressedDir = $uploadDir . "compressed/";
                    if (!is_dir($compressedDir)) {
                        if (!mkdir($compressedDir, 0755, true)) {
                            error_log("Failed to create compressed directory: $compressedDir");
                            // Fallback: use uploads dir if compressed dir can't be created
                            $compressedDir = $uploadDir;
                        }
                    }

                    $compressedPath = $compressedDir . $filename;

                // Move original file
                if (!move_uploaded_file($tmpPath, $finalPath)) {
                    error_log("Failed to move uploaded file from $tmpPath to $finalPath");
                    echo "<p class='text-red-500'>Error: Could not move uploaded file for $originalName. Check directory permissions or file size limits.</p>";
                    continue; 
                }
                echo "<p>File $sortOrder ($originalName) moved to $finalPath.</p>";


                // Compress image into the Uploads/compressed/ subfolder
                $compression_success = compressImage($finalPath, $compressedPath, 25);
                $dbCompressedPath = $compression_success ? $compressedPath : $finalPath;

                // Determine if this is the primary image
                $isPrimary = ($sortOrder === 0) ? 1 : 0; 

                // Insert image into item_images table
                $stmt = $conn->prepare("INSERT INTO item_images (item_id, image_path, compressed_path, sort_order, is_primary) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $finalPath, $dbCompressedPath, $sortOrder, $isPrimary]);
                
                $newImageId = $conn->lastInsertId();
                $insertedImageIds[] = $newImageId;

                // Save primary image path to update main item row later
                if ($isPrimary) {
                    $primaryImagePath = $dbCompressedPath;
                }

                echo "<p>-> Image Inserted: ID = $newImageId, Path = $dbCompressedPath, Primary = $isPrimary</p>";

            }
        } // End of image upload block

        // Update the main item's image field with primary image if available
        if (isset($primaryImagePath) && !empty($primaryImagePath)) {
            $stmt = $conn->prepare("UPDATE items SET image = ? WHERE id = ?");
            $stmt->execute([$primaryImagePath, $item_id]);
        }

        // ----------------- Insert item variants -----------------
        $variant_weights = isset($_POST['variant_weight_value']) ? $_POST['variant_weight_value'] : [];
        $variant_units = isset($_POST['variant_weight_unit']) ? $_POST['variant_weight_unit'] : [];
        $variant_prices = isset($_POST['variant_price']) ? $_POST['variant_price'] : [];
        $variant_old_prices = isset($_POST['variant_old_price']) ? $_POST['variant_old_price'] : [];
        $variant_discounts = isset($_POST['variant_discount']) ? $_POST['variant_discount'] : [];
        $variant_stocks = isset($_POST['variant_stock']) ? $_POST['variant_stock'] : [];
        $variant_statuses = isset($_POST['variant_status']) ? $_POST['variant_status'] : [];

        $insertVarStmt = $conn->prepare("INSERT INTO item_variants (item_id, weight_value, weight_unit, price, old_price, discount, stock, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        if (count($variant_weights) > 0) {
            for ($i = 0; $i < count($variant_weights); $i++) {
                $w_val = floatval(str_replace(',', '', $variant_weights[$i]));
                $w_unit = isset($variant_units[$i]) && in_array($variant_units[$i], ['g','kg','ml','l','pcs']) ? $variant_units[$i] : 'pcs';

                $price = isset($variant_prices[$i]) ? floatval(str_replace(',', '', $variant_prices[$i])) : 0.00;
                // Enforce positive price
                if ($price <= 0) {
                    throw new Exception("Variant price must be a positive number.");
                }

                $old_price = isset($variant_old_prices[$i]) && $variant_old_prices[$i] !== '' ? floatval(str_replace(',', '', $variant_old_prices[$i])) : null;

                // Calculate discount server-side if old_price supplied and greater than price
                if ($old_price !== null && $old_price > $price) {
                    $discount = round((($old_price - $price) / $old_price) * 100, 2);
                } else {
                    $discount = 0.00;
                    // allow override if user supplied a discount but keep logic predictable
                    if (isset($variant_discounts[$i]) && $variant_discounts[$i] !== '') {
                        $maybe = floatval($variant_discounts[$i]);
                        if ($maybe > 0) $discount = $maybe;
                    }
                }

                $stock = isset($variant_stocks[$i]) ? intval($variant_stocks[$i]) : 0;
                $status = isset($variant_statuses[$i]) ? intval($variant_statuses[$i]) : 1;

                $insertVarStmt->execute([$item_id, $w_val, $w_unit, $price, $old_price, $discount, $stock, $status]);
            }
        } else {
            throw new Exception("At least one variant is required. Please add at least one variant before saving the item.");
        }

        $conn->commit(); // Commit transaction on success
        $success = true;
        echo "<h3>Database Transaction Status: COMMITTED</h3>";
        echo "<p class='text-green-500 text-center'>Item uploaded successfully! (Item ID: $item_id)</p>";
        echo "<h4>Inserted Image IDs: " . implode(', ', $insertedImageIds) . "</h4>";


    } catch (PDOException $e) {
        $conn->rollBack(); // Rollback on database error
        echo "<h3>Database Transaction Status: ROLLED BACK</h3>";
        if ($item_id) {
            $cleanupDir = "Uploads/" . $item_id;
            if (is_dir($cleanupDir)) {
                 // deleteDirectory($cleanupDir); 
            }
        }
        echo "<p class='text-red-500 text-center'>Database Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    } catch (Exception $e) {
         $conn->rollBack(); 
         echo "<h3>Database Transaction Status: ROLLED BACK</h3>";
         echo "<p class='text-red-500 text-center'>System Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
         exit;
    }
    echo "<h2>--- SERVER-SIDE DEBUGGING END ---</h2>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add items</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/config.js"></script>
    <script src="/cart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/Styles.css" rel="stylesheet">
    <style>
    body {
        font-family: 'Poppins', sans-serif;
    }

    .admin-main {
        margin-left: 3rem;
    }

    .image-preview-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 15px;
    }

    .img-box-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .img-box {
        width: 120px;
        position: relative;
        padding: 10px;
        border: 2px solid #ccc;
        border-radius: 12px;
        background: #f9f9f9;
        cursor: grab;
        transition: transform 0.2s, border-color 0.2s;
    }

    .img-box.dragging {
        opacity: 0.5;
        transform: scale(1.05);
    }

    .img-box.primary {
        border-color: #1D4ED8;
    }

    .img-box img {
        width: 100%;
        height: 90px;
        object-fit: cover;
        border-radius: 8px;
    }

    .img-box .delete-icon {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.6);
        color: #fff;
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: bold;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s, transform 0.2s;
    }

    .img-box .delete-icon:hover {
        background: rgba(255, 0, 0, 0.9);
        transform: scale(1.1);
    }

    .thumbnail-control {
        position: absolute;
        bottom: 5px;
        left: 5px;
        text-align: center;
        font-size: 11px;
        background: rgba(29, 78, 216, 0.85);
        color: #fff;
        padding: 2px 6px;
        border-radius: 12px;
        font-weight: 500;
    }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex">
        <?php require_once './common/admin_sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-lg mt-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-indigo-600">Add New Product</h1>
                </div>

                <?php if(isset($_GET['success'])): ?>
                    <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6">Item added successfully!</div>
                <?php endif; ?>

                <form id="itemForm" method="POST" enctype="multipart/form-data" class="space-y-8">
                    <input type="hidden" name="single_upload" value="1">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <div>
                                <label class="block font-medium text-gray-700">Product Name</label>
                                <input type="text" name="name" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-medium text-gray-700">Status</label>
                                    <select name="status" class="w-full p-3 border rounded-lg outline-none">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-medium text-gray-700">Packaging</label>
                                    <input type="text" name="Packaging_type" class="w-full p-3 border rounded-lg outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block font-medium text-gray-700">Category</label>
                                    <div class="flex gap-2">
                                        <select id="categorySelect" name="category_id" required class="flex-1 p-3 border rounded-lg outline-none"></select>
                                        <button type="button" onclick="openCategoryModal()" class="p-3 bg-gray-100 rounded-lg hover:bg-gray-200"><i class="fa-solid fa-plus"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block font-medium text-gray-700">Brand</label>
                                    <div class="flex gap-2">
                                        <select id="brandSelect" name="brand_id" required class="flex-1 p-3 border rounded-lg outline-none"></select>
                                        <button type="button" onclick="openBrandModal()" class="p-3 bg-gray-100 rounded-lg hover:bg-gray-200"><i class="fa-solid fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t pt-4">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="font-bold text-gray-700">Variants</label>
                                    <button type="button" onclick="addVariantRow()" class="text-sm text-indigo-600 font-semibold">+ Add Variant</button>
                                </div>
                                <div id="variantsContainer" class="space-y-3"></div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block font-medium text-gray-700">Description</label>
                                <textarea name="description" rows="3" class="w-full p-3 border rounded-lg outline-none"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-medium text-gray-700">Origin</label>
                                    <input type="text" name="origin" class="w-full p-3 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block font-medium text-gray-700">Flavor</label>
                                    <input type="text" name="floavour" class="w-full p-3 border rounded-lg">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-medium text-gray-700">Shelf Life</label>
                                    <input type="text" name="self_life" class="w-full p-3 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block font-medium text-gray-700">Purity</label>
                                    <input type="text" name="puriy" class="w-full p-3 border rounded-lg">
                                </div>
                            </div>
                              <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700"> Nutrition</label>
                                    <input type="text" name="nutrition" class="w-full mt-1 p-3 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Product form</label>
                                    <input type="text" name="product_form"  class="w-full mt-1 p-3 border rounded-lg">
                                </div>
                            </div>
                             <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Storage Instructions</label>
                                <input type="text" name="storage_instruction"  class="w-full mt-1 p-3 border rounded-lg">
                            </div>
                               <div>
                                    <label class="block text-sm font-medium text-gray-700">Grade</label>
                                    <input type="text" name="grade"  class="w-full mt-1 p-3 border rounded-lg">
                                </div>
                                    </div>
                             <div class="grid grid-cols-2 gap-4">
                                 <div>
                                    <label class="block text-sm font-medium text-gray-700">Expiry Info</label>
                                    <input type="text" name="expiry_info" class="w-full mt-1 p-3 border rounded-lg">
                                </div>
                            <div>
                                <label class="block font-medium text-gray-700">Tags (comma separated)</label>
                                <input type="text" name="tags" class="w-full p-3 border rounded-lg">
                            </div>
                </div>
                        </div>
                    </div>

                    <div class="mt-8 border-t pt-6">
                        <div class="flex justify-between items-center mb-4">
                            <label class="block font-bold text-gray-700">Product Images (First is Thumbnail)</label>
                            <label for="images" class="cursor-pointer bg-indigo-50 text-indigo-600 px-4 py-2 rounded-lg hover:bg-indigo-100 transition">
                                <i class="fa-solid fa-upload mr-2"></i> Add  Images
                            </label>
                            <input type="file" id="images" name="images[]" multiple accept="image/*" class="hidden">
                        </div>
                        <div id="preview" class="image-preview-container"></div>
                        <div id="hiddenInputs"></div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-indigo-700 transition shadow-lg">
                        Save Product
                    </button>
                </form>
            </div>
        </main>
    </div>

    <div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-xl w-96">
            <h2 class="text-xl font-bold mb-4 text-green-700">Add Category</h2>

            <input id="newCategoryName" type="text" class="w-full p-2 border rounded mb-3" placeholder="Category Name">

            <button onclick="saveCategory()" class="w-full py-2 bg-green-600 text-white rounded">Save</button>
            <button onclick="closeCategoryModal()" class="w-full mt-2 py-2 border rounded">Cancel</button>
        </div>
    </div>

    <div id="brandModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-xl w-96">
            <h2 class="text-xl font-bold mb-4 text-green-700">Add Brand</h2>

            <input id="newBrandName" type="text" class="w-full p-2 border rounded mb-3" placeholder="Brand Name">

            <button onclick="saveBrand()" class="w-full py-2 bg-green-600 text-white rounded">Save</button>
            <button onclick="closeBrandModal()" class="w-full mt-2 py-2 border rounded">Cancel</button>
        </div>
    </div>

    <div id="toast-container"></div>

    <script>
    /* -------------------- FETCH DROPDOWN DATA -------------------- */
    // BASE_URL is assumed to be defined in /config.js

    function loadCategories() {
        fetch(BASE_URL + "/api/fetch_categories.php")
            .then(res => res.json())
            .then(data => {
                let html = "";
                data.forEach(c => {
                    html += `<option value="${c.id}">${c.name}</option>`;
                });
                document.getElementById("categorySelect").innerHTML = html;
            })
            .catch(error => console.error('Error loading categories:', error));
    }

    function loadBrands() {
        fetch(BASE_URL + "/api/fetch_brands.php")
            .then(res => res.json())
            .then(data => {
                let html = "";
                data.forEach(b => {
                    html += `<option value="${b.id}">${b.name}</option>`;
                });
                document.getElementById("brandSelect").innerHTML = html;
            })
            .catch(error => console.error('Error loading brands:', error));
    }

    loadCategories();
    loadBrands();

    /* -------------------- VARIANT UI: add/remove rows + discount calculation -------------------- */

 function computeVariantDiscount(row) {
    // Select inputs specifically within this row
    const priceInput = row.querySelector('input[name="variant_price[]"]');
    const oldPriceInput = row.querySelector('input[name="variant_old_price[]"]');
    const discountInput = row.querySelector('input[name="variant_discount[]"]');

    const price = parseFloat(priceInput.value) || 0;
    const oldPrice = parseFloat(oldPriceInput.value) || 0;

    if (oldPrice > price && price > 0) {
        const percentage = ((oldPrice - price) / oldPrice) * 100;
        // Display with 2 decimal places
        discountInput.value = Math.round(percentage) + "%"; 
    } else {
        discountInput.value = "0%";
    }
}
function addVariantRow() {
    const html = `
    <div class="variant-row bg-gray-50 p-3 rounded-lg border relative">
        <div class="grid grid-cols-3 gap-2">
            <div class="col-span-2">
                <label class="text-[10px] uppercase font-bold text-gray-500">Weight</label>
                <div class="flex gap-1">
                    <input type="number" step="0.01" name="variant_weight_value[]" required class="w-2/3 p-1.5 text-sm border rounded">
                    <select name="variant_weight_unit[]" class="w-1/3 p-1.5 text-sm border rounded">
                        <option value="g">g</option><option value="kg">kg</option><option value="ml">ml</option><option value="l">l</option><option value="pcs">pcs</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-[10px] uppercase font-bold text-gray-500">Price</label>
                <input type="number" step="0.01" name="variant_price[]" oninput="computeVariantDiscount(this.closest('.variant-row'))" required class="w-full p-1.5 text-sm border rounded">
            </div>
            <div>
                <label class="text-[10px] uppercase font-bold text-gray-500">Old Price</label>
                <input type="number" step="0.01" name="variant_old_price[]" oninput="computeVariantDiscount(this.closest('.variant-row'))" class="w-full p-1.5 text-sm border rounded">
            </div>
            <div>
                <label class="text-[10px] uppercase font-bold text-gray-500">Discount%</label>
                <input type="text" name="variant_discount[]" readonly class="w-full p-1.5 text-sm border rounded bg-gray-100">
            </div>
            <div>
                <label class="text-[10px] uppercase font-bold text-gray-500">Stock</label>
                <input type="number" name="variant_stock[]" value="0" class="w-full p-1.5 text-sm border rounded">
            </div>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 bg-red-500 text-white w-5 h-5 rounded-full text-xs">×</button>
    </div>`;
    document.getElementById("variantsContainer").insertAdjacentHTML('beforeend', html);
}


      

    function removeVariant(btn) {
        const row = btn.closest('.variant-row');
        if (row) row.remove();
    }

    // Add an initial empty row for convenience
    addVariantRow();

    /* -------------------- CATEGORY/BRAND MODAL FUNCTIONS -------------------- */
    // (Kept as is, assuming corresponding API endpoints exist)

    function openCategoryModal() {
        document.getElementById("categoryModal").classList.remove("hidden");
    }

    function closeCategoryModal() {
        document.getElementById("categoryModal").classList.add("hidden");
        document.getElementById("newCategoryName").value = '';
    }

    function saveCategory() {
        let name = document.getElementById("newCategoryName").value;
        if (!name) return;
        fetch(BASE_URL + "/api/add_category.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "name=" + encodeURIComponent(name)
        }).then(() => {
            showToastMessage("Category added successfully!");
            closeCategoryModal();
            loadCategories();
        }).catch(error => console.error('Error saving category:', error));
    }

    function openBrandModal() {
        document.getElementById("brandModal").classList.remove("hidden");
    }

    function closeBrandModal() {
        document.getElementById("brandModal").classList.add("hidden");
        document.getElementById("newBrandName").value = '';
    }

    function saveBrand() {
        let name = document.getElementById("newBrandName").value;
        if (!name) return;
        fetch(BASE_URL + "/api/add_brand.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "name=" + encodeURIComponent(name)
        }).then(() => {
            showToastMessage(" New Brand added successfully!");
            closeBrandModal();
            loadBrands();
        }).catch(error => console.error('Error saving brand:', error));
    }


    /* -------------------- IMAGE HANDLER: Drag & Drop + Hidden Inputs -------------------- */
    
    // selectedFiles: stores the File objects in the correct, current order
    let selectedFiles = [];
    // originalFileIndexes: stores the original index of each file in the initial file selection.
    // This is needed to map the reordered array back to the $_FILES array in PHP.
    let originalFileIndexes = [];
    let fileCounter = 0;

    // Handle file selection (APPEND MODE)
    document.getElementById("images").addEventListener("change", function(e) {
        const newFiles = Array.from(e.target.files);

        newFiles.forEach(file => {
            selectedFiles.push(file);
            // Assign a unique temporary index for tracking in the `originalFileIndexes` array
            originalFileIndexes.push(fileCounter++); 
        });

        renderPreview();

        // Reset the input value so the same file can be selected again
        e.target.value = "";
    });

    // Render preview grid
    function renderPreview() {
        const preview = document.getElementById("preview");
        preview.innerHTML = "";
        
        // Loop over selectedFiles, which is already in the correct order
        selectedFiles.forEach((file, index) => {
            const wrapper = document.createElement("div");
            wrapper.classList.add("img-box-wrapper");

            const div = document.createElement("div");
            div.classList.add("img-box");
            div.setAttribute("draggable", "true");
            
            // CRITICAL: The data-index MUST hold the index into the `originalFileIndexes` array, 
            // which in turn holds the true index for the PHP `$_FILES` array.
            div.dataset.index = index; 

            // Primary is always the first one in the visual order
            if (index === 0) div.classList.add("primary");

            const reader = new FileReader();
            reader.onload = function(event) {
                div.innerHTML = `
                <img src="${event.target.result}">
                <span class="delete-icon">&times;</span>
                ${index === 0 ? `<span class="thumbnail-control">Thumbnail</span>` : ""}
                `;

                // Delete functionality
                div.querySelector(".delete-icon").addEventListener("click", function(e) {
                    e.stopPropagation();
                    
                    // Remove the file and its corresponding original index
                    selectedFiles.splice(index, 1);
                    originalFileIndexes.splice(index, 1);

                    renderPreview();
                });
            };
            reader.readAsDataURL(file);

            addDragEvents(div);
            wrapper.appendChild(div);
            preview.appendChild(wrapper);
        });

        updateHiddenInputs();
    }

    // Drag & Drop
    let dragSrcEl = null;

    function addDragEvents(el) {
        el.addEventListener("dragstart", function(e) {
            dragSrcEl = this;
            this.classList.add("dragging");
            e.dataTransfer.effectAllowed = "move";
        });

        el.addEventListener("dragend", function() {
            this.classList.remove("dragging");
        });

        el.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
        });

        el.addEventListener("drop", function(e) {
            e.preventDefault();
            if (!dragSrcEl || dragSrcEl === this) return;

            const previewContainer = document.getElementById("preview");
            const wrappers = Array.from(previewContainer.children);
            const dragWrapper = dragSrcEl.parentNode;
            const dropWrapper = this.parentNode;
            
            const dragIndex = wrappers.indexOf(dragWrapper);
            const dropIndex = wrappers.indexOf(dropWrapper);

            // Reorder the DOM elements
            if (dragIndex < dropIndex) {
                previewContainer.insertBefore(dragWrapper, dropWrapper.nextSibling);
            } else {
                previewContainer.insertBefore(dragWrapper, dropWrapper);
            }

            // Reorder the internal data arrays based on the new DOM structure
            reorderFiles();
        });
    }

    // Reorder array after drag & drop
    function reorderFiles() {
        const newSelectedFiles = [];
        const newOriginalFileIndexes = [];
        
        // Iterate through the DOM in its new order
        document.querySelectorAll(".img-box").forEach(box => {
            const currentVisualIndex = parseInt(box.dataset.index); // This is the old index
            
            // Map the old index to the actual file and its original index
            newSelectedFiles.push(selectedFiles[currentVisualIndex]);
            newOriginalFileIndexes.push(originalFileIndexes[currentVisualIndex]);
        });
        
        // Update the main arrays
        selectedFiles = newSelectedFiles;
        originalFileIndexes = newOriginalFileIndexes;
        
        // Re-render to update the 'Thumbnail' badge and the internal data-index to reflect the new order
        renderPreview();
    }
    
    // Update hidden inputs for backend submission
  // Update hidden inputs for backend submission
function updateHiddenInputs() {
    const hidden = document.getElementById("hiddenInputs");
    hidden.innerHTML = "";
    
    // --- JS Debugging Output ---
    console.log("--- JS: updateHiddenInputs START ---");
    console.log("Selected Files (Final Order):", selectedFiles);
    // --- End Debugging Output ---

    if (selectedFiles.length > 0) {
        // Method 1: Dynamically setting the file input (Often Fails in Chrome/Firefox for security)
        try {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('images').files = dataTransfer.files;
            
            console.log("JS: Successfully set file input 'images' using DataTransfer.");
            console.log("JS: File Input Files:", document.getElementById('images').files);
            
        } catch (e) {
            console.error("JS: DataTransfer failed (Security restriction likely). \$_FILES array will be empty!", e);
        }

        // Method 2: Hidden inputs for order and primary index (This is what your PHP relies on)
        // primary_index is always 0 as it's the first in the sorted array
        hidden.innerHTML += `<input type="hidden" name="primary_index" value="0">`;
        
        // The order array sends the new sequential indices (0, 1, 2, ...)
        // which correspond to the new indices in the re-indexed $_FILES array.
        selectedFiles.forEach((_, i) => {
            hidden.innerHTML += `<input type="hidden" name="order[]" value="${i}">`;
        });
        
        console.log("JS: Hidden inputs for PHP 'order' array generated (0 to " + (selectedFiles.length - 1) + ").");
        
    } else {
        // If the user selected and then deleted all files, ensure no hidden inputs are sent
        console.log("JS: No files selected. Not generating hidden inputs.");
    }
    console.log("--- JS: updateHiddenInputs END ---");
}
    
    // Ensure uploads always get sent: if DataTransfer fails to populate the
    // native file input, submit the selectedFiles array via FormData as a
    // fallback when the form is submitted.
    document.getElementById('itemForm').addEventListener('submit', function(e) {
        // Validate variants: at least one and required fields
        const variantErrorEl = document.getElementById('variantError');
        if (variantErrorEl) { variantErrorEl.classList.add('hidden'); variantErrorEl.textContent = ''; }

        const variantRows = document.querySelectorAll('.variant-row');
        if (variantRows.length === 0) {
            e.preventDefault();
            if (variantErrorEl) { variantErrorEl.textContent = 'Please add at least one variant.'; variantErrorEl.classList.remove('hidden'); }
            document.getElementById('variantsSection').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        let invalid = false;
        variantRows.forEach((r) => {
            const priceEl = r.querySelector('input[name="variant_price[]"]');
            const weightEl = r.querySelector('input[name="variant_weight_value[]"]');
            const price = priceEl?.value ? parseFloat(priceEl.value) : 0;
            const weight = weightEl?.value ? parseFloat(weightEl.value) : 0;
            if (!(price > 0) || !(weight > 0)) invalid = true;
        });
        if (invalid) {
            e.preventDefault();
            if (variantErrorEl) { variantErrorEl.textContent = 'Each variant requires a positive weight and a positive price.'; variantErrorEl.classList.remove('hidden'); }
            document.getElementById('variantsSection').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Always refresh hidden inputs before submit
        updateHiddenInputs();

        const imagesInput = document.getElementById('images');

        // If user selected files but native input has no files, use AJAX fallback
        if (selectedFiles.length > 0 && (!imagesInput.files || imagesInput.files.length === 0)) {
            e.preventDefault();

            const form = document.getElementById('itemForm');
            const fd = new FormData();

            // Append every non-file form control into FormData
            Array.from(form.elements).forEach(el => {
                if (!el.name) return;
                if (el.type === 'file') return;
                if (el.tagName === 'BUTTON') return;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (!el.checked) return;
                }
                // For multiple valued names (like order[]) this will append them naturally
                fd.append(el.name, el.value);
            });

            // Append files in the exact visual order
            selectedFiles.forEach(file => {
                fd.append('images[]', file, file.name);
            });

            // Ensure the server receives the submit button flag
            if (!fd.has('single_upload')) fd.append('single_upload', '1');

            // Send with fetch and replace the document with the server response
            fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.text())
                .then(html => {
                    document.open();
                    document.write(html);
                    document.close();
                })
                .catch(err => {
                    console.error('AJAX upload failed:', err);
                    alert('Upload failed: ' + err.message);
                });
        }
    });
    
    // Placeholder for toast message
    function showToastMessage(message) {
        console.log("Toast: " + message);
    }
    
    </script>

</body>

</html>