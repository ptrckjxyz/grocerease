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
    SELECT item_name, quantity
    FROM items
    WHERE user_id = ?
    ORDER BY quantity DESC
    LIMIT 5
");
$topStmt->bind_param("i", $user_id);
$topStmt->execute();
$topItems = $topStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$topStmt->close();

// Least stocked items
$leastStmt = $conn->prepare("
    SELECT item_name, quantity
    FROM items
    WHERE user_id = ?
    ORDER BY quantity ASC
    LIMIT 5
");
$leastStmt->bind_param("i", $user_id);
$leastStmt->execute();
$leastItems = $leastStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$leastStmt->close();

// Food waste stats (expired)
$wasteStmt = $conn->prepare("
    SELECT COUNT(*) AS expired_items, SUM(price * quantity) AS waste_cost
    FROM items
    WHERE user_id = ? AND expiration_date < CURDATE()
");
$wasteStmt->bind_param("i", $user_id);
$wasteStmt->execute();
$wasteResult = $wasteStmt->get_result()->fetch_assoc();
$expiredCount = $wasteResult['expired_items'] ?? 0;
$wasteCost = $wasteResult['waste_cost'] ?? 0;
$wasteStmt->close();

// Spending summary
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

// --- Recent purchases ---
$recentStmt = $conn->prepare("
    SELECT item_name, category, quantity, price, created_at
    FROM items
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$recentStmt->bind_param("i", $user_id);
$recentStmt->execute();
$recentPurchases = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>GrocerEase — Dashboard</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="brand">
        <div class="logo">
          <img src="image/logo.png" alt="GrocerEase Logo">
        </div>
        <div class="brand-text">
          <h1>GrocerEase</h1>
          <small>Smart Grocery & Meal Planner</small>
        </div>
      </div>

      <nav class="nav">
        <a href="dashboard.php" class="nav-item active">
          <span class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V21H3z"/><path d="M9 21V12h6v9"/></svg>
          </span><span>Dashboard</span>
        </a>
        <a href="items.php" class="nav-item">
          <span class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61H19a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          </span><span>Grocery List</span>
        </a>
        <a href="recipes.php" class="nav-item">
          <span class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 3h16v4H4zM4 9h16v12H4z"/></svg>
          </span><span>Meal Planning</span>
        </a>
        <a href="budget.php" class="nav-item">
          <span class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          </span><span>Budgeting</span>
        </a>
        <div class="spacer"></div>
        <a href="logout.php" class="nav-item logout">
          <span class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </span><span>Logout</span>
        </a>
      </nav>
    </aside>

    <!-- MAIN -->
    <main class="main">
      <header class="topbar">
        <div>
          <h2>Dashboard</h2>
          <p class="subtitle">Overview of your grocery management</p>
        </div>
      </header>

      <!-- Cards -->
      <section class="cards">
        <div class="card">
          <div class="card-head"><h3>Most Purchased Items</h3></div>
          <div class="card-body">
            <?php if (count($topItems) > 0): ?>
              <ul>
                <?php foreach ($topItems as $item): ?>
                  <li><?= htmlspecialchars($item['item_name']) ?> — <?= (int)$item['quantity'] ?></li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?><div class="placeholder">No items yet</div><?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>Least Purchased</h3></div>
          <div class="card-body">
            <?php if (count($leastItems) > 0): ?>
              <ul>
                <?php foreach ($leastItems as $item): ?>
                  <li><?= htmlspecialchars($item['item_name']) ?> — <?= (int)$item['quantity'] ?></li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?><div class="placeholder">No data yet</div><?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>Food Waste Stats</h3></div>
          <div class="card-body">
            <div class="chart-container"><canvas id="wasteChart"></canvas></div>
            <p class="muted">Expired items: <?= $expiredCount ?></p>
            <p><strong>Total Waste:</strong> ₱<?= number_format($wasteCost, 2) ?></p>
          </div>
        </div>
      </section>

      <!-- Panels -->
      <section class="content">
        <div class="panel">
          <div class="panel-head"><h4>Recent Purchases</h4></div>
          <div class="panel-body">
            <table class="table">
              <thead><tr><th>Item</th><th>Category</th><th>Qty</th><th>Price</th><th>Date</th></tr></thead>
              <tbody>
                <?php if (count($recentPurchases) > 0): ?>
                  <?php foreach ($recentPurchases as $purchase): ?>
                    <tr>
                      <td><?= htmlspecialchars($purchase['item_name']) ?></td>
                      <td><?= htmlspecialchars($purchase['category']) ?></td>
                      <td><?= (int)$purchase['quantity'] ?></td>
                      <td>₱<?= number_format($purchase['price'], 2) ?></td>
                      <td><?= htmlspecialchars(date('Y-m-d', strtotime($purchase['created_at']))) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr class="muted-row"><td colspan="5">No recent purchases</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel small-panel">
          <div class="panel-head"><h4>Top List Summary</h4></div>
          <ol>
  <?php if (count($topItems) > 0): ?>
    <?php foreach ($topItems as $item): ?>
      <li><?= htmlspecialchars($item['item_name']) ?> — <?= (int)$item['quantity'] ?></li>
    <?php endforeach; ?>
  <?php else: ?>
    <li class="muted-row">No data</li>
  <?php endif; ?>
</ol>

        </div>
      </section>
    </main>
  </div>

  <script>
    // Chart.js - simple waste vs total spent visualization
    const ctx = document.getElementById('wasteChart');
    if (ctx) {
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Wasted', 'Spent'],
          datasets: [{
            data: [<?= $wasteCost ?>, <?= $totalSpent ?>],
            backgroundColor: ['#ef4444', '#10b981'],
            borderWidth: 0,
          }]
        },
        options: {
          plugins: {
            legend: { display: true, position: 'bottom' }
          },
          cutout: '70%',
        }
      });
    }
  </script>
</body>
</html>
