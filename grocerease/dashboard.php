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

// Expiring Soon stats (0-7 days)
$expiringSoonStmt = $conn->prepare("
    SELECT item_name, expiration_date, DATEDIFF(expiration_date, CURDATE()) AS days_left
    FROM items
    WHERE user_id = ? 
    AND expiration_date >= CURDATE() 
    AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY expiration_date ASC
    LIMIT 5
");
$expiringSoonStmt->bind_param("i", $user_id);
$expiringSoonStmt->execute();
$expiringSoonItems = $expiringSoonStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$expiringSoonCount = count($expiringSoonItems);
$expiringSoonStmt->close();

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
          <div class="card-head"><h3>Expiring Soon</h3></div>
          <div class="card-body">
            <?php if (count($expiringSoonItems) > 0): ?>
              <ul style="margin: 0; padding: 0; list-style: none;">
                <?php foreach ($expiringSoonItems as $item): ?>
                  <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                    <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                    <br>
                    <small style="color: <?= $item['days_left'] <= 1 ? '#ef4444' : '#f59e0b' ?>;">
                      <?php 
                        $days = (int)$item['days_left'];
                        if ($days == 0) echo "Expires today";
                        else if ($days == 1) echo "Expires tomorrow";
                        else echo "Expires in {$days} days";
                      ?>
                    </small>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="placeholder" style="padding: 20px; text-align: center; color: #10b981;">
                ✓ No items expiring soon
              </div>
            <?php endif; ?>
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

  <!-- 🔔 Centered Expiration Toast -->
   <!-- Toast Container (top center) -->
<div id="toastContainer"></div>

  <script>

document.addEventListener("DOMContentLoaded", () => {
  // Check if toasts have already been shown this session
  if (!sessionStorage.getItem('expirationToastsShown')) {
    checkExpirations();
  }
});


function checkExpirations() {
  console.log("Checking expirations...");
  
  fetch("items.php?action=check_expiring")
    .then(res => res.json())
    .then(items => {
      console.log("Fetched items:", items);
      
      if (!items || !items.length) {
        console.log("No expiring or expired items found");
        // Mark as shown even if no items
        sessionStorage.setItem('expirationToastsShown', 'true');
        return;
      }

      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const container = document.getElementById("toastContainer");

      // Reset container
      container.innerHTML = "";

      // Arrays to track items
      const expiredItems = [];
      const expiringSoonItems = [];

      items.forEach(item => {
        const expDate = new Date(item.expiration_date + "T00:00:00");
        const diffMs = expDate - today;
        const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

        console.log(`Item: ${item.item_name}, Exp: ${item.expiration_date}, Diff: ${diffDays} days`);

        // === EXPIRED (past date) ===
        if (diffDays < 0) {
          expiredItems.push(item);
          console.log(`  -> EXPIRED`);
        }
        // === EXPIRING SOON (0-3 days) ===
        else if (diffDays >= 0 && diffDays <= 3) {
          expiringSoonItems.push({ item, diffDays });
          console.log(`  -> EXPIRING SOON`);
        }
      });

      // Show expired notifications
      if (expiredItems.length > 0) {
        console.log("Showing expired notifications:", expiredItems.length);
        expiredItems.forEach(item => {
          const msg = `<strong>${escapeHtml(item.item_name)}</strong> expired on ${escapeHtml(item.expiration_date)} and will be automatically deleted.`;
          container.appendChild(createToast("red", msg, expiredIcon()));
        });
      }

      // Show expiring soon notifications
      if (expiringSoonItems.length > 0) {
        console.log("Showing expiring soon notifications:", expiringSoonItems.length);
        expiringSoonItems.forEach(({ item, diffDays }) => {
          const dayWord = diffDays === 0 ? "today" : `in ${diffDays} day${diffDays > 1 ? "s" : ""}`;
          const msg = `<strong>${escapeHtml(item.item_name)}</strong> will expire ${dayWord} (${escapeHtml(item.expiration_date)}).`;
          container.appendChild(createToast("yellow", msg, warningIcon()));
        });
      }

      // Mark toasts as shown for this session
      sessionStorage.setItem('expirationToastsShown', 'true');

      // Auto-delete expired items after showing notifications
      if (expiredItems.length > 0) {
        console.log("Deleting expired items in 3 seconds...");
        setTimeout(() => {
          fetch("items.php?action=delete_expired")
            .then(res => res.json())
            .then(data => {
              console.log("Auto-deleted expired items:", data.deleted);
              // Reload page after deletion
              setTimeout(() => {
                console.log("Reloading page...");
                window.location.reload();
              }, 2000);
            })
            .catch(err => console.error("Auto-delete error:", err));
        }, 3000);
      }
    })
    .catch(err => console.error("Toast fetch error:", err));
}

// ===== Toast builder =====
function createToast(color, message, iconSVG) {
  const toast = document.createElement("div");
  toast.className = `expiry-toast ${color}`;
  toast.innerHTML = `
    <div class="toast-icon">${iconSVG}</div>
    <div>${message}</div>
  `;

  document.getElementById("toastContainer").appendChild(toast);

  // animate in
  toast.style.animation = "toastIn 0.35s ease forwards";

  setTimeout(() => {
    toast.style.animation = "toastOut 0.35s ease forwards";
    setTimeout(() => toast.remove(), 400);
  }, 6000);

  return toast;
}

// ===== SVGs =====
function warningIcon() {
  return `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
      <path d="M12 3L1 21h22L12 3z" stroke="#b45309" stroke-width="1.6"/>
      <path d="M12 9v5m0 3h.01" stroke="#b45309" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
  `;
}
function expiredIcon() {
  return `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
      <circle cx="12" cy="12" r="10" stroke="#b91c1c" stroke-width="1.6"/>
      <path d="M8 8l8 8m0-8l-8 8" stroke="#b91c1c" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
  `;
}
function escapeHtml(str) {
  return String(str || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}


  </script>
</body>
</html>