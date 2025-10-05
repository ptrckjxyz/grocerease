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

    default:
        echo "invalid_action";
}
?>
