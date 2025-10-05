<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Top purchased items ---
$insertStatement = $conn->prepare("
    SELECT item_name, SUM(quantity) AS total_bought
    FROM items
    WHERE user_id = ?
    GROUP BY item_name
    ORDER BY total_bought DESC
    LIMIT 5
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$topResult = $insertStatement->get_result();

$topItems = [];
$topQty = [];
while ($row = $topResult->fetch_assoc()) {
    $topItems[] = $row['item_name'];
    $topQty[] = $row['total_bought'];
}
$insertStatement->close();

// --- Least purchased items ---
$insertStatement = $conn->prepare("
    SELECT item_name, SUM(quantity) AS total_bought
    FROM items
    WHERE user_id = ?
    GROUP BY item_name
    ORDER BY total_bought ASC
    LIMIT 5
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$leastResult = $insertStatement->get_result();

$leastItems = [];
$leastQty = [];
while ($row = $leastResult->fetch_assoc()) {
    $leastItems[] = $row['item_name'];
    $leastQty[] = $row['total_bought'];
}
$insertStatement->close();

// --- Expired items count ---
$insertStatement = $conn->prepare("
    SELECT COUNT(*) AS expired_items
    FROM items
    WHERE user_id = ? AND expiration_date < CURDATE()
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$expiredResult = $insertStatement->get_result();
$expiredCount = $expiredResult->fetch_assoc()['expired_items'];
$insertStatement->close();

// --- Spending summary ---
$insertStatement = $conn->prepare("
    SELECT SUM(price * quantity) AS total_spent
    FROM items
    WHERE user_id = ?
");
$insertStatement->bind_param("i", $user_id);
$insertStatement->execute();
$spendingResult = $insertStatement->get_result();
$totalSpent = $spendingResult->fetch_assoc()['total_spent'];
$insertStatement->close();
?>

//sa bbaa nito ung html ng dashboard

