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
