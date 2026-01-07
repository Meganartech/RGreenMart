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
$defaultImage = "./images/default.jpg";

$imgStmt = $conn->prepare("SELECT compressed_path, image_path FROM item_images WHERE item_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
$imgStmt->execute([$id]);
$img = $imgStmt->fetch(PDO::FETCH_ASSOC);

if ($img && (!empty($img['compressed_path']) || !empty($img['image_path']))) {
    $candidate = !empty($img['compressed_path']) ? $img['compressed_path'] : $img['image_path'];
    $variants_paths = ['/' . ltrim($candidate, '/'), '/admin/' . ltrim($candidate, '/')];
    $found = false;
    foreach ($variants_paths as $v) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $v)) {
            $displayImgPath = $v;
            $found = true;
            break;
        }
    }
    if (!$found) $displayImgPath = $defaultImage;
} else {
    $image = basename($product['image'] ?? '');
    $originalImgPath = "./admin/Uploads/$image";
    $compressedImgPath = "./admin/Uploads/compressed/$image";
    $displayImgPath = file_exists($compressedImgPath) ? $compressedImgPath : (file_exists($originalImgPath) ? $originalImgPath : $defaultImage);
}

$displayImgPath = htmlspecialchars($displayImgPath, ENT_QUOTES, 'UTF-8');

// --- Fetch all images for gallery ---
$images = [];
$allStmt = $conn->prepare("SELECT id, image_path, compressed_path, is_primary FROM item_images WHERE item_id = ? ORDER BY is_primary DESC, sort_order ASC");
$allStmt->execute([$id]);
$rows = $allStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $candidate = !empty($r['compressed_path']) ? $r['compressed_path'] : $r['image_path'];
    if (empty($candidate)) continue;
    $v_check = ['/' . ltrim($candidate, '/'), '/admin/' . ltrim($candidate, '/')];
    $src = null;
    foreach ($v_check as $vc) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $vc)) { $src = $vc; break; }
    }
    if (!$src) continue;
    $images[] = ['id' => $r['id'], 'src' => $src, 'is_primary' => (bool)$r['is_primary']];
}

if (empty($images)) {
    $image = basename($product['image'] ?? '');
    if ($image) {
        $orig = '/admin/Uploads/' . $image;
        $comp = '/admin/Uploads/compressed/' . $image;
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $comp)) $images[] = ['id'=>0,'src'=>$comp,'is_primary'=>true];
        elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . $orig)) $images[] = ['id'=>0,'src'=>$orig,'is_primary'=>true];
    }
}

$mainImageSrc = $displayImgPath;
$initialIndex = 0;
if (!empty($images)) {
    foreach ($images as $idx => $imgEntry) {
        if ($imgEntry['is_primary']) { $mainImageSrc = $imgEntry['src']; $initialIndex = $idx; break; }
    }
}
$mainImageSrc = htmlspecialchars($mainImageSrc, ENT_QUOTES, 'UTF-8');

// ----------------------- VARIANTS -----------------------
$varStmt = $conn->prepare("SELECT id, weight_value, weight_unit, price, old_price, discount, stock FROM item_variants WHERE item_id = ? AND status = 1 ORDER BY weight_value ASC");
$varStmt->execute([$id]);
$variants = $varStmt->fetchAll(PDO::FETCH_ASSOC);
$defaultVariant = $variants[0] ?? null;

// ----------------------- PRICE CALCULATION -----------------------
if ($defaultVariant) {
    $grossPrice = round($defaultVariant['price']);
    $discountRate = round((float)$defaultVariant['discount']);
    $simpleDiscountedPrice = round($grossPrice * (1 - ($discountRate / 100)));
    $stockQty = (int)$defaultVariant['stock'];
    $variantId = $defaultVariant['id'];
} else {
    $grossPrice = 0; $discountRate = 0; $simpleDiscountedPrice = 0; $stockQty = 0; $variantId = 0;
}

