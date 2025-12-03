<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';
require_once __DIR__ . "/includes/env.php";
require_once __DIR__ . "/admin/config.php";
session_start();

use Razorpay\Api\Api;

$minimumOrder = 1000;
$gstRate = isset($settings['gst_rate']) ? floatval($settings['gst_rate']) : 18;

// DB Config
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'diwali_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

$razorpayOrderId = null;
$order = [];
$orderId = null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill'])) {

        $customerName = trim($_POST['customer_name'] ?? '');
        $customerMobile = trim($_POST['customer_mobile'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerState = trim($_POST['customer_state'] ?? '');
        $customerCity = trim($_POST['customer_city'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $itemsBought = json_decode($_POST['items_bought'] ?? '[]', true);
        $orderedDateTime = $_POST['ordered_date_time'] ?? date('Y-m-d H:i:s');

        if (!$customerName || !$customerMobile || !$customerEmail || !$customerState || !$customerCity || !$customerAddress) {
            die("<p style='color:red;'>Error: Required fields missing.</p>");
        }
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            die("<p style='color:red;'>Invalid email.</p>");
        }
        if (!is_array($itemsBought) || empty($itemsBought)) {
            die("<p style='color:red;'>No items found in order.</p>");
        }

        try {
            $conn->beginTransaction();
            $stmt = $conn->query("SELECT last_enquiry_number FROM settings LIMIT 1 FOR UPDATE");
            $row = $stmt->fetch();
            $enquiryNumber = ($row['last_enquiry_number'] ?? 1000) + 1;
            $conn->exec("UPDATE settings SET last_enquiry_number = $enquiryNumber");
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            die("Error generating enquiry number.");
        }

        $subtotal = 0;
        $netTotal = 0;
        $packingpercent = 3;
        $couponDiscount = floatval($_POST['coupon_discount'] ?? 0);
        $couponCode = $_POST['coupon_code'] ?? '';

        foreach ($itemsBought as $item) {
            $grossPrice = $item['grossPrice'];
            $discount = $item['discount'] ?? 0;

            $discounted = $grossPrice - ($grossPrice * ($discount / 100));
            $subtotal += $grossPrice * $item['quantity'];
            $netTotal += $discounted * $item['quantity'];
        }

        $netTotalAfterCoupon = $netTotal - $couponDiscount;

        $packingcharge = ($netTotalAfterCoupon * $packingpercent) / 100;
        $overallTotal = $netTotalAfterCoupon + $packingcharge;

        $stmt = $conn->prepare("
            INSERT INTO orders (enquiry_no, name, mobile, email, state, city, address, subtotal, packing_charge, net_total, overall_total, order_date, payment_status, coupon_code, coupon_discount_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)
        ");
        $stmt->execute([$enquiryNumber, $customerName, $customerMobile, $customerEmail, $customerState, $customerCity, $customerAddress, $subtotal, $packingcharge, $netTotalAfterCoupon, $overallTotal, $orderedDateTime, $couponCode, $couponDiscount]);

        $orderId = $conn->lastInsertId();

        $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, product_name, price, discount, discounted_price, qty, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($itemsBought as $item) {
            $grossPrice = $item['grossPrice'];
            $discount = $item['discount'];
            $discountedPrice = $grossPrice - ($grossPrice * ($discount / 100));
            $amount = $discountedPrice * $item['quantity'];

            $stmtItem->execute([$orderId, $item['name'], $grossPrice, $discount, $discountedPrice, $item['quantity'], $amount]);
        }

        $_SESSION['order_id'] = $orderId;
        $order = [
            'id' => $orderId,
            'overall_total' => $overallTotal,
            'name' => $customerName,
            'email' => $customerEmail,
            'mobile' => $customerMobile
        ];

        $api = new Api($_ENV['RAZORPAY_KEY_ID'], $_ENV['RAZORPAY_KEY_SECRET']);

        $razorpayOrder = $api->order->create([
            'receipt' => "ORDER_$orderId",
            'amount' => round($order['overall_total'] * 100), 
            'currency' => 'INR'
        ]);

        $razorpayOrderId = $razorpayOrder['id'];

        $conn->prepare("UPDATE orders SET razorpay_order_id = ? WHERE id = ?")->execute([$razorpayOrderId, $orderId]);
    }

} catch (Exception $e) {
    die("<p style='color:red;'>Server error: " . $e->getMessage() . "</p>");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RGreenMart Checkout</title>
    <link rel="icon" type="image/png" href="./images/LOGO.jpg">
    <meta name="keywords" content="Deepavali crackers sale 2025, Buy crackers online Deepavali 2025, Diwali crackers offer 2025, Deepavali discount crackers online, Diwali crackers shop near me, Deepavali crackers combo offer 2025, Wholesale Diwali crackers online, Sivakasi crackers online shopping, Diwali crackers home delivery 2025, Best price Diwali crackers online, Cheapest Deepavali crackers online 2025, Eco-friendly Diwali crackers online 2025, Diwali crackers gift box sale 2025, Online cracker booking for Deepavali 2025, Buy Sivakasi crackers for Deepavali 2025, Buy crackers online Chennai Deepavali 2025, Diwali crackers sale Coimbatore 2025, Deepavali crackers shop Madurai 2025, Tirunelveli Deepavali crackers online, Salem Diwali crackers discount 2025, Deepavali crackers gift pack 2025, Green crackers for Diwali 2025, Cheap Diwali crackers online 2025, Buy Diwali crackers online Tamil Nadu 2025, Standard Fireworks Diwali crackers 2025, Ayyan Fireworks branded crackers online, Sony Fireworks crackers sale 2025, Sri Kaliswari branded crackers Deepavali 2025, RGreenMart crackers sale 2025, Trichy branded crackers discount Diwali 2025">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="./Styles.css">
         <script src="../cart.js"></script>
</head>

<body>
    <?php include "./includes/header.php"; ?>
    
    <div class="details-container m-5">
        
        <?php if ($razorpayOrderId === null): ?>
            <div class="summary">
                <div><span><strong>Total (Inc. GST)</strong></span><span id="total">₹0.00</span></div>
                <div><span style="color:red">Discount %</span><span id="discountTotal" style="color:red;">₹0.00</span></div>
                <div><span>Net Rate</span><span id="netRate">₹0.00</span></div>
                <span style="display:none">
                <div><span><strong>Overall Total</strong></span><span id="overallTotal">₹0.00</span></div>
                <div><span><strong>Item Price</strong></span><span id="afterCouponNetRate">₹0.00</span></div>
                <div><span>Inclusive GST (<?php echo $gstRate; ?>%)</span><span id="gst">₹0.00</span></div></span>
                <div><span class="finalTotal"><strong>Final Total</strong></span><span
                        class="finalTotal" id="finalTotal">₹0.00</span></div>
                <div class="minimum-order"><span>Minimum
                        Order</span><span>₹<?php echo number_format($minimumOrder); ?></span></div>
            </div>
            
            <h2 style="text-align: center; color: #1f2937; font-weight: bold; margin-bottom: 1.5rem;">Enter Your Details
            </h2>
            <form id="customerDetailsForm" action="details.php" method="POST">
                <div>
                    <label for="customerName">Name <span style="color: #dc2626">*</span></label>
                    <input type="text" id="customerName" name="customer_name" required pattern="^[A-Za-z ]{2,}$"
                        title="Enter a valid name (letters only)">
                </div>
                <div>
                    <label for="customerMobile">Mobile Number <span style="color: #dc2626">*</span></label>
                    <input type="tel" id="customerMobile" name="customer_mobile" required pattern="^[6-9][0-9]{9}$"
                        maxlength="10" title="Enter a valid 10-digit mobile number">
                </div>
                <div>
                    <label for="customerEmail">Email <span style="color: #dc2626">*</span></label>
                    <input type="email" id="customerEmail" name="customer_email" required
                        title="Enter a valid email address">
                </div>
                <div>
                    <label for="customerState">State <span style="color: #dc2626">*</span></label>
                    <input type="text" id="customerState" name="customer_state" required pattern="^[A-Za-z ]{2,}$"
                        title="Enter a valid state">
                </div>
                <div>
                    <label for="customerCity">City <span style="color: #dc2626">*</span></label>
                    <input type="text" id="customerCity" name="customer_city" required pattern="^[A-Za-z ]{2,}$"
                        title="Enter a valid city">
                </div>
                <div>
                    <label for="customerAddress">Address <span style="color: #dc2626">*</span></label>
                    <textarea id="customerAddress" name="customer_address" required minlength="5"
                        title="Enter your address"></textarea>
                </div>
                <input type="hidden" name="ordered_date_time" value="<?php echo date('Y-m-d H:i:s'); ?>">
                <input type="hidden" name="items_bought" id="itemsBought">
                <input type="hidden" name="generate_bill" value="true">
                <input type="hidden" name="coupon_discount" id="couponDiscountHidden" value="0">
                <input type="hidden" name="coupon_discount_percent" id="couponDiscountPercentHidden" value="0">
                <input type="hidden" name="coupon_code" id="couponCodeHidden" value="">
                <button type="submit" class="continue-button" id="continueButton">Order Now</button>
            </form>
        <?php else: ?>
           <div>Redirecting you to Payment page.Please Wait! </div>


            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
            var options = {
                "key": "<?= $_ENV['RAZORPAY_KEY_ID'] ?>",
                "amount": "<?= round($order['overall_total'] * 100) ?>",
                "currency": "INR",
                "name": "RgreenMart",
                "description": "Order Payment for #<?= $orderId ?>",
                "order_id": "<?= $razorpayOrderId ?>",
                "handler": function (response){
                    window.location.href = "verify_payment.php?order_id=<?= $orderId ?>&payment_id=" + response.razorpay_payment_id + "&signature=" + response.razorpay_signature;
                },
                "prefill": {
                    "name": "<?= htmlspecialchars($order['name']) ?>",
                    "email": "<?= htmlspecialchars($order['email']) ?>",
                    "contact": "<?= htmlspecialchars($order['mobile']) ?>"
                },
                "theme": {
                    "color": "#3399cc"
                }
            };
            var rzp1 = new Razorpay(options);

                rzp1.open();
                e.preventDefault();
           
            </script>
        <?php endif; ?>

    </div>

    <div id="imageModal" class="modal">
        <div class="modal-content" style="width:60%;height:60%;margin:0 auto;margin-top:5%;">
            <span class="modal-close">&times;</span>
            <img id="modalImage" src="" alt="Enlarged Image" style="width:100%;height:100%;margin:0 auto; padding:20px">
        </div>
    </div>
    
    <script>
        const gstRate = <?php echo $gstRate; ?>;
        let selectedBrand = 'all';
        let selectedCategory = 'all';

        const totalDisplay = document.getElementById('total');
        const discountTotalDisplay = document.getElementById('discountTotal');
        const netRateDisplay = document.getElementById('netRate');
        const gstDisplay = document.getElementById('gst');
        const overallTotalDisplay = document.getElementById('overallTotal');
        const afterCouponNetRateDisplay = document.getElementById('afterCouponNetRate');
        const finalTotalDisplay = document.getElementById('finalTotal');
        const minimumOrder = <?php echo $minimumOrder; ?>;
        
        function getCartItems() {
            return JSON.parse(localStorage.getItem("cart")) || [];
        }

        function recalcTotals() {
            let subtotal = 0;
            let totalNetRate = 0;
            let totalGst = 0;
            
            const cartItems = getCartItems();

            cartItems.forEach((item) => {
                const qty = item.quantity || 0;
                if (qty === 0) return;

                const grossPrice = parseFloat(item.oldamt) || 0;
                const discountRate = parseFloat(item.discountRate) || 0;
                const itemGstRate = parseFloat(item.gst) || gstRate;

                const discountAmount = Math.round((grossPrice * discountRate) / 100);
                const discountedPrice = grossPrice - discountAmount;
                const gstAmount = Math.round((discountedPrice * itemGstRate) / 100);
                const finalUnitPrice = discountedPrice + gstAmount;

                subtotal += grossPrice * qty;
                totalNetRate += discountedPrice * qty;
                totalGst += gstAmount * qty;
            });

            const totalAmountBeforeCoupon = totalNetRate + totalGst;
            const displayDiscount = Math.round(subtotal - totalAmountBeforeCoupon);
            
            let couponDiscount = parseFloat(document.getElementById('couponDiscountHidden').value) || 0;
            const couponDiscountPercent = parseFloat(document.getElementById('couponDiscountPercentHidden').value) || 0;
            
            if (couponDiscountPercent > 0) {
                // Coupon applies to the pre-GST, discounted rate (netRate)
                couponDiscount = Math.round((totalNetRate * couponDiscountPercent) / 100);
                document.getElementById('couponDiscountHidden').value = couponDiscount;
            }

            const netRateAfterCoupon = Math.round(totalNetRate - couponDiscount);
            
            // Re-calculate GST based on the coupon-discounted net rate
            const finalGst = Math.round(netRateAfterCoupon * (gstRate / 100));
            
            const finalTotal = netRateAfterCoupon + finalGst;

            totalDisplay.textContent = '₹' + Math.round(subtotal);
            discountTotalDisplay.textContent = '- ₹' + displayDiscount;
            netRateDisplay.textContent = '₹' + Math.round(totalNetRate + totalGst);
            gstDisplay.textContent = '₹' + finalGst;
            overallTotalDisplay.textContent = '₹' + finalTotal;
            finalTotalDisplay.textContent = '₹' + finalTotal;
            afterCouponNetRateDisplay.textContent = '₹' + netRateAfterCoupon;
        }
        
        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
        const debouncedRecalcTotals = debounce(recalcTotals, 100);

        function applyCoupon() {
            const couponCode = document.getElementById('couponCode').value.trim();
            if (!couponCode) {
                alert('Please enter a coupon code.');
                return;
            }

            const currentNetRate = parseFloat(afterCouponNetRateDisplay.textContent.replace('₹', '').replace(',', '')) || 0;

            fetch('checkcoupon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'coupon_code=' + encodeURIComponent(couponCode) + '&net_rate=' + currentNetRate
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('couponCodeHidden').value = couponCode;
                        document.getElementById('couponDiscountPercentHidden').value = data.discount_percent;
                        alert('Coupon applied successfully! Discount: ' + data.discount_percent + '%');
                    } else {
                        document.getElementById('couponCodeHidden').value = '';
                        document.getElementById('couponDiscountHidden').value = '0';
                        document.getElementById('couponDiscountPercentHidden').value = '0';
                        alert('Invalid or expired coupon code. ' + (data.message || ''));
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

        function collectItems() {
            const itemsData = getCartItems();
            const items = [];
            itemsData.forEach((item) => {
                let grossPrice=parseFloat(item.oldamt) || 0;
                let simpleDiscountedPrice=item.price;
                items.push({
                    id: item.id,
                    name: item.name,
                    brand: item.brand,
                    category: item.category,
                    pieces: item.pieces,
                    items: item.items,
                    grossPrice: grossPrice,
                    simpleDiscountedPrice: simpleDiscountedPrice,
                    quantity: item.quantity,
                    discount: item.discountRate
                });
            });
            return items;
        }

        document.getElementById('customerDetailsForm').addEventListener('submit', function (event) {
            event.preventDefault();

            const finalTotalValue = parseFloat(finalTotalDisplay.textContent.replace('₹', '').replace(/,/g, '')) || 0;

            if (finalTotalValue < minimumOrder) {
                alert("Please select more crackers to meet the minimum order amount of ₹" + minimumOrder.toFixed(2));
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
        
        recalcTotals();

    </script>
    <?php include "includes/footer.php"; ?>
</body>

</html>