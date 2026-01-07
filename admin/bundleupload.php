<?php
// Set a higher execution time for bulk uploads
set_time_limit(600);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

// ----------------- Functions from additems.php -----------------
function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) deleteDirectory($path);
        else unlink($path);
    }
    return rmdir($dir);
}

function compressImage($source, $destination, $quality = 25) {
    $info = getimagesize($source);
    if ($info === false) return false;

    $image = false;
    switch ($info['mime']) {
        case 'image/jpeg': $image = imagecreatefromjpeg($source); break;
        case 'image/png': $image = imagecreatefrompng($source); break;
        case 'image/webp': $image = imagecreatefromwebp($source); break;
        default: return false;
    }
    if ($image === false) return false;

    $destDir = dirname($destination);
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $result = false;
    switch ($info['mime']) {
        case 'image/jpeg':
        case 'image/webp':
            $result = imagejpeg($image, $destination, $quality);
            break;
        case 'image/png':
            $png_quality = max(0, min(9, round(($quality / 100) * 9)));
            $result = imagepng($image, $destination, $png_quality);
            break;
    }
    imagedestroy($image);
    return $result;
}

// ----------------- Handle CSV + Images Bundle Upload -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bundle_upload'])) {

    $errors = [];
    $successCount = 0;

    // Validate CSV
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "CSV file is required!";
    }

    // Validate Images
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        $errors[] = "Images are required!";
    }

    if (empty($errors)) {
        $csvFile = $_FILES['csv_file']['tmp_name'];
        $images = $_FILES['images'];

        // Read CSV
        $rows = [];
        if (($handle = fopen($csvFile, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ",");
            if ($header === false) $errors[] = "CSV header is empty.";
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if (count($data) !== count($header)) {
                    $errors[] = "CSV row does not match header columns.";
                    continue;
                }
                $rows[] = array_combine($header, $data);
            }
            fclose($handle);
        } else {
            $errors[] = "Failed to open CSV file.";
        }

        foreach ($rows as $rowIndex => $row) {
            $conn->beginTransaction();
            try {
                // ----------------- Insert main item -----------------
                $sql = "INSERT INTO items 
                (name, category_id, brand_id, status, packaging_type, product_form, origin, grade, purity, flavor, description, nutrition, shelf_life, storage_instructions, expiry_info, tags) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    trim($row['name']),
                    intval($row['category_id']),
                    intval($row['brand_id']),
                    isset($row['status']) ? intval($row['status']) : 1,
                    trim($row['packaging_type']),
                    trim($row['product_form']),
                    trim($row['origin']),
                    trim($row['grade']),
                    trim($row['purity']),
                    trim($row['flavor']),
                    trim($row['description']),
                    trim($row['nutrition']),
                    trim($row['shelf_life']),
                    trim($row['storage_instructions']),
                    trim($row['expiry_info']),
                    trim($row['tags'])
                ]);

                $item_id = $conn->lastInsertId();

                // ----------------- Handle images for this item -----------------
              // ----------------- Handle images for this item -----------------
$imageNames = explode('|', $row['images']); 
$uploadDir = "Uploads/";
$compressedDir = $uploadDir . "compressed/";

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($compressedDir)) mkdir($compressedDir, 0755, true);

