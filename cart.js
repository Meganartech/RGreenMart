function adjust(change) {
    let qty = document.getElementById('qty');
    qty.value = Math.max(1, parseInt(qty.value) + change);
}

// Add to Cart
function addToCart(item) {
    const cart = JSON.parse(localStorage.getItem("cart")) || [];

    // Always safe: if cart is empty, this returns -1
    const existingIndex = cart.findIndex(cartItem => cartItem.id === item.id);

    if (existingIndex !== -1) {
        // Item exists → increase qty
        cart[existingIndex].quantity += item.quantity;
    } else {
        // Item doesn't exist → push new
        cart.push(item);
    }

    localStorage.setItem("cart", JSON.stringify(cart));
    updateCartCount();
    showToast(item);

}
function loadCart() {
    const cartContainer = document.getElementById("cartItemsContainer");
    const cart = JSON.parse(localStorage.getItem("cart")) || [];

    cartContainer.innerHTML = "";

    if (cart.length === 0) {
        cartContainer.innerHTML = "<p>No items in cart</p>";

        document.getElementById("totalItems").textContent = 0;
        document.getElementById("totalQty").textContent = 0;
        document.getElementById("grandTotal").textContent = 0;
        return;
    }

    let grandTotal = 0;
    let totalQty = 0;

    cart.forEach((item, index) => {
        const price = Number(item.price);
        const qty = Number(item.quantity);
        const itemTotal = price * qty;

        grandTotal += itemTotal;
        totalQty += qty;

        // Existing row render code...
        const row = document.createElement("div");
        row.classList.add("cart-item-row", "col3");

        row.innerHTML = `
            <div class="cart-item-info">
                <img src="${item.image || './images/default.jpg'}" class="cart-img">
                <div class="floatright">
                    <h4>${item.name}</h4>
                    <span>${item.brand || ""}</span>
                    <span class="price-new">₹${price}</span>
                    <br/>
                    <button class=" delete-btn" onclick="removeItem(${index})">
                               Remove
                           </button>
                </div>
            </div>

            <div class="qty custqty">
                <div class="qty-box">
                    ${item.quantity > 1 
                        ? `<button class="qty-btn" onclick="changeQuantity(${index}, -1)">
                                <i class="fa-solid fa-minus"></i>
                           </button>`
                        : `<button class="qty-btn delete-btn" onclick="removeItem(${index})">
                                <i class="fa-solid fa-trash"></i>
                           </button>`
                    }
                    <hr class="hrline">
                    <input id="qty-${index}" class="qty-input" type="number" value=${qty} min="1" onchange="setQty(${index}, this.value)">
                    <hr class="hrline">
                    <button class="qty-btn" onclick="changeQuantity(${index}, 1)">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>

            <div class="cart-item-amount">
                <strong class="m-1">₹</strong><span id="amount-${index}" class="itemtotal">${itemTotal}</span>
            </div>
        `;

        cartContainer.appendChild(row);
    });

    // Update summary values
    document.getElementById("totalItems").textContent = cart.length;
    document.getElementById("totalQty").textContent = totalQty;
    document.getElementById("grandTotal").textContent = grandTotal;
}

function showToast(item) {
    const toastContainer = document.getElementById("toast-container");

    const toast = document.createElement("div");
    toast.classList.add("mytoast");

    toast.innerHTML = `
        <img src="${item.image}" alt="${item.name}">
        <div class="mytoast-content">
            <div class="mytoast-title">${item.name}</div>
            <div class="mytoast-price">₹${item.price}</div>
        </div>
        <button onclick="location.href='viewcart.php'">View Cart</button>
    `;

    toastContainer.appendChild(toast);

    // Auto remove after 10 seconds
    setTimeout(() => {
        toast.style.animation = "slideUp 0.4s forwards";
        setTimeout(() => toast.remove(), 400);
    }, 2000);
}

// Update cart count
function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

    const countElement = document.getElementById("cartCount");
    if (countElement) {
        countElement.textContent = totalItems;
    }
}
function changeQuantity(index, change) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    
    let newQty = cart[index].quantity + change;

    // If quantity becomes zero → remove item
    if (newQty <= 0) {
        const removedItem = cart[index].name;
        cart.splice(index, 1);
        showToastMessage(`${removedItem} removed from cart`);
    } else {
        cart[index].quantity = newQty;

        if (change > 0) {
            showToastMessage("1 item added");
        } else {
            showToastMessage("Item quantity decreased");
        }
    }

    localStorage.setItem("cart", JSON.stringify(cart));
    loadCart();
    updateCartCount();
}
function removeItem(index) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    const removedItem = cart[index].name;

    cart.splice(index, 1);
    localStorage.setItem("cart", JSON.stringify(cart));

    showToastMessage(`${removedItem} removed from cart`);
    loadCart();
    updateCartCount();
}

function showToastMessage(message) {
    const toastContainer = document.getElementById("toast-container");

    if (!toastContainer) return console.error("Toast container missing!");

    const toast = document.createElement("div");
    toast.classList.add("mytoast");

    toast.innerHTML = `
        <div class="toast-text">${message}</div>
    `;

    toastContainer.appendChild(toast);

    // Auto disappear after 2 sec
    setTimeout(() => {
        toast.style.opacity = "0";
        toast.style.transform = "translateY(-20px)";
        setTimeout(() => toast.remove(), 400);
    }, 2000);
}

// Load count when page loads
document.addEventListener("DOMContentLoaded", updateCartCount);
