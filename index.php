<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

$res = $conn->prepare("SELECT * FROM carousel ORDER BY sort_order ASC");
$res->execute();
$slides = $res->fetchAll(PDO::FETCH_ASSOC);


// Fetch GST rate from the settings table
$stmt = $conn->prepare("SELECT gst_rate, last_enquiry_number,notification_text FROM settings LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$notificationText = !empty($settings['notification_text']) ? $settings['notification_text'] : null;
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
    <link rel="stylesheet" type="text/css" href="./Styles.css">

</head>

<body>
    
<main id="main" style="position: relative; z-index: 2;">
   <?php if ($notificationText): ?>
<div class="scrolling-text-container">
    <span class="scrolling-text">
        <?= htmlspecialchars($notificationText); ?>
    </span>
</div>
<?php endif; ?>

    <?php require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/header.php"; ?>
    <section id="hero" >
<div id="bannerCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">

  <!-- Indicators -->
  <div class="carousel-indicators">
    <?php foreach ($slides as $i => $slide): ?>
      <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="<?= $i ?>"
        class="<?= $i === 0 ? 'active' : '' ?>"></button>
    <?php endforeach; ?>
  </div>

  <!-- Slides -->
  <div class="carousel-inner">
    <?php foreach ($slides as $i => $slide): ?>
     <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>" data-bs-interval="5000">
        <img src="<?= $slide['image_path'] ?>" class="d-block w-100" 
             style="object-fit: cover; max-height: 80vh;">
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Controls -->
  <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
  </button>

</div>

</section>



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

       

            <div class="card-container"
                 data-idx="<?= $item['id']; ?>"
            >
 <a href="product.php?id=<?= $item['id']; ?>" >
                <!-- Product Image -->
                <div class="product-image">
                    <img src="<?= $item['displayImgPath']; ?>" alt="<?= $item['name']; ?>">

                    <?php if ($item['discountRate'] > 0): ?>
                        <span class="badge">-<?= $item['discountRate']; ?>%</span>
                    <?php endif; ?>
                </div>

              <p class="product-title"><?= htmlspecialchars($item['name']); ?></p>



                <!-- Price -->
                <p class="price">
                    
                    <span class="new-price">₹<?= $item['simpleDiscountedPrice']; ?></span>
                    <span class="old-price">₹<?= $item['grossPrice']; ?></span>
                </p>
                    </a>
                <!-- Add to Cart -->
                <button class="add-to-cart-btn"
                        onclick='event.stopPropagation();
                                 event.preventDefault();
                                 saveToCart(<?= json_encode($cartData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>);'
                        title="Add to Cart">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>

            </div>

     
    <?php endforeach; ?>
</div>

    </div>
    </section>
                    </main>

    <script>
function saveToCart(product) {

    // Add default quantity
    product.quantity = 1;

    // Now send it to addToCart()
    addToCart(product);

}
    </script>
    <?php require_once $_SERVER["DOCUMENT_ROOT"] ."/includes/footer.php"; ?>
    
    <div id="toast-container"></div>
    <script src="/cart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>