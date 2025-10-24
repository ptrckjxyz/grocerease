<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$item_id = $_GET['id'] ?? $_GET['item_id'] ?? null;

if ($action) {
    switch ($action) {
        /* ADD ITEM */
        case 'add':
            $item_name = trim($_POST['item_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 1);
            $unit = trim($_POST['unit'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $expiration_date = $_POST['expiration_date'] ?? null;
            $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');

            if (!$item_name || !$category || !$expiration_date) {
                echo "missing_fields";
                exit;
            }

            // Check duplicate
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND item_name = ?");
            $checkStmt->bind_param("is", $user_id, $item_name);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count > 0) {
                echo "<script>alert('Item already exists! Please update the quantity instead.'); window.location='items.php';</script>";
                exit;
            }

            $stmt = $conn->prepare("
                INSERT INTO items (user_id, item_name, category, quantity, unit, price, expiration_date, purchase_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                echo "prepare_error";
                exit;
            }

            $stmt->bind_param("issisdss", $user_id, $item_name, $category, $quantity, $unit, $price, $expiration_date, $purchase_date);

            if ($stmt->execute()) {
                header("Location: items.php");
                exit;
            } else {
                echo "error";
            }
            $stmt->close();
            exit;

        /* GET SINGLE ITEM */
        case 'get_item':
            if ($item_id) {
                $stmt = $conn->prepare("SELECT * FROM items WHERE item_id=? AND user_id=?");
                $stmt->bind_param("ii", $item_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode($result->fetch_assoc());
                $stmt->close();
            }
            exit;

        /* UPDATE ITEM */
case 'update':
    if ($item_id) {
        $item_name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);
        $unit = trim($_POST['unit'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $expiration_date = $_POST['expiration_date'] ?? null;
        $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');

        $stmt = $conn->prepare("
            UPDATE items 
            SET item_name=?, category=?, quantity=?, unit=?, price=?, expiration_date=?, purchase_date=?
            WHERE item_id=? AND user_id=?
        ");
        if (!$stmt) {
            echo "prepare_error";
            exit;
        }

        // ✅ Fixed bind_param type string
        $stmt->bind_param("ssisdssii", $item_name, $category, $quantity, $unit, $price, $expiration_date, $purchase_date, $item_id, $user_id);

        $result = $stmt->execute();
        echo $result ? "updated" : "update_error";
    
        $stmt->close();
    }
    exit;


        /* DELETE ITEM */
        case 'delete':
            if ($item_id) {
                $stmt = $conn->prepare("DELETE FROM items WHERE item_id=? AND user_id=?");
                $stmt->bind_param("ii", $item_id, $user_id);
                if ($stmt->execute()) {
                    header("Location: items.php");
                    exit;
                } else {
                    echo "delete_error";
                }
                $stmt->close();
            }
            exit;

        /* FETCH ALL ITEMS */
        case 'fetch_all':
            // Update status before fetching
            $conn->query("
                UPDATE items 
                SET status = CASE
                    WHEN expiration_date < CURDATE() THEN 'expired'
                    WHEN expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'expiring_soon'
                    ELSE 'fresh'
                END
                WHERE user_id = '$user_id'
            ");

            $stmt = $conn->prepare("SELECT * FROM items WHERE user_id=? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode($items);
            $stmt->close();
            exit;

        /* CHECK EXPIRING */
        case 'check_expiring':
            $stmt = $conn->prepare("
                SELECT item_name, expiration_date, status
                FROM items 
                WHERE user_id=? 
                  AND (status='expiring_soon' OR status='expired')
                ORDER BY expiration_date ASC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = [];
            while ($row = $result->fetch_assoc()) $items[] = $row;
            echo json_encode($items);
            $stmt->close();
            exit;

        /* MANUAL STATUS UPDATE */
        case 'update_status':
            $conn->query("
                UPDATE items 
                SET status = CASE
                    WHEN expiration_date < CURDATE() THEN 'expired'
                    WHEN expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'expiring_soon'
                    ELSE 'fresh'
                END
                WHERE user_id = $user_id
            ");
            echo "status_updated";
            exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Grocery List Management - GrocerEase</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
  <style>
    * {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Poppins', 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 40px;
}

/* ====== Container ====== */
.container {
  max-width: 1400px;
  width: 100%;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
  padding: 40px;
  border: 1px solid #dcedc8;
  animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ====== Header ====== */
.header-glow {
  background: linear-gradient(135deg, #a5d6a7, #81c784);
  border-radius: 15px;
  padding: 25px;
  text-align: center;
  margin-bottom: 30px;
  color: white;
  box-shadow: 0 4px 15px rgba(76, 175, 80, 0.2);
}

.header-glow h1 {
  font-size: 2.2rem;
  font-weight: 700;
  letter-spacing: 1px;
}

/* ====== Form ====== */
form {
  background: #f9fff9;
  padding: 25px;
  border-radius: 15px;
  border: 1px solid #d0e8d0;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
  transition: transform 0.2s ease;
}

form:hover {
  transform: scale(1.01);
}

form input,
form select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e0f2f1;
  border-radius: 10px;
  margin-bottom: 15px;
  font-size: 1rem;
  transition: all 0.3s ease;
}

form input:focus,
form select:focus {
  border-color: #66bb6a;
  box-shadow: 0 0 6px rgba(102, 187, 106, 0.3);
  outline: none;
}

form button {
  width: 100%;
  padding: 12px;
  background: linear-gradient(135deg, #66bb6a, #43a047);
  color: white;
  font-weight: 600;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
}

form button:hover {
  background: linear-gradient(135deg, #57a05c, #2e7d32);
  box-shadow: 0 6px 18px rgba(67, 160, 71, 0.3);
  transform: translateY(-2px);
}

/* ====== Table ====== */
table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
}

th,
td {
  padding: 14px 18px;
  text-align: left;
}

th {
  background: #dcedc8;
  color: #33691e;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.9rem;
}

td {
  border-bottom: 1px solid #e0f2f1;
}

tr:hover {
  background-color: #f1f8e9;
  transition: background 0.3s ease;
}

tr.fresh {
  background-color: #e8f5e9;
}

tr.warning {
  background-color: #fffde7;
}

tr.expired {
  background-color: #ffebee;
}

/* ====== Buttons ====== */
.btn-edit,
.btn-delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  cursor: pointer;
  padding: 8px 10px;
  border-radius: 6px;
  transition: all 0.3s ease;
  font-size: 15px;
}

.btn-edit {
  background: #66bb6a;
  color: white;
}

.btn-edit:hover {
  background: #43a047;
  box-shadow: 0 4px 12px rgba(67, 160, 71, 0.3);
}

.btn-delete {
  background: #e57373;
  color: white;
}

.btn-delete:hover {
  background: #c62828;
  box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
}

/* ====== Back Arrow ====== */
.back-arrow {
  position: fixed;
  top: 25px;
  left: 25px;
  width: 45px;
  height: 45px;
  background: #ffffff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #43a047;
  font-size: 18px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
  z-index: 1000;
  text-decoration: none; /* removes underline */
}

.back-arrow:hover {
  transform: translateY(-3px);
  background: #e8f5e9;
}

/* ====== Modals ====== */
#editModal,
#deleteModal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  align-items: center;
  justify-content: center;
  z-index: 2000;
  animation: fadeIn 0.3s ease;
}

#editModal > div,
#deleteModal > div {
  background: white;
  padding: 25px;
  border-radius: 15px;
  width: 400px;
  max-width: 90%;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from { transform: translateY(-20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

#editModal h3 {
  text-align: center;
  color: #2e7d32;
  margin-bottom: 20px;
  font-weight: 600;
}

#editModal input {
  width: 100%;
  padding: 10px;
  margin-bottom: 12px;
  border: 2px solid #e0f2f1;
  border-radius: 8px;
  transition: all 0.3s;
}

#editModal input:focus {
  border-color: #66bb6a;
  outline: none;
  box-shadow: 0 0 5px rgba(102, 187, 106, 0.3);
}

#editModal button {
  width: 100%;
  padding: 10px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 5px;
}

#editModal button[type="submit"] {
  background: linear-gradient(135deg, #66bb6a, #43a047);
  color: white;
}

#editModal button[type="submit"]:hover {
  background: linear-gradient(135deg, #57a05c, #2e7d32);
}

#editModal button[type="button"] {
  background: #e0e0e0;
  color: #333;
}

#editModal button[type="button"]:hover {
  background: #bdbdbd;
}

/* Delete Modal */
#deleteModal p {
  margin-bottom: 20px;
  color: #424242;
  font-size: 1rem;
}

#deleteModal button {
  width: 100%;
  padding: 10px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 10px;
}

#confirmDeleteBtn {
  background: #e57373;
  color: white;
}

#confirmDeleteBtn:hover {
  background: #c62828;
}

#deleteModal button:last-child {
  background: #e0e0e0;
  color: #333;
}

#deleteModal button:last-child:hover {
  background: #bdbdbd;
}

  </style>
</head>
<body>
  <a href="dashboard.php" class="back-arrow"><i class="fas fa-arrow-left"></i></a>

  <div class="container">
    <div class="header-glow">
      <h1>🛒 Grocery List Management</h1>
    </div>

    <form method="POST" action="items.php?action=add">
      <select name="category" required>
        <option value="" disabled selected>Select Category</option>
        <option>Fruits</option>
        <option>Vegetables</option>
        <option>Dairy</option>
        <option>Beverages</option>
        <option>Snacks</option>
        <option>Meat</option>
        <option>Others</option>
      </select>
      <input type="text" name="item_name" placeholder="Item Name" required>
      <input type="number" name="quantity" placeholder="Quantity" required>
      <input type="text" name="unit" placeholder="Unit (e.g. kg, pcs)">
      <input type="number" step="0.01" name="price" placeholder="Price (₱)">
      <label for="purchase_date">📅 Purchase Date</label>
      <input type="date" name="purchase_date" required>
      <label for="expiration_date">⏳ Expiration Date</label>
      <input type="date" name="expiration_date" required>
      <button type="submit">Add Item</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>Category</th>
          <th>Item Name</th>
          <th>Quantity</th>
          <th>Unit</th>
          <th>Price (₱)</th>
          <th>Purchase Date</th>
          <th>Expiration Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="itemTableBody"></tbody>
    </table>
  </div>

  <!-- Edit Modal -->
<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; 
  background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
  <div style="background:white; padding:20px; border-radius:10px; width:400px;">
    <h3>Edit Item</h3>
    <form id="editForm">
      <input type="hidden" name="item_id" id="editItemId">
      
      <!-- ✅ Added category dropdown -->
      <select name="category" id="editCategory" required>
        <option value="" disabled>Select Category</option>
        <option>Fruits</option>
        <option>Vegetables</option>
        <option>Dairy</option>
        <option>Beverages</option>
        <option>Snacks</option>
        <option>Meat</option>
        <option>Others</option>
      </select><br>

      <input type="text" name="item_name" id="editItemName" placeholder="Item Name" required><br>
      <input type="number" name="quantity" id="editQuantity" placeholder="Quantity" required><br>
      <input type="text" name="unit" id="editUnit" placeholder="Unit"><br>
      <input type="number" step="0.01" name="price" id="editPrice" placeholder="Price"><br>
      <label>Expiration Date</label>
      <input type="date" name="expiration_date" id="editExpiration"><br>
      <button type="submit">Save Changes</button>
      <button type="button" onclick="closeEditModal()">Cancel</button>
    </form>
  </div>
</div>


<!-- Delete Modal -->
<div id="deleteModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; 
  background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
  <div style="background:white; padding:20px; border-radius:10px; width:300px; text-align:center;">
    <p id="deleteMessage"></p>
    <button id="confirmDeleteBtn">Delete</button>
    <button onclick="closeDeleteModal()">Cancel</button>
  </div>
</div>


  <script>
    // Fetch and display all items
    function getStatusColor(expirationDate) {
      const today = new Date();
      const expDate = new Date(expirationDate);
      const diffDays = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) return 'expired';
      if (diffDays <= 3) return 'warning';
      return 'fresh';
    }

    function loadItems() {
      fetch('items.php?action=fetch_all')
        .then(res => res.json())
        .then(items => {
          const tbody = document.getElementById('itemTableBody');
          tbody.innerHTML = items.map(item => {
            const colorClass = getStatusColor(item.expiration_date);
            return `
              <tr class="${colorClass}">
                <td>${item.category}</td>
                <td>${item.item_name}</td>
                <td>${item.quantity}</td>
                <td>${item.unit}</td>
                <td>₱${parseFloat(item.price).toFixed(2)}</td>
                <td>${item.purchase_date}</td>
                <td>${item.expiration_date}</td>
                <td>${item.status}</td>
                <td>
                  <a href="javascript:void(0)" class="btn-edit" onclick="openEditModal(${item.item_id})"><i class="fas fa-edit"></i></a>
                  <a href="javascript:void(0)" class="btn-delete" onclick="openDeleteModal(${item.item_id}, '${item.item_name.replace(/'/g, "\\'")}')"><i class="fas fa-trash-alt"></i></a>
                </td>
              </tr>`;
          }).join('');
        });
    }

    loadItems();
    setInterval(loadItems, 60000); // Auto-refresh every minute

    function openEditModal(itemId) {
  fetch(`items.php?action=get_item&id=${itemId}`)
    .then(res => res.json())
    .then(item => {
      document.getElementById('editItemId').value = item.item_id;
      document.getElementById('editItemName').value = item.item_name;
      document.getElementById('editQuantity').value = item.quantity;
      document.getElementById('editUnit').value = item.unit;
      document.getElementById('editPrice').value = item.price;
      document.getElementById('editExpiration').value = item.expiration_date;

      // ✅ Set category dropdown
      document.getElementById('editCategory').value = item.category;

      document.getElementById('editModal').style.display = 'flex';
    });
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch(`items.php?action=update&id=${formData.get('item_id')}`, {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(result => {
    if (result === 'updated') {
      closeEditModal();
      loadItems();
    } else {
      alert('Update failed.');
    }
  });
});

let deleteItemId = null;

function openDeleteModal(itemId, itemName) {
  deleteItemId = itemId;
  document.getElementById('deleteMessage').innerText = `Are you sure you want to delete "${itemName}"?`;
  document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
  fetch(`items.php?action=delete&id=${deleteItemId}`)
    .then(() => {
      closeDeleteModal();
      loadItems();
    });
});

  </script>
</body>
</html>
