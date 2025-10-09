<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Top purchased items ---
$topStmt = $conn->prepare("
    SELECT item_name, SUM(quantity) AS total_bought
    FROM items
    WHERE user_id = ?
    GROUP BY item_name
    ORDER BY total_bought DESC
    LIMIT 5
");
$topStmt->bind_param("i", $user_id);
$topStmt->execute();
$topResult = $topStmt->get_result();

$topItems = [];
$topQty = [];
while ($row = $topResult->fetch_assoc()) {
    $topItems[] = $row['item_name'];
    $topQty[] = $row['total_bought'];
}
$topStmt->close();

// --- Least purchased items ---
$leastStmt = $conn->prepare("
    SELECT item_name, SUM(quantity) AS total_bought
    FROM items
    WHERE user_id = ?
    GROUP BY item_name
    ORDER BY total_bought ASC
    LIMIT 5
");
$leastStmt->bind_param("i", $user_id);
$leastStmt->execute();
$leastResult = $leastStmt->get_result();

$leastItems = [];
$leastQty = [];
while ($row = $leastResult->fetch_assoc()) {
    $leastItems[] = $row['item_name'];
    $leastQty[] = $row['total_bought'];
}
$leastStmt->close();

// --- Expired items count ---
$expiredStmt = $conn->prepare("
    SELECT COUNT(*) AS expired_items
    FROM items
    WHERE user_id = ? AND expiration_date < CURDATE()
");
$expiredStmt->bind_param("i", $user_id);
$expiredStmt->execute();
$expiredResult = $expiredStmt->get_result();
$expiredCount = $expiredResult->fetch_assoc()['expired_items'] ?? 0;
$expiredStmt->close();

// --- Spending summary ---
$spendingStmt = $conn->prepare("
    SELECT SUM(price * quantity) AS total_spent
    FROM items
    WHERE user_id = ?
");
$spendingStmt->bind_param("i", $user_id);
$spendingStmt->execute();
$spendingResult = $spendingStmt->get_result();
$totalSpent = $spendingResult->fetch_assoc()['total_spent'] ?? 0;
$spendingStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>GrocerEase — Dashboard</title>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="styles.css" />
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
        <a href="dashboard.php" class="nav-item active">
          <span class="icon">🏠</span><span>Dashboard</span>
        </a>
        <a href="items.php" class="nav-item">
          <span class="icon">🧾</span><span>Grocery List Management</span>
        </a>
        <a href="recipes.php" class="nav-item">
          <span class="icon">🍽️</span><span>Meal Planning</span>
        </a>
        <a href="inventory.php" class="nav-item">
          <span class="icon">📦</span><span>Inventory</span>
        </a>
        <a href="budget.php" class="nav-item">
          <span class="icon">💲</span><span>Budgeting & Cost Optimization</span>
        </a>

        <div class="spacer"></div>
        <a href="logout.php" class="nav-item logout"><span class="icon">↩️</span><span>Logout</span></a>
      </nav>
    </aside>

    <!-- MAIN -->
    <main class="main">
      <header class="topbar">
        <div>
          <h2>Dashboard</h2>
          <p class="subtitle">Overview of your grocery management activities</p>
        </div>
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
            <?php if (count($leastItems) > 0): ?>
              <ul>
                <?php foreach ($leastItems as $index => $item): ?>
                  <li><?= htmlspecialchars($item) ?> — <?= $leastQty[$index] ?></li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="placeholder">No items yet</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card card-waste">
          <div class="card-head">
            <h3>Food Waste Statistics</h3>
          </div>
          <div class="card-body small">
            <p class="muted">Expired items: <?= $expiredCount ?></p>
            <div class="waste-row">
              <span>Total Waste Cost</span>
              <strong class="price">₱<?= number_format($totalSpent, 2) ?></strong>
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
            <?php if (count($topItems) > 0): ?>
              <?php foreach ($topItems as $index => $item): ?>
                <li><?= htmlspecialchars($item) ?> — <?= $topQty[$index] ?></li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="muted-row">No items yet</li>
            <?php endif; ?>
          </ol>
        </div>
      </section>
    </main>
  </div>

  <script src="script.js"></script>
</body>
</html>
