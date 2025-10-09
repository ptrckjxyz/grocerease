<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$item_id = $_GET['id'] ?? null;

if ($action) {
    switch ($action) {
        case 'add':
            $item_name = trim($_POST['item_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 1);
            $unit = trim($_POST['unit'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $expiration_date = $_POST['expiration_date'] ?? null;

            if ($item_name && $category && $expiration_date) {
                $stmt = $conn->prepare("
                    INSERT INTO items (user_id, item_name, category, quantity, unit, price, expiration_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issisds", $user_id, $item_name, $category, $quantity, $unit, $price, $expiration_date);
                if ($stmt->execute()) {
                    header("Location: items.php");
                    exit;
                } else {
                    echo "error";
                }
                $stmt->close();
            } else {
                echo "missing_fields";
            }
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

                $stmt = $conn->prepare("
                    UPDATE items SET item_name=?, category=?, quantity=?, unit=?, price=?, expiration_date=?
                    WHERE item_id=? AND user_id=?
                ");
                $stmt->bind_param("ssisdsii", $item_name, $category, $quantity, $unit, $price, $expiration_date, $item_id, $user_id);
                echo $stmt->execute() ? "updated" : "update_error";
                $stmt->close();
            }
            exit;

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
            break;

        case 'fetch':
            $category = $_GET['category'] ?? '';
            if ($category) {
                $stmt = $conn->prepare("SELECT * FROM items WHERE user_id=? AND category=? ORDER BY created_at DESC");
                $stmt->bind_param("is", $user_id, $category);
            } else {
                $stmt = $conn->prepare("SELECT * FROM items WHERE user_id=? ORDER BY created_at DESC");
                $stmt->bind_param("i", $user_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode($items);
            $stmt->close();
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
  <style>
    * { 
      margin: 0; 
      padding: 0; 
      box-sizing: border-box; 
    } 
    
    body { 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
      background: radial-gradient(circle at top left, #e8f5e9 0%, #c8e6c9 50%, #a5d6a7 100%); 
      min-height: 100vh; 
      padding: 20px; 
      display: flex; 
      justify-content: center; 
      align-items: flex-start; } 
      
    .container { 
      position: relative; 
      z-index: 1; 
      max-width: 1100px; 
      width: 95%; 
      margin: 40px auto; 
      background: #ffffff; 
      border-radius: 20px; 
      box-shadow: 0 8px 25px rgba(76, 175, 80, 0.1); 
      padding: 40px; 
      overflow: hidden; 
      border: 1px solid #c8e6c9; } 
      
    .header-glow { 
      background: linear-gradient(135deg, #c8f7d1, #b2eabf); 
      border-radius: 15px; 
      padding: 25px 20px; 
      text-align: center; 
      position: relative; 
      margin-bottom: 25px; 
      overflow: hidden; } 
        
    .header-glow h1 { 
      color: #2e7d32; 
      font-size: 2.8rem; 
      font-weight: 700; 
      margin: 0; 
      display: inline-block; 
      position: relative; 
      letter-spacing: 1px; 
      z-index: 2; } 
      
    .header-glow::after { 
      content: ""; 
      position: absolute; 
      top: 0; 
      left: -80%; 
      width: 50%; 
      height: 100%; 
      background: linear-gradient( 120deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.9) 50%, rgba(255, 255, 255, 0) 100% ); 
      transform: rotate(25deg); 
      animation: shimmer 3s infinite linear; 
      mix-blend-mode: screen; 
    } 
    
    @keyframes shimmer { 
      0% { transform: translateX(-150%) translateY(-50%) rotate(45deg); } 
      100% { transform: translateX(150%) translateY(50%) rotate(45deg); } 
    } 
    
    .header-glow h1::after { 
      content: attr(data-text); 
      position: absolute; 
      top: 100%; 
      left: 0; 
      right: 0; 
      color: #2e7d32; 
      opacity: 0.25; 
      transform: scaleY(-1); 
      background: linear-gradient(to bottom, rgba(46, 125, 50, 0.3), transparent); 
      -webkit-background-clip: text; 
      -webkit-text-fill-color: transparent; 
      pointer-events: none; 
    } 
    
    form { 
      background: #f9fff9; 
      padding: 25px; 
      border-radius: 15px; 
      border: 1px solid #c8e6c9; 
      box-shadow: 0 5px 15px rgba(76, 175, 80, 0.05); 
      margin-bottom: 30px; 
    } 
    
    form input, form select { 
      width: 100%; 
      padding: 12px 16px; 
      border: 2px solid #d4edda; 
      border-radius: 10px; 
      margin-bottom: 15px; 
      font-size: 1rem; 
      transition: all 0.3s ease; 
      background: white; 
    } 
    
    form input:focus, form select:focus { 
      border-color: #81c784; 
      box-shadow: 0 0 6px rgba(129, 199, 132, 0.3); 
    } 
    
    form button { 
      width: 100%; 
      padding: 12px 24px; 
      background: linear-gradient(135deg, #81c784, #66bb6a); 
      color: white; 
      font-weight: 600; 
      border: none; 
      border-radius: 8px; 
      cursor: pointer; 
      transition: all 0.3s ease; 
    } 
    
    button:hover { 
      transform: translateY(-2px); 
      box-shadow: 0 6px 18px rgba(102, 187, 106, 0.3); 
    } 
    
    table { 
      width: 100%; 
      border-collapse: collapse; 
      background: white; 
      border-radius: 12px; 
      overflow: hidden; 
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05); 
    } 
    
    th, td { 
      padding: 14px 18px; 
      border-bottom: 1px solid #e0f2f1; 
      text-align: left; 
    } 
    
    th { 
      background: #dcedc8; 
      color: #33691e; 
      font-weight: 600; 
    } 
    
    tr:hover { 
      background-color: #f1f8e9; 
    } 
    
    footer { 
      text-align: center; 
      margin-top: 30px; 
      color: #4e7d4a; 
      font-size: 0.9rem; 
      opacity: 0.8; 
    } 
    
    .back-arrow { 
      position: fixed; 
      top: 20px; 
      left: 20px; 
      width: 40px; 
      height: 40px; 
      background: white; 
      border-radius: 50%; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
      cursor: pointer; 
      color: #388e3c; 
      font-size: 18px; 
      z-index: 1000; 
      transition: all 0.3s ease; 
    } 
    
    .back-arrow:hover { 
      transform: translateY(-2px); 
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15); 
    } 
    
    .btn-edit, .btn-delete { 
      margin-right: 6px; 
      text-decoration: none; 
      padding: 6px 10px; 
      border-radius: 6px; 
      color: white; 
      font-size: 0.9rem; } 
      
    .btn-edit { 
      background-color: #4caf50; 
    } 
    
    .btn-delete { 
      background-color: #f44336; 
    } 
    
    .btn-edit:hover, .btn-delete:hover { 
      opacity: 0.85; 
    }

    .category-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 30px;
    }

    .category-box {
      flex: 1 1 calc(33.333% - 20px);
      background: #dcedc8;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: bold;
      color: #33691e;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .category-box:hover {
      background: #c5e1a5;
      transform: translateY(-2px);
    }

    .category-section {
      display: none;
      margin-top: 20px;
    }

    .category-section.active {
      display: block;
    }

    .btn-edit, .btn-delete {
      margin-right: 6px;
      text-decoration: none;
      padding: 6px 10px;
      border-radius: 6px;
      color: white;
      font-size: 0.9rem;
    }

    .btn-edit {
      background-color: #4caf50;
    }

    .btn-delete {
      background-color: #f44336;
    }

    .btn-edit:hover, .btn-delete:hover {
      opacity: 0.85;
    }

    .btn-red {
      background: #e53935;
      color: white;
    }

    .btn-red:hover {
      background: #c62828;
    }

    .modal-box {
  display: none;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: white;
  padding: 20px 25px;
  border-radius: 10px;
  box-shadow: 0 0 20px rgba(0,0,0,0.2);
  z-index: 999;
  width: 300px;
  text-align: center;
}

.btn-green {
  background: #43a047;
  color: white;
  border: none;
  padding: 10px 18px;
  border-radius: 6px;
  cursor: pointer;
}

.btn-green:hover {
  background: #388e3c;
}
  </style>
</head>
<body>
  <a href="dashboard.php" class="back-arrow"><i class="fas fa-arrow-left"></i></a>

  <div class="container">
    <div class="header-glow">
      <h1 data-text="🛒 Grocery List Management">🛒 Grocery List Management</h1>
    </div>

    <div class="category-grid">
      <div class="category-box" data-category="Fruits">Fruits</div>
      <div class="category-box" data-category="Vegetables">Vegetables</div>
      <div class="category-box" data-category="Dairy">Dairy</div>
      <div class="category-box" data-category="Beverages">Beverages</div>
      <div class="category-box" data-category="Snacks">Snacks</div>
      <div class="category-box" data-category="Meat">Meat</div>
      <div class="category-box" data-category="Others">Others</div>
    </div>

    <div id="category-sections"></div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%); background:white; padding:20px; border-radius:10px; box-shadow:0 0 20px rgba(0,0,0,0.2); z-index:999;">
    <h3>Edit Item</h3>
    <form id="editForm">
      <input type="hidden" name="item_id">
      <input type="text" name="item_name" required>
      <input type="number" name="quantity" required>
      <select name="category" required>
        <option>Fruits</option><option>Vegetables</option><option>Dairy</option>
        <option>Beverages</option><option>Snacks</option><option>Meat</option><option>Others</option>
      </select>
      <input type="text" name="unit">
      <input type="number" step="0.01" name="price">
      <input type="date" name="expiration_date" required>
      <button type="submit">Save Changes</button>
      <button type="button" class="btn-red" onclick="closeEditModal()">Cancel</button>
    </form>
  </div>

  <!-- Delete Modal -->
<div id="deleteModal" class="modal-box">
  <h3>Confirm Delete</h3>
  <p id="deleteMessage" style="margin-bottom:15px;"></p>
  <div style="display:flex; justify-content:space-between;">
    <button id="confirmDelete" class="btn-green">Yes, Delete</button>
    <button onclick="closeDeleteModal()" class="btn-red">Cancel</button>
  </div>
</div>


<script>
  let currentDeleteId = null;

  function openEditModal(id) {
    fetch(`items.php?action=get_item&id=${id}`)
      .then(res => res.json())
      .then(data => {
        const form = document.getElementById('editForm');
        form.item_id.value = data.item_id;
        form.item_name.value = data.item_name;
        form.quantity.value = data.quantity;
        form.category.value = data.category;
        form.unit.value = data.unit;
        form.price.value = data.price;
        form.expiration_date.value = data.expiration_date;
        document.getElementById('editModal').style.display = 'block';
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
    }).then(res => res.text()).then(response => {
      if (response === 'updated') location.reload();
      else alert('Update failed');
    });
  });

  function openDeleteModal(id, name) {
    currentDeleteId = id;
    document.getElementById('deleteMessage').textContent = `Do you really want to delete "${name}"?`;
    document.getElementById('deleteModal').style.display = 'block';
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
  }

  document.getElementById('confirmDelete').addEventListener('click', function() {
    window.location.href = `items.php?action=delete&id=${currentDeleteId}`;
  });
</script>

<script>
  const categoryBoxes = document.querySelectorAll('.category-box');
  const categorySections = document.getElementById('category-sections');

  categoryBoxes.forEach(box => {
    box.addEventListener('click', () => {
      const category = box.dataset.category;
      loadCategory(category);
    });
  });

  function loadCategory(category) {
    fetch(`items.php?action=fetch&category=${encodeURIComponent(category)}`)
      .then(res => res.json())
      .then(items => {
        categorySections.innerHTML = `
          <div class="category-section active">
            <h2>${category}</h2>
            <form method="POST" action="items.php?action=add">
              <input type="hidden" name="category" value="${category}">
              <input type="text" name="item_name" placeholder="Item Name" required>
              <input type="number" name="quantity" placeholder="Quantity" required>
              <input type="text" name="unit" placeholder="Unit (e.g. kg, pcs)">
              <input type="number" step="0.01" name="price" placeholder="Price (₱)">
              <input type="date" name="expiration_date" required>
              <button type="submit">Add ${category} Item</button>
            </form>

            <table>
              <thead>
                <tr>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price (₱)</th>
                  <th>Expiration Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                ${items.map(item => `
                  <tr>
                    <td>${item.item_name}</td>
                    <td>${item.quantity}</td>
                    <td>${item.unit}</td>
                    <td>₱${parseFloat(item.price).toFixed(2)}</td>
                    <td>${item.expiration_date}</td>
                    <td>
                      <a href="javascript:void(0)" onclick="openEditModal(${item.item_id})" class="btn-edit"><i class="fas fa-edit"></i></a>
                      <a href="javascript:void(0)" onclick="openDeleteModal(${item.item_id}, '${item.item_name.replace(/'/g, "\\'")}')" class="btn-delete"><i class="fas fa-trash-alt"></i></a>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      });
  }
</script>
</body>
</html>
