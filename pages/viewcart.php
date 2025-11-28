<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RGreenMart</title>
    <link rel="stylesheet" type="text/css" href="./Styles.css">
    <script src="./cart.js"></script>
</head>

<body>

        <?php include "includes/header.php"; ?>
   

    <div class="cartsplit">
        <div class="itemscontainer">
        <div class="col3">
            <h6 class="heading">Items</h6>
            <h6 class="heading">Quantity</h6>
            <h6 class="heading">Amount</h6>
        </div>

        <div  id="cartItemsContainer"></div>
</div>
        <div class="checkoutcontainer">
            <h3 class="summary-title">Order Summary</h3>
    <p>Total Items: <span id="totalItems">0</span></p>
    <p>Total Quantity: <span id="totalQty">0</span></p>
    <p>Total Amount: ₹<span id="grandTotal" class="finalTotal">0</span></p>
    <button onclick="window.location.href='details.php'" class="checkout-btn">
    Checkout
</button>


</div>

    </div>

</body>

<script>
loadCart();



/** Update Quantity (+ or -) */
function updateQty(index, change) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];

    cart[index].qty = Math.max(1, Number(cart[index].qty) + change);
    localStorage.setItem("cart", JSON.stringify(cart));

    loadCart();
    updateCartCount();
}

/** Manually change qty using input */
function setQty(index, value) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];

    cart[index].qty = Math.max(1, Number(value));
    localStorage.setItem("cart", JSON.stringify(cart));

    loadCart();
    updateCartCount();
}

/** Update cart badge count */
function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    const totalQty = cart.reduce((sum, item) => sum + Number(item.qty), 0);

    const countElement = document.getElementById("cartCount");
    if (countElement) countElement.textContent = totalQty;
}



document.addEventListener("DOMContentLoaded", () => {
    loadCart();
    updateCartCount();
});
</script>

</html>
