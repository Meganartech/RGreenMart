<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// NOTE: Ensure your dbconf.php correctly sets up the $conn PDO object.
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

/* ---------------------------------------
   HANDLE POST REQUESTS (UPDATE & DELETE)
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_item'])) {
        $id = $_POST['id'];
        $fields = [
            'name','price','old_price','discount','stock','category_id','brand_id','weight',
            'packaging_type','product_form','origin','grade','purity','flavor','shelf_life',
            'description','nutrition','storage_instructions','expiry_info','tags'
        ];
        $data = [];
        foreach ($fields as $f) $data[$f] = $_POST[$f] ?? '';

        // Fetch old image path
        $stmt = $conn->prepare("SELECT image FROM items WHERE id=?");
        $stmt->execute([$id]);
        $oldImage = $stmt->fetchColumn();

        // Handle image upload
        $imagePath = $oldImage;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Delete old image if it exists and is not the default/placeholder
            if ($oldImage && file_exists($oldImage)) {
                unlink($oldImage);
            }

            // Get Brand Name for folder creation
            $brandStmt = $conn->prepare("SELECT name FROM brands WHERE id=?");
            $brandStmt->execute([$data['brand_id']]);
            $brandFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $brandStmt->fetchColumn());

            $uploadDir = "Uploads/" . $brandFolder . "/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $imageName = time() . "_" . basename($_FILES['image']['name']);
            $imagePath = $uploadDir . $imageName;
            
            // Move uploaded file
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                // Handle upload failure if necessary
                $imagePath = $oldImage; // Revert to old path on failure
            }
        }

        // Update DB
        $stmt = $conn->prepare("
            UPDATE items SET 
                name=?, price=?, old_price=?, discount=?, stock=?,
                category_id=?, brand_id=?, weight=?, packaging_type=?, product_form=?,
                origin=?, grade=?, purity=?, flavor=?, shelf_life=?,
                description=?, nutrition=?, storage_instructions=?, expiry_info=?, tags=?,
                image=?
            WHERE id=?
        ");
        $stmt->execute([
            $data['name'],$data['price'],$data['old_price'],$data['discount'],$data['stock'],
            $data['category_id'],$data['brand_id'],$data['weight'],$data['packaging_type'],$data['product_form'],
            $data['origin'],$data['grade'],$data['purity'],$data['flavor'],$data['shelf_life'],
            $data['description'],$data['nutrition'],$data['storage_instructions'],$data['expiry_info'],$data['tags'],
            $imagePath,$id
        ]);
        // Redirect to prevent form resubmission and show updated data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    }

if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $message = '';
    $message_class = '';

    try {
        // 1. Get image path BEFORE deleting DB record
        $stmt = $conn->prepare("SELECT image FROM items WHERE id=?");
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();

        // 2. Delete database record first
        $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
        $stmt->execute([$id]);

        // 3. If DB deletion successful, delete the image file
        if ($img && file_exists($img)) {
            @unlink($img); // suppress errors if file missing
        }

        $message = 'Item deleted successfully.';
        $message_class = 'success';
    } catch (Exception $e) {
        $message = 'Error deleting item: ' . $e->getMessage();
        $message_class = 'error';
    }

    // If AJAX request, return message
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo "<div id='server-message' class='" . ($message_class==='success'?'success':'error') . "'>$message</div>";
        exit;
    }
}




}

/* ---------------------------------------
   FETCH DATA
---------------------------------------- */
// Fetch all items with category and brand names
$items = $conn->query("
    SELECT items.*, categories.name AS category_name, brands.name AS brand_name
    FROM items
    LEFT JOIN categories ON items.category_id=categories.id
    LEFT JOIN brands ON items.brand_id=brands.id
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$brands = $conn->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Items</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="admin-container flex">
    <?php // Assuming './common/admin_sidebar.php' exists and is correctly structured ?>
    <?php require_once './common/admin_sidebar.php'; ?>

    <main class="p-6 flex-1">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-7xl mx-auto overflow-x-auto">
            <h2 class="text-2xl font-bold text-indigo-600 mb-6">Manage Items</h2>
<!-- Message Container -->
<div id="message-container" class="mb-4"></div>

            <table class="w-full border-collapse bg-white rounded-lg shadow-sm">
                <thead>
                    <tr class="bg-indigo-500 text-white text-sm">
                        <th class="p-2">ID</th>
                        <th class="p-2">Name</th>
                        <th class="p-2">Price</th>
                        <th class="p-2">Old Price</th>
                        <th class="p-2">Discount</th>
                        <th class="p-2">Stock</th>
                        <th class="p-2">Category</th>
                        <th class="p-2">Brand</th>
                        <th class="p-2">Weight</th>
                        <th class="p-2">Packaging</th>
                        <th class="p-2">Form</th>
                        <th class="p-2">Origin</th>
                        <th class="p-2">Grade</th>
                        <th class="p-2">Purity</th>
                        <th class="p-2">Flavor</th>
                        <th class="p-2">Shelf Life</th>
                        <th class="p-2">Description</th>
                        <th class="p-2">Nutrition</th>
                        <th class="p-2">Expiry Info</th>
                        <th class="p-2">Tags</th>
                        <th class="p-2">Storage</th>
                        <th class="p-2">Image</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr class="hover:bg-gray-50 text-sm">
                        <td class="p-2 border"><?= $item['id'] ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['name']) ?></td>
                        <td class="p-2 border">₹<?= $item['price'] ?></td>
                        <td class="p-2 border">₹<?= $item['old_price'] ?></td>
                        <td class="p-2 border"><?= $item['discount'] ?></td>
                        <td class="p-2 border"><?= $item['stock'] ?></td>
                        <td class="p-2 border" data-category-id="<?= $item['category_id'] ?>"><?= htmlspecialchars($item['category_name'] ?? '') ?></td>
                        <td class="p-2 border" data-brand-id="<?= $item['brand_id'] ?>"><?= htmlspecialchars($item['brand_name'] ?? '') ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['weight']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['packaging_type']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['product_form']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['origin']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['grade']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['purity']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['flavor']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($item['shelf_life']) ?></td>
                        <td class="p-2 border description-cell"><?= htmlspecialchars($item['description']) ?></td>
                        <td class="p-2 border nutrition-cell"><?= htmlspecialchars($item['nutrition']) ?></td>
                        <td class="p-2 border expiry-info-cell"><?= htmlspecialchars($item['expiry_info']) ?></td>
                        <td class="p-2 border tags-cell"><?= htmlspecialchars($item['tags']) ?></td>
                        <td class="p-2 border storage-instructions-cell"><?= htmlspecialchars($item['storage_instructions']) ?></td>
                        <td class="p-2 border image-cell">
                            <?php if(!empty($item['image']) && file_exists($item['image'])): ?>
                                <img src="<?= $item['image'] ?>" class="w-12 h-12 object-cover rounded">
                            <?php else: echo "No Image"; endif; ?>
                        </td>
                        <td class="p-2 border">
                            <button class="edit-btn bg-indigo-600 text-white px-3 py-1 rounded" data-id="<?= $item['id'] ?>">Edit</button>
                            <form method="POST" class="inline-block delete-form" data-item-name="<?= htmlspecialchars($item['name']) ?>">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" name="delete" class="bg-red-600 text-white px-3 py-1 rounded delete-btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div class="modal fade" id="updateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data" id="updateForm">
        <div class="modal-header bg-indigo-600 text-white">
          <h5 class="modal-title">Update Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="modal_id">

          <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="name" id="modal_name" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Price</label>
              <input type="number" step="0.01" class="form-control" name="price" id="modal_price" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Old Price</label>
              <input type="number" step="0.01" class="form-control" name="old_price" id="modal_old_price">
          </div>
          <div class="mb-3">
              <label class="form-label">Discount</label>
              <input type="text" class="form-control" name="discount" id="modal_discount">
          </div>
          <div class="mb-3">
              <label class="form-label">Stock</label>
              <input type="number" class="form-control" name="stock" id="modal_stock">
          </div>
          <div class="mb-3">
              <label class="form-label">Category</label>
              <select class="form-select" name="category_id" id="modal_category" required>
                  <?php foreach($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="mb-3">
              <label class="form-label">Brand</label>
              <select class="form-select" name="brand_id" id="modal_brand" required>
                  <?php foreach($brands as $brand): ?>
                  <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="mb-3">
              <label class="form-label">Weight</label>
              <input type="text" class="form-control" name="weight" id="modal_weight">
          </div>
          <div class="mb-3">
              <label class="form-label">Packaging Type</label>
              <input type="text" class="form-control" name="packaging_type" id="modal_packaging_type">
          </div>
          <div class="mb-3">
              <label class="form-label">Product Form</label>
              <input type="text" class="form-control" name="product_form" id="modal_product_form">
          </div>
          <div class="mb-3">
              <label class="form-label">Origin</label>
              <input type="text" class="form-control" name="origin" id="modal_origin">
          </div>
          <div class="mb-3">
              <label class="form-label">Grade</label>
              <input type="text" class="form-control" name="grade" id="modal_grade">
          </div>
          <div class="mb-3">
              <label class="form-label">Purity</label>
              <input type="text" class="form-control" name="purity" id="modal_purity">
          </div>
          <div class="mb-3">
              <label class="form-label">Flavor</label>
              <input type="text" class="form-control" name="flavor" id="modal_flavor">
          </div>
          <div class="mb-3">
              <label class="form-label">Shelf Life</label>
              <input type="text" class="form-control" name="shelf_life" id="modal_shelf_life">
          </div>
          <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" id="modal_description"></textarea>
          </div>
          <div class="mb-3">
              <label class="form-label">Nutrition</label>
              <textarea class="form-control" name="nutrition" id="modal_nutrition"></textarea>
          </div>
          <div class="mb-3">
              <label class="form-label">Storage Instructions</label>
              <textarea class="form-control" name="storage_instructions" id="modal_storage_instructions"></textarea>
          </div>
          <div class="mb-3">
              <label class="form-label">Expiry Info</label>
              <input type="text" class="form-control" name="expiry_info" id="modal_expiry_info">
          </div>
          <div class="mb-3">
              <label class="form-label">Tags</label>
              <input type="text" class="form-control" name="tags" id="modal_tags">
          </div>
          <div class="mb-3">
              <label class="form-label">Image</label>
              <input type="file" class="form-control" name="image" id="modal_image">
              <small id="current_image" class="form-text text-muted"></small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_item" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Function to show the modal
function showModal() {
    new bootstrap.Modal(document.getElementById('updateModal')).show();
}

// Function to set a select element by its visible text content
function setSelectByText(selectElement, text) {
    for (let i = 0; i < selectElement.options.length; i++) {
        if (selectElement.options[i].text.trim() === text.trim()) {
            selectElement.value = selectElement.options[i].value;
            return;
        }
    }
}

/* ---------------------------------------
   EDIT MODAL LOGIC
---------------------------------------- */
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('tr');
        const cells = row.querySelectorAll('td');

        // Column Index Mapping (based on table headers):
        // 0: ID, 1: Name, 2: Price, 3: Old Price, 4: Discount, 5: Stock,
        // 6: Category, 7: Brand, 8: Weight, 9: Packaging, 10: Form, 11: Origin,
        // 12: Grade, 13: Purity, 14: Flavor, 15: Shelf Life, 16: Description,
        // 17: Nutrition, 18: Expiry Info, 19: Tags, 20: Storage, 21: Image

        // Extracting data from table cells
        const itemCategoryName = cells[6].innerText.trim();
        const itemBrandName = cells[7].innerText.trim();
        const itemImageElement = cells[21].querySelector('img');
        const itemImageSrc = itemImageElement ? itemImageElement.src : 'No Image';

        // Set modal values
        document.getElementById('modal_id').value = cells[0].innerText.trim();
        document.getElementById('modal_name').value = cells[1].innerText.trim();
        document.getElementById('modal_price').value = cells[2].innerText.replace('₹','').trim();
        document.getElementById('modal_old_price').value = cells[3].innerText.replace('₹','').trim();
        document.getElementById('modal_discount').value = cells[4].innerText.trim();
        document.getElementById('modal_stock').value = cells[5].innerText.trim();
        document.getElementById('modal_weight').value = cells[8].innerText.trim();
        document.getElementById('modal_packaging_type').value = cells[9].innerText.trim();
        document.getElementById('modal_product_form').value = cells[10].innerText.trim();
        document.getElementById('modal_origin').value = cells[11].innerText.trim();
        document.getElementById('modal_grade').value = cells[12].innerText.trim();
        document.getElementById('modal_purity').value = cells[13].innerText.trim();
        document.getElementById('modal_flavor').value = cells[14].innerText.trim();
        document.getElementById('modal_shelf_life').value = cells[15].innerText.trim();
        
        // Textareas
        document.getElementById('modal_description').value = cells[16].innerText.trim();
        document.getElementById('modal_nutrition').value = cells[17].innerText.trim();
        document.getElementById('modal_expiry_info').value = cells[18].innerText.trim();
        document.getElementById('modal_tags').value = cells[19].innerText.trim();
        document.getElementById('modal_storage_instructions').value = cells[20].innerText.trim();
        
        // Image path display
        document.getElementById('current_image').textContent = itemImageSrc;

        // Set Category and Brand Selects by matching the displayed name
        setSelectByText(document.getElementById('modal_category'), itemCategoryName);
        setSelectByText(document.getElementById('modal_brand'), itemBrandName);

        showModal();
    });
});

