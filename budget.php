<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'set_budget':
        $start = $_POST['period_start'];
        $end = $_POST['period_end'];
        $limit = $_POST['budget_limit'];

        // Check if budget already exists in this period
        $check = $conn->prepare("
            SELECT * FROM budgets 
            WHERE user_id=? AND period_start=? AND period_end=?
        ");
        $check->bind_param("iss", $user_id, $start, $end);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            // Update
            $stmt = $conn->prepare("
                UPDATE budgets 
                SET budget_limit=? 
                WHERE user_id=? AND period_start=? AND period_end=?
            ");
            $stmt->bind_param("diss", $limit, $user_id, $start, $end);
            echo $stmt->execute() ? "budget_updated" : "error";
        } else {
            // Insert new
            $stmt = $conn->prepare("
                INSERT INTO budgets (user_id, period_start, period_end, budget_limit)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("issd", $user_id, $start, $end, $limit);
            echo $stmt->execute() ? "budget_added" : "error";
        }
        break;

    case 'calculate_spending':
        // Sum all items purchased within the date range
        $start = $_GET['period_start'];
        $end = $_GET['period_end'];

        $query = $conn->prepare("
            SELECT SUM(price * quantity) AS total_spent
            FROM items
            WHERE user_id=? 
              AND created_at BETWEEN ? AND ?
        ");
        $query->bind_param("iss", $user_id, $start, $end);
        $query->execute();
        $result = $query->get_result()->fetch_assoc();
        $spent = $result['total_spent'] ?? 0;

        // Update total_spent in budgets table
        $stmt = $conn->prepare("
            UPDATE budgets
            SET total_spent=?
            WHERE user_id=? AND period_start=? AND period_end=?
        ");
        $stmt->bind_param("diss", $spent, $user_id, $start, $end);
        $stmt->execute();

        echo json_encode(["total_spent" => $spent]);
        break;

    case 'get_budget_report':
        $start = $_GET['period_start'];
        $end = $_GET['period_end'];

        $res = $conn->prepare("
            SELECT budget_limit, total_spent, (budget_limit - total_spent) AS remaining_budget
            FROM budgets
            WHERE user_id=? AND period_start=? AND period_end=?
        ");
        $res->bind_param("iss", $user_id, $start, $end);
        $res->execute();
        $data = $res->get_result()->fetch_assoc();

        if (!$data) {
            echo json_encode(["error" => "no_budget"]);
            exit;
        }

        $limit = $data['budget_limit'];
        $spent = $data['total_spent'];
        $remaining = $data['remaining_budget'];

        // Suggestion logic
        $suggestions = [];

        if ($spent > $limit) {
            $suggestions[] = "You exceeded your budget. Try reducing luxury or non-essential items.";
        } elseif ($spent > ($limit * 0.8)) {
            $suggestions[] = "You're close to your budget limit. Consider cheaper alternatives for next purchases.";
        } else {
            $suggestions[] = "Good job! You're managing your spending well.";
        }

        // Additional insights (optional)
        $category_query = $conn->prepare("
            SELECT category, SUM(price * quantity) AS total
            FROM items
            WHERE user_id=? AND created_at BETWEEN ? AND ?
            GROUP BY category
            ORDER BY total DESC
            LIMIT 3
        ");
        $category_query->bind_param("iss", $user_id, $start, $end);
        $category_query->execute();
        $categories = $category_query->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            "budget_limit" => $limit,
            "total_spent" => $spent,
            "remaining_budget" => $remaining,
            "suggestions" => $suggestions,
            "top_categories" => $categories
        ]);
        break;

    default:
        echo "invalid_action";
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>GrocerEase — Budget Optimization</title>
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
        <a href="dashboard.html" class="nav-item">
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
        <a href="budget.html" class="nav-item active">
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
          <h2>Budget & Cost Optimization</h2>
          <p class="subtitle">Track your total spending and find cheaper alternatives</p>
        </div>
        <!-- Demo User removed -->
      </header>

      <section class="cards">
        <div class="card">
          <div class="card-head">
            <h3>Total Grocery Cost</h3>
          </div>
          <div class="card-body">
            <strong id="totalCost" class="price">₱0.00</strong>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <h3>Set Budget</h3>
          </div>
          <div class="card-body">
            <input type="number" id="budgetInput" placeholder="Enter budget amount" class="budget-input"/>
            <button id="updateBudget" class="btn">Update Budget</button>
            <p id="budgetStatus" class="muted"></p>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <h3>Alternative Suggestions</h3>
          </div>
          <div class="card-body">
            <ul id="altList" class="top-list"><li class="muted-row">No costly items detected</li></ul>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="script.js"></script>
</body>
</html>

<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'set_budget':
        $start = $_POST['period_start'];
        $end = $_POST['period_end'];
        $limit = $_POST['budget_limit'];

        $check = $conn->prepare("SELECT * FROM budgets WHERE user_id=? AND period_start=? AND period_end=?");
        $check->bind_param("iss", $user_id, $start, $end);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE budgets SET budget_limit=? WHERE user_id=? AND period_start=? AND period_end=?");
            $stmt->bind_param("diss", $limit, $user_id, $start, $end);
            echo $stmt->execute() ? "budget_updated" : "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO budgets (user_id, period_start, period_end, budget_limit) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issd", $user_id, $start, $end, $limit);
            echo $stmt->execute() ? "budget_added" : "error";
        }
        break;

    case 'calculate_spending':
        $start = $_GET['period_start'];
        $end = $_GET['period_end'];

        $query = $conn->prepare("SELECT SUM(price * quantity) AS total_spent FROM items WHERE user_id=? AND created_at BETWEEN ? AND ?");
        $query->bind_param("iss", $user_id, $start, $end);
        $query->execute();
        $spent = $query->get_result()->fetch_assoc()['total_spent'] ?? 0;

        $stmt = $conn->prepare("UPDATE budgets SET total_spent=? WHERE user_id=? AND period_start=? AND period_end=?");
        $stmt->bind_param("diss", $spent, $user_id, $start, $end);
        $stmt->execute();

        echo json_encode(["total_spent" => $spent]);
        break;

    case 'get_budget_report':
        $start = $_GET['period_start'];
        $end = $_GET['period_end'];

        $res = $conn->prepare("SELECT budget_limit, total_spent, (budget_limit - total_spent) AS remaining_budget FROM budgets WHERE user_id=? AND period_start=? AND period_end=?");
        $res->bind_param("iss", $user_id, $start, $end);
        $res->execute();
        $data = $res->get_result()->fetch_assoc();

        if (!$data) {
            echo json_encode(["error" => "no_budget"]);
            exit;
        }

        $limit = $data['budget_limit'];
        $spent = $data['total_spent'];
        $remaining = $data['remaining_budget'];

        $suggestions = [];
        if ($spent > $limit) {
            $suggestions[] = "You exceeded your budget. Reduce non-essential items.";
        } elseif ($spent > ($limit * 0.8)) {
            $suggestions[] = "You're close to your budget. Consider cheaper alternatives.";
        } else {
            $suggestions[] = "Good job! You're managing your spending well.";
        }

        $category_query = $conn->prepare("SELECT category, SUM(price * quantity) AS total FROM items WHERE user_id=? AND created_at BETWEEN ? AND ? GROUP BY category ORDER BY total DESC LIMIT 3");
        $category_query->bind_param("iss", $user_id, $start, $end);
        $category_query->execute();
        $categories = $category_query->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            "budget_limit" => $limit,
            "total_spent" => $spent,
            "remaining_budget" => $remaining,
            "suggestions" => $suggestions,
            "top_categories" => $categories
        ]);
        break;

    default:
        echo "invalid_action";
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>GrocerEase — Budget Optimization</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body class="budget-fullscreen">
  <header class="budget-topbar">
    <a href="#" onclick="history.back()" class="back-btn">←</a>
    <div>
      <h2>Budget & Cost Optimization</h2>
      <p class="subtitle">Track your total spending and find cheaper alternatives</p>
    </div>
  </header>

  <main class="budget-main">
    <section class="budget-cards">
      <div class="card">
        <div class="card-head"><h3>Total Grocery Cost</h3></div>
        <div class="card-body"><strong id="totalCost" class="price">₱0.00</strong></div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Set Budget</h3></div>
        <div class="card-body">
          <input type="number" id="budgetInput" placeholder="Enter budget amount" class="budget-input"/>
          <button id="updateBudget" class="btn">Update Budget</button>
          <p id="budgetStatus" class="muted"></p>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Alternative Suggestions</h3></div>
        <div class="card-body">
          <ul id="altList" class="top-list"><li class="muted-row">No costly items detected</li></ul>
        </div>
      </div>
    </section>
  </main>

  <script src="script.js"></script>
</body>
</html>



