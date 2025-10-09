<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Top purchased items ---
$insertStatement = $conn->prepare("
    SELECT item_name, SUM(quantity) AS total_bought
    FROM items
    WHERE user_id = ?
    GROUP BY item_name
    ORDER BY total_bought DESC
    LIMIT 5
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$topResult = $insertStatement->get_result();

$topItems = [];
$topQty = [];
while ($row = $topResult->fetch_assoc()) {
    $topItems[] = $row['item_name'];
    $topQty[] = $row['total_bought'];
}
$insertStatement->close();

// --- Least purchased items ---
$insertStatement = $conn->prepare("
    SELECT item_name, SUM(quantity) AS total_bought
    FROM items
    WHERE user_id = ?
    GROUP BY item_name
    ORDER BY total_bought ASC
    LIMIT 5
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$leastResult = $insertStatement->get_result();

$leastItems = [];
$leastQty = [];
while ($row = $leastResult->fetch_assoc()) {
    $leastItems[] = $row['item_name'];
    $leastQty[] = $row['total_bought'];
}
$insertStatement->close();

// --- Expired items count ---
$insertStatement = $conn->prepare("
    SELECT COUNT(*) AS expired_items
    FROM items
    WHERE user_id = ? AND expiration_date < CURDATE()
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$expiredResult = $insertStatement->get_result();
$expiredCount = $expiredResult->fetch_assoc()['expired_items'];
$insertStatement->close();

// --- Spending summary ---
$insertStatement = $conn->prepare("
    SELECT SUM(price * quantity) AS total_spent
    FROM items
    WHERE user_id = ?
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$spendingResult = $insertStatement->get_result();
$totalSpent = $spendingResult->fetch_assoc()['total_spent'];
$insertStatement->close();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>GrocerEase — Dashboard</title>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="brand">
        <div class="logo">G</div>
        <div class="brand-text">
          <h1>GrocerEase</h1>
          <small>Smart Grocery & Meal Planner</small>
        </div>
      </div>

      <nav class="nav">
        <a href="dashboard.html" class="nav-item active">
          <span class="icon">🏠</span><span>Dashboard</span>
        </a>
        <a href="#" class="nav-item">
          <span class="icon">🧾</span><span>Grocery List Management</span>
        </a>
        <a href="#" class="nav-item">
          <span class="icon">🍽️</span><span>Meal Planning</span>
        </a>
        <a href="#" class="nav-item">
          <span class="icon">📦</span><span>Inventory</span>
        </a>
        <a href="budget.html" class="nav-item">
          <span class="icon">💲</span><span>Budgeting & Cost Optimization</span>
        </a>

        <div class="spacer"></div>
        <a href="#" class="nav-item logout"><span class="icon">↩️</span><span>Logout</span></a>
      </nav>
    </aside>

    <!-- MAIN -->
    <main class="main">
      <header class="topbar">
        <div>
          <h2>Dashboard</h2>
          <p class="subtitle">Overview of your grocery management activities</p>
        </div>
        <!-- Demo User removed -->
      </header>

      <section class="cards">
        <div class="card card-stats">
          <div class="card-head">
            <h3>Most Purchased Items</h3>
            <button class="btn-small" id="sortMost">Sort</button>
          </div>
          <div class="card-body">
            <canvas id="mostChart" height="120"></canvas>
          </div>
        </div>

        <div class="card card-stats">
          <div class="card-head">
            <h3>Least Purchased Items</h3>
          </div>
          <div class="card-body">
            <div class="placeholder">No items yet</div>
          </div>
        </div>

        <div class="card card-waste">
          <div class="card-head">
            <h3>Food Waste Statistics</h3>
          </div>
          <div class="card-body small">
            <p class="muted">No items yet</p>
            <div class="waste-row">
              <span>Total Waste Cost</span>
              <strong class="price">₱0.00</strong>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="panel">
          <div class="panel-head">
            <h4>Recent Purchases</h4>
            <div class="panel-actions">
              <button id="addSample" class="btn">Add sample data</button>
              <button id="clearData" class="btn ghost">Clear</button>
            </div>
          </div>
          <div class="panel-body">
            <table class="table" id="purchaseTable">
              <thead>
                <tr><th>Item</th><th>Category</th><th>Qty</th><th>Price</th><th>Date</th></tr>
              </thead>
              <tbody>
                <tr class="muted-row"><td colspan="5">No purchases yet — add sample data to preview</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel small-panel">
          <h4>Top Purchased List</h4>
          <ol id="topList" class="top-list">
            <li class="muted-row">No items yet</li>
          </ol>
        </div>
      </section>
    </main>
  </div>

  <script src="script.js"></script>
</body>
</html>


