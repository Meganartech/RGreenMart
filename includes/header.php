<?php
// Ensure the header is only included once
if (!defined('HEADER_INCLUDED')) {
    define('HEADER_INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 9.143 15.143 12l2.286 6.857L12 15.143 6.857 18 9.143 11.143 3 8l5.714 2.857L12 3z" />
                    </svg>
                    <div class="logo-text">
                        <h1>RGreenMart</h1>
                        <p>Fresh. Pure. Premium.</p>
                    </div>
                </div>
                  
                <nav class="desktop-nav" aria-label="Main navigation">
                    <a class="header-nav" href="/Ecommerce/index.php">Home</a>
                    <a class="header-nav" href="/Ecommerce/includes/About.php">About Us</a>
                    <a class="header-nav" href="/Ecommerce/includes/HealthyTips.php">Healthy Tips</a>
                    <a class="header-nav" href="/Ecommerce/includes/ContactUs.php">Contact</a>
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
                 <a href="?page=viewcart" class="hideanchor mr-5 cart-link">
                                        <div class=" mx-1" title="continue to pay">
                                    <div class="cart-icon-wrapper">
    <i class="fa-solid fa-cart-shopping headicon" style="font-size:22px;"></i>
    <span id="cartCount" class="noticount">0</span>
</div>

</div></a>
                <button class="mobile-nav-toggle" aria-label="Toggle mobile menu" aria-expanded="false" onclick="toggleMobileNav()">
                    <svg id="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg id="close-icon" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
    </script>
</body>
</html>
<?php } ?>







