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
                $stmt->bind_param("ssisdssii", $item_name, $category, $quantity, $unit, $price, $expiration_date, $purchase_date, $item_id, $user_id);
                echo $stmt->execute() ? "updated" : "update_error";
                $stmt->close();
            }
            exit;

        case 'delete':
            if ($item_id) {
                $stmt = $conn->prepare("DELETE FROM items WHERE item_id=? AND user_id=?");
                $stmt->bind_param("ii", $item_id, $user_id);
                $stmt->execute();
                header("Location: items.php");
            }
            exit;

        case 'fetch_all':
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
            while ($row = $result->fetch_assoc()) $items[] = $row;
            echo json_encode($items);
            exit;
        
        /* CHECK EXPIRING ITEMS */
        case 'check_expiring':
            // Get both expired AND expiring soon items
            $stmt = $conn->prepare("
                SELECT item_id, item_name, expiration_date 
                FROM items 
                WHERE user_id = ? 
                AND (
                    expiration_date < CURDATE() 
                    OR expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                )
                ORDER BY expiration_date ASC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }

            header('Content-Type: application/json');
            echo json_encode($items);
            exit;

        /* DELETE EXPIRED ITEMS */
        case 'delete_expired':
            $stmt = $conn->prepare("
                SELECT item_id, item_name 
                FROM items 
                WHERE user_id = ? AND expiration_date < CURDATE()
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $deletedItems = [];
            while ($row = $result->fetch_assoc()) {
                $deletedItems[] = $row;
            }
            $stmt->close();

            // Delete expired items
            $deleteStmt = $conn->prepare("DELETE FROM items WHERE user_id = ? AND expiration_date < CURDATE()");
            $deleteStmt->bind_param("i", $user_id);
            $deleteStmt->execute();
            $deleteStmt->close();

            header('Content-Type: application/json');
            echo json_encode(['deleted' => $deletedItems]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Grocery List Management - GrocerEase</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;background:#e8f5e9;min-height:100vh;padding:40px;}

/* Container */
.container{max-width:1300px;margin:auto;background:white;border-radius:20px;padding:30px;box-shadow:0 10px 25px rgba(0,0,0,0.1);}

/* Header */
.header {
  display: flex;
  align-items: center;
  justify-content: center; /* center title */
  position: relative; /* needed for absolute button */
  background: linear-gradient(135deg,#a5d6a7,#81c784);
  color: white;
  padding: 20px 30px;
  border-radius: 15px;
}

.header h1 {
  text-align: center;
  flex: 1;
}

.add-btn{
  background:white;color:#388e3c;border:none;border-radius:50%;
  width:45px;height:45px;font-size:22px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all 0.3s; position: absolute; right:20px;
}
.add-btn:hover{background:#c8e6c9;transform:rotate(90deg);}

/* Category grid */
.categories{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:30px;}
.category-card{
  border:1px solid #c8e6c9;border-radius:15px;padding:20px;
  background:#f9fff9;box-shadow:0 4px 12px rgba(0,0,0,0.05);
}
.category-card h2{
  font-size:1.2rem;color:#2e7d32;margin-bottom:15px;
  border-bottom:2px solid #a5d6a7;padding-bottom:5px;
}
.item{display:flex;justify-content:space-between;align-items:center;margin:8px 0;padding:8px 10px;border-radius:8px;transition:0.3s;}
.item:hover{background:#f1f8e9;}
.item span{font-size:0.95rem;}
.actions button{
  border:none;border-radius:6px;padding:5px 8px;margin-left:4px;
  cursor:pointer;font-size:0.85rem;
}
.actions .edit{background:#66bb6a;color:white;}
.actions .delete{background:#e57373;color:white;}
.actions .edit:hover{background:#388e3c;}
.actions .delete:hover{background:#c62828;}

/* Modal Form */
#addModal,#editModal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;z-index:1000;}
.modal-content{
  background:white;padding:25px;border-radius:15px;width:400px;
  box-shadow:0 8px 20px rgba(0,0,0,0.2);
}
.modal-content h3{text-align:center;margin-bottom:15px;color:#2e7d32;}
.modal-content input,.modal-content select{width:100%;padding:10px;margin:6px 0;border:1px solid #c8e6c9;border-radius:8px;}
.modal-content button{width:100%;padding:10px;margin-top:10px;border:none;border-radius:8px;cursor:pointer;font-weight:600;}
.modal-content .save{background:#66bb6a;color:white;}
.modal-content .cancel{background:#e0e0e0;}
.modal-content .save:hover{background:#388e3c;}
.modal-content .cancel:hover{background:#bdbdbd;}

/* Delete Modal */
#deleteModal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000;animation:fadeIn 0.3s;}
#deleteModal .modal-content{text-align:center;max-width:400px;}
#deleteModal p{margin-top:10px;color:#555;}
@keyframes fadeIn {from {opacity:0;} to {opacity:1;}}


.back-arrow{
  position: fixed;
  top: 25px;
  left: 25px;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.back-arrow svg {
  width: 20px;
  height: 20px;
}

.back-arrow:hover {
  background: #e8f5e9;
}

</style>
</head>
<body>
<a href="dashboard.php" class="back-arrow" title="Back to Dashboard">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M15 18L9 12L15 6" stroke="#43a047" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</a>

<div class="container">
  <div class="header">
    <h1>🛒 Grocery List Management</h1>
    <button class="add-btn" onclick="openAddModal()"><i class="fas fa-plus"></i></button>
  </div>

  <div class="categories" id="categoryContainer"></div>
</div>

<!-- Add Modal -->
<div id="addModal">
  <div class="modal-content">
    <h3>Add New Item</h3>
    <form method="POST" action="items.php?action=add">
      <select name="category" required>
        <option value="" disabled selected>Select Category</option>
        <option>Fruits</option><option>Vegetables</option>
        <option>Dairy</option><option>Beverages</option>
        <option>Cereals</option><option>Meat</option><option>Poultry Products</option><option>Others</option>
      </select>
      <input type="text" name="item_name" placeholder="Item Name" required>
      <input type="number" name="quantity" placeholder="Quantity" required>
      <input type="text" name="unit" placeholder="Unit (e.g. kg, pcs)">
      <input type="number" step="0.01" name="price" placeholder="Price (₱)">
      <label>Purchase Date</label><input type="date" name="purchase_date" required>
      <label>Expiration Date</label><input type="date" name="expiration_date" required>
      <button type="submit" class="save">Add Item</button>
      <button type="button" class="cancel" onclick="closeAddModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal">
  <div class="modal-content">
    <h3>Edit Item</h3>
    <form id="editForm">
      <input type="hidden" name="item_id" id="editItemId">
      <select name="category" id="editCategory" required>
        <option>Fruits</option><option>Vegetables</option><option>Dairy</option>
        <option>Beverages</option><option>Cereals</option><option>Meat</option><option>Poultry Products</option><option>Others</option>
      </select>
      <input type="text" name="item_name" id="editItemName" placeholder="Item Name" required>
      <input type="number" name="quantity" id="editQuantity" placeholder="Quantity" required>
      <input type="text" name="unit" id="editUnit" placeholder="Unit">
      <input type="number" step="0.01" name="price" id="editPrice" placeholder="Price">
      <label>Expiration Date</label><input type="date" name="expiration_date" id="editExpiration">
      <button type="submit" class="save">Save Changes</button>
      <button type="button" class="cancel" onclick="closeEditModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal">
  <div class="modal-content">
    <h3>Delete Item</h3>
    <p>Are you sure you want to delete this item?</p>
    <div style="display:flex;justify-content:space-between;margin-top:20px;">
      <button class="save" id="confirmDeleteBtn" style="flex:1;margin-right:10px;">Delete</button>
      <button class="cancel" onclick="closeDeleteModal()" style="flex:1;">Cancel</button>
    </div>
  </div>
</div>

<script>
function openAddModal(){document.getElementById('addModal').style.display='flex';}
function closeAddModal(){document.getElementById('addModal').style.display='none';}
function closeEditModal(){document.getElementById('editModal').style.display='none';}

function getStatusColor(expDate){
  const today=new Date();const exp=new Date(expDate);
  const diff=(exp-today)/(1000*60*60*24);
  if(diff<0)return 'expired';if(diff<=3)return 'warning';return 'fresh';
}

function loadItems(){
  fetch('items.php?action=fetch_all').then(res=>res.json()).then(items=>{
    const cats=['Fruits','Vegetables','Dairy','Beverages','Cereals','Meat','Poultry Products','Others'];
    const container=document.getElementById('categoryContainer');
    container.innerHTML='';
    cats.forEach(cat=>{
      const catItems=items.filter(i=>i.category===cat);
      const list=catItems.map(item=>{
        const color=getStatusColor(item.expiration_date);
        return `<div class="item ${color}">
          <span>${item.item_name} (${item.quantity} ${item.unit||''})</span>
          <div class="actions">
            <button class="edit" onclick="openEditModal(${item.item_id})"><i class='fas fa-edit'></i></button>
            <button class="delete" onclick="deleteItem(${item.item_id})"><i class='fas fa-trash'></i></button>
          </div></div>`;
      }).join('') || '<p style="color:#777;font-size:0.9rem;">No items yet.</p>';
      container.innerHTML+=`<div class="category-card"><h2>${cat}</h2>${list}</div>`;
    });
  });
}
loadItems();

function openEditModal(id){
  fetch(`items.php?action=get_item&id=${id}`).then(r=>r.json()).then(item=>{
    document.getElementById('editItemId').value=item.item_id;
    document.getElementById('editCategory').value=item.category;
    document.getElementById('editItemName').value=item.item_name;
    document.getElementById('editQuantity').value=item.quantity;
    document.getElementById('editUnit').value=item.unit;
    document.getElementById('editPrice').value=item.price;
    document.getElementById('editExpiration').value=item.expiration_date;
    document.getElementById('editModal').style.display='flex';
  });
}

document.getElementById('editForm').addEventListener('submit',e=>{
  e.preventDefault();
  const fd=new FormData(e.target);
  fetch(`items.php?action=update&id=${fd.get('item_id')}`,{method:'POST',body:fd})
  .then(r=>r.text()).then(t=>{if(t==='updated'){closeEditModal();loadItems();}});
});

let deleteItemId = null;

function deleteItem(id){
  deleteItemId = id;
  document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal(){
  deleteItemId = null;
  document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
  if(deleteItemId){
    fetch(`items.php?action=delete&id=${deleteItemId}`)
      .then(()=> {
        loadItems();
        closeDeleteModal();
      });
  }
});
</script>
</body>
</html>