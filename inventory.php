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
  <title>🧺 Inventory & Expiration Tracking</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: radial-gradient(circle at top left, #e8f5e9 0%, #c8e6c9 50%, #a5d6a7 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 40px 20px;
      color: #0f1720;
    }

    .container {
      max-width: 1200px;
      width: 100%;
      background: #ffffff;
      border-radius: 25px;
      box-shadow: 0 10px 35px rgba(76, 175, 80, 0.15);
      padding: 50px 40px;
      margin-top: 20px;
      border: 1px solid #c8e6c9;
    }

    .header-glow {
      background: linear-gradient(135deg, #c8f7d1, #b2eabf);
      border-radius: 18px;
      padding: 30px;
      text-align: center;
      margin-bottom: 30px;
      position: relative;
      overflow: hidden;
    }

    .header-glow h1 {
      color: #2e7d32;
      font-size: 2.2rem;
      font-weight: 800;
      margin: 0;
    }

    .subtitle {
      color: #4e7d4a;
      text-align: center;
      margin-bottom: 25px;
      font-size: 1.1rem;
    }

    .back-arrow {
      position: fixed;
      top: 20px;
      left: 20px;
      width: 45px;
      height: 45px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      cursor: pointer;
      color: #388e3c;
      font-size: 20px;
      z-index: 1000;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .back-arrow:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    /* Counter Cards */
    .counter-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }

    .counter-card {
      background: #f9fff9;
      border-radius: 14px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.05);
      border: 1px solid #c8e6c9;
      padding: 22px;
      text-align: center;
      transition: all 0.2s ease;
      font-weight: 600;
      font-size: 1.05rem;
    }

    .counter-card strong {
      display: block;
      font-size: 22px;
      color: #43a047;
      margin-top: 6px;
    }

    .counter-card.add {
      background: linear-gradient(135deg,#81c784,#66bb6a);
      color: #fff;
      cursor: pointer;
      font-weight: 700;
    }

    .counter-card.add:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(76,175,80,0.3);
    }

    /* Table */
    .table-container {
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.05);
      border: 1px solid #c8e6c9;
      padding: 20px;
      overflow: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      text-align: left;
      padding: 14px 12px;
      border-bottom: 1px solid #e6e6e6;
      font-size: 15px;
    }

    th {
      background: linear-gradient(135deg,#81c784,#66bb6a);
      color: #000;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    tr:hover { background-color: #f9fff9; }

    .btn {
      background: linear-gradient(135deg,#81c784,#66bb6a);
      border: none;
      border-radius: 10px;
      padding: 8px 12px;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      margin: 4px;
      transition: all 0.2s ease;
    }

    .btn:hover { opacity: 0.9; }

    .btn.ghost {
      background: transparent;
      border: 1px solid rgba(0,0,0,0.1);
      color: #333;
    }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      align-items: center;
      justify-content: center;
      z-index: 100;
    }

    .modal.show { display: flex; }

    .modal-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.45);
      backdrop-filter: blur(1px);
    }

    .modal-dialog {
      position: relative;
      background: #fff;
      width: 520px;
      max-width: 95%;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,0.25);
      z-index: 101;
    }

    .modal-header {
      background: linear-gradient(90deg,#81c784,#66bb6a);
      color: #000;
      padding: 18px 20px;
      font-weight: 800;
      font-size: 19px;
    }

    .modal-body {
      padding: 20px 22px;
      display:flex;
      flex-direction:column;
      gap:14px;
    }

    .modal-body label {
      font-weight:600;
      font-size:14px;
      display:flex;
      flex-direction:column;
      gap:8px;
    }

    .modal-body input, .modal-body select {
      padding: 11px 14px;
      border-radius: 8px;
      border: 1px solid #e6e6e6;
      font-size: 14px;
    }

    .modal-actions {
      display:flex;
      justify-content:flex-end;
      gap:10px;
      padding: 14px 18px;
      border-top: 1px solid #f1f1f1;
    }

    @media (max-width: 900px) {
      .container { padding: 40px 20px; }
    }
  </style>
</head>

<body>
  <a href="dashboard.php" class="back-arrow" title="Back to Dashboard"><i class="fas fa-arrow-left"></i></a>

  <div class="container">
    <div class="header-glow">
      <h1>🧺 Inventory & Expiration Tracking</h1>
    </div>

    <p class="subtitle">Monitor your food inventory and expiration dates easily.</p>

    <!-- Counter Bar -->
    <div class="counter-bar">
      <div class="counter-card">Total Items<strong>0</strong></div>
      <div class="counter-card">Fresh<strong>0</strong></div>
      <div class="counter-card">Expiring Soon<strong>0</strong></div>
      <div class="counter-card">Expired<strong>0</strong></div>
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

    // ✅ Updated to include live counter updates
    function renderTable(data) {
      tableBody.innerHTML = '';
      let total = data.length;
      let fresh = 0, expSoon = 0, expired = 0;

      data.forEach((item, index) => {
        const tr = document.createElement('tr');
        const now = new Date();
        const expDate = new Date(item.date);
        let status = 'Good';

        if (expDate < now) {
          status = 'Expired';
          expired++;
        } else if ((expDate - now) / (1000 * 60 * 60 * 24) <= 3) {
          status = 'Expiring Soon';
          expSoon++;
        } else {
          fresh++;
        }

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

      // Update counter values
      const counters = document.querySelectorAll('.counter-card strong');
      if (counters.length >= 4) {
        counters[0].textContent = total;
        counters[1].textContent = fresh;
        counters[2].textContent = expSoon;
        counters[3].textContent = expired;
      }
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

