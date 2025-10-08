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












<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GrocerEase • Inventory & Expiration Tracking</title>

  <!-- Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --line: #e5e7eb;
      --muted: #6b7280;
      --brand: #1ae691;
      --danger: #ef4444;
      --warn: #f59e0b;
      --ok: #16a34a;
      --bg: #f9fafb;
      --text: #111827;
    }

    * {
      box-sizing: border-box;
      font-family: "Inter", sans-serif;
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      display: flex;
      min-height: 100vh;
    }

    .app-shell {
      display: flex;
      width: 100%;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background: #fff;
      border-right: 1px solid var(--line);
      display: flex;
      flex-direction: column;
      padding: 24px 20px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 800;
      font-size: 1.4em;
      margin-bottom: 24px;
    }

    .brand img {
      width: 38px;
      height: 38px;
    }

    .menu {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .menu-section {
      margin: 12px 0 6px;
      font-size: 12px;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 8px;
      color: var(--text);
      text-decoration: none;
      transition: 0.2s;
      background: transparent;
      border: none;
      font-weight: 600;
      cursor: pointer;
    }

    .menu-item:hover {
      background: #f3f4f6;
    }

    .menu-item.active {
      background: var(--brand);
      color: #fff;
    }

    .icon {
      font-size: 1.2em;
    }

    /* Main Content */
    .main {
      flex: 1;
      padding: 32px;
    }

    .topbar h1 {
      margin: 0;
      font-size: 1.7rem;
      font-weight: 800;
    }

    .lede {
      color: var(--muted);
      margin: 8px 0 20px;
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 16px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.2s;
    }

    .btn-primary {
      background: var(--brand);
      color: #fff;
    }

    .btn-primary:hover {
      background: #17ce7f;
    }

    .btn-danger {
      background: var(--danger);
      color: #fff;
    }

    .btn-ghost {
      background: transparent;
      border: 1px solid var(--line);
      color: var(--text);
    }

    .btn-ghost:hover {
      background: #f3f4f6;
    }

    /* Inventory Controls */
    .inv-controls {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .stat-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border: 1px solid var(--line);
      border-radius: 999px;
      background: #fff;
      font-weight: 700;
      color: #111;
    }

    .stat-pill .count {
      background: #111;
      color: #fff;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
    }

    .stat-pill.warn {
      border-color: #fde68a;
      background: #fffbeb;
    }

    .stat-pill.danger {
      border-color: #fecaca;
      background: #fef2f2;
    }

    /* Alert */
    .alert {
      border: 1px solid #fecaca;
      background: #fef2f2;
      color: #991b1b;
      padding: 10px 12px;
      border-radius: 12px;
      margin: 6px 0 12px;
      font-weight: 700;
    }

    /* Inventory Grid */
    .inv-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 18px;
    }

    .inv-card {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px;
      display: grid;
      gap: 8px;
      transition: transform 0.15s ease;
    }

    .inv-card:hover {
      transform: translateY(-2px);
    }

    .inv-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .inv-name {
      font-weight: 800;
      font-size: 1rem;
    }

    .badge {
      border: 1px solid var(--line);
      border-radius: 999px;
      padding: 4px 8px;
      font-size: 12px;
      font-weight: 700;
    }

    .badge.ok {
      color: #065f46;
      border-color: #a7f3d0;
      background: #ecfdf5;
    }

    .badge.warn {
      color: #7c2d12;
      border-color: #fed7aa;
      background: #fffbeb;
    }

    .badge.danger {
      color: #7f1d1d;
      border-color: #fecaca;
      background: #fef2f2;
    }

    .inv-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: var(--muted);
      font-size: 13px;
    }

    .progress {
      height: 8px;
      background: #f3f4f6;
      border-radius: 999px;
      overflow: hidden;
    }

    .progress > span {
      display: block;
      height: 100%;
      background: #111;
    }

    .card-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 6px;
    }

    .qty-ctrl {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .icon-btn {
      border: 1px solid var(--line);
      background: #fff;
      border-radius: 8px;
      padding: 6px 10px;
      cursor: pointer;
      font-weight: 700;
    }

    .icon-btn:hover {
      border-color: #cbd5e1;
      background: #f9fafb;
    }

    /* Form & Modal */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin: 6px 0 10px;
    }

    .form-grid label {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-weight: 600;
      font-size: 14px;
    }

    .form-grid input {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid var(--line);
    }

    .modal {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }

    .modal.show {
      display: flex;
    }

    .modal-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.3);
    }

    .modal-dialog {
      position: relative;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      z-index: 10;
      max-width: 480px;
      width: 90%;
    }
  </style>