$primaryImagePath = '';
foreach ($imageNames as $i => $imgName) {
    $imgName = trim($imgName);
    
    // Find the image in the uploaded array
    $foundIndex = array_search(strtolower($imgName), array_map('strtolower', $images['name']));
    
    if ($foundIndex === false) continue;

    $tmpPath = $images['tmp_name'][$foundIndex];
    
    // Create unique names for this specific product
    $uniqueName = uniqid() . "_" . basename($imgName);
    $finalPath = $uploadDir . $uniqueName;
    $compressedPath = $compressedDir . $uniqueName;

    // USE copy() INSTEAD OF move_uploaded_file() 
    // This allows the same honey1.png to be used by multiple rows
    if (!copy($tmpPath, $finalPath)) {
        $errors[] = "Failed to copy image: $imgName for row " . ($rowIndex + 2);
        continue;
    }

    if (!compressImage($finalPath, $compressedPath, 25)) {
        $errors[] = "Failed to compress image: $imgName for row " . ($rowIndex + 2);
    }

    $isPrimary = ($i === 0) ? 1 : 0;
    if ($isPrimary) $primaryImagePath = $compressedPath;

    $stmt = $conn->prepare("INSERT INTO item_images (item_id, image_path, compressed_path, sort_order, is_primary) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$item_id, $finalPath, $compressedPath, $i, $isPrimary]);
}

                // ----------------- Insert variants -----------------
                $variantValues = isset($row['variant_weight_value']) ? explode('|', $row['variant_weight_value']) : [];
                $variantUnits = isset($row['variant_weight_unit']) ? explode('|', $row['variant_weight_unit']) : [];
                $variantPrices = isset($row['variant_price']) ? explode('|', $row['variant_price']) : [];
                $variantOldPrices = isset($row['variant_old_price']) ? explode('|', $row['variant_old_price']) : [];
                $variantDiscounts = isset($row['variant_discount']) ? explode('|', $row['variant_discount']) : [];
                $variantStocks = isset($row['variant_stock']) ? explode('|', $row['variant_stock']) : [];
                $variantStatuses = isset($row['variant_status']) ? explode('|', $row['variant_status']) : [];

                $insertVarStmt = $conn->prepare("INSERT INTO item_variants (item_id, weight_value, weight_unit, price, old_price, discount, stock, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $variantCount = count($variantValues);
                for ($v = 0; $v < $variantCount; $v++) {
                    $w_val = isset($variantValues[$v]) ? floatval($variantValues[$v]) : 0;
                    $w_unit = isset($variantUnits[$v]) ? $variantUnits[$v] : 'pcs';
                    $price = isset($variantPrices[$v]) ? floatval($variantPrices[$v]) : 0;
                    $old_price = isset($variantOldPrices[$v]) && is_numeric($variantOldPrices[$v]) ? floatval($variantOldPrices[$v]) : null;
                    $discount = isset($variantDiscounts[$v]) ? floatval($variantDiscounts[$v]) : 0;
                    $stock = isset($variantStocks[$v]) ? intval($variantStocks[$v]) : 0;
                    $status = isset($variantStatuses[$v]) ? intval($variantStatuses[$v]) : 1;

                    $insertVarStmt->execute([$item_id, $w_val, $w_unit, $price, $old_price, $discount, $stock, $status]);
                }

                $conn->commit();
                $successCount++;

            } catch (Exception $e) {
                $conn->rollBack();
                $errors[] = "Row " . ($rowIndex + 2) . " failed: " . $e->getMessage();
            }
        }

        echo "<h3>Upload Completed: $successCount items added successfully</h3>";
        if (!empty($errors)) {
            echo "<h4>Errors:</h4><ul>";
            foreach ($errors as $err) echo "<li>" . htmlspecialchars($err) . "</li>";
            echo "</ul>";
        }
    } else {
        foreach ($errors as $err) echo "<p>" . htmlspecialchars($err) . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bundle Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex">
      <?php require_once './common/admin_sidebar.php'; ?>
    <div class="max-w-3xl m-4 mx-auto bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold text-indigo-600 mb-4">Bundle Upload Items</h1>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block font-medium text-gray-700 mb-1">CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required class="w-full border p-2 rounded">
                <p class="text-xs text-gray-500 mt-1">CSV columns must include: name, category_id, brand_id, status, packaging_type, product_form, origin, grade, purity, flavor, description, nutrition, shelf_life, storage_instructions, expiry_info, tags, images, variant_weight_value, variant_weight_unit, variant_price, variant_old_price, variant_discount, variant_stock, variant_status</p>
            </div>
            <div class="mb-4">
                <label class="block font-medium text-gray-700 mb-1">Images</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full border p-2 rounded">
                <p class="text-xs text-gray-500 mt-1">Images filenames must match those listed in the CSV <strong>images</strong> column, separated by |</p>
            </div>
            <button type="submit" name="bundle_upload" class="w-full bg-indigo-600 text-white py-3 rounded-lg font-bold hover:bg-indigo-700 transition">Upload Bundle</button>
        </form>
    </div>
    </div>
</body>
</html>
