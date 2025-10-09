<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'add_item':
        $name = $_POST['item_name'] ?? '';
        $category = $_POST['category'] ?? '';
        $qty = $_POST['quantity'] ?? 1;
        $unit = $_POST['unit'] ?? '';
        $purchase = $_POST['purchase_date'] ?? date('Y-m-d');
        $expire = $_POST['expiration_date'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO inventory (user_id, item_name, category, quantity, unit, purchase_date, expiration_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ississs", $user_id, $name, $category, $qty, $unit, $purchase, $expire);
        echo $stmt->execute() ? "item_added" : "error";
        break;

    case 'edit_item':
        $id = $_POST['item_id'] ?? 0;
        $name = $_POST['item_name'] ?? '';
        $category = $_POST['category'] ?? '';
        $qty = $_POST['quantity'] ?? 1;
        $unit = $_POST['unit'] ?? '';
        $purchase = $_POST['purchase_date'] ?? date('Y-m-d');
        $expire = $_POST['expiration_date'] ?? null;

        $stmt = $conn->prepare("
            UPDATE inventory 
            SET item_name=?, category=?, quantity=?, unit=?, purchase_date=?, expiration_date=? 
            WHERE item_id=? AND user_id=?
        ");
        $stmt->bind_param("ssisssii", $name, $category, $qty, $unit, $purchase, $expire, $id, $user_id);
        echo $stmt->execute() ? "item_updated" : "error";
        break;

    case 'delete_item':
        $id = $_GET['item_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM inventory WHERE item_id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user_id);
        echo $stmt->execute() ? "item_deleted" : "error";
        break;

    case 'fetch_items':
        // Update status before fetching
        $conn->query("
            UPDATE inventory 
            SET status = CASE
                WHEN expiration_date < CURDATE() THEN 'expired'
                WHEN expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'expiring_soon'
                ELSE 'fresh'
            END
            WHERE user_id = '$user_id'
        ");

        $res = $conn->query("SELECT * FROM inventory WHERE user_id='$user_id' ORDER BY expiration_date ASC");
        $items = [];
        while ($r = $res->fetch_assoc()) $items[] = $r;
        echo json_encode($items);
        break;

    case 'check_expiring':
        $res = $conn->query("
            SELECT item_name, expiration_date, status
            FROM inventory 
            WHERE user_id='$user_id' 
              AND (status='expiring_soon' OR status='expired')
            ORDER BY expiration_date ASC
        ");
        $items = [];
        while ($r = $res->fetch_assoc()) $items[] = $r;
        echo json_encode($items);
        break;

    default:
        echo "invalid_action";
}
?>












<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inventory & Expiration Tracking</title>

  <style>
    :root {
      --bg: #ffffff;
      --soft: #f3fff7;
      --brand: #bfeedd;
      --brand-600: #8fdab3;
      --ink: #0f1720;
      --muted: #6b7280;
      --card: #fbfdfb;
      --radius: 14px;
      --shadow: 0 8px 30px rgba(16,24,40,0.06);
      font-family: Inter, system-ui, "Segoe UI", Roboto;
      color: var(--ink);
    }

    *{box-sizing:border-box;margin:0;padding:0;}
    html,body{height:100%;background:linear-gradient(180deg,var(--bg),#f7fff8);}

    .app{display:grid;grid-template-columns:270px 1fr;gap:22px;padding:26px;height:100vh;align-items:start;}

    /* Sidebar */
    .sidebar{background:var(--card);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);height:calc(100vh - 52px);position:sticky;top:26px;display:flex;flex-direction:column;}
    .brand{display:flex;gap:12px;align-items:center;margin-bottom:20px;}
    .logo{width:52px;height:52px;border-radius:10px;background:linear-gradient(135deg,var(--brand-600),var(--brand));display:flex;align-items:center;justify-content:center;font-weight:700;color:#063;box-shadow:0 8px 20px rgba(127,215,173,0.16);}
    .brand-text h1{margin:0;font-size:18px;}
    .brand-text small{color:var(--muted);font-size:12px;}
    .nav{display:flex;flex-direction:column;gap:8px;margin-top:10px;}
    .nav-item{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;color:var(--ink);text-decoration:none;font-weight:600;transition:all .18s ease;}
    .nav-item:hover{background:var(--soft);}
    .nav-item.active{background:linear-gradient(90deg,var(--brand-600),var(--brand));color:white;box-shadow:0 6px 18px rgba(127,215,173,0.14);}
    .nav-item .icon{width:28px;text-align:center;}
    .spacer{flex:1;}
    .logout{border:1px solid rgba(0,0,0,0.06);padding:12px;border-radius:12px;}

    /* Main */
    .main{display:flex;flex-direction:column;gap:18px;height:100%;}
    .topbar{display:flex;justify-content:space-between;align-items:center;}
    .topbar h2{margin:0;}
    .subtitle{color:var(--muted);font-size:13px;margin-bottom:12px;}

    /* Counter Bar */
    .counter-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 14px;
      margin-bottom: 10px;
    }
    .counter-card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 16px;
      text-align: center;
      transition: all 0.2s ease;
      font-weight: 600;
      color: var(--ink);
    }
    .counter-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 22px rgba(16, 24, 40, 0.1);
    }
    .counter-card strong {
      display: block;
      font-size: 20px;
      color: var(--brand-600);
      margin-top: 4px;
    }
    .counter-card.add {
      background: linear-gradient(90deg,var(--brand-600),var(--brand));
      color: #000;
      cursor: pointer;
      font-weight: 700;
    }
    .counter-card.add:hover {
      opacity: 0.9;
    }

    /* Table */
    .table-container {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 16px;
      overflow:auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      border-radius: var(--radius);
      overflow: hidden;
    }
    th, td {
      text-align: left;
      padding: 14px 12px;
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    th {
      background: linear-gradient(90deg,var(--brand-600),var(--brand));
      color: #000;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    tr:hover { background-color: var(--soft); }
    .btn {
      background: linear-gradient(90deg,var(--brand-600),var(--brand));
      border: none;
      border-radius: 10px;
      padding: 8px 12px;
      color: #000;
      font-weight: 600;
      cursor: pointer;
      margin: 4px;
    }
    .btn.ghost {
      background: transparent;
      border: 1px solid rgba(0,0,0,0.06);
      color: var(--ink);
    }

    /* Modal */
    .modal{display:none;position:fixed;inset:0;align-items:center;justify-content:center;z-index:100;}
    .modal.show{display:flex;}
    .modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,0.5);}
    .modal-dialog{position:relative;background:#fff;width:520px;max-width:90%;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.2);z-index:1;}
    .modal-header{background:linear-gradient(90deg,var(--brand-600),var(--brand));color:#000;padding:16px 20px;font-weight:800;font-size:18px;}
    .modal-body{padding:20px;display:flex;flex-direction:column;gap:12px;}
    .modal-body label{font-weight:600;font-size:14px;display:flex;flex-direction:column;gap:6px;}
    .modal-body input,.modal-body select{padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,0.1);font-size:14px;}
    .modal-actions{display:flex;justify-content:flex-end;gap:10px;padding:16px 20px;border-top:1px solid rgba(0,0,0,0.05);}
  </style>
</head>

<body>
  <div class="app">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="brand">
        <div class="logo">GE</div>
        <div class="brand-text">
          <h1>GrocerEase</h1>
          <small>Smart Grocery Manager</small>
        </div>
      </div>

      <nav class="nav">
        <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span> Dashboard</a>
        <a href="items.php" class="nav-item"><span class="icon">🧾</span> Grocery List</a>
        <a href="meal.html" class="nav-item"><span class="icon">🍳</span> Meal Planning</a>
        <a href="#" class="nav-item active"><span class="icon">📦</span> Inventory</a>
        <a href="budget.php" class="nav-item"><span class="icon">💲</span> Budgeting</a>
        <div class="spacer"></div>
        <a href="#" class="nav-item logout"><span class="icon">↪</span> Logout</a>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <div class="topbar">
        <h2>Inventory & Expiration Tracking</h2>
      </div>

      <div class="subtitle">Monitor your food inventory and expiration dates easily.</div>

      <!-- Counter Bar -->
      <div class="counter-bar">
        <div class="counter-card">
          Total Items
          <strong>0</strong>
        </div>
        <div class="counter-card">
          Fresh
          <strong>0</strong>
        </div>
        <div class="counter-card">
          Expiring Soon
          <strong>0</strong>
        </div>
        <div class="counter-card">
          Expired
          <strong>0</strong>
        </div>
        <div class="counter-card add" id="addItemBtn">+ Add Item</div>
      </div>

      <div class="table-container">
        <table id="inventoryTable">
          <thead>
            <tr>
              <th>Item Name</th>
              <th>Quantity</th>
              <th>Category</th>
              <th>Expiration Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="inventoryBody"></tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Modal -->
  <div class="modal" id="inventoryModal">
    <div class="modal-backdrop" data-close></div>
    <div class="modal-dialog">
      <div class="modal-header">Add / Edit Item</div>
      <div class="modal-body">
        <label>Item Name<input type="text" id="itemName" /></label>
        <label>Quantity<input type="number" id="itemQuantity" /></label>
        <label>Category<input type="text" id="itemCategory" /></label>
        <label>Expiration Date<input type="date" id="itemDate" /></label>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" data-close>Cancel</button>
        <button class="btn" id="saveItemBtn">Save</button>
      </div>
    </div>
  </div>

  <script>
    const tableBody = document.getElementById('inventoryBody');
    const modal = document.getElementById('inventoryModal');
    const addItemBtn = document.getElementById('addItemBtn');
    const saveBtn = document.getElementById('saveItemBtn');
    const inputs = {
      name: document.getElementById('itemName'),
      qty: document.getElementById('itemQuantity'),
      cat: document.getElementById('itemCategory'),
      date: document.getElementById('itemDate')
    };
    let editingIndex = null;

    function openModal() { modal.classList.add('show'); }
    function closeModal() { modal.classList.remove('show'); clearInputs(); editingIndex = null; }
    function clearInputs() { Object.values(inputs).forEach(i => i.value = ''); }

    document.querySelectorAll('[data-close]').forEach(b => b.onclick = closeModal);
    addItemBtn.onclick = openModal;

    function loadInventory() {
      const data = JSON.parse(localStorage.getItem('inventoryData') || '[]');
      renderTable(data);
    }

    function saveInventory(data) {
      localStorage.setItem('inventoryData', JSON.stringify(data));
    }

    function renderTable(data) {
      tableBody.innerHTML = '';
      data.forEach((item, index) => {
        const tr = document.createElement('tr');
        const now = new Date();
        const expDate = new Date(item.date);
        let status = 'Good';
        if (expDate < now) status = 'Expired';
        else if ((expDate - now) / (1000 * 60 * 60 * 24) <= 3) status = 'Expiring Soon';

        tr.innerHTML = `
          <td>${item.name}</td>
          <td>${item.qty}</td>
          <td>${item.cat}</td>
          <td>${item.date}</td>
          <td>${status}</td>
          <td>
            <button class="btn" onclick="editItem(${index})">✏️</button>
            <button class="btn ghost" onclick="deleteItem(${index})">🗑️</button>
          </td>`;
        tableBody.appendChild(tr);
      });
    }

    saveBtn.onclick = () => {
      const newItem = {
        name: inputs.name.value.trim(),
        qty: inputs.qty.value,
        cat: inputs.cat.value.trim(),
        date: inputs.date.value
      };
      if (!newItem.name || !newItem.date) return;
      const data = JSON.parse(localStorage.getItem('inventoryData') || '[]');
      if (editingIndex !== null) data[editingIndex] = newItem;
      else data.push(newItem);
      saveInventory(data);
      renderTable(data);
      closeModal();
    };

    function editItem(index) {
      const data = JSON.parse(localStorage.getItem('inventoryData') || '[]');
      const item = data[index];
      inputs.name.value = item.name;
      inputs.qty.value = item.qty;
      inputs.cat.value = item.cat;
      inputs.date.value = item.date;
      editingIndex = index;
      openModal();
    }

    function deleteItem(index) {
      const data = JSON.parse(localStorage.getItem('inventoryData') || '[]');
      data.splice(index, 1);
      saveInventory(data);
      renderTable(data);
    }

    document.addEventListener('DOMContentLoaded', loadInventory);
  </script>
</body>
</html>