</head>

<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <img src="./assets/logo.png" alt="GrocerEase logo" />
        <span>GrocerEase</span>
      </div>
      <nav class="menu" aria-label="Main">
        <a class="menu-item" href="./dashboard.html"><span class="icon">🏠</span>Dashboard</a>
        <div class="menu-section">Management</div>
        <a class="menu-item" href="./grocery.html"><span class="icon">🧾</span>Grocery List</a>
        <a class="menu-item" href="./meal.html"><span class="icon">🍳</span>Meal Planning</a>
        <a class="menu-item active" href="#"><span class="icon">📦</span>Inventory</a>
        <a class="menu-item" href="./budgeting.html"><span class="icon">💲</span>Budgeting</a>
        <div class="menu-section">Settings</div>
        <button id="logoutBtn" class="menu-item" type="button"><span class="icon">↪</span>Logout</button>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1>Inventory & Expiration Tracking</h1>
      </header>
      <p class="lede">Monitor your food inventory and track expiration dates</p>

      <div id="alertBar" class="alert" hidden></div>

      <section class="inv-controls">
        <div class="stat-pill">Total Items <span id="invTotal" class="count">0</span></div>
        <div class="stat-pill">Fresh Items <span id="invFresh" class="count">0</span></div>
        <div class="stat-pill warn">Expiring Soon <span id="invSoon" class="count">0</span></div>
        <div class="stat-pill danger">Expired <span id="invExpired" class="count">0</span></div>
        <button id="addInvBtn" class="btn btn-primary">Add Item</button>
      </section>

      <section id="invGrid" class="inv-grid"></section>
    </main>
  </div>

  <!-- Modals -->
  <div id="invModal" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close></div>
    <form id="invForm" class="modal-dialog">
      <h2 id="invModalTitle">Add Inventory Item</h2>
      <div class="form-grid">
        <label>Name<input id="iName" required placeholder="e.g., Yogurt"></label>
        <label>Category<input id="iCategory" placeholder="e.g., Dairy"></label>
        <label>Quantity<input id="iQty" type="number" min="0" step="1" value="1" required></label>
        <label>Unit<input id="iUnit" placeholder="e.g., pack"></label>
        <label>Expiration<input id="iExpiry" type="date" required></label>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>

  <div id="logoutModal" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close></div>
    <div class="modal-dialog">
      <h2>Are you sure you want to log out?</h2>
      <div class="modal-actions">
        <button id="logoutCancel" class="btn btn-ghost">Cancel</button>
        <button id="logoutConfirm" class="btn btn-danger">Logout</button>
      </div>
    </div>
  </div>

  <script>
    const INV_LS_KEY = 'ge_inventory_items_v1';
    const $ = (s, r=document) => r.querySelector(s);
    const $$ = (s, r=document) => [...r.querySelectorAll(s)];

    function readInv(){try{const raw=localStorage.getItem(INV_LS_KEY);const arr=raw?JSON.parse(raw):[];return Array.isArray(arr)?arr:[];}catch{return[];}}
    function writeInv(items){localStorage.setItem(INV_LS_KEY,JSON.stringify(items));}
    function daysUntil(d){try{const t=new Date();t.setHours(0,0,0,0);const x=new Date(d);x.setHours(0,0,0,0);return Math.round((x-t)/(1000*60*60*24));}catch{return NaN;}}
    function classifyItem(it){const d=daysUntil(it.expiry);if(isNaN(d))return{state:'unknown',badge:'ok'};if(d<0)return{state:'expired',badge:'danger'};if(d<=3)return{state:'soon',badge:'warn'};return{state:'fresh',badge:'ok'};}
    function calcStats(items){const s={total:items.length,fresh:0,soon:0,exp:0};items.forEach(i=>{const st=classifyItem(i).state;if(st==='fresh')s.fresh++;else if(st==='soon')s.soon++;else if(st==='expired')s.exp++;});return s;}
    function formatDate(d){try{return new Date(d).toISOString().slice(0,10);}catch{return'';}}

    function render(){
      const items=readInv();
      const g=$('#invGrid');g.innerHTML='';
      const {total,fresh,soon,exp}=calcStats(items);
      $('#invTotal').textContent=total;$('#invFresh').textContent=fresh;$('#invSoon').textContent=soon;$('#invExpired').textContent=exp;
      const alert=$('#alertBar');
      if(exp>0||soon>0){alert.hidden=false;alert.textContent=`${exp} expired • ${soon} nearing expiration`;}else{alert.hidden=true;alert.textContent='';}
      if(!items.length){const e=document.createElement('div');e.className='inv-card';e.style.textAlign='center';e.style.color='#6b7280';e.textContent='No inventory yet. Click Add Item to get started.';g.appendChild(e);return;}
      items.sort((a,b)=>daysUntil(a.expiry)-daysUntil(b.expiry)).forEach(it=>{
        const c=classifyItem(it);
        const card=document.createElement('article');card.className='inv-card';
        const qty=Number(it.qty)||0;
        card.innerHTML=`<div class="inv-head"><div class="inv-name">${it.name}</div><span class="badge ${c.badge}">${c.state==='expired'?'Expired':c.state==='soon'?'Expiring Soon':'Fresh'}</span></div>
        <div class="inv-meta"><span>${it.category||'Uncategorized'}</span><span>${it.expiry?'Exp: '+formatDate(it.expiry):'No expiration'}</span></div>
        <div class="progress"><span style="width:${Math.min(100,qty*8)}%"></span></div>
        <div class="card-actions"><div class="qty-ctrl"><button class="icon-btn" data-dec>−</button><b>${qty}</b><button class="icon-btn" data-inc>+</button><span style="color:#6b7280;font-size:12px">${it.unit||''}</span></div>
        <div><button class="icon-btn" data-edit>✏️</button><button class="icon-btn" data-del>🗑️</button></div></div>
        <div class="card-actions" style="justify-content:flex-end"><button class="icon-btn" data-use>Mark as Used</button></div>`;
        $('[data-inc]',card).onclick=()=>{const n=readInv().map(x=>x.id===it.id?{...x,qty:x.qty+1}:x);writeInv(n);render();};
        $('[data-dec]',card).onclick=()=>{const n=readInv().map(x=>x.id===it.id?{...x,qty:Math.max(0,x.qty-1)}:x);writeInv(n);render();};
        $('[data-use]',card).onclick=()=>{const n=readInv().map(x=>x.id===it.id?{...x,qty:0}:x);writeInv(n);render();};
        $('[data-edit]',card).onclick=()=>openEdit(it);
        $('[data-del]',card).onclick=()=>{const n=readInv().filter(x=>x.id!==it.id);writeInv(n);render();};
        g.appendChild(card);
      });
    }

    function showModal(el){el.classList.add('show');el.setAttribute('aria-hidden','false');}
    function hideModal(el){el.classList.remove('show');el.setAttribute('aria-hidden','true');}
    let editingId=null;
    function openEdit(i){editingId=i?.id||null;$('#invModalTitle').textContent=i?'Edit Item':'Add Item';$('#iName').value=i?.name||'';$('#iCategory').value=i?.category||'';$('#iQty').value=i?.qty??1;$('#iUnit').value=i?.unit||'';$('#iExpiry').value=i?.expiry?new Date(i.expiry).toISOString().slice(0,10):'';showModal($('#invModal'));}
    function init(){
      $('#addInvBtn').onclick=()=>openEdit(null);
      $('#invForm').onsubmit=e=>{e.preventDefault();const it={id:editingId||crypto.randomUUID(),name:$('#iName').value.trim(),category:$('#iCategory').value.trim(),qty:Math.max(0,parseInt($('#iQty').value||'0',10)),unit:$('#iUnit').value.trim(),expiry:$('#iExpiry').value};if(!it.name)return;const n=editingId?readInv().map(x=>x.id===editingId?{...x,...it}:x):[...readInv(),it];writeInv(n);hideModal($('#invModal'));render();};
      $$('#invModal [data-close]').forEach(b=>b.onclick=()=>hideModal($('#invModal')));
      document.onkeydown=e=>{if(e.key==='Escape'){hideModal($('#invModal'));hideModal($('#logoutModal'));}};
      $('#logoutBtn').onclick=()=>{$('#logoutModal').classList.add('show');$('#logoutModal').setAttribute('aria-hidden','false');};
      $('#logoutCancel').onclick=()=>hideModal($('#logoutModal'));
      $('#logoutConfirm').onclick=()=>window.location.href='login.html';
      render();
    }
    window.onload=init;
  </script>
</body>
</html>