$catStmt = $conn->prepare("SELECT name FROM categories WHERE id=?");
$catStmt->execute([$product['category_id']]);
$categoryName = $catStmt->fetchColumn() ?: "Unknown";

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Variant Box Styles */
        .variant-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .variant-box { min-width: 110px; padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; text-align: center; background: #fff; transition: all 0.2s ease; }
        .variant-box:hover { border-color: #16a34a; }
        .variant-box.active { border-color: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,0.2); background: #f0fdf4; }
        .variant-weight { font-weight: 600; font-size: 15px; }
        .variant-price { font-size: 14px; color: #15803d; margin-top: 4px; }

        /* Carousel & Image Styles */
        .thumb-box { position:relative; cursor:pointer; border:2px solid #e5e7eb; border-radius:6px; width:100px; height:100px; overflow:hidden; display:flex; align-items:center; justify-content:center; }
        .thumb-box img { width:100%; height:100%; object-fit:cover; border-radius:6px; display:block; }
        .thumb-box.selected { border-color: #16a34a; box-shadow:0 0 0 3px rgba(16,185,129,0.12); }
        .image-area { display:flex; gap:1rem; align-items:flex-start; }
        .thumbs { flex: 0 0 auto; }
        .product-main { flex:1 1 auto; }

        @media (min-width: 768px) {
            .product-main { width:500px; height:500px; background:#fff; border-radius:12px; padding:10px; border:1px solid #e5e7eb; }
            .product-main .carousel, .product-main .carousel-inner, .product-main .carousel-item { height:480px; }
            .product-main .product-image { width:100%; height:100%; object-fit:contain; display:block; margin:0 auto; }
            .thumbs { min-width:120px; max-height:520px; overflow-y:auto; padding-right:6px; }
            .thumbs .thumb-box { width:90px; height:90px; margin-bottom:8px; }
            .thumbs::-webkit-scrollbar { width:8px; }
            .thumbs::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius:4px; }
        }

        @media (max-width: 767px) {
            .image-area { flex-direction: column; }
            .thumbs { width:100%; display:flex; flex-direction:row; gap:0.5rem; overflow-x:auto; padding:0.5rem 0; }
            .thumbs .thumb-box { width:70px; height:70px; flex:0 0 auto; }
            .product-main { width:100%; height:auto; max-height:350px; }
        }

        .product-main .carousel-control-prev, .product-main .carousel-control-next {
            position: absolute; top: 50%; transform: translateY(-50%); width:44px; height:44px; border-radius:50%; background: rgba(0,0,0,0.35); display:flex; align-items:center; justify-content:center; border: none; z-index:20;
        }
    </style>
</head>

<body>
    <?php require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/header.php"; ?>

    <div class="p-6 m-10 bg-white shadow-lg rounded-xl mb-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
            <div class="w-full image-area flex gap-4 items-start">
                <div id="thumbsColumn" class="thumbs flex flex-col items-center gap-3">
                    <?php if (!empty($images)): ?>
                        <?php foreach($images as $i => $imgEntry): ?>
                            <div class="thumb-box <?= ($i === $initialIndex) ? 'selected' : '' ?>" data-bs-target="#productCarousel" data-bs-slide-to="<?= $i ?>" role="button">
                                <img src="<?= htmlspecialchars($imgEntry['src'], ENT_QUOTES, 'UTF-8') ?>" alt="thumb" />
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="thumb-box selected">
                            <img src="<?= $displayImgPath ?>" alt="thumb" />
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex-1 product-main">
                    <div id="productCarousel" class="carousel slide">
                        <div class="carousel-inner">
                            <?php foreach($images as $i => $imgEntry): ?>
                                <div class="carousel-item <?= ($i === $initialIndex) ? 'active' : '' ?>">
                                    <img src="<?= htmlspecialchars($imgEntry['src'], ENT_QUOTES, 'UTF-8') ?>" class="d-block product-image" alt="Product image">
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($images)): ?>
                                <div class="carousel-item active">
                                    <img src="<?= $displayImgPath ?>" class="d-block product-image" alt="Product image">
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <h1 class="text-3xl font-semibold text-gray-800"><?= htmlspecialchars($product['name']); ?></h1>
                <p class="text-gray-600 text-sm"><?= nl2br($product['description']); ?></p>

                <?php if (!empty($variants)): ?>
                <div class="mt-4">
                    <p class="text-sm font-semibold mb-2">Select Size / Weight:</p>
                    <div class="variant-grid">
                        <?php foreach ($variants as $index => $v): ?>
                        <div class="variant-box <?= $index === 0 ? 'active' : '' ?>"
                            data-id="<?= $v['id'] ?>"
                            data-price="<?= $v['price'] ?>"
                            data-discount="<?= $v['discount'] ?>"
                            data-stock="<?= $v['stock'] ?>"
                            data-name="<?= rtrim($v['weight_value'], '0.') . $v['weight_unit'] ?>"
                            data-weight-value="<?= htmlspecialchars($v['weight_value']) ?>"
                            data-weight-unit="<?= htmlspecialchars($v['weight_unit']) ?>"
                        >
                            <div class="variant-weight"><?= rtrim($v['weight_value'], '0.') . $v['weight_unit'] ?></div>
                            <div class="variant-price">₹<?= number_format($v['price'] * (1 - $v['discount']/100)) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex items-center gap-3">
                    <span id="sellPrice" class="text-3xl font-bold text-green-600">₹<?= number_format($simpleDiscountedPrice); ?></span>
                    <?php if ($discountRate > 0): ?>
                        <span id="oldPrice" class="text-gray-500 line-through text-lg">₹<?= number_format($grossPrice); ?></span>
                        <span id="discountBadge" class="px-2 py-1 bg-green-700 text-white text-xs rounded"><?= $discountRate; ?>% OFF</span>
                    <?php endif; ?>
                </div>
                <div id="selectedVariantInfo" class="text-sm text-gray-700 mt-2">
                    <!-- Selected variant details will appear here -->
                    <span id="sv-name"><?= htmlspecialchars(rtrim($defaultVariant['weight_value'] ?? '', '0.') . ($defaultVariant['weight_unit'] ?? '')) ?></span>
                    &nbsp;|&nbsp;
                    <span id="sv-price">₹<?= number_format($simpleDiscountedPrice); ?></span>
                    <?php if ($discountRate > 0): ?>
                        &nbsp;|&nbsp;<span id="sv-discount"><?= $discountRate; ?>% OFF</span>
                    <?php endif; ?>
                </div>

                <p class="text-sm mt-1">
                    <span class="font-semibold">Stock:</span>
                    <span id="stockText" class="<?= $stockQty > 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $stockQty > 0 ? 'Available' : 'Out of stock' ?>
                    </span>
                </p>

                <div>
                    <p class="text-sm font-semibold mb-1">Choose Quantity:</p>
                    <div class="flex items-center w-32 border rounded-lg shadow-sm bg-gray-50">
                        <button onclick="adjust(-1)" class="px-3 py-2 text-lg font-bold hover:bg-gray-200">−</button>
                        <input id="qty" type="number" value="1" min="1" class="w-full text-center border-x py-2">
                        <button onclick="adjust(1)" class="px-3 py-2 text-lg font-bold hover:bg-gray-200">+</button>
                    </div>
                </div>

                <button onclick="sendToCart()" class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-lg">
                    Add to Cart <i class="fas fa-shopping-cart ml-2"></i>
                </button>
            </div>
        </div>

        <div class="mt-12 space-y-10">

            <div class="p-6 bg-gradient-to-br from-white to-green-50 rounded-2xl border border-green-100 shadow-md">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Product Details</h2>
                  <?php if(!empty($product['nutrition'])): ?>
          
            <?php endif; ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-gray-800">
                    <?php 
                    $fields = [
                        'Nutritional Info' => $product['nutrition'],
                        'Origin' => $product['origin'], 
                        'Grade' => $product['grade'], 
                        'Packaging Type' => $product['packaging_type'],
                    'Product Form'   => $product['product_form'],
                        'Purity' => $product['purity'],
                        'Flavor' => $product['flavor'], 
                        'Shelf Life' => $product['shelf_life'], 
                        'Storage' => $product['storage_instructions'], 
                        'Expiry' => $product['expiry_info']
                    ];
                    foreach($fields as $label => $val): if($val): ?>
                    <div>
                        <p class="text-sm text-gray-500"><?= $label ?></p>
                        <p class="text-lg font-semibold pl-3"><?= htmlspecialchars($val); ?></p>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once $_SERVER["DOCUMENT_ROOT"] ."/includes/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
  const PRODUCT_DATA = {
    id: <?= (int)$id; ?>,
    name: "<?= addslashes($product['name']); ?>",
    oldamt: <?= (float)$grossPrice; ?>,
    discountRate: <?= (float)$discountRate; ?>,
    gstRate: <?= (float)($gstRate ?? 0); ?>,
    price: <?= (float)$simpleDiscountedPrice; ?>, // This will store the price for 1 unit
    image: "<?= $mainImageSrc; ?>",
    variant_id: <?= (int)$variantId; ?>,
    variant_price: <?= (float)$simpleDiscountedPrice; ?>,
    variant_weight: "<?= htmlspecialchars($defaultVariant['weight_value'] ?? '') ?>",
    variant_unit: "<?= htmlspecialchars($defaultVariant['weight_unit'] ?? '') ?>"
};

function adjust(val) {
    let qtyInput = document.getElementById("qty");
    let currentQty = parseInt(qtyInput.value) || 1;
    qtyInput.value = Math.max(1, currentQty + val);
    
    updateDisplayPrice(); // Trigger price update
}

// Also trigger update if user types manually in the input
document.getElementById("qty").addEventListener('input', updateDisplayPrice);

function updateDisplayPrice() {
    const qty = parseInt(document.getElementById("qty").value) || 1;
    const unitPrice = PRODUCT_DATA.price; 
    const unitOldPrice = PRODUCT_DATA.oldamt;

    const totalSellPrice = unitPrice * qty;
    const totalOldPrice = unitOldPrice * qty;

    // Update the UI
    document.getElementById('sellPrice').innerText = '₹' + totalSellPrice.toLocaleString();
    
    const oldPriceEl = document.getElementById('oldPrice');
    if (oldPriceEl && PRODUCT_DATA.discountRate > 0) {
        oldPriceEl.innerText = '₹' + totalOldPrice.toLocaleString();
    }

    // Update selected variant information summary
    updateSelectedVariantInfo();
}

function updateSelectedVariantInfo() {
    const nameEl = document.getElementById('sv-name');
    const priceEl = document.getElementById('sv-price');
    const discountEl = document.getElementById('sv-discount');

    if (nameEl) nameEl.innerText = PRODUCT_DATA.variant_weight ? PRODUCT_DATA.variant_weight + (PRODUCT_DATA.variant_unit ? ' ' + PRODUCT_DATA.variant_unit : '') : '';
    if (priceEl) priceEl.innerText = '₹' + (PRODUCT_DATA.variant_price ?? PRODUCT_DATA.price).toLocaleString();
    if (discountEl) {
        if (PRODUCT_DATA.discountRate && PRODUCT_DATA.discountRate > 0) {
            discountEl.innerText = PRODUCT_DATA.discountRate + '% OFF';
            discountEl.style.display = 'inline';
        } else {
            discountEl.style.display = 'none';
        }
    }
}
    function sendToCart() {
        const quantity = parseInt(document.getElementById("qty").value) || 1;
        console.log('sendToCart - adding to cart', {
            id: PRODUCT_DATA.id ?? PRODUCT_DATA.item_id ?? null,
            variant_id: PRODUCT_DATA.variant_id ?? null,
            variant_price: PRODUCT_DATA.variant_price ?? PRODUCT_DATA.price ?? null,
            variant_weight: PRODUCT_DATA.variant_weight ?? null,
            variant_unit: PRODUCT_DATA.variant_unit ?? null,
            quantity
        });
        addToCart({ ...PRODUCT_DATA, quantity: quantity });
    }

    // Carousel Sync Logic
    const carouselEl = document.getElementById('productCarousel');
    const bsCarousel = new bootstrap.Carousel(carouselEl, { interval: false });
    const thumbs = document.querySelectorAll('#thumbsColumn .thumb-box');

    thumbs.forEach((t, i) => {
        t.addEventListener('click', () => {
            bsCarousel.to(i);
            thumbs.forEach(x => x.classList.remove('selected'));
            t.classList.add('selected');
        });
    });

    // Variant Update Logic
document.querySelectorAll('.variant-box').forEach(box => {
    box.addEventListener('click', () => {
        document.querySelectorAll('.variant-box').forEach(b => b.classList.remove('active'));
        box.classList.add('active');

        const price = parseFloat(box.dataset.price);
        const discount = parseFloat(box.dataset.discount);
        const stock = parseInt(box.dataset.stock);
        const finalUnitPrice = Math.round(price * (1 - discount / 100));

        // Update the Global Object with the NEW unit price and variant info
        PRODUCT_DATA.price = finalUnitPrice;
        PRODUCT_DATA.oldamt = price;
        PRODUCT_DATA.discountRate = discount;
        PRODUCT_DATA.variant_id = box.dataset.id;
        PRODUCT_DATA.variant_price = finalUnitPrice;
        PRODUCT_DATA.variant_weight = box.dataset.weightValue || box.dataset.weight_value || '';
        PRODUCT_DATA.variant_unit = box.dataset.weightUnit || box.dataset.weight_unit || '';


        // Update Stock UI
        const stockText = document.getElementById('stockText');
        stockText.innerText = stock > 0 ? 'Available' : 'Out of stock';
        stockText.className = stock > 0 ? 'text-green-600' : 'text-red-600';

        // Update Discount Badge
        const discountBadge = document.getElementById('discountBadge');
        if(discountBadge) {
            if(discount > 0) {
                discountBadge.innerText = discount + '% OFF';
                discountBadge.style.display = 'inline';
            } else {
                discountBadge.style.display = 'none';
            }
        }

        // Final Step: Refresh the total price based on existing quantity
        updateDisplayPrice();
        console.log('variant selected', {
            variant_id: PRODUCT_DATA.variant_id ?? null,
            variant_price: PRODUCT_DATA.variant_price ?? PRODUCT_DATA.price ?? null,
            variant_weight: PRODUCT_DATA.variant_weight ?? null,
            variant_unit: PRODUCT_DATA.variant_unit ?? null
        });
    });
});
    </script>

     <div id="toast-container"></div>
</body>
</html>