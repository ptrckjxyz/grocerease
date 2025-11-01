<?php
include 'connection.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function sanitize($value) {
    return htmlspecialchars(trim($value));
}

if ($action !== '') {
    header('Content-Type: application/json'); // ✅ Ensure proper response type

    switch ($action) {

        case 'set_budget':
            $limit_raw = $_POST['budget_limit'] ?? null;

            if (!isset($limit_raw) || !is_numeric($limit_raw)) {
                echo json_encode(["error" => "missing_parameters"]);
                exit;
            }

            $limit = floatval($limit_raw);

            // Calculate total spent to update DB fields properly
            $spent_stmt = $conn->prepare("SELECT SUM(price * quantity) AS total_spent FROM items WHERE user_id=?");
            $spent_stmt->bind_param("i", $user_id);
            $spent_stmt->execute();
            $spent_data = $spent_stmt->get_result()->fetch_assoc();
            $spent_stmt->close();

            $total_spent = floatval($spent_data['total_spent'] ?? 0);
            $remaining_budget = $limit - $total_spent;

            // Check if the user already has a budget
            $check = $conn->prepare("SELECT budget_id FROM budgets WHERE user_id=?");
            $check->bind_param("i", $user_id);
            $check->execute();
            $res = $check->get_result();

            if ($res->num_rows > 0) {
                // ✅ Update existing budget
                $stmt = $conn->prepare("UPDATE budgets SET budget_limit=?, total_spent=?, remaining_budget=? WHERE user_id=?");
                $stmt->bind_param("dddi", $limit, $total_spent, $remaining_budget, $user_id);
                $stmt->execute();
                echo json_encode(["status" => "budget_updated", "remaining" => $remaining_budget]);
                $stmt->close();
            } else {
                // ✅ Insert new budget for new user
                $stmt = $conn->prepare("INSERT INTO budgets (user_id, budget_limit, total_spent, remaining_budget) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iddd", $user_id, $limit, $total_spent, $remaining_budget);
                $stmt->execute();
                echo json_encode(["status" => "budget_added", "remaining" => $remaining_budget]);
                $stmt->close();
            }

            $check->close();
            exit;

        case 'get_items':
            $stmt = $conn->prepare("SELECT item_name AS name, price, quantity, category FROM items WHERE user_id=?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode($items);
            exit;

        case 'get_budget_report':
            $stmt = $conn->prepare("SELECT budget_limit, total_spent, remaining_budget FROM budgets WHERE user_id=?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $budget_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$budget_data) {
                echo json_encode(["budget_limit" => 0, "total_spent" => 0, "remaining_budget" => 0]);
                exit;
            }

            echo json_encode($budget_data);
            exit;

        default:
            echo json_encode(["error" => "invalid_action"]);
            exit;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Budget & Cost Optimization - GrocerEase</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
  <style>
    /* (Your existing CSS stays exactly the same — unchanged) */
        * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 50%, #a5d6a7 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 60px 20px;
      color: #1b3d27;
    }

    .container {
      max-width: 1200px;
      width: 100%;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 25px;
      backdrop-filter: blur(10px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      padding: 60px 50px;
      margin-top: 30px;
      transition: all 0.3s ease;
      border: 1px solid rgba(200, 230, 201, 0.6);
    }

    .container:hover {
      box-shadow: 0 18px 45px rgba(56, 142, 60, 0.25);
    }

    .header-glow {
      background: linear-gradient(120deg, #b9f6ca, #a5d6a7);
      border-radius: 18px;
      padding: 35px;
      text-align: center;
      margin-bottom: 40px;
      box-shadow: inset 0 0 10px rgba(56, 142, 60, 0.3);
    }

    .header-glow h1 {
      color: #1b5e20;
      font-size: 2.4rem;
      font-weight: 800;
      letter-spacing: 1px;
    }

    .subtitle {
      color: #33691e;
      text-align: center;
      margin-bottom: 40px;
      font-size: 1.15rem;
      font-weight: 500;
    }

    .back-arrow{
  position: fixed;
  top: 25px;
  left: 25px;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.back-arrow svg {
  width: 20px;
  height: 20px;
}

.back-arrow:hover {
  background: #e8f5e9;
}

    .counter-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 30px;
      margin-bottom: 20px;
    }

    .counter-card {
      background: rgba(255, 255, 255, 0.8);
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
      border: 1px solid #c8e6c9;
      padding: 35px;
      text-align: center;
      transition: all 0.3s ease;
    }

    .counter-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 35px rgba(76, 175, 80, 0.2);
    }

    .counter-card h3 {
      color: #2e7d32;
      font-size: 1.25rem;
      margin-bottom: 15px;
      font-weight: 600;
    }

    .counter-card strong {
      display: block;
      font-size: 30px;
      color: #1b5e20;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .budget-input {
      width: 100%;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid #c8e6c9;
      font-size: 15px;
      margin-bottom: 14px;
      transition: all 0.3s ease;
      outline: none;
    }

    .budget-input:focus {
      border-color: #81c784;
      box-shadow: 0 0 0 3px rgba(129, 199, 132, 0.3);
    }

    .btn {
      background: linear-gradient(135deg, #81c784, #66bb6a);
      border: none;
      border-radius: 10px;
      padding: 12px 18px;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      font-size: 15px;
      transition: all 0.3s ease;
    }

    .btn:hover {
      background: linear-gradient(135deg, #66bb6a, #43a047);
      transform: scale(1.05);
    }

    .muted {
      color: #757575;
      font-size: 14px;
      margin-top: 10px;
    }

    /* New: status layout for SVG + text */
    .status {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
    }

    .status .status-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      flex: 0 0 20px;
    }

    .status .status-icon svg {
      width: 20px;
      height: 20px;
      display: block;
    }

    ul.top-list {
      list-style: none;
      padding: 0;
      margin: 0;
      text-align: left;
    }

    .top-list li {
      padding: 10px 0;
      border-bottom: 1px solid #e0e0e0;
      color: #2e7d32;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .top-list li:hover {
      color: #1b5e20;
      transform: translateX(3px);
    }

    .muted-row {
      color: #9e9e9e;
      font-style: italic;
    }

    @media (max-width: 768px) {
      .container {
        padding: 40px 25px;
      }

      .header-glow h1 {
        font-size: 2rem;
      }

      .counter-card {
        padding: 25px;
      }
    }
  </style>
</head>
<body>
  <a href="dashboard.php" class="back-arrow" title="Back to Dashboard">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M15 18L9 12L15 6" stroke="#43a047" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</a>

  <div class="container">
    <div class="header-glow">
      <h1>💰 Budget & Cost Optimization</h1>
    </div>
    <p class="subtitle">Track your total spending and discover smarter choices.</p>

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

    let totalCost = 0;
    let budget = 0;
    let items = [];

    function fetchItems() {
      fetch('budget.php?action=get_items')
        .then(res => res.json())
        .then(data => {
          items = data;
          updateTotal();
          suggestAlternatives();
          updateBudgetStatus();
        });
    }

    function updateTotal() {
      totalCost = items.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity)), 0);
      totalCostEl.textContent = `₱${totalCost.toFixed(2)}`;
    }

    function updateBudgetStatus() {
      if (!budget) {
        budgetStatus.textContent = 'Please set your budget.';
        budgetStatus.style.color = '#888';
        return;
      }
      if (totalCost > budget) {
        const overBy = (totalCost - budget).toFixed(2);
        budgetStatus.style.color = 'red';
        budgetStatus.innerHTML = `
          <span class="status" aria-live="polite">
            <span class="status-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 21h22L12 2 1 21z" fill="currentColor" />
                <path d="M12 9v4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                <path d="M12 17h.01" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </span>
            <span>Over budget by ₱${overBy}</span>
          </span>
        `;
      } else {
        const remaining = (budget - totalCost).toFixed(2);
        budgetStatus.style.color = '#2e7d32';
        budgetStatus.innerHTML = `
          <span class="status" aria-live="polite">
            <span class="status-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="currentColor" />
                <path d="M7 12.5l2.5 2.5L17 8" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span>
            <span>Within budget. ₱${remaining} remaining.</span>
          </span>
        `;
      }
    }

    function suggestAlternatives() {
      altList.innerHTML = '';
      if (items.length === 0) {
        altList.innerHTML = '<li class="muted-row">No items found</li>';
        return;
      }

      const overBudget = totalCost - budget;
      if (overBudget > 0) {
        let remainingOver = overBudget;
        for (let item of items.sort((a,b)=> (b.price*b.quantity)-(a.price*a.quantity))) {
          if (remainingOver <= 0) break;
          const itemTotal = item.price * item.quantity;
          const li = document.createElement('li');
          li.textContent = `Consider reducing or removing '${item.name}' (₱${itemTotal})`;
          altList.appendChild(li);
          remainingOver -= itemTotal;
        }
      } else {
        const costlyItems = items.filter(i => i.price * i.quantity > 300);
        if (costlyItems.length === 0) {
          altList.innerHTML = '<li class="muted-row">No costly items detected</li>';
          return;
        }
        costlyItems.forEach(i => {
          const li = document.createElement('li');
          li.textContent = `${i.name} → Consider a cheaper alternative`;
          altList.appendChild(li);
        });
      }
    }

    document.getElementById('updateBudgetBtn').addEventListener('click', () => {
      budget = parseFloat(budgetInput.value) || 0;
      updateBudgetStatus();

      fetch('budget.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'set_budget',
          budget_limit: budget
        })
      })
      .then(res => res.json())
      .then(data => {
        console.log('Response:', data);
        suggestAlternatives();
      });
    });

    document.addEventListener('DOMContentLoaded', fetchItems);
  </script>
</body>
</html>
