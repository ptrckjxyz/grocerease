<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'add_item':
        $name = $_POST['item_name'] ?? '';
        $category = $_POST['category'] ?? '';
        $qty = $_POST['quantity'] ?? 1;
        $unit = $_POST['unit'] ?? '';
        $purchase = $_POST['purchase_date'] ?? date('Y-m-d');
        $expire = $_POST['expiration_date'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO inventory (user_id, item_name, category, quantity, unit, purchase_date, expiration_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ississs", $user_id, $name, $category, $qty, $unit, $purchase, $expire);
        echo $stmt->execute() ? "item_added" : "error";
        break;

    case 'edit_item':
        $id = $_POST['item_id'] ?? 0;
        $name = $_POST['item_name'] ?? '';
        $category = $_POST['category'] ?? '';
        $qty = $_POST['quantity'] ?? 1;
        $unit = $_POST['unit'] ?? '';
        $purchase = $_POST['purchase_date'] ?? date('Y-m-d');
        $expire = $_POST['expiration_date'] ?? null;

        $stmt = $conn->prepare("
            UPDATE inventory 
            SET item_name=?, category=?, quantity=?, unit=?, purchase_date=?, expiration_date=? 
            WHERE item_id=? AND user_id=?
        ");
        $stmt->bind_param("ssisssii", $name, $category, $qty, $unit, $purchase, $expire, $id, $user_id);
        echo $stmt->execute() ? "item_updated" : "error";
        break;

    case 'delete_item':
        $id = $_GET['item_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM inventory WHERE item_id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user_id);
        echo $stmt->execute() ? "item_deleted" : "error";
        break;

    case 'fetch_items':
        // Update status before fetching
        $conn->query("
            UPDATE inventory 
            SET status = CASE
                WHEN expiration_date < CURDATE() THEN 'expired'
                WHEN expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'expiring_soon'
                ELSE 'fresh'
            END
            WHERE user_id = '$user_id'
        ");

        $res = $conn->query("SELECT * FROM inventory WHERE user_id='$user_id' ORDER BY expiration_date ASC");
        $items = [];
        while ($r = $res->fetch_assoc()) $items[] = $r;
        echo json_encode($items);
        break;

    case 'check_expiring':
        $res = $conn->query("
            SELECT item_name, expiration_date, status
            FROM inventory 
            WHERE user_id='$user_id' 
              AND (status='expiring_soon' OR status='expired')
            ORDER BY expiration_date ASC
        ");
        $items = [];
        while ($r = $res->fetch_assoc()) $items[] = $r;
        echo json_encode($items);
        break;

    default:
        echo "invalid_action";
}
?>
