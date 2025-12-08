<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";


// ----------------- Function to Delete Directory Recursively -----------------
function deleteDirectory($dir) {
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
        mkdir($destDir, 0777, true);
    }

    // Save compressed image
    $result = imagejpeg($image, $destination, $quality);
    imagedestroy($image);
    return $result;
}


// ----------------- Single Upload -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_upload'])) {

    // BASIC INFO
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $brand_id = intval($_POST['brand_id']);

    // PRICING
    $price = floatval(str_replace(',', '', $_POST['price']));
    $discount = floatval($_POST['discount']);
    $old_price = $price; // optional, set old price initially same as price

    // STOCK
    $stock = intval($_POST['stock']);

    // PRODUCT DETAILS
    $weight = trim($_POST['weight']);
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

    // NOT IN YOUR FORM (optional)
    $expiry_info = null;
    $tags = null;

    // IMAGE HANDLING
    $imagePath = null;
    $compressedImagePath = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

        $uploadDir = 'Uploads/';
        $compressedDir = 'Uploads/compressed/';

        $imageFileName = uniqid() . "_" . basename($_FILES['image']['name']);

        $imagePath = $uploadDir . $imageFileName;
        $compressedImagePath = $compressedDir . $imageFileName;

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (!is_dir($compressedDir)) mkdir($compressedDir, 0777, true);

        move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);

        // compress function (you already have)
        if (!compressImage($imagePath, $compressedImagePath, 25)) {
            $compressedImagePath = $imagePath;
        }
    }

    // SQL INSERT
    try {
        $sql = "INSERT INTO items 
        (
            name, category_id, brand_id, 
            price, old_price, discount, stock, 
            image, compressed_image,
            weight, packaging_type, product_form, origin, grade, purity, flavor, 
            description, nutrition, shelf_life, storage_instructions, expiry_info, tags
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $name, $category_id, $brand_id,
            $price, $old_price, $discount, $stock,
            $imagePath, $compressedImagePath,
            $weight, $packaging_type, $product_form, $origin, $grade, $purity, $flavor,
            $description, $nutrition, $shelf_life, $storage_instructions, $expiry_info, $tags
        ]);
 echo "<p class='text-green-500 text-center'>Item uploaded successfully!</p>";
    } catch (PDOException $e) {
        echo "<p class='text-red-500 text-center'>Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bundle_upload'])) {

    $uploadDirBase = 'Uploads/';
    $compressedDirBase = 'Uploads/compressed/';

    if (!is_dir($uploadDirBase)) mkdir($uploadDirBase, 0777, true);
    if (!is_dir($compressedDirBase)) mkdir($compressedDirBase, 0777, true);

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo "<p class='text-red-500 text-center'>Error: No CSV file uploaded.</p>";
        exit;
    }

    $csvFile = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        echo "<p class='text-red-500 text-center'>Error: Unable to read CSV file.</p>";
        exit;
    }

    $header = fgetcsv($handle); // skip header
    $rowNumber = 2;

    while (($row = fgetcsv($handle)) !== false) {

        // Ensure CSV has minimum 8 columns
        if (count($row) < 8) {
            echo "<p class='text-red-500 text-center'>Error: Row $rowNumber has insufficient columns.</p>";
            $rowNumber++;
            continue;
        }

        // Extract CSV values
        $name            = trim($row[0]);
        $price           = floatval($row[1]);
        $discount        = floatval($row[2]);
        $category_id     = intval($row[3]);
        $brand_id        = intval($row[4]);
        $stock           = intval($row[5]);
        $description     = trim($row[6]);
        $imageFileName   = trim($row[7]); // CSV image filename

        // Additional fields if required
        $weight = $row[8] ?? null;
        $packaging_type = $row[9] ?? null;
        $product_form = $row[10] ?? null;
        $origin = $row[11] ?? null;
        $grade = $row[12] ?? null;
        $purity = $row[13] ?? null;
        $flavor = $row[14] ?? null;
        $nutrition = $row[15] ?? null;
        $shelf_life = $row[16] ?? null;
        $storage_instructions = $row[17] ?? null;

        // Validate required values
        if (empty($name) || $price <= 0 || $category_id <= 0 || $brand_id <= 0) {
            echo "<p class='text-red-500 text-center'>Error: Invalid data in row $rowNumber. Skipped.</p>";
            $rowNumber++;
            continue;
        }

        // =============== Image Handling ==================
        $imagePath = null;
        $compressedImagePath = null;

        if ($imageFileName && isset($_FILES['images'])) {

            $imgIndex = array_search($imageFileName, $_FILES['images']['name']);

            if ($imgIndex !== false && $_FILES['images']['error'][$imgIndex] === UPLOAD_ERR_OK) {

                $imagePath = $uploadDirBase . $imageFileName;
                $compressedImagePath = $compressedDirBase . $imageFileName;

                move_uploaded_file($_FILES['images']['tmp_name'][$imgIndex], $imagePath);

                // compress image
                if (!compressImage($imagePath, $compressedImagePath, 25)) {
                    $compressedImagePath = $imagePath; // fallback
                }

            } else {
                echo "<p class='text-red-500 text-center'>Warning: Image '$imageFileName' missing for row $rowNumber.</p>";
            }
        }

        // =============== Database Insert =================
        try {

            $stmt = $conn->prepare("
                INSERT INTO items 
                    (name, price, discount, category_id, brand_id, stock,
                     description, image, compressed_image, weight, packaging_type, 
                     product_form, origin, grade, purity, flavor, nutrition, shelf_life, storage_instructions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name, $price, $discount, $category_id, $brand_id, $stock,
                $description, $imagePath, $compressedImagePath,
                $weight, $packaging_type, $product_form, $origin, $grade,
                $purity, $flavor, $nutrition, $shelf_life, $storage_instructions
            ]);

        } catch (PDOException $e) {
            echo "<p class='text-red-500 text-center'>DB Error (Row $rowNumber): " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        $rowNumber++;
    }

    fclose($handle);

    echo "<p class='text-green-500 text-center'>Bundle upload completed successfully!</p>";
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="admin-container flex">
        <?php require_once './common/admin_sidebar.php'; ?>
        <main class="admin-main flex-1 p-6">
            <hr class="my-8">

            <div class="container mx-auto max-w-4xl p-6 bg-white rounded-lg shadow-lg mt-10">
                <!-- Single Upload Form -->
                <h3 class="text-xl font-semibold text-indigo-600 mt-4 mb-4">Single Upload</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="text" name="name" placeholder="Name" required
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <!-- Category Dropdown -->


                    <div class="flex gap-3">
                        <select id="categorySelect" name="category_id" class="w-full p-2 border rounded"
                            required></select>

                        <button type="button" onclick="openCategoryModal()" class="px-2 py-2 bg-green-600 text-white rounded
           shadow-md hover:bg-green-700 
           hover:shadow-lg transition-all duration-200 
           flex items-center gap-2 hover:scale-105 active:scale-95">
                            <i class="fa-solid fa-plus"></i>

                        </button>


                    </div>

                    <!-- Brand Dropdown -->


                    <div class="flex gap-3">
                        <select id="brandSelect" name="brand_id" class="w-full p-2 border rounded" required></select>

                        <button type="button" onclick="openBrandModal()" class="px-2 py-2 bg-green-600 text-white rounded
           shadow-md hover:bg-green-700 
           hover:shadow-lg transition-all duration-200 
           flex items-center gap-2 hover:scale-105 active:scale-95">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>

                    <input type="text" name="price" placeholder="Price" required
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="discount" placeholder="Discount (%)"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="stock" placeholder="Stock"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="weight" placeholder="Weight"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="Packaging_type" placeholder="Packaging Type"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="product_form" placeholder="Product form"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="origin" placeholder="Origin (country)"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="grade" placeholder="Grade"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="puriy" placeholder="Purity"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="floavour" placeholder="Flavour"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="description" placeholder="Description"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="nutrition" placeholder="Nutrition"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="self_life" placeholder="Self Life (best before)"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="storage_instruction" placeholder="Storage Instruction"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="file" name="image" accept="image/*" class="w-full p-3 border rounded-lg">
                    <button type="submit" name="single_upload"
                        class="w-full bg-indigo-600 text-white p-3 rounded-lg hover:bg-indigo-700 transition-colors">Single
                        Upload</button>
                </form>

                <hr class="my-8">

                <!-- Bundle Upload Form -->
                <h3 class="text-xl font-semibold text-green-600 mt-4 mb-4">Bundle Upload (CSV + Images)</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <label class="block text-gray-700">Upload CSV File (Format:
                        name,price,discount,category,pieces,items,brand,image_filename)</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full p-3 border rounded-lg">
                    <label class="block text-gray-700">Upload Images (multiple, names must match CSV)</label>
                    <input type="file" name="images[]" accept="image/*" multiple class="w-full p-3 border rounded-lg">
                    <button type="submit" name="bundle_upload"
                        class="w-full bg-green-600 text-white p-3 rounded-lg hover:bg-green-700 transition-colors">Bundle
                        Upload</button>
                </form>


            </div>
        </main>
    </div>

    <!-- CATEGORY MODAL -->
    <div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-xl w-96">
            <h2 class="text-xl font-bold mb-4 text-green-700">Add Category</h2>

            <input id="newCategoryName" type="text" class="w-full p-2 border rounded mb-3" placeholder="Category Name">

            <button onclick="saveCategory()" class="w-full py-2 bg-green-600 text-white rounded">Save</button>
            <button onclick="closeCategoryModal()" class="w-full mt-2 py-2 border rounded">Cancel</button>
        </div>
    </div>

    <!-- BRAND MODAL -->
    <div id="brandModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-xl w-96">
            <h2 class="text-xl font-bold mb-4 text-green-700">Add Brand</h2>

            <input id="newBrandName" type="text" class="w-full p-2 border rounded mb-3" placeholder="Brand Name">

            <button onclick="saveBrand()" class="w-full py-2 bg-green-600 text-white rounded">Save</button>
            <button onclick="closeBrandModal()" class="w-full mt-2 py-2 border rounded">Cancel</button>
        </div>
    </div>


    <script>
    /* -------------------- FETCH DROPDOWN DATA -------------------- */

    function loadCategories() {
        fetch(BASE_URL + "/api/fetch_categories.php")
            .then(res => res.json())
            .then(data => {
                let html = "";
                data.forEach(c => {
                    html += `<option value="${c.id}">${c.name}</option>`;
                });
                document.getElementById("categorySelect").innerHTML = html;
            });
    }

    function loadBrands() {
        console.log("Loading brands...");
        fetch(BASE_URL + "/api/fetch_brands.php")
            .then(res => res.json())
            .then(data => {
                let html = "";
                data.forEach(b => {
                    html += `<option value="${b.id}">${b.name}</option>`;
                    console.log(b);
                });
                document.getElementById("brandSelect").innerHTML = html;
            });
    }

    loadCategories();
    loadBrands();

    /* -------------------- CATEGORY MODAL -------------------- */
    function openCategoryModal() {
        document.getElementById("categoryModal").classList.remove("hidden");
    }

    function closeCategoryModal() {
        document.getElementById("categoryModal").classList.add("hidden");
    }

    function saveCategory() {
        let name = document.getElementById("newCategoryName").value;

        fetch(BASE_URL + "/api/add_category.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "name=" + name
        }).then(() => {
            showToastMessage("Category added successfully!");
            closeCategoryModal();
            loadCategories();
        });
    }

    /* -------------------- BRAND MODAL -------------------- */
    function openBrandModal() {
        document.getElementById("brandModal").classList.remove("hidden");
    }

    function closeBrandModal() {
        document.getElementById("brandModal").classList.add("hidden");
    }

    function saveBrand() {
        let name = document.getElementById("newBrandName").value;

        fetch(BASE_URL + "/api/add_brand.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "name=" + name
        }).then(() => {
            showToastMessage(" New Brand added successfully!");
            closeBrandModal();
            loadBrands();
        });
    }
    </script>
    <div id="toast-container"></div>
</body>

</html>