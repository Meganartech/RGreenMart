<?php
require_once 'vendor/autoload.php';

// Fetch GST rate from the settings table
$stmt = $conn->prepare("SELECT gst_rate, last_enquiry_number FROM settings LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$gstRate = isset($settings['gst_rate']) ? floatval($settings['gst_rate']) : 18;
$minimumOrder = 2000; // Minimum order amount

// Fetch shop details from DB
$stmt = $conn->prepare("SELECT name, shopaddress, phone, email FROM admin_details LIMIT 1");
$stmt->execute();
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

$adminName = $shop['name'] ?? 'RGreenMart';
$shopAddress = $shop['shopaddress'] ?? 'Chandragandhi Nagar, Madurai, Tamil Nadu';
$shopPhone = $shop['phone'] ?? '99524 24474';
$shopEmail = $shop['email'] ?? 'sales@rgreenmart.com';

// Fetch data from items table
$stmt = $conn->prepare("SELECT * FROM items");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GST Rate (Set from your code)
$gstRate = 18; // <-- Change if needed

$processedItems = [];

foreach ($items as $idx => $item) {

    // -------- PRICE CALCULATIONS -------- //
    $grossPrice = round($item['price']);
    $netPrice   = round($item['price'] / (1 + $gstRate / 100));
    $gstAmount  = $grossPrice - $netPrice;

    $discountRate = (float) $item['discount'];
    $discountAmount = round($netPrice * ($discountRate / 100));
    $simpleDiscountedPrice = round($grossPrice * (1 - ($discountRate / 100)));

    // -------- IMAGE PATH HANDLING -------- //

    $imageFile = trim($item['image']);   // Example: Uploads/abc.jpg
    $compressedFile = trim($item['compressed_image']);

    $defaultPublicImage = "/images/default.jpg";

    // Physical file paths
$serverOriginal   = $_SERVER["DOCUMENT_ROOT"] . "/admin/" . $imageFile;

$serverCompressed = $_SERVER["DOCUMENT_ROOT"] . "/admin/" . $compressedFile;

$publicOriginal   = "/admin/" . $imageFile;
$publicCompressed = "/admin/" . $compressedFile;


    // Select best available image
    if (!empty($imageFile) && file_exists($serverOriginal)) {
        $displayImgPath = $publicOriginal;
    } elseif (!empty($compressedFile) && file_exists($serverCompressed)) {
        $displayImgPath = $publicCompressed;
    } else {
        $displayImgPath = $defaultPublicImage;
    }

    // -------- BUILD CLEAN OUTPUT ARRAY -------- //
    $processedItems[$idx] = [
        // BASIC FIELDS
        'id'          => htmlspecialchars($item['id'], ENT_QUOTES),
        'name'        => htmlspecialchars($item['name'], ENT_QUOTES),
        'category_id' => $item['category_id'],
        'brand_id'    => $item['brand_id'],

        // PRICING
        'price'                => $item['price'],
        'old_price'            => $item['old_price'],
        'discount'             => $discountRate,
        'grossPrice'           => $grossPrice,
        'netPrice'             => $netPrice,
        'gstAmount'            => $gstAmount,
        'discountAmount'       => $discountAmount,
        'simpleDiscountedPrice'=> $simpleDiscountedPrice,

        // STOCK
        'stock'  => $item['stock'],
        'status' => $item['status'],

        // IMAGES
        'image'           => $publicOriginal,
        'compressedImage' => $publicCompressed,
        'displayImgPath'  => htmlspecialchars($displayImgPath, ENT_QUOTES),

        // PRODUCT DETAILS
        'weight'        => $item['weight'],
        'packaging_type'=> $item['packaging_type'],
        'product_form'  => $item['product_form'],
        'origin'        => $item['origin'],
        'grade'         => $item['grade'],
        'purity'        => $item['purity'],
        'flavor'        => $item['flavor'],

        // TEXT INFO
        'description'           => $item['description'],
        'nutrition'             => $item['nutrition'],
        'shelf_life'            => $item['shelf_life'],
        'storage_instructions'  => $item['storage_instructions'],
        'expiry_info'           => $item['expiry_info'],

        // META
        'tags'        => $item['tags'],
        'created_at'  => $item['created_at'],
        'updated_at'  => $item['updated_at'],

        'discountRate' => $discountRate,
    ];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RGreenMart</title>
    <link rel="icon" type="image/png" href="./images/LOGO.jpg">
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="cart.js"></script>
    <link rel="stylesheet" type="text/css" href="./Styles.css">

</head>

<body>
    <section id="hero" class="d-flex align-items-center justify-content-center" style="position: relative; z-index: 1;">
  <div id="bannerCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
    <!-- Indicators (small dots) -->
    <div class="carousel-indicators">
      <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
      <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
      <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>

    <!-- Carousel Inner -->
    <div class="carousel-inner">
      <div class="carousel-item active" data-bs-interval="5000" style=" position: relative;">
        <img src="images/BANNER1.png" class="d-block w-100" alt="Banner 1" style="object-fit: cover; max-height: 80vh;">
      </div>
      <div class="carousel-item" data-bs-interval="5000" style="position: relative;">
        <img src="images/BANNER3.png" class="d-block w-100" alt="Banner 3" style="object-fit: cover; max-height: 80vh;">
      </div>
      <div class="carousel-item" data-bs-interval="5000">
        <img src="images/BANNER2.png" class="d-block w-100" alt="Banner 2" style="object-fit: cover; max-height: 80vh;">
      </div>
    </div>

    <!-- Controls -->
    <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>
</section>
        <?php include "includes/header.php"; ?>

    <div id="main-body">
        <section id="products">

            <div class="container">


              <div class="product-grid">
    <?php foreach ($processedItems as $item): ?>

        <?php
        // Build the cart object for JS
        $cartData = [
            "id" => $item["id"],
            "name" => $item["name"],
            "oldamt" => $item["grossPrice"],
            "discountRate" => $item["discountRate"],
            "gstRate" => $gstRate,
            "price" => $item["simpleDiscountedPrice"],
            "image" => $item["displayImgPath"]
        ];
        ?>

        <a href="index.php?page=product&id=<?= $item['id']; ?>" >

            <div class="card-container"
                 data-idx="<?= $item['id']; ?>"
            >

                <!-- Product Image -->
                <div class="product-image">
                    <img src="<?= $item['displayImgPath']; ?>" alt="<?= $item['name']; ?>">

                    <?php if ($item['discountRate'] > 0): ?>
                        <span class="badge">-<?= $item['discountRate']; ?>%</span>
                    <?php endif; ?>
                </div>

                <!-- Title -->
                <p class="product-title"><?= $item['name']; ?></p>


                <!-- Price -->
                <p class="price">
                    <span class="old-price">₹<?= $item['grossPrice']; ?></span>
                    <span class="new-price">₹<?= $item['simpleDiscountedPrice']; ?></span>
                </p>

                <!-- Add to Cart -->
                <button class="add-to-cart-btn"
                        onclick='event.stopPropagation();
                                 event.preventDefault();
                                 saveToCart(<?= json_encode($cartData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>);'
                        title="Add to Cart">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>

            </div>

        </a>
    <?php endforeach; ?>
</div>

    </div>
    </section>

    <script>
function saveToCart(product) {

    // Add default quantity
    product.quantity = 1;

    // Now send it to addToCart()
    addToCart(product);

}
    </script>
    <?php include "includes/footer.php"; ?>
</body>

</html>