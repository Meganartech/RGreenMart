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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <?php include "includes/header.php"; ?>

    <div class="max-w-3xl mx-auto m-10 p-6 bg-white rounded-xl shadow-lg">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-700">Select Delivery Address</h2>
            <button onclick="openModal()" class="text-indigo-600 hover:text-indigo-800 font-semibold">
                + Add New Address
            </button>
        </div>

        <div class="space-y-4">
            <?php foreach ($addresses as $addr): ?>
            <div class="border rounded-xl p-4 hover:shadow-md bg-gray-50 transition address-card">
                <div class="flex justify-between">
                    <label class="flex items-start space-x-3 cursor-pointer w-full">
                        <input type="radio" name="selected_address" value="<?= $addr['id'] ?>"
                            data-pincode="<?= htmlspecialchars($addr['pincode']) ?>"
                            class="mt-1 w-4 h-4 text-indigo-600" <?= $addr['is_default'] ? 'checked' : '' ?>>

                        <div class="flex-1">
                            <p class="font-semibold text-gray-800">
                                <?= htmlspecialchars($addr['contact_name']) ?>
                                <?php if($addr['is_default']): ?>
                                <span
                                    class="ml-2 text-xs bg-green-200 text-green-700 px-2 py-1 rounded-full">Default</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($addr['contact_mobile']) ?></p>
                            <p class="text-gray-700 mt-1 text-sm">
                                <?= htmlspecialchars($addr['address_line1']) ?>,
                                <?= htmlspecialchars($addr['address_line2']) ?><br>
                                <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> -
                                <span class="font-bold"><?= htmlspecialchars($addr['pincode']) ?></span>
                                <?php if(!empty($addr['landmark'])): ?>
                                <br><span class="text-gray-500 italic">Landmark:
                                    <?= htmlspecialchars($addr['landmark']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </label>

                    <div class="flex space-x-4 ml-4">
                        <button onclick='editAddress(<?= json_encode($addr) ?>)'
                            class="text-blue-500 hover:text-blue-700">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <a href="delete_address.php?id=<?= $addr['id'] ?>"
                            onclick="return confirm('Delete this address?')" class="text-red-500 hover:text-red-700">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($addresses)): ?>
            <div class="text-center py-10 border-2 border-dashed rounded-xl">
                <p class="text-gray-500">No saved addresses found. Please add one to continue.</p>
            </div>
            <?php endif; ?>
        </div>
        <div id="orderSummary"
            class="mt-8 mb-4 p-4 bg-gray-50 border-t-4 border-indigo-500 rounded-lg text-gray-700 space-y-2">

            <div class="flex justify-between">
                <span>Items Subtotal:</span>
                <span>₹<span id="subtotalAmount">0.00</span></span>
            </div>

            <div class="flex justify-between">
                <span>Shipping Charge:</span>
                <span>₹<span id="shippingCharge">0.00</span></span>
            </div>

            <!-- NEW -->
            <div class="flex justify-between ">
                <span>Courier:</span>
                <span id="courierName">—</span>
            </div>

            <div class="flex justify-between ">
                <span>Estimated Delivery:</span>
                <span id="courierETA">—</span>
            </div>

            <hr class="my-2">

            <div class="flex justify-between text-xl font-bold text-gray-900">
                <span>Final Total:</span>
                <span>₹<span id="finalTotal">0.00</span></span>
            </div>
        </div>


        <button onclick="continueToPayment()" id="payBtn"
            class="mt-4 w-full bg-green-600 text-white py-3 rounded-lg font-bold text-lg hover:bg-green-700 transition disabled:bg-gray-400">
            Continue to Payment
        </button>
    </div>

    <div id="addressModal" style="z-index: 51;"
        class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white p-6 rounded-xl shadow-lg w-full max-w-md">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Address</h3>
            <form id="addressForm" action="save_address.php" method="POST" class="space-y-3">
                <input type="hidden" name="id" id="address_id">
                <input type="text" name="contact_name" id="contact_name" placeholder="Full Name"
                    class="w-full border rounded p-2" required>
                <input type="text" name="contact_mobile" id="contact_mobile" placeholder="Mobile Number"
                    class="w-full border rounded p-2" required>
                <input type="text" name="address_line1" id="address_line1" placeholder="Flat, House no., Building"
                    class="w-full border rounded p-2" required>
                <input type="text" name="address_line2" id="address_line2" placeholder="Area, Street, Sector, Village"
                    class="w-full border rounded p-2">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="city" id="city" placeholder="City" class="w-full border rounded p-2"
                        required>
                    <input type="text" name="state" id="state" placeholder="State" class="w-full border rounded p-2"
                        required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="pincode" id="pincode" placeholder="Pincode"
                        class="w-full border rounded p-2" required>
                    <input type="text" name="landmark" id="landmark" placeholder="Landmark (Optional)"
                        class="w-full border rounded p-2">
                </div>
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_default" id="is_default" value="1">
                    <span class="text-sm text-gray-600">Set as Default Address</span>
                </label>
                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" onclick="closeModal()"
                        class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                    <button id="submitButton" type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    window.currentShippingCharge = 0;
    window.currentFinalTotal = 0;
    window.currentCourier = null;
    window.currentETA = null;

    function openModal() {
        document.getElementById("addressForm").reset();
        document.getElementById("modalTitle").innerText = "Add New Address";
        document.getElementById("addressForm").action = "save_address.php";
        document.getElementById("address_id").value = "";
        document.getElementById('addressModal').classList.remove('hidden');
    }

    function editAddress(addr) {
        document.getElementById("modalTitle").innerText = "Edit Address";
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
        return JSON.parse(localStorage.getItem("cart")) || [];
    }

    function calculateSubtotal() {
        let cart = getCartItems();
        return cart.reduce((total, item) => {
            const price = parseFloat(item.variant_price ?? item.price) || 0;
            const qty = parseInt(item.quantity ?? item.qty) || 1;
            return total + (price * qty);
        }, 0);
    }

    function calculateCartWeight() {
        let cart = getCartItems();
        return cart.reduce((total, item) => {
            const w = parseFloat(item.weight ?? 0.5);
            const qty = parseInt(item.quantity ?? item.qty) || 1;
            return total + (w * qty);
        }, 0);
    }

    function updateOrderSummary(shipping = 0, courier = null, eta = null, courierId = null) {
        shipping = parseFloat(shipping) || 0;

        const subtotal = parseFloat(calculateSubtotal()) || 0;
        const finalTotal = subtotal + shipping;

        document.getElementById('subtotalAmount').innerText = subtotal.toFixed(2);
        document.getElementById('shippingCharge').innerText = shipping.toFixed(2);
        document.getElementById('finalTotal').innerText = finalTotal.toFixed(2);

        document.getElementById('courierName').innerText = courier ?? '—';
        document.getElementById('courierETA').innerText = eta ?? '—';

        window.currentShippingCharge = shipping;
        window.currentCourierId = courierId;
        window.currentFinalTotal = finalTotal;
        window.currentCourier = courier;
        window.currentETA = eta;
    }


    // Address selection handler - attach on DOMContentLoaded and ensure first address selected by default
    document.addEventListener('DOMContentLoaded', () => {
        const radios = Array.from(document.querySelectorAll('input[name="selected_address"]'));
        if (radios.length === 0) {
            updateOrderSummary(0, 'Calculating…', 'Calculating…',0);

            return;
        }

        // Attach change listeners
        radios.forEach(radio => {
            radio.addEventListener('change', async function() {
                const pincode = this.getAttribute('data-pincode');
                // Update displayed subtotal immediately
                updateOrderSummary(0, 'Calculating…', 'Calculating…',0);

                if (!pincode) {
                    // If no pincode, keep shipping at 0
                    return;
                }

                document.getElementById('shippingCharge').innerText = "Calculating...";

                try {
                    const totalWeight = calculateCartWeight();

                    console.log('getDeliveryCharge request:', {
                        pincode,
                        totalWeight
                    });

                    const res = await fetch('getDeliveryCharge.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `pincode=${encodeURIComponent(pincode)}&weight=${encodeURIComponent(totalWeight)}`
                    });

                    const rawText = await res.text();

                    // Safely parse JSON
                    const data = JSON.parse(rawText);

                    let shipping = 0;

                    if (data.success === true) {

                        shipping = parseFloat(data.rate) || 0;

                        console.log("Selected Courier:");
                        console.log("Courier ID:", data.courier_id);
                        console.log("Courier Name:", data.courier_name);
                        console.log("Rate:", data.rate);
                        console.log("ETA Days:", data.estimated_delivery_days);
                        console.log("ETD:", data.etd);
                        console.log("Shiprocket Recommended:", data
                            .is_shiprocket_recommended);

                        // OPTIONAL: show courier info in UI
                        document.getElementById('courierName').innerText = data
                            .courier_name;
                        document.getElementById('courierETA').innerText =
                            data.estimated_delivery_days ?
                            `${data.estimated_delivery_days} Days` :
                            'N/A';

                    } else {
                        console.warn("Delivery API error:", data.error);
                    }

                    // Update order summary
                    updateOrderSummary(
                        shipping,
                        data.courier_name,
                        data.estimated_delivery_days ?
                        `${data.estimated_delivery_days} Days` :
                        'N/A',data.courier_id
                    );


                } catch (err) {
                    console.error("Shipping fetch error:", err);
                    updateOrderSummary(0, 'Calculating…', 'Calculating…',0);

                }

            });
        });

        // If no address is pre-selected, select the first one by default
        let checked = document.querySelector('input[name="selected_address"]:checked');
        if (!checked) {
            radios[0].checked = true;
            radios[0].dispatchEvent(new Event('change', {
                bubbles: true
            }));
        } else {
            checked.dispatchEvent(new Event('change', {
                bubbles: true
            }));
        }
    });

    window.continueToPayment = async function() {
        const selected = document.querySelector('input[name="selected_address"]:checked');
        if (!selected) {
            alert("Please select a delivery address");
            return;
        }

        const cart = getCartItems();
        if (!cart.length) {
            alert("Cart is empty");
            return;
        }

        const btn = document.getElementById("payBtn");
        btn.disabled = true;
        btn.innerText = "Processing...";

        try {
          const payload = {
    address_id: selected.value,
    cart: cart,
    subtotal: calculateSubtotal().toFixed(2),
    shipping_charge: window.currentShippingCharge.toFixed(2),
    overall_total: window.currentFinalTotal.toFixed(2),
    ...(window.currentCourier ? { courier_name: window.currentCourier } : {}),
    ...(window.currentETA ? { courier_eta: window.currentETA } : {}),
    ...(window.currentCourierId ? { courier_company_id: window.currentCourierId } : {})
};


           
            const res = await fetch("create_order.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            const options = {
                key: data.key,
                amount: data.amount,
                currency: "INR",
                name: "RgreenMart",
                description: "Order #" + data.order_id,
                order_id: data.razorpay_order_id,
                handler: async function(response) {
                    window.location.href =
                        `verify_payment.php?order_id=${data.order_id}&payment_id=${response.razorpay_payment_id}&signature=${response.razorpay_signature}`;
                },
                modal: {
                    ondismiss: function() {
                        resetPaymentButton();
                        fetch("update_payment_status.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: `order_id=${data.order_id}&status=failed`
                        });
                    }
                },
                prefill: {
                    name: data.prefill.name,
                    email: data.prefill.email,
                    contact: data.prefill.mobile
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();

        } catch (error) {
            console.error("Payment initiation error:", error);
            showToast("Error: " + error.message, {
                background: "#e63946",
                color: "#fff"
            });
            resetPaymentButton();
        }
    };

    function resetPaymentButton() {
        const btn = document.getElementById("payBtn");
        btn.disabled = false;
        btn.innerText = "Continue to Payment";
    }
    </script>

    <?php include "includes/footer.php"; ?>
</body>

</html>