document.addEventListener('DOMContentLoaded', function() {

    let itemToDeleteForm = null;

    // Attach delete button click to open modal
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            itemToDeleteForm = this.closest('form');
            const itemName = itemToDeleteForm.getAttribute('data-item-name');
            document.getElementById('deleteModalMessage').textContent = `Are you sure you want to delete "${itemName}"?`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });

    // Confirm delete
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!itemToDeleteForm) return;

        const formData = new FormData(itemToDeleteForm);
        formData.append('delete', '1'); // Ensure 'delete' is sent

        // Debug: check FormData
        // for (let pair of formData.entries()) console.log(pair[0]+ ': ' + pair[1]);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(data => {
            // Parse PHP response
            const parser = new DOMParser();
            const htmlDoc = parser.parseFromString(data, 'text/html');
            const messageDiv = htmlDoc.querySelector('#server-message');

            if (messageDiv) {
                showMessage(messageDiv.textContent, messageDiv.classList.contains('success'));
            }

            if (messageDiv && messageDiv.classList.contains('success')) {
                // Remove row from table
                itemToDeleteForm.closest('tr').remove();
            }

            itemToDeleteForm = null;
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        })
        .catch(err => {
            console.error(err);
            showMessage('An error occurred while deleting. Try again.', false);
        });
    });

    // Function to show message
    function showMessage(msg, success = true) {
        const container = document.getElementById('message-container');
        container.innerHTML = `<div class="alert ${success ? 'alert-success' : 'alert-danger'}">${msg}</div>`;
        if (success) setTimeout(() => { container.innerHTML = ''; }, 5000);
    }

});

</script>
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-red-600 text-white">
        <h5 class="modal-title">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="deleteModalMessage">Are you sure you want to delete this item?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>


</body>
</html>