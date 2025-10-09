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
  <title>💰 Budget & Cost Optimization</title>
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
      padding: 60px 20px;
      color: #0f1720;
    }

    .container {
      max-width: 1400px;
      width: 100%;
      background: #ffffff;
      border-radius: 30px;
      box-shadow: 0 12px 45px rgba(76, 175, 80, 0.2);
      padding: 70px 60px;
      margin-top: 30px;
      border: 1px solid #b5e1b7;
      transform: scale(1.02);
      transition: transform 0.3s ease;
    }

    .container:hover { transform: scale(1.025); }

    .header-glow {
      background: linear-gradient(135deg, #c8f7d1, #b2eabf);
      border-radius: 20px;
      padding: 40px;
      text-align: center;
      margin-bottom: 40px;
      position: relative;
      overflow: hidden;
    }

    .header-glow h1 {
      color: #2e7d32;
      font-size: 2.6rem;
      font-weight: 800;
      margin: 0;
    }

    .subtitle {
      color: #4e7d4a;
      text-align: center;
      margin-bottom: 35px;
      font-size: 1.2rem;
    }

    .back-arrow {
      position: fixed;
      top: 25px;
      left: 25px;
      width: 55px;
      height: 55px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
      cursor: pointer;
      color: #388e3c;
      font-size: 22px;
      z-index: 1000;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .back-arrow:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }

    /* Cards */
    .counter-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
      margin-bottom: 40px;
    }

    .counter-card {
      background: #f9fff9;
      border-radius: 18px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.05);
      border: 1px solid #c8e6c9;
      padding: 30px;
      text-align: center;
      transition: all 0.2s ease;
    }

    .counter-card h3 {
      color: #2e7d32;
      font-size: 1.3rem;
      margin-bottom: 10px;
    }

    .counter-card strong {
      display: block;
      font-size: 28px;
      color: #2e7d32;
      font-weight: 700;
    }

    .budget-input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #e6e6e6;
      font-size: 15px;
      margin-bottom: 12px;
    }

    .btn {
      background: linear-gradient(135deg,#81c784,#66bb6a);
      border: none;
      border-radius: 12px;
      padding: 10px 16px;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      font-size: 15px;
      transition: all 0.2s ease;
    }

    .btn:hover { opacity: 0.9; }

    .muted {
      color: #888;
      font-size: 14px;
    }

    .table-container {
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.05);
      border: 1px solid #c8e6c9;
      padding: 28px;
    }

    ul.top-list {
      list-style: none;
      padding: 0;
      margin: 0;
      text-align: left;
    }

    .top-list li {
      padding: 10px 0;
      border-bottom: 1px solid #eee;
    }

    .muted-row {
      color: #aaa;
      font-style: italic;
    }
  </style>
</head>

<body>
  <a href="dashboard.php" class="back-arrow" title="Back to Dashboard"><i class="fas fa-arrow-left"></i></a>

  <div class="container">
    <div class="header-glow">
      <h1>💰 Budget & Cost Optimization</h1>
    </div>

    <p class="subtitle">Track your total spending and find cheaper alternatives.</p>

    <!-- Budget Cards -->
    <div class="counter-bar">
      <div class="counter-card">
        <h3>Total Grocery Cost</h3>
        <strong id="totalCost">₱0.00</strong>
      </div>

      <div class="counter-card">
        <h3>Set Budget</h3>
        <input type="number" placeholder="Enter budget amount" class="budget-input" id="budgetInput" />
        <button class="btn" id="updateBudgetBtn">Update Budget</button>
        <p class="muted" id="budgetStatus">Your budget status will appear here.</p>
      </div>

      <div class="counter-card">
        <h3>Alternative Suggestions</h3>
        <ul class="top-list" id="altList">
          <li class="muted-row">No costly items detected</li>
        </ul>
      </div>
    </div>
  </div>

  <script>
    const totalCostEl = document.getElementById('totalCost');
    const budgetInput = document.getElementById('budgetInput');
    const budgetStatus = document.getElementById('budgetStatus');
    const altList = document.getElementById('altList');

    // Example data simulation
    let totalCost = 0;
    let budget = 0;
    const sampleItems = [
      { name: 'Chicken Breast', price: 300, alt: 'Frozen Chicken' },
      { name: 'Imported Cheese', price: 450, alt: 'Local Cheese' },
      { name: 'Organic Apples', price: 250, alt: 'Regular Apples' }
    ];

    function updateTotal() {
      totalCost = sampleItems.reduce((sum, item) => sum + item.price, 0);
      totalCostEl.textContent = `₱${totalCost.toFixed(2)}`;
    }

    function updateBudgetStatus() {
      if (!budget) {
        budgetStatus.textContent = 'Please set your budget.';
        return;
      }
      if (totalCost > budget) {
        budgetStatus.textContent = `⚠️ Over budget by ₱${(totalCost - budget).toFixed(2)}!`;
        budgetStatus.style.color = 'red';
      } else {
        budgetStatus.textContent = `✅ Within budget. ₱${(budget - totalCost).toFixed(2)} remaining.`;
        budgetStatus.style.color = '#2e7d32';
      }
    }

    function suggestAlternatives() {
      const expensive = sampleItems.filter(i => i.price > 300);
      altList.innerHTML = '';
      if (expensive.length === 0) {
        altList.innerHTML = '<li class="muted-row">No costly items detected</li>';
        return;
      }
      expensive.forEach(i => {
        const li = document.createElement('li');
        li.textContent = `${i.name} → Try ${i.alt}`;
        altList.appendChild(li);
      });
    }

    document.getElementById('updateBudgetBtn').addEventListener('click', () => {
      budget = parseFloat(budgetInput.value) || 0;
      updateBudgetStatus();
    });

    document.addEventListener('DOMContentLoaded', () => {
      updateTotal();
      suggestAlternatives();
    });
  </script>
</body>
</html>

