<?php
// Ensure the footer is only included once
if (!defined('FOOTER_INCLUDED')) {
    define('FOOTER_INCLUDED', true);

    
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";
    $contactStmt = mysqli_query($mysqli, "SELECT * FROM admin_details LIMIT 1");
    $contact = mysqli_fetch_assoc($contactStmt);
    $address = $contact['shopaddress'] ?? 'Sivakasi, Tamil Nadu';
    $phone = $contact['phone'];
    $phone2 = $contact['phone2'] ?? '';
    $email = $contact['email'] ?? 'info@rgreencrackers.com';
?>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<!-- Font Awesome for WhatsApp Icon -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

<!-- Add Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
.safety-section {
    max-width: 800px;
    margin: auto;
    padding: 20px;
}

.safety-item {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.safety-item i {
    font-size: 40px;
    margin-right: 15px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dos {
    color: #15803d;
}

.donts {
    color: #b91c1c;
}
</style>

<style>
.wa-icon {
    font-size: 30px;
    color: white;
    margin-top: 15px;
}

.whatsapp-float {
    position: fixed;
    width: 60px;
    height: 60px;
    bottom: 40px;
    right: 40px;
    background-color: #25d366;
    color: #FFF;
    border-radius: 50%;
    text-align: center;
    font-size: 30px;
    box-shadow: 2px 2px 3px #999;
    z-index: 1000;
}

.whatsapp-icon {
    margin: 0 auto;
}

.whatsapp-float:hover {
    background-color: #1ebe57;
}

.info-float {
    position: fixed;
    width: 100px;
    height: 100px;
    bottom: 110px;
    /* Placed above the WhatsApp button (60px height + 10px spacing) */
    right: 20px;
    color: #FFF;
    border-radius: 50%;
    text-align: center;
    font-size: 24px;
    line-height: 50px;
    box-shadow: 2px 2px 3px #999;
    z-index: 1001;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.info-float:hover {
    background-color: transparent;
}
</style>
<footer id="footer" class="bg-gradient-to-b from-gray-900 to-black text-white">
    <div class="container max-w-7xl mx-auto px-4 py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

            <!-- Company Info -->
            <div class="space-y-6">
                <div class="flex items-center space-x-3">
                    <!-- <img src="assets/img/logo.png" alt="RGreen Crackers Logo" class="h-10 w-auto"> -->
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 9.143 15.143 12l2.286 6.857L12 15.143 6.857 18 9.143 11.143 3 8l5.714 2.857L12 3z" />
                    </svg>
                    <div>
                        <h3 class="text-xl font-bold text-white">RGreenMart</h3>
                        <p class="text-red-400 text-sm">Fresh. Pure. Premium.</p>
                    </div>
                </div>
                <p class="text-gray-300 leading-relaxed text-sm">
                    Discover the natural goodness of farm-fresh dried fruits and premium-grade nuts, handpicked to bring
                    you the healthiest snacking experience.
                    At RGreenMart, we believe healthy eating should be simple, tasty, and trustworthy.

                </p>
                <div class="flex items-center space-x-2 text-sm">
                    <span class="bg-blue-600 text-white px-3 py-1 rounded-full">Made In India</span>
                    <span class="bg-green-600 text-white px-3 py-1 rounded-full">Fresh and Pure</span>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="space-y-4">
                <h3 class="text-xl font-bold text-red-400 mb-6">Quick Links</h3>
                <ul class="space-y-3">
                    <li><a href="/index.php"
                            class="text-gray-300 hover:text-red-400 transition-colors flex items-center space-x-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            <span>Home</span>
                        </a></li>

                    <li><a href="/includes/HealthyTips.php"
                            class="text-gray-300 hover:text-red-400 transition-colors flex items-center space-x-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            <span>Healthy Tips</span>
                        </a></li>
                    <li><a href="/includes/ContactUs.php"
                            class="text-gray-300 hover:text-red-400 transition-colors flex items-center space-x-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            <span>Contact Us</span>
                        </a></li>
                    <li><a href="/includes/About.php"
                            class="text-gray-300 hover:text-red-400 transition-colors flex items-center space-x-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            <span>About Us</span>
                        </a></li>
                    <!-- <li><a href="#offers" class="text-gray-300 hover:text-red-400 transition-colors flex items-center space-x-2">
                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                        <span>Special Offers</span>
                    </a></li> -->
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="space-y-4">
                <h3 class="text-xl font-bold text-red-400 mb-6">Contact Info</h3>
                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="bg-red-600 p-2 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-white font-medium">RGreenMart</p>
                            <p class="text-gray-300 text-sm">
                                <?php echo nl2br(htmlspecialchars($address, ENT_QUOTES, 'UTF-8')); ?></p>
                            <p class="text-gray-400 text-xs">Farm-fresh dried fruits and premium-grade nuts</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="bg-green-600 p-2 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </div>
                        <div>
                            <a href="/index.php" id="scrollToBodyBtn" class="info-float" title="Scroll to Main Body">
                                <img src="/images/booknow.webp" alt="">
                            </a>
                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $phone); ?>" target="_blank"
                                class="block">
                                <p class="text-white font-medium hover:text-green-400 transition">
                                    <?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </a>
                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $phone2); ?>" target="_blank"
                                class="block">
                                <p class="text-white font-medium hover:text-green-400 transition">
                                    <?php echo htmlspecialchars($phone2, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </a>
                            <p class="text-gray-300 text-sm">24/7 Contact Support</p>
                        </div>


                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="bg-blue-600 p-2 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-white font-medium">
                                <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-gray-300 text-sm">24/7 Email Support</p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Social Media & Newsletter -->
            <div class="space-y-4">
                <h3 class="text-xl font-bold text-red-400 mb-6">Connect With Us</h3>
                <!-- Social Icons -->
                <div class="flex space-x-4">
                    <div class="bg-blue-600 hover:bg-blue-700 w-12 h-12 rounded-full flex items-center justify-center transition-colors"
                        style="color:white" target="_blank">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                        </svg>
                    </div>
                    <div class="bg-red-600 hover:bg-red-700 w-12 h-12 rounded-full flex items-center justify-center transition-colors"
                        style="color:white">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M23.498 6.186a2.97 2.97 0 0 0-2.092-2.105C19.606 3.5 12 3.5 12 3.5s-7.606 0-9.406.581a2.97 2.97 0 0 0-2.092 2.105C0 8.001 0 12 0 12s0 3.999.502 5.814a2.97 2.97 0 0 0 2.092 2.105C4.394 20.5 12 20.5 12 20.5s7.606 0 9.406-.581a2.97 2.97 0 0 0 2.092-2.105C24 15.999 24 12 24 12s0-3.999-.502-5.814zM9.75 15.02V8.98L15.5 12l-5.75 3.02z" />
                        </svg>
                    </div>
                    <!-- 
                    <a href="#" class="bg-blue-400 hover:bg-blue-500 w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z" />
                        </svg>
                    </a>
                    <a href="#" class="bg-green-600 hover:bg-green-700 w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5v-4a2 2 0 012-2h10a2 2 0 012 2v4h-4M3 5h18v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5z" />
                        </svg>
                    </a> -->
                </div>


                <!-- Trust Badges -->
                <div class="space-y-2">
                    <div class="flex items-center space-x-2 text-sm">
                        <span class="text-green-400">✓</span>
                        <span class="text-gray-300">Licensed Dealer</span>
                    </div>
                    <div class="flex items-center space-x-2 text-sm">
                        <span class="text-green-400">✓</span>
                        <span class="text-gray-300">Quality Assured</span>
                    </div>
                    <div class="flex items-center space-x-2 text-sm">
                        <span class="text-green-400">✓</span>
                        <span class="text-gray-300">24/7 Support</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Payment QR Code -->
        <!-- 
<div class="space-y-4">
    <h3 class="text-xl font-bold text-red-400 mb-6 text-center">Payment Options</h3>
    <div class="bg-gray-800 p-4 rounded-lg text-center">
        <a href="https://rgreenenterprise.com/Payments.php">
            <img src="./images/PaymentShort.jpg" alt="Scan to Pay" class="mx-auto w-40 h-40 rounded-lg shadow-md border border-gray-700">
        </a>
    </div>
</div>
-->



        <!-- Bottom Section -->
        <div class="border-t border-gray-800 mt-12 pt-8">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="text-center md:text-left">
                    <p class="text-gray-400 text-sm">
                        &copy; 2025 RGreenMart. All Rights Reserved.
                    </p>
                    <p class="text-gray-500 text-xs mt-1">
                        Celebrating safely since 1995 • Licensed & Certified • Eco-Friendly Products
                    </p>
                </div>
                <div class="flex flex-wrap justify-center gap-4 text-xs text-gray-500">
                    <a href="/includes/PrivacyPolicy.php" class="hover:text-white transition-colors">Privacy
                        Policy</a>
                    <a href="/includes/TandC.php" class="hover:text-white transition-colors">Terms of
                        Service</a>
                    <a href="/includes/ShipmentAndDelivery.php"
                        class="hover:text-white transition-colors">Shipping Policy</a>
                    <a href="/includes/CancellationAndReturn.php"
                        class="hover:text-white transition-colors">Return Policy</a>

                </div>
            </div>
        </div>
    </div>

    <!-- Festive Bottom Strip -->
    <div class="bg-gradient-to-r from-green-600 via-lime-500 to-green-600 py-3">
        <div class="text-center">
            <p class="text-white font-medium text-sm">
                🌿 GreenMart: Fresh Quality, Healthy Choices • Shop Sustainably • Live Well! 💚
            </p>
        </div>
    </div>
</footer>

<!-- ✅ WhatsApp Floating Button -->
<a href="https://wa.me/<?php echo preg_replace('/\D/', '', $phone); ?>?text=Hello%20RGreen%20Crackers%2C%20I%20would%20like%20more%20information."
    class="whatsapp-float" target="_blank">
    <i class="fa-brands fa-whatsapp wa-icon"></i>
</a>



<?php } ?>