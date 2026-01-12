<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

// Fetch all settings from single-row settings table
$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$gstRate = $settings['gst_rate'] ?? '';
$discount = $settings['discount'] ?? '';
$pickuplocation_pincode  = $settings['pickuplocation_pincode'] ?? '';
$notificationText = $settings['notification_text'] ?? '';
$minimumOrder = $settings['minimum_order'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Poppins', sans-serif; }
    .admin-main { margin-left: 3rem; }
</style>
</head>
<body class="bg-gray-100">

<div class="admin-container flex">
    <?php require_once './common/admin_sidebar.php'; ?>

    <main class="admin-main flex-1 p-6">
        <div class="container mx-auto max-w-4xl p-6 bg-white rounded-lg shadow-lg mt-10">

            <h2 class="text-2xl font-bold text-indigo-600 mb-4">Update Settings</h2>

            <!-- Message box -->
            <div id="messageBox" class="hidden mb-4 p-3 rounded"></div>

            <form id="settingsForm" class="space-y-4">

                <div>
                    <label for="gst_rate" class="block text-sm font-medium text-gray-700">GST Rate (%):</label>
                    <input type="number" name="gst_rate" id="gst_rate"
                           value="<?= htmlspecialchars($gstRate) ?>" step="0.01" required
                           class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="discount" class="block text-sm font-medium text-gray-700">Discount (%):</label>
                    <input type="number" name="discount" id="discount"
                           value="<?= htmlspecialchars($discount) ?>" step="0.01" required
                           class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="pickuplocation_pincode" class="block text-sm font-medium text-gray-700">Pickup Location Pincode:</label>
                    <input type="number" name="pickuplocation_pincode" id="pickuplocation_pincode"
                           value="<?= htmlspecialchars($pickuplocation_pincode) ?>" step="0.01" required
                           class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="minimum_order" class="block text-sm font-medium text-gray-700">Minimum Order Amount:</label>
                    <input type="number" name="minimum_order" id="minimum_order"
                           value="<?= htmlspecialchars($minimumOrder) ?>" step="0.01" min="0"
                           class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="notification_text" class="block text-sm font-medium text-gray-700">Notification Text:</label>
                    <textarea name="notification_text" id="notification_text" rows="4"
                              class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($notificationText) ?></textarea>
                </div>

                <button type="submit"
                        class="w-full bg-indigo-600 text-white p-3 rounded-lg hover:bg-indigo-700 transition-colors">
                    Update Settings
                </button>
            </form>

        </div>
    </main>
</div>

<script>
// Handle form submit using fetch
document.getElementById("settingsForm").addEventListener("submit", async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    const response = await fetch("/api/admin/settings_update.php", {
        method: "POST",
        body: formData
    });

    const result = await response.json();
    const msgBox = document.getElementById("messageBox");

    msgBox.classList.remove("hidden");
    msgBox.textContent = result.message;

    if (result.status === "success") {
        msgBox.style.backgroundColor = "#d1fae5"; // green
        msgBox.style.color = "#065f46";
    } else {
        msgBox.style.backgroundColor = "#fee2e2"; // red
        msgBox.style.color = "#991b1b";
    }
      setTimeout(() => {
        msgBox.classList.add("hidden");
        msgBox.textContent = "";
    }, 10000);
});
</script>

</body>
</html>
