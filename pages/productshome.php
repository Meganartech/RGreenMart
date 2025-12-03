<?php
require_once 'vendor/autoload.php';
require_once __DIR__ . "/../includes/env.php";

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
$stmt = $conn->prepare("SELECT id,name, price, discount, pieces, items, category, brand, image FROM items");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Precompute values for each item
$processedItems = [];

foreach ($items as $idx => $item) {
    $grossPrice = round($item['price']); // integer
    $netPrice = round($item['price'] / (1 + $gstRate / 100)); // integer
    $gstAmount = $grossPrice - $netPrice; // integer by logic
    $discountAmount = round($netPrice * ((float) $item['discount'] / 100)); // integer
    $simpleDiscountedPrice = round($grossPrice * (1 - ((float) $item['discount'] / 100))); // integer

    // Original image path
    $originalImgPath = !empty($item['image']) && !empty($item['brand'])
        ? './admin/Uploads/' . htmlspecialchars($item['brand'], ENT_QUOTES, 'UTF-8') . '/' . basename($item['image'])
        : '';

    // Compressed image path
    $compressedImgPath = !empty($item['image']) && !empty($item['brand'])
        ? './admin/Uploads/compressed/' . htmlspecialchars($item['brand'], ENT_QUOTES, 'UTF-8') . '/' . basename($item['image'])
        : '';

$defaultImage = './images/default.png';

$displayImgPath = file_exists($compressedImgPath) ? $compressedImgPath :
                  (file_exists($originalImgPath) ? $originalImgPath : $defaultImage);

$displayImgPath = htmlspecialchars($displayImgPath, ENT_QUOTES, 'UTF-8');


    $processedItems[$idx] = [
        'id' => htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'),
        'name' => htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'),
        'category' => htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'),
        'category_raw' => $item['category'],
        'brand' => htmlspecialchars($item['brand'], ENT_QUOTES, 'UTF-8'),
        'pieces' => (int) $item['pieces'],
        'items' => (int) $item['items'],
        'grossPrice' => $grossPrice,
        'netPrice' => $netPrice,
        'gstAmount' => $gstAmount,
        'discountAmount' => $discountAmount,
        'simpleDiscountedPrice' => $simpleDiscountedPrice,
       'originalImgPath' => file_exists($originalImgPath)
    ? htmlspecialchars($originalImgPath, ENT_QUOTES, 'UTF-8')
    : './images/default.png',
        'displayImgPath' => $displayImgPath,
        'discountRate' => round((float) $item['discount']), // as integer
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
    <meta name="keywords"
        content="Deepavali crackers sale 2025, Buy crackers online Deepavali 2025, Diwali crackers offer 2025, Deepavali discount crackers online, Diwali crackers shop near me, Deepavali crackers combo offer 2025, Wholesale Diwali crackers online, Sivakasi crackers online shopping, Diwali crackers home delivery 2025, Best price Diwali crackers online, Cheapest Deepavali crackers online 2025, Eco-friendly Diwali crackers online 2025, Diwali crackers gift box sale 2025, Online cracker booking for Deepavali 2025, Buy Sivakasi crackers for Deepavali 2025, Buy crackers online Chennai Deepavali 2025, Diwali crackers sale Coimbatore 2025, Deepavali crackers shop Madurai 2025, Tirunelveli Deepavali crackers online, Salem Diwali crackers discount 2025, Deepavali crackers gift pack 2025, Green crackers for Diwali 2025, Cheap Diwali crackers online 2025, Buy Diwali crackers online Tamil Nadu 2025, Standard Fireworks Diwali crackers 2025, Ayyan Fireworks branded crackers online, Sony Fireworks crackers sale 2025, Sri Kaliswari branded crackers Deepavali 2025, RGreenMart crackers sale 2025, Trichy branded crackers discount Diwali 2025">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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
   <div class="sticky-header">
        <?php include "includes/header.php"; ?>
    </div>
    <div id="main-body">
        <section id="products">

            <div class="container">


                <div class="table-container">
                    <div class="product-grid">
                        <?php foreach ($processedItems as $item): ?>
                     <a href="index.php?page=product&id=<?= $item['id']; ?>" class="product-card">
<div  data-category="<?= $item['category_raw']; ?>" data-brand="<?= $item['brand']; ?>" data-idx="<?= $item['id']; ?>">


                            <div class="product-image">
                                <img src="<?= $item['displayImgPath']; ?>" alt="<?= $item['name']; ?>">
                                <?php if ($item['discountRate'] > 0): ?>
                                <span class="badge">-<?= $item['discountRate']; ?>%</span>
                                <?php endif; ?>
                            </div>

                            <p class="product-title"><?= $item['name']; ?></p>
                            <p class="brand"><?= $item['brand']; ?></p>

                            <p class="price">
                                <span class="old-price">₹<?= $item['grossPrice']; ?></span>
                                <span class="new-price">₹<?= $item['simpleDiscountedPrice']; ?></span>
                            </p>


                            <p class="total-text">Total: <span class="item-total"
                                    id="total-<?= $item['id']; ?>">₹0</span></p>

                        </div>
                                </a>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
    </div>
    </section>

    <script>
    // Precomputed items data from PHP
    const itemsData = <?php echo json_encode($processedItems); ?>;
    const gstRate = <?php echo $gstRate; ?>;
    const brandPdfs = <?php echo json_encode($brandPdfs); ?>;

    let selectedBrand = 'all';
    let selectedCategory = 'all';

    // Cache DOM elements
    const qtyInputs = document.querySelectorAll('.qty');
    const tableRows = document.querySelectorAll('#productsTable tbody tr');
    const mobileCards = document.querySelectorAll('.mobile-card');
    const totalDisplay = document.getElementById('total');
    const discountTotalDisplay = document.getElementById('discountTotal');
    const netRateDisplay = document.getElementById('netRate');
    const couponDiscountDisplay = document.getElementById('couponDiscount');
    const gstDisplay = document.getElementById('gst');
    const sumgstDisplay = document.getElementById('sumgst');
    const overallTotalDisplay = document.getElementById('overallTotal');
    const afterCouponNetRateDisplay = document.getElementById('afterCouponNetRate');
    const finalTotalDisplay = document.getElementById('finalTotal');
    const netTotalSpan = document.querySelector('.net_total');
    const discountTotalSpan = document.querySelector('.discount_total');
    const subTotalSpan = document.querySelector('.sub_total');
    const totalProductsCount = document.querySelector('.total_products_count');
    const downloadPriceList = document.getElementById('downloadPriceList');

    // Category header management
    function rebuildCategoryHeaders() {
        const tbody = document.querySelector('#productsTable tbody');
        if (!tbody) return;

        tbody.querySelectorAll('.category-header').forEach(row => row.remove());

        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.classList.contains('category-header'));

        let lastCategory = null;

        rows.forEach(row => {
            const category = row.dataset.category;
            if (row.classList.contains('hidden')) return;
            if (category !== lastCategory) {
                lastCategory = category;
                const headerRow = document.createElement('tr');
                headerRow.classList.add('category-header');
                const headerCell = document.createElement('td');
                headerCell.setAttribute('colspan', '10');
                headerCell.textContent = category;
                headerRow.appendChild(headerCell);
                tbody.insertBefore(headerRow, row);
            }
        });
    }

    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Lazy load images
    function lazyLoadImages() {
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => {
            const container = img.closest('.image-container');
            if (container && container.closest('.hidden') === null) {
                img.src = img.dataset.src;
                img.classList.add('loaded');
                img.removeAttribute('data-src');
            }
        });
    }

    function updateQuantity(idx, change) {
        const inputs = document.querySelectorAll(`.qty[data-idx="${idx}"]`);
        let newValue = 0;
        inputs.forEach(input => {
            const currentValue = parseInt(input.value) || 0;
            newValue = Math.max(0, currentValue + change);
            input.value = newValue;
        });

        // Toggle 'selected' class based on quantity
        document.querySelectorAll(`[data-idx="${idx}"]`).forEach(row => {
            if (newValue > 0) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });

        debouncedRecalcTotals();
    }

    function resetQuantities() {
        qtyInputs.forEach(input => input.value = 0);
        document.querySelectorAll('.action-button').forEach(button => button.disabled = true);
        document.getElementById('couponCode').value = '';
        document.getElementById('couponCodeHidden').value = '';
        document.getElementById('couponDiscountHidden').value = '0';
        document.getElementById('couponDiscountPercentHidden').value = '0';
        debouncedRecalcTotals();
    }

    function recalcTotals() {
        let subtotal = 0;
        let totalDiscountAmount = 0;
        let totalNetRate = 0;
        let totalGst = 0;
        let totalItems = 0;
        let finalTotal = 0;

        Object.entries(itemsData).forEach(([idx, item]) => {
            const qtyInput = document.querySelector(`.qty[data-idx="${idx}"]`);
            const qty = qtyInput ? parseInt(qtyInput.value) || 0 : 0;
            if (qty === 0) return;

            const {
                grossPrice,
                discountRate,
                netPrice,
                discountAmount,
                simpleDiscountedPrice
            } = item;
            const itemTotal = Math.round(simpleDiscountedPrice * qty); // Match pdf_generation.php

            document.querySelectorAll(`[data-idx="${idx}"]`).forEach(row => {
                const totalEl = row.querySelector('.item-total');
                if (totalEl) totalEl.textContent = itemTotal; // Display integer
                const actionButton = row.querySelector('.action-button');
                if (actionButton) actionButton.disabled = qty === 0;
            });

            subtotal += grossPrice * qty;
            totalDiscountAmount += discountAmount * qty;
            totalNetRate += (netPrice - discountAmount) * qty;
            totalGst += (netPrice - discountAmount) * (gstRate / 100) * qty;
            finalTotal += itemTotal; // Sum of item totals for finalTotal
            totalItems += qty;
        });

        let couponDiscount = parseFloat(document.getElementById('couponDiscountHidden').value) || 0;
        const couponDiscountPercent = parseFloat(document.getElementById('couponDiscountPercentHidden').value) || 0;
        if (couponDiscountPercent > 0) {
            couponDiscount = Math.round((totalNetRate * couponDiscountPercent) / 100); // Integer
            document.getElementById('couponDiscountHidden').value = couponDiscount;
        }
        const discountedNetRate = Math.round(totalNetRate - couponDiscount); // Integer
        const finalGst = Math.round(discountedNetRate * (gstRate / 100)); // Integer
        const displayDiscount = Math.round(subtotal - (totalNetRate + totalGst)); // Integer

        totalDisplay.textContent = '₹' + Math.round(subtotal);
        discountTotalDisplay.textContent = '- ₹' + displayDiscount;
        netRateDisplay.textContent = Math.round(totalNetRate + totalGst);
        couponDiscountDisplay.textContent = '- ₹' + couponDiscount;
        gstDisplay.textContent = '₹' + finalGst;
        sumgstDisplay.textContent = '₹' + finalGst;
        overallTotalDisplay.textContent = '₹' + finalTotal;
        finalTotalDisplay.textContent = '₹' + finalTotal;
        netTotalSpan.textContent = subtotal;
        discountTotalSpan.textContent = '- ' + displayDiscount;
        subTotalSpan.textContent = Math.round(totalNetRate + totalGst);
        totalProductsCount.textContent = totalItems;
        afterCouponNetRateDisplay.textContent = '₹' + discountedNetRate;
    }

    const debouncedRecalcTotals = debounce(recalcTotals, 100);

    function applyCoupon() {
        const couponCode = document.getElementById('couponCode').value.trim();
        if (!couponCode) {
            alert('Please enter a coupon code.');
            return;
        }

        fetch('checkcoupon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'coupon_code=' + encodeURIComponent(couponCode)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const couponDiscount = (netRate * data.discount_percent) / 100;
                    document.getElementById('couponCodeHidden').value = couponCode;
                    document.getElementById('couponDiscountHidden').value = couponDiscount.toFixed(2);
                    document.getElementById('couponDiscountPercentHidden').value = data.discount_percent;
                    alert('Coupon applied successfully! Discount: ' + data.discount_percent + '%');
                } else {
                    document.getElementById('couponCodeHidden').value = '';
                    document.getElementById('couponDiscountHidden').value = '0';
                    document.getElementById('couponDiscountPercentHidden').value = '0';
                    alert('Invalid or expired coupon code.');
                }
                debouncedRecalcTotals();
            })
            .catch(error => {
                console.error('Error checking coupon:', error);
                alert('Error applying coupon. Please try again.');
                document.getElementById('couponCodeHidden').value = '';
                document.getElementById('couponDiscountHidden').value = '0';
                document.getElementById('couponDiscountPercentHidden').value = '0';
                debouncedRecalcTotals();
            });
    }

    function attachEventListeners() {
        document.querySelector('.table-container').addEventListener('click', function(event) {
            const target = event.target.closest('.minus, .plus');
            if (!target) return;
            const idx = target.dataset.idx;
            const change = target.classList.contains('minus') ? -1 : 1;
            updateQuantity(idx, change);
        });

        document.querySelector('.mobile-cards').addEventListener('click', function(event) {
            const target = event.target.closest('.minus, .plus');
            if (!target) return;
            const idx = target.dataset.idx;
            const change = target.classList.contains('minus') ? -1 : 1;
            updateQuantity(idx, change);
        });

        const debouncedInputHandler = debounce(function(event) {
            const idx = event.target.dataset.idx;
            const value = parseInt(event.target.value) || 0;
            document.querySelectorAll(`.qty[data-idx="${idx}"]`).forEach(inp => {
                inp.value = Math.max(0, value);
            });
            debouncedRecalcTotals();
        }, 100);

        document.querySelector('.table-container').addEventListener('input', function(event) {
            if (event.target.classList.contains('qty')) {
                debouncedInputHandler(event);
            }
        });

        document.querySelector('.mobile-cards').addEventListener('input', function(event) {
            if (event.target.classList.contains('qty')) {
                debouncedInputHandler(event);
            }
        });

        document.getElementById('applyCoupon').addEventListener('click', applyCoupon);
    }

    function collectItems() {
        const items = [];
        Object.entries(itemsData).forEach(([idx, item]) => {
            const qtyInput = document.querySelector(`.qty[data-idx="${idx}"]`);
            const quantity = qtyInput ? parseInt(qtyInput.value) || 0 : 0;
            if (quantity > 0) {
                items.push({
                    id: item.id, // Product ID
                    name: item.name, // Product Name
                    brand: item.brand, // Brand
                    category: item.category, // Category
                    pieces: item.pieces, // Pieces (part of Content)
                    items: item.items, // Items (part of Content)
                    grossPrice: item.grossPrice, // Price (Inc. GST)
                    simpleDiscountedPrice: item.simpleDiscountedPrice, // Discounted Price
                    quantity: quantity, // Quantity
                    discount: item.discountRate // Discount Rate (for reference)
                });
            }
        });
        return items;
    }

    function updateCategoryButtons(selectedBrand) {
        const categories = [...new Set(Object.values(itemsData)
            .filter(item => selectedBrand === 'all' || item.brand === selectedBrand)
            .map(item => item.category_raw))].sort();

        const categoryButtonsContainer = document.querySelector('.category-buttons');
        const fragment = document.createDocumentFragment();

        const allButton = document.createElement('button');
        allButton.type = 'button';
        allButton.className = 'category-button active';
        allButton.dataset.category = 'all';
        allButton.textContent = 'All Categories';
        fragment.appendChild(allButton);

        categories.forEach(category => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'category-button';
            button.dataset.category = category;
            button.textContent = category.replace(/&quot;/g, '"');
            fragment.appendChild(button);
        });

        categoryButtonsContainer.innerHTML = '';
        categoryButtonsContainer.appendChild(fragment);

        document.querySelectorAll('.category-button').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.category-button').forEach(btn => btn.classList.remove(
                    'active'));
                this.classList.add('active');
                applyFilters();
            });
        });
    }

    function applyFilters() {
        const selectedCategory = document.querySelector('.category-button.active')?.dataset.category || 'all';
        const selectedBrand = document.querySelector('.brand-button.active')?.dataset.brand || 'all';
        const categoryTitle = document.getElementById('categoryTitle');
        categoryTitle.textContent = selectedCategory === 'all' ? 'All Products' : selectedCategory.replace(/&quot;/g,
            '"').charAt(0).toUpperCase() + selectedCategory.replace(/&quot;/g, '"').slice(1).toLowerCase();

        // Show/hide download button based on brand selection
        if (selectedBrand !== 'all' && brandPdfs[selectedBrand]) {
            downloadPriceList.style.display = 'block';
            downloadPriceList.href = brandPdfs[selectedBrand];
            downloadPriceList.download = selectedBrand + '_pricelist.pdf';
        } else {
            downloadPriceList.style.display = 'none';
        }

        document.querySelectorAll('#productsTable tbody tr').forEach(row => {
            const category = row.dataset.category;
            const brand = row.dataset.brand;
            const categoryMatch = selectedCategory === 'all' || category === selectedCategory;
            const brandMatch = selectedBrand === 'all' || brand === selectedBrand;
            row.classList.toggle('hidden', !(categoryMatch && brandMatch));
        });

        document.querySelectorAll('.mobile-card').forEach(card => {
            const category = card.dataset.category;
            const brand = card.dataset.brand;
            const categoryMatch = selectedCategory === 'all' || category === selectedCategory;
            const brandMatch = selectedBrand === 'all' || brand === selectedBrand;
            card.classList.toggle('hidden', !(categoryMatch && brandMatch));
        });

        lazyLoadImages();
        rebuildCategoryHeaders();
        debouncedRecalcTotals();
    }

    document.querySelectorAll('.brand-button').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.brand-button').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            const brand = this.dataset.brand;
            resetQuantities();
            document.querySelector('.category-buttons').style.display = 'flex';
            updateCategoryButtons(brand);
            applyFilters();
        });
    });

    window.addEventListener('load', function() {
        const brandButtons = document.querySelectorAll('.brand-button');
        if (brandButtons.length > 0) {
            brandButtons[0].click();
        }

        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        const closeModal = document.querySelector('.modal-close');

        document.querySelectorAll('.image-container').forEach(container => {
            container.addEventListener('click', function() {
                const originalImgPath = this.dataset.originalImgPath;
                if (originalImgPath) {
                    modal.style.display = 'flex';
                    modalImg.src = originalImgPath;
                }
            });
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        lazyLoadImages();
        rebuildCategoryHeaders();
        debouncedRecalcTotals();
    });

    document.getElementById('customerDetailsForm').addEventListener('submit', function(event) {
        event.preventDefault();

        const finalTotal = parseFloat(finalTotalDisplay.textContent.replace('₹', '').replace(',', '')) || 0;
        const minimumOrder = <?php echo $minimumOrder; ?>;

        if (finalTotal < minimumOrder) {
            alert("Please select more crackers to meet the minimum order amount of ₹" + minimumOrder.toFixed(
            2));
            return;
        }

        const items = collectItems();
        if (items.length === 0) {
            alert("Please select at least one item to proceed");
            return;
        }

        document.getElementById('itemsBought').value = JSON.stringify(items);
        this.submit();
    });

    attachEventListeners();
    </script>
    <?php include "includes/footer.php"; ?>
</body>

</html>