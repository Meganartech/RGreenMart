<?php
// Load environment and header layout
require_once 'includes/env.php';
require_once 'includes/header.php';
require_once 'includes/db.php';

// Fetch shop details
$stmt = $conn->prepare("SELECT shopaddress, phone, email FROM admin_details LIMIT 1");
$stmt->execute();
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

$shopAddress = $shop['shopaddress'] ?? 'Chandragandhi Nagar, Madurai, Tamil Nadu';
$shopPhone = $shop['phone'] ?? '99524 24474';
$shopEmail = $shop['email'] ?? 'sales@rgreenmart.com';

// Determine which content page to show
$page = $_GET['page'] ?? 'productshome';
$pageFile = __DIR__ . "/../pages/" . basename($page) . ".php";

?>

<main id="main" style="position: relative; z-index: 2;">
    <?php
    if (file_exists($pageFile)) {
        include $pageFile; // Only include the inner content
    } else {
        echo "<h2 style='text-align:center;padding:50px;'>Page Not Found ❌</h2>";
    }
    ?>
</main>

<?php require_once './includes/footer.php'; ?>

<!-- Page-specific scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const carouselEl = document.getElementById('bannerCarousel');
  const carousel = new bootstrap.Carousel(carouselEl, {
    interval: 500, // default interval
    ride: false     // disable auto start, we'll control manually
  });


  // Track current slide
  let currentIndex = 0;

  // Function to go to next slide
  function goNext() {
    carousel.next();
  }

  carousel.cycle();
});
</script>



<?php $conn = null; ?>
