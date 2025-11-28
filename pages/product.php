<?php
require_once 'vendor/autoload.php';
require_once __DIR__ . "/../includes/env.php";

$id = $_GET['id'] ?? null;
if (!$id) exit("Invalid Product");

$stmt = $conn->prepare("SELECT * FROM items WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

$gstRate = isset($settings['gst_rate']) ? floatval($settings['gst_rate']) : 18;
if (!$product) exit("Product Not Found");

// ----------------------- IMAGE LOGIC -----------------------
$brand = htmlspecialchars($product['brand'], ENT_QUOTES, 'UTF-8');
$image = basename($product['image']);

$originalImgPath = "./admin/Uploads/$brand/$image";
$compressedImgPath = "./admin/Uploads/compressed/$brand/$image";
$defaultImage = "./images/default.png";

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
<?php include "includes/header.php"; ?>

<div class="product-wrapper">

    <div class="product-img-container">
        <img src="<?= $displayImgPath ?>" class="product-img" alt="<?= htmlspecialchars($product['name']); ?>">
    </div>

    <div class="product-info">
        <h1><?= htmlspecialchars($product['name']); ?></h1>
        <p class="brand">Brand: <?= htmlspecialchars($product['brand']); ?></p>
        <p class="brand">Category: <?= htmlspecialchars($product['category']); ?></p>

        <!-- PRICE DISPLAY -->
        <div class="price-section">
            <span class="price-new">₹<?= number_format($simpleDiscountedPrice); ?></span>
            <?php if ($discountRate > 0): ?>
                <span class="old-price">₹<?= number_format($grossPrice); ?></span>
                <span class="badge-discount"><?= $discountRate; ?>% OFF</span>
            <?php endif; ?>
        </div>

        

        <p class="stock-label">🔥 Pieces: <?= $product['pieces']; ?></p>

      <div class="qty"> <h6>Choose Quantity :</h6> <div class="qty-box">
         <button class="qty-btn" onclick="adjust(-1)"><i class="fa-solid fa-minus"></i></button> 
         <hr class="hrline"> 
         <input id="qty" class="qty-input" type="number" value="1" min="1"> 
         <hr class="hrline"> 
         <button class="qty-btn" onclick="adjust(1)"><i class="fa-solid fa-plus"></i></button> 
        </div> 
    </div>

        <button class="btn add-cart" onclick="sendToCart()">
            Add to Cart <i class="fas fa-shopping-cart"></i>
        </button>
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
    price: <?= $simpleDiscountedPrice; ?>, // discounted price
    brand: "<?= addslashes($product['brand']); ?>",
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

</body>
</html>
