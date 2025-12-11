<?php
require_once 'vendor/autoload.php';
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

$id = $_GET['id'] ?? null;
if (!$id) exit("Invalid Product");

$stmt = $conn->prepare("SELECT * FROM items WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

$gstRate = isset($settings['gst_rate']) ? floatval($settings['gst_rate']) : 18;
if (!$product) exit("Product Not Found");

// ----------------------- IMAGE LOGIC -----------------------
$image = basename($product['image']);
$originalImgPath = "./admin/Uploads/$image";
$compressedImgPath = "./admin/Uploads/compressed/$image";
$defaultImage = "./images/default.jpg";

$displayImgPath = file_exists($compressedImgPath)
    ? $compressedImgPath
    : (file_exists($originalImgPath) ? $originalImgPath : $defaultImage);

$displayImgPath = htmlspecialchars($displayImgPath, ENT_QUOTES, 'UTF-8');


// ----------------------- PRICE CALCULATION -----------------------
    $discountRate=round((float) $product['discount']);
    $grossPrice = round($product['price']); // integer
    $netPrice = round($product['price'] / (1 + $gstRate / 100)); // integer
    $gstAmount = $grossPrice - $netPrice; // integer by logic
    $discountAmount = round($netPrice * ((float) $product['discount'] / 100)); // integer
    $simpleDiscountedPrice = round($grossPrice * (1 - ((float) $product['discount'] / 100))); // integer
    // Fetch Category
$catStmt = $conn->prepare("SELECT name FROM categories WHERE id=?");
$catStmt->execute([$product['category_id']]);
$categoryName = $catStmt->fetchColumn() ?: "Unknown";

// Fetch Brand
$brandStmt = $conn->prepare("SELECT name FROM brands WHERE id=?");
$brandStmt->execute([$product['brand_id']]);
$brandName = $brandStmt->fetchColumn() ?: "No Brand";
                   
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']); ?> - RGreenMart</title>
    <link rel="icon" type="image/png" href="./images/LOGO.jpg">
    <link rel="stylesheet" href="./Styles.css">
    <script src="cart.js"></script>
</head>

<body>
       <?php require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/header.php"; ?>
   
<div class="max-w-6xl mx-auto p-6 mt-10 bg-white shadow-lg rounded-xl mb-5">

    <!-- Product Main Block -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">

        <!-- LEFT: IMAGE -->
        <div class="w-full">
            <img src="<?= $displayImgPath ?>" 
                 alt="<?= htmlspecialchars($product['name']); ?>"
                 class="w-full h-[350px] object-contain rounded-lg shadow">
        </div>

        <!-- RIGHT: DETAILS -->
        <div class="space-y-4">

            <!-- NAME -->
            <h1 class="text-3xl font-semibold text-gray-800">
                <?= htmlspecialchars($product['name']); ?>
            </h1>

            <!-- BRAND + CATEGORY -->
            <p class="text-gray-600 text-sm">Brand: 
                <span class="font-semibold text-gray-700"><?= htmlspecialchars($brandName); ?></span>
            </p>

            <p class="text-gray-600 text-sm">Category: 
                <span class="font-semibold text-gray-700"><?= htmlspecialchars($categoryName); ?></span>
            </p>

            <!-- PRICE SECTION -->
            <div class="flex items-center gap-3">
                <span class="text-3xl font-bold text-green-600">
                    ₹<?= number_format($simpleDiscountedPrice); ?>
                </span>

                <?php if ($discountRate > 0): ?>
                <span class="text-gray-500 line-through text-lg">
                    ₹<?= number_format($grossPrice); ?>
                </span>
                <span class="px-2 py-1 bg-green-700 text-white text-xs rounded">
                    <?= $discountRate; ?>% OFF
                </span>
                <?php endif; ?>
            </div>

            <!-- STOCK -->
            <p class="text-sm">
                <span class="font-semibold">Stock:</span> 
                <?= $product['stock'] > 0 ? '<span class="text-green-600">Available</span>' : '<span class="text-red-600">Out of stock</span>' ?>
            </p>

            <!-- QUANTITY -->
            <div>
                <p class="text-sm font-semibold mb-1">Choose Quantity:</p>

                <div class="flex items-center w-32 border rounded-lg shadow-sm bg-gray-50">
                    <button onclick="adjust(-1)" 
                        class="px-3 py-2 text-lg font-bold hover:bg-gray-200">−</button>

                    <input id="qty" type="number" value="1" min="1"
                        class="w-full text-center border-x py-2">

                    <button onclick="adjust(1)" 
                        class="px-3 py-2 text-lg font-bold hover:bg-gray-200">+</button>
                </div>
            </div>

            <!-- ADD TO CART -->
            <button onclick="sendToCart()"
                class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-lg">
                Add to Cart <i class="fas fa-shopping-cart ml-2"></i>
            </button>

        </div>
    </div>

    <!-- FULL DETAILS SECTION -->
<div class="mt-12 space-y-10">

    <!-- DESCRIPTION -->
    <?php if(!empty($product['description'])): ?>
    <div class="p-6 bg-gradient-to-br from-white to-blue-50 rounded-2xl border border-blue-100 shadow-md">
        <h2 class="text-xl font-semibold text-gray-900 mb-3">Description</h2>
        <p class="text-gray-700 leading-relaxed"><?= nl2br($product['description']); ?></p>
    </div>
    <?php endif; ?>

    <!-- NUTRITION -->
    <?php if(!empty($product['nutrition'])): ?>
    <div class="p-6 bg-gradient-to-br from-white to-green-50 rounded-2xl border border-green-100 shadow-md">
        <h2 class="text-xl font-semibold text-gray-900 mb-3">Nutritional Information</h2>
        <p class="text-gray-700 leading-relaxed"><?= nl2br($product['nutrition']); ?></p>
    </div>
    <?php endif; ?>


    <!-- PRODUCT DETAILS -->
    <div class="p-6 bg-gradient-to-br from-white to-green-50 rounded-2xl border border-green-100 shadow-md">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Product Details</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-gray-800">

    <?php if($product['weight']): ?>
    <div >
        <p class="text-sm text-gray-500">Weight</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['weight']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['packaging_type']): ?>
    <div >
        <p class="text-sm text-gray-500">Packaging</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['packaging_type']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['product_form']): ?>
    <div >
        <p class="text-sm text-gray-500">Form</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['product_form']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['origin']): ?>
    <div >
        <p class="text-sm text-gray-500">Origin</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['origin']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['grade']): ?>
    <div >
        <p class="text-sm text-gray-500">Grade</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['grade']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['purity']): ?>
    <div >
        <p class="text-sm text-gray-500">Purity</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['purity']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['flavor']): ?>
    <div >
        <p class="text-sm text-gray-500">Flavor</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['flavor']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['shelf_life']): ?>
    <div >
        <p class="text-sm text-gray-500">Shelf Life</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['shelf_life']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['storage_instructions']): ?>
    <div >
        <p class="text-sm text-gray-500">Storage</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['storage_instructions']); ?></p>
    </div>
    <?php endif; ?>

    <?php if($product['expiry_info']): ?>
    <div >
        <p class="text-sm text-gray-500">Expiry</p>
        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($product['expiry_info']); ?></p>
    </div>
    <?php endif; ?>

</div>


    </div>

</div>



</div>

<script>
function adjust(val) {
    let qty = document.getElementById("qty");
    qty.value = Math.max(1, parseInt(qty.value) + val);
}

const PRODUCT_DATA = {
    id: <?= $id; ?>,
    name: "<?= addslashes($product['name']); ?>",
    oldamt: <?= $grossPrice; ?>,  
    discountRate: <?= $discountRate; ?>,
    gstRate: <?= $gstRate ?? 0 ?>,
    price: <?= $simpleDiscountedPrice; ?>, 
    image: "<?= $displayImgPath; ?>"
};

function sendToCart() {
    const quantity = parseInt(document.getElementById("qty").value) || 1;

    addToCart({
        ...PRODUCT_DATA,
        quantity: quantity
    });
}
</script>
  <?php require_once $_SERVER["DOCUMENT_ROOT"] ."/includes/footer.php"; ?>
    
    <div id="toast-container"></div>
</body>
</html>
