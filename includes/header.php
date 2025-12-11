<?php
$isLoggedIn = isset($_SESSION['user_id']);

// Ensure the header is only included once
if (!defined('HEADER_INCLUDED')) {
    define('HEADER_INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', 'G-PE2CJCXNGL');
    </script>
    <link rel="stylesheet" href="../Styles.css">
</head>

<body>


    <header id="header">
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <svg class="sparkles-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 9.143 15.143 12l2.286 6.857L12 15.143 6.857 18 9.143 11.143 3 8l5.714 2.857L12 3z" />
                    </svg>
                    <div class="logo-text">
                        <h1>RGreenMart</h1>
                        <p>Fresh. Pure. Premium.</p>
                    </div>
                </div>

                <nav class="desktop-nav" aria-label="Main navigation">
                    <a class="header-nav" href="/index.php">Home</a>
                    <a class="header-nav" href="/includes/About.php">About Us</a>
                    <a class="header-nav" href="/includes/HealthyTips.php">Healthy Tips</a>
                    <a class="header-nav" href="/includes/ContactUs.php">Contact</a> 
                     <?php if($isLoggedIn): ?>
                    <a class="header-nav" href="my_orders.php">My Orders</a>
                     <div class="relative">
        <!-- User Icon -->
        <button id="userBtn" class="flex items-center gap-2 focus:outline-none">
            <i class="fa-solid fa-user fa-lg headicon"></i>
        </button>

        <!-- Dropdown -->
        <div id="userDropdown" class="absolute right-0 mt-4 w-36 bg-green-600 text-white rounded-lg shadow-lg hidden">
            <button id="logoutBtn" class="w-full text-left px-4 py-2 hover:bg-red-400 rounded-lg">Logout</button>
        </div>
    </div>
    <div id="logoutModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6">
        <h2 class="text-lg font-semibold mb-4">Confirm Logout</h2>
        <p class="mb-6">Are you sure you want to logout?</p>
        <div class="flex justify-end gap-4">
            <button id="cancelLogout" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
            <button id="confirmLogout" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">Logout</button>
        </div>
    </div>
</div>
    <?php else: ?>
        <div>
        <a href="login.php" class="px-4 py-2 rounded bg-green-600  hover:bg-green-700 transition"style="color: white !important;">Login</a>
        <a href="register.php" class="px-4 py-2 rounded bg-blue-600  hover:bg-blue-700 transition"style="color: white !important;">Register</a>
    </div>
        <?php endif; ?>
                    <!-- <div class="contact-icons">
                        <a href="https://www.instagram.com/mass__mari/reel/DPGeGiDgcos/" class="contact-icon instagram-icon" aria-label="Instagram" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" width="24" height="24">
                                <path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2zm0 1.5A4.25 4.25 0 0 0 3.5 7.75v8.5A4.25 4.25 0 0 0 7.75 20.5h8.5a4.25 4.25 0 0 0 4.25-4.25v-8.5A4.25 4.25 0 0 0 16.25 3.5h-8.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.5A3.5 3.5 0 1 0 12 15a3.5 3.5 0 0 0 0-7zm5.25-.75a1.25 1.25 0 1 1-2.5 0 1.25 1.25 0 0 1 2.5 0z"/>
                            </svg>
                        </a>
                        <a href="https://www.youtube.com/@RGreenCrackerss" class="contact-icon youtube-icon" aria-label="YouTube">
                            <svg fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a2.97 2.97 0 0 0-2.092-2.105C19.606 3.5 12 3.5 12 3.5s-7.606 0-9.406.581a2.97 2.97 0 0 0-2.092 2.105C0 8.001 0 12 0 12s0 3.999.502 5.814a2.97 2.97 0 0 0 2.092 2.105C4.394 20.5 12 20.5 12 20.5s7.606 0 9.406-.581a2.97 2.97 0 0 0 2.092-2.105C24 15.999 24 12 24 12s0-3.999-.502-5.814zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/>
                            </svg>
                        </a>
                        <a href="https://www.facebook.com/profile.php?id=61579694912040" class="contact-icon facebook-icon" aria-label="Facebook">
                            <svg fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                            </svg>
                        </a>
                    </div> -->

                </nav>
                 
                      <a href="viewcart.php" class="hideanchor mr-5 cart-link">
                        <div class=" mx-1" title="continue to pay">
                            <div class="cart-icon-wrapper">
                                <i class="fa-solid fa-cart-shopping headicon" style="font-size:22px;"></i>
                                <span id="cartCount" class="noticount">0</span>
                            </div>

                        </div>
                    </a>
              
                <button class="mobile-nav-toggle" aria-label="Toggle mobile menu" aria-expanded="false"
                    onclick="toggleMobileNav()">
                    <svg id="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg id="close-icon" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mobile-nav" id="mobile-nav" aria-hidden="true">
                <nav aria-label="Mobile navigation">
                    <ul>
                        <li><a href="/index.php">Home</a></li>
                        <li><a href="/includes/About.php">About Us</a></li>
                        <li><a href="/HealthyTips.php">Safety</a></li>
                        <li><a href="/ContactUs.php">Contact</a></li>
                          <li><a href="my_orders.php">My Orders</a></li>
                         <li>  <a href="logout.php" >Logout</a></li>
                       
                      
                        <!-- <li>
                            <div class="contact-icons">
                                <a href="https://www.instagram.com/mass__mari/reel/DPGeGiDgcos/" class="contact-icon instagram-icon" aria-label="Instagram" target="_blank">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" width="24" height="24">
                                        <path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2zm0 1.5A4.25 4.25 0 0 0 3.5 7.75v8.5A4.25 4.25 0 0 0 7.75 20.5h8.5a4.25 4.25 0 0 0 4.25-4.25v-8.5A4.25 4.25 0 0 0 16.25 3.5h-8.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.5A3.5 3.5 0 1 0 12 15a3.5 3.5 0 0 0 0-7zm5.25-.75a1.25 1.25 0 1 1-2.5 0 1.25 1.25 0 0 1 2.5 0z"/>
                                    </svg>
                                </a>
                                <a href="https://www.youtube.com/@RGreenCrackerss" class="contact-icon youtube-icon" aria-label="YouTube">
                                    <svg fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M23.498 6.186a2.97 2.97 0 0 0-2.092-2.105C19.606 3.5 12 3.5 12 3.5s-7.606 0-9.406.581a2.97 2.97 0 0 0-2.092 2.105C0 8.001 0 12 0 12s0 3.999.502 5.814a2.97 2.97 0 0 0 2.092 2.105C4.394 20.5 12 20.5 12 20.5s7.606 0 9.406-.581a2.97 2.97 0 0 0 2.092-2.105C24 15.999 24 12 24 12s0-3.999-.502-5.814zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/>
                                    </svg>
                                </a>
                                <a href="https://www.facebook.com/profile.php?id=61579694912040" class="contact-icon facebook-icon" aria-label="Facebook">
                                    <svg fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                                    </svg>
                                </a>
                            </div>
                        </li> -->
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    <script>
    function toggleMobileNav() {
        const mobileNav = document.getElementById('mobile-nav');
        const menuIcon = document.getElementById('menu-icon');
        const closeIcon = document.getElementById('close-icon');
        const toggleButton = document.querySelector('.mobile-nav-toggle');
        const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';

        mobileNav.classList.toggle('active');
        menuIcon.classList.toggle('hidden');
        closeIcon.classList.toggle('hidden');
        mobileNav.setAttribute('aria-hidden', !isExpanded);
        toggleButton.setAttribute('aria-expanded', !isExpanded);
    }
    document.querySelector('.mobile-nav-toggle').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleMobileNav();
        }
    });


    // Toggle dropdown
    const userBtn = document.getElementById('userBtn');
    const userDropdown = document.getElementById('userDropdown');
    userBtn.addEventListener('click', () => {
        userDropdown.classList.toggle('hidden');
    });

    // Show logout modal
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    logoutBtn.addEventListener('click', () => {
        userDropdown.classList.add('hidden');
        logoutModal.classList.remove('hidden');
    });

    // Cancel logout
    const cancelLogout = document.getElementById('cancelLogout');
    cancelLogout.addEventListener('click', () => {
        logoutModal.classList.add('hidden');
    });

    // Confirm logout
    const confirmLogout = document.getElementById('confirmLogout');
    confirmLogout.addEventListener('click', () => {
        window.location.href = 'logout.php';
    });

    // Close dropdown if clicked outside
    document.addEventListener('click', (e) => {
        if (!userBtn.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.classList.add('hidden');
        }
    });
    </script>
</body>

</html>
<?php } ?>