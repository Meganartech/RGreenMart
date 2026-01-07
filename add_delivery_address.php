<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'vendor/autoload.php';
session_start();
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php"; // PDO $conn

use Razorpay\Api\Api;

if (!isset($_SESSION["user_id"])) {
    $_SESSION["redirect_after_login"] = "add_delivery_address.php";
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION["user_id"] ?? 0;


// Fetch user addresses
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Select Delivery Address</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="cart.js"></script>
    <script src="toast.js"></script>
</head>

<body>
    <?php include "includes/header.php"; ?>
    <!-- MAIN CONTAINER -->
    <div class="max-w-3xl mx-auto m-10 p-6 bg-white rounded-xl shadow-lg">

        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-700">Select Delivery Address</h2>

            <button onclick="openModal()" class="text-indigo-600 hover:text-indigo-800 font-semibold">
                + Add New Address
            </button>
        </div>


        <!-- Saved Addresses -->
        <div class="space-y-4">
            <?php foreach ($addresses as $addr): ?>
            <div class="border rounded-xl p-4 hover:shadow-md bg-gray-50 transition">
                <div class="flex justify-between">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input type="radio" name="selected_address" value="<?= $addr['id'] ?>" class="mt-1"
                            <?= $addr['is_default'] ? 'checked' : '' ?>>

                        <div>
                            <p class="font-semibold text-gray-800">
                                <?= htmlspecialchars($addr['contact_name']) ?>
                                <?= $addr['is_default'] ? '<span class="ml-2 text-xs bg-green-200 text-green-700 px-2 py-1 rounded-full">Default</span>' : '' ?>
                            </p>
                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($addr['contact_mobile']) ?></p>
                            <p class="text-gray-700 mt-1 text-sm">
                                <?= htmlspecialchars($addr['address_line1']) ?><br>
                                <?= htmlspecialchars($addr['address_line2']) ?><br>
                                <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> -
                                <?= htmlspecialchars($addr['pincode']) ?>
                                <br>
                                <span class="text-gray-500">Landmark: </span><?= htmlspecialchars($addr['landmark']) ?>
                            </p>
                        </div>
                    </label>

                    <!-- Edit/Delete Icons -->
                    <div class="flex space-x-4">
                        <a onclick='editAddress(<?= json_encode($addr) ?>)'
                            class="text-blue-500 hover:text-blue-700 cursor-pointer">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>


                        <a href="delete_address.php?id=<?= $addr['id'] ?>"
                            onclick="return confirm('Delete this address?')" class="text-red-500 hover:text-red-700">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($addresses)): ?>
            <p class="text-gray-500 text-center">No saved addresses.</p>
            <?php endif; ?>
        </div>
        <!-- Continue Button -->
        <button onclick="continueToPayment()"
            class="mt-4 w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition">
            Continue to Payment
        </button>
    </div>


    <!-- ADD / EDIT ADDRESS MODAL -->
    <div id="addressModal" style="z-index: 51;"
        class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden flex items-center justify-center">
        <div class="bg-white p-6 rounded-xl  shadow-lg w-96">

            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Address</h3>

            <form id="addressForm" action="save_address.php" method="POST" class="space-y-3">

                <input type="hidden" name="id" id="address_id">

                <input type="text" name="contact_name" id="contact_name" placeholder="Full Name"
                    class="w-full border rounded p-2" required>

                <input type="text" name="contact_mobile" id="contact_mobile" placeholder="Mobile Number"
                    class="w-full border rounded p-2" required>

                <input type="text" name="address_line1" id="address_line1" placeholder="Address Line 1"
                    class="w-full border rounded p-2" required>

                <input type="text" name="address_line2" id="address_line2" placeholder="Address Line 2"
                    class="w-full border rounded p-2">

                <input type="text" name="city" id="city" placeholder="City" class="w-full border rounded p-2" required>

                <input type="text" name="state" id="state" placeholder="State" class="w-full border rounded p-2"
                    required>

                <input type="text" name="pincode" id="pincode" placeholder="Pincode" class="w-full border rounded p-2"
                    required>

                <input type="text" name="landmark" id="landmark" placeholder="Landmark"
                    class="w-full border rounded p-2">

                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_default" id="is_default" value="1">
                    <span>Set as Default</span>
                </label>

                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded">
                        Cancel
                    </button>

                    <button id="submitButton" type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
    // Open add modal
    function openModal() {
        document.getElementById("modalTitle").innerText = "Add New Address";
        document.getElementById("submitButton").innerText = "Save";
        document.getElementById("addressForm").action = "save_address.php";
        document.getElementById("address_id").value = "";
        document.getElementById("contact_name").value = "";
        document.getElementById("contact_mobile").value = "";
        document.getElementById("address_line1").value = "";
        document.getElementById("address_line2").value = "";
        document.getElementById("city").value = "";
        document.getElementById("state").value = "";
        document.getElementById("pincode").value = "";
        document.getElementById("landmark").value = "";
        document.getElementById("is_default").checked = false;
        document.getElementById('addressModal').classList.remove('hidden');
    }

    function editAddress(addr) {
        document.getElementById("modalTitle").innerText = "Edit Address";
        document.getElementById("submitButton").innerText = "Save Changes";
        document.getElementById("addressForm").action = "update_address.php";
        document.getElementById("address_id").value = addr.id;
        document.getElementById("contact_name").value = addr.contact_name;
        document.getElementById("contact_mobile").value = addr.contact_mobile;
        document.getElementById("address_line1").value = addr.address_line1;
        document.getElementById("address_line2").value = addr.address_line2;
        document.getElementById("city").value = addr.city;
        document.getElementById("state").value = addr.state;
        document.getElementById("pincode").value = addr.pincode;
        document.getElementById("landmark").value = addr.landmark;
        document.getElementById("is_default").checked = addr.is_default == 1;
        document.getElementById('addressModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('addressModal').classList.add('hidden');
    }

    function getCartItems() {
        console.log("Fetching cart items from localStorage");
        console.log(localStorage.getItem("cart"));
        return JSON.parse(localStorage.getItem("cart")) || [];
    }
window.continueToPayment = async function() {
    const selected = document.querySelector('input[name="selected_address"]:checked');
    if (!selected) {
        showToast("Please Select a delivery address", { background: "#20aeaeff", color: "#fff" });
        return;
    }

    const btn = document.querySelector("button[onclick='continueToPayment()']");
    btn.disabled = true;
    btn.innerText = "Processing...";
    btn.style.background = "#999";

    let cart = getCartItems();
    if (cart.length === 0) {
        showToast("Cart is empty", { background: "#20aeaeff", color: "#fff" });
        resetPaymentButton();
        return;
    }

    let subtotal = 0;
    cart.forEach(item => {
        const unit = parseFloat(item.variant_price ?? item.price) || 0;
        const qty = parseInt(item.quantity ?? item.qty) || 1;
        subtotal += unit * qty;
    });

    const packingPercent = 3;
    const packingCharge = (subtotal * packingPercent) / 100;
    const netTotal = subtotal;
    const overallTotal = netTotal + packingCharge;

    // Create order in DB
    const res = await fetch("create_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            address_id: selected.value,
            cart: cart,
            subtotal: subtotal.toFixed(2),
            packing_charge: packingCharge.toFixed(2),
            net_total: netTotal.toFixed(2),
            overall_total: overallTotal.toFixed(2)
        })
    });

    const data = await res.json();
    if (!data.success) {
        showToast("Error: " + data.message, { background: "#e63946", color: "#fff" });
        resetPaymentButton();
        return;
    }

    let localOrderId = data.order_id; // use this dynamically

    let options = {
        key: data.key,
        amount: data.amount,
        currency: "INR",
        name: "RgreenMart",
        description: "Order Payment #" + data.order_id,
        order_id: data.razorpay_order_id,

        handler: async function(response) {
            await fetch("update_payment_status.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `order_id=${localOrderId}&status=success&payment_id=${response.razorpay_payment_id}`
            });

            showToast("Payment Successful", { background: "#16a34a", color: "#fff" });

            window.location.href =
                "verify_payment.php?order_id=" + localOrderId +
                "&payment_id=" + response.razorpay_payment_id +
                "&signature=" + response.razorpay_signature;
        },

        modal: {
            ondismiss: function() {
                showToast("Payment cancelled by user", { background: "#e63946", color: "#fff" });
                resetPaymentButton();
                fetch("update_payment_status.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `order_id=${localOrderId}&status=failed`
                });
            }
        },

        prefill: {
            name: data.prefill.name,
            email: data.prefill.email,
            contact: data.prefill.mobile
        }
    };

    let rzp = new Razorpay(options);

    rzp.on('payment.failed', function(response) {
        showToast("Payment Failed! Please try again.", { background: "#e63946", color: "#fff" });
        resetPaymentButton();
        fetch("update_payment_status.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `order_id=${localOrderId}&status=failed&payment_id=${response.error.metadata?.payment_id || ''}`
        });
    });

    rzp.open();
};




function resetPaymentButton() {
    const btn = document.querySelector("button[onclick='continueToPayment()']");
    btn.disabled = false;
    btn.innerText = "Continue to Payment";
    btn.style.background = "#16a34a"; // green
}

    </script>
    <?php include "includes/footer.php"; ?>
</body>

</html>