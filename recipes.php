<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

   
//recipe management
    // Add recipe
    case 'add_recipe':
        $name = $_POST['recipe_name'] ?? '';
        $desc = $_POST['description'] ?? '';
        $instr = $_POST['instructions'] ?? '';
        $cost = $_POST['estimated_cost'] ?? 0;

        $stmt = $conn->prepare("
            INSERT INTO recipes (user_id, recipe_name, description, instructions, estimated_cost)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssd", $user_id, $name, $desc, $instr, $cost);
        echo $stmt->execute() ? "recipe_added" : "error";
        break;

    // Edit recipe
    case 'edit_recipe':
        $id = $_POST['recipe_id'] ?? 0;
        $name = $_POST['recipe_name'] ?? '';
        $desc = $_POST['description'] ?? '';
        $instr = $_POST['instructions'] ?? '';
        $cost = $_POST['estimated_cost'] ?? 0;

        $stmt = $conn->prepare("
            UPDATE recipes
            SET recipe_name=?, description=?, instructions=?, estimated_cost=?
            WHERE recipe_id=? AND user_id=?
        ");
        $stmt->bind_param("sssdis", $name, $desc, $instr, $cost, $id, $user_id);
        echo $stmt->execute() ? "recipe_updated" : "error";
        break;

    // Delete recipe
    case 'delete_recipe':
        $id = $_GET['recipe_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM recipes WHERE recipe_id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user_id);
        echo $stmt->execute() ? "recipe_deleted" : "error";
        break;

    // Fetch all recipes
    case 'fetch_recipes':
        $res = $conn->query("SELECT * FROM recipes WHERE user_id='$user_id' ORDER BY created_at DESC");
        $recipes = [];
        while ($r = $res->fetch_assoc()) $recipes[] = $r;
        echo json_encode($recipes);
        break;


//meal plan
    // Add or Update meal plan (7-day planner)
    case 'save_plan':
        $day = $_POST['day_of_week'] ?? '';
        $meal = $_POST['meal_type'] ?? '';
        $recipe_id = $_POST['recipe_id'] ?? null;

        $check = $conn->prepare("SELECT plan_id FROM meal_plans WHERE user_id=? AND day_of_week=? AND meal_type=?");
        $check->bind_param("iss", $user_id, $day, $meal);
        $check->execute();
        $exists = $check->get_result();

        if ($exists->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE meal_plans SET recipe_id=? WHERE user_id=? AND day_of_week=? AND meal_type=?");
            $stmt->bind_param("iiss", $recipe_id, $user_id, $day, $meal);
            echo $stmt->execute() ? "plan_updated" : "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO meal_plans (user_id, day_of_week, meal_type, recipe_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $user_id, $day, $meal, $recipe_id);
            echo $stmt->execute() ? "plan_added" : "error";
        }
        break;

    // Delete meal plan
    case 'delete_plan':
        $id = $_GET['plan_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM meal_plans WHERE plan_id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user_id);
        echo $stmt->execute() ? "plan_deleted" : "error";
        break;

    // Fetch full weekly plan
    case 'fetch_plans':
        $query = $conn->prepare("
            SELECT m.plan_id, m.day_of_week, m.meal_type, r.recipe_name
            FROM meal_plans m
            LEFT JOIN recipes r ON m.recipe_id = r.recipe_id
            WHERE m.user_id=?
            ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
                     FIELD(meal_type, 'Breakfast','Lunch','Dinner','Breaktime')
        ");
        $query->bind_param("i", $user_id);
        $query->execute();
        $result = $query->get_result();
        $plans = [];
        while ($row = $result->fetch_assoc()) $plans[] = $row;
        echo json_encode($plans);
        break;

//recipe suggestion
    case 'suggest':
        $items = $conn->query("SELECT item_name FROM items WHERE user_id='$user_id'");
        $userItems = [];
        while ($r = $items->fetch_assoc()) $userItems[] = strtolower($r['item_name']);

        $recipes = $conn->query("SELECT * FROM recipes WHERE user_id='$user_id'");
        $suggested = [];

        while ($rec = $recipes->fetch_assoc()) {
            foreach ($userItems as $item) {
                if (stripos($rec['description'], $item) !== false || stripos($rec['instructions'], $item) !== false) {
                    $suggested[] = $rec;
                    break;
                }
            }
        }

        echo json_encode($suggested);
        break;

    default:
        echo "invalid_action";
}
?>
