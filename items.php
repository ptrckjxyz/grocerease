<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'add':
        $item_name = $_POST['item_name'] ?? '';
        $category = $_POST['category'] ?? '';
        $quantity = $_POST['quantity'] ?? 1;
        $unit = $_POST['unit'] ?? '';
        $price = $_POST['price'] ?? 0;
        $expiration_date = $_POST['expiration_date'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO items (user_id, item_name, category, quantity, unit, price, expiration_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issisds", $user_id, $item_name, $category, $quantity, $unit, $price, $expiration_date);
        echo $stmt->execute() ? "success" : "error";
        break;

    case 'edit':
        $item_id = $_POST['item_id'] ?? 0;
        $item_name = $_POST['item_name'] ?? '';
        $category = $_POST['category'] ?? '';
        $quantity = $_POST['quantity'] ?? 1;
        $unit = $_POST['unit'] ?? '';
        $price = $_POST['price'] ?? 0;
        $expiration_date = $_POST['expiration_date'] ?? null;

        $stmt = $conn->prepare("
            UPDATE items 
            SET item_name=?, category=?, quantity=?, unit=?, price=?, expiration_date=? 
            WHERE item_id=? AND user_id=?
        ");
        $stmt->bind_param("ssisdsii", $item_name, $category, $quantity, $unit, $price, $expiration_date, $item_id, $user_id);
        echo $stmt->execute() ? "updated" : "error";
        break;

    case 'delete':
        $item_id = $_GET['item_id'] ?? 0;

        $stmt = $conn->prepare("DELETE FROM items WHERE item_id=? AND user_id=?");
        $stmt->bind_param("ii", $item_id, $user_id);
        echo $stmt->execute() ? "deleted" : "error";
        break;

    case 'fetch':
        $category = $_GET['category'] ?? '';
        if ($category) {
            $stmt = $conn->prepare("SELECT * FROM items WHERE user_id=? AND category=? ORDER BY created_at DESC");
            $stmt->bind_param("is", $user_id, $category);
        } else {
            $stmt = $conn->prepare("SELECT * FROM items WHERE user_id=? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode($items);
        break;

    case 'check_expiring':
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT * FROM items 
            WHERE user_id=? AND expiration_date IS NOT NULL 
            AND expiration_date <= DATE_ADD(?, INTERVAL 3 DAY)
            ORDER BY expiration_date ASC
        ");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();

        $expiring = [];
        while ($row = $result->fetch_assoc()) {
            $expiring[] = $row;
        }
        echo json_encode($expiring);
        break;

    case 'get_cost_summary':
        $stmt = $conn->prepare("
            SELECT category, SUM(price * quantity) AS total_spent 
            FROM items WHERE user_id=? 
            GROUP BY category
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $summary = [];
        while ($row = $result->fetch_assoc()) {
            $summary[] = $row;
        }
        echo json_encode($summary);
        break;

    default:
        echo "invalid_action";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Grocery List Management - GrocerEase</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: radial-gradient(circle at top left, #e8f5e9 0%, #c8e6c9 50%, #a5d6a7 100%);
      min-height: 100vh;
      padding: 20px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .container {
      position: relative;
      z-index: 1;
      max-width: 1100px;
      width: 95%;
      margin: 40px auto;
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(76, 175, 80, 0.1);
      padding: 40px;
      overflow: hidden;
      border: 1px solid #c8e6c9;
    }

    .header-glow {
      background: linear-gradient(135deg, #c8f7d1, #b2eabf);
      border-radius: 15px;
      padding: 25px 20px;
      text-align: center;
      position: relative;
      margin-bottom: 25px;
      overflow: hidden;
    }

    .header-glow h1 {
      color: #2e7d32;
      font-size: 2.8rem;
      font-weight: 700;
      margin: 0;
      display: inline-block;
      position: relative;
      letter-spacing: 1px;
      z-index: 2;
    }

    .header-glow::after {
      content: "";
      position: absolute;
      top: 0;
      left: -80%;
      width: 50%;
      height: 100%;
      background: linear-gradient(
        120deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.9) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(25deg);
      animation: shimmer 3s infinite linear;
      mix-blend-mode: screen;
    }

    @keyframes shimmer {
      0% {
        transform: translateX(-150%) translateY(-50%) rotate(45deg);
      }
      100% {
        transform: translateX(150%) translateY(50%) rotate(45deg);
      }
    }

    .header-glow h1::after {
      content: attr(data-text);
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      color: #2e7d32;
      opacity: 0.25;
      transform: scaleY(-1);
      background: linear-gradient(to bottom, rgba(46, 125, 50, 0.3), transparent);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      pointer-events: none;
    }

    form {
      background: #f9fff9;
      padding: 25px;
      border-radius: 15px;
      border: 1px solid #c8e6c9;
      box-shadow: 0 5px 15px rgba(76, 175, 80, 0.05);
      margin-bottom: 30px;
    }

    form input, form select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #d4edda;
      border-radius: 10px;
      margin-bottom: 15px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background: white;
    }

    form input:focus, form select:focus {
      border-color: #81c784;
      box-shadow: 0 0 6px rgba(129, 199, 132, 0.3);
    }

    form button {
      width: 100%;
      padding: 12px 24px;
      background: linear-gradient(135deg, #81c784, #66bb6a);
      color: white;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    form button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(102, 187, 106, 0.3);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
    }

    th, td {
      padding: 14px 18px;
      border-bottom: 1px solid #e0f2f1;
      text-align: left;
    }

    th {
      background: #dcedc8;
      color: #33691e;
      font-weight: 600;
    }

    tr:hover {
      background-color: #f1f8e9;
    }

    footer {
      text-align: center;
      margin-top: 30px;
      color: #4e7d4a;
      font-size: 0.9rem;
      opacity: 0.8;
    }

    .back-arrow {
      position: fixed;
      top: 20px;
      left: 20px;
      width: 40px;
      height: 40px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      cursor: pointer;
      color: #388e3c;
      font-size: 18px;
      z-index: 1000;
      transition: all 0.3s ease;
    }

    .back-arrow:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
  </style>
</head>

<body>
  <a href="dashboard.php" class="back-arrow"><i class="fas fa-arrow-left"></i></a>

  <div class="container">
    <div class="header-glow">
      <h1 data-text="🛒 Grocery List Management">🛒 Grocery List Management</h1>
    </div>

    <form method="POST" action="items.php">
      <input type="text" name="item_name" placeholder="Item Name" required>
      <input type="number" name="quantity" placeholder="Quantity" required>

      <select name="category" required>
        <option value="">Select Category</option>
        <option value="Fruits">Fruits</option>
        <option value="Vegetables">Vegetables</option>
        <option value="Dairy">Dairy</option>
        <option value="Beverages">Beverages</option>
        <option value="Snacks">Snacks</option>
        <option value="Meat">Meat</option>
        <option value="Others">Others</option>
      </select>

      <input type="number" step="0.01" name="price" placeholder="Price (₱)">
      <input type="date" name="expiration_date" required>
      <button type="submit" name="add_item">Add Item</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>Item Name</th>
          <th>Quantity</th>
          <th>Category</th>
          <th>Price (₱)</th>
          <th>Expiration Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        
      </tbody>
    </table>

  </div>
</body>
</html>
