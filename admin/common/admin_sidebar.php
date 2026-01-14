<!-- Mobile Toggle Button -->
<button id="menuBtn" class="md:hidden fixed top-4 left-4 z-50 bg-indigo-800 text-white p-2 rounded focus:outline-none">
    ☰
</button>

<!-- Sidebar -->
<aside id="sidebar" class="w-64 bg-indigo-800 text-white min-h-screen p-6 fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-300 md:relative md:translate-x-0">
    <h1 class="text-2xl font-bold mb-8">Admin Dashboard</h1>

    <nav class="space-y-4">
        <a href="order.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Orders</a>
        <a href="Manageitems.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Manage items</a>
        <a href="Additems.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Add items</a>
        <a href="bundleupload.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Bundle Upload</a>
        <a href="ListOfEnquiries.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">List of Enquiries</a>
        <a href="manage_categories.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Manage Categories</a>
        <a href="manage_brands.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Manage Brands</a>
         <a href="Setting.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Settings</a>
        <a href="Profile.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Admin Profile</a>
        <a href="carousel_manager.php" class="block p-3 rounded hover:bg-indigo-700 transition-colors">Carousel Manager</a>
        <form method="POST" action="logout.php" class="mt-4">
            <button type="submit" class="w-full bg-red-600 text-white p-3 rounded hover:bg-red-700 transition-colors">
                Logout
            </button>
        </form>
    </nav>
</aside>

<!-- JavaScript for Toggle -->
<script>
    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");

    menuBtn.addEventListener("click", () => {
        sidebar.classList.toggle("-translate-x-full");
    });
</script>

<style>
    @media (max-width: 768px) {
        .admin-main {
            margin-left: 0 !important;
        }
    }
</style>
