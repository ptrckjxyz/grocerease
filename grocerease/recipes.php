<?php
include 'connection.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("unauthorized");
}

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action !== '') {
    switch ($action) {

        // Add recipe manually
        case 'add_recipe':
            $name = trim($_POST['recipe_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $instr = trim($_POST['instructions'] ?? '');
            $mealType = $_POST['meal_type'] ?? 'Lunch';
            $imageData = null;

            if (!empty($_FILES['recipe_image']['tmp_name'])) {
                $imageData = file_get_contents($_FILES['recipe_image']['tmp_name']);
            }

            if ($name === '' || strlen($name) > 255) {
                echo "invalid_recipe_name";
                exit;
            }

            // ✅ Fixed bind_param and image sending
            $stmt = $conn->prepare("
                INSERT INTO recipes (user_id, recipe_name, description, instructions, meal_type, recipe_image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssb", $user_id, $name, $desc, $instr, $mealType, $imageData);
            $stmt->send_long_data(5, $imageData);
            echo $stmt->execute() ? "recipe_added" : "error";
            $stmt->close();
            exit;


        // Suggest recipes using TheMealDB API (optimized with cURL multi)
        case 'suggest':
            header('Content-Type: application/json');

            // 1️⃣ Get user's stored ingredients
            $items_stmt = $conn->prepare("SELECT item_name FROM items WHERE user_id=?");
            $items_stmt->bind_param("i", $user_id);
            $items_stmt->execute();
            $items_res = $items_stmt->get_result();

            $userItems = [];
            while ($r = $items_res->fetch_assoc()) {
                if (!empty($r['item_name'])) {
                    $userItems[] = strtolower(trim($r['item_name']));
                }
            }
            $items_stmt->close();

            if (empty($userItems)) {
                echo json_encode([]);
                exit;
            }

            $suggested = [];
            $context = stream_context_create(['http' => ['timeout' => 5]]);

            // 2️⃣ Step 1: Get meals by ingredients (TheMealDB)
            $allMeals = [];
            foreach ($userItems as $ingredient) {
                $apiUrl = "https://www.themealdb.com/api/json/v1/1/filter.php?i=" . urlencode($ingredient);
                $response = @file_get_contents($apiUrl, false, $context);
                if ($response === false) continue;

                $data = json_decode($response, true);
                if (!isset($data['meals']) || !$data['meals']) continue;

                foreach (array_slice($data['meals'], 0, 3) as $meal) {
                    $mealID = $meal['idMeal'];
                    if (!isset($allMeals[$mealID])) {
                        $allMeals[$mealID] = [
                            'name' => $meal['strMeal'],
                            'thumb' => $meal['strMealThumb'] ?? ''
                        ];
                    }
                }
            }

            if (!empty($allMeals)) {
                // 3️⃣ Step 2: Fetch details from TheMealDB with curl_multi
                $mh = curl_multi_init();
                $curlHandles = [];
                foreach ($allMeals as $mealID => $meal) {
                    $ch = curl_init("https://www.themealdb.com/api/json/v1/1/lookup.php?i=$mealID");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_multi_add_handle($mh, $ch);
                    $curlHandles[$mealID] = $ch;
                }

                $running = null;
                do {
                    curl_multi_exec($mh, $running);
                    curl_multi_select($mh);
                } while ($running > 0);

                foreach ($curlHandles as $mealID => $ch) {
                    $detailsResponse = curl_multi_getcontent($ch);
                    curl_multi_remove_handle($mh, $ch);

                    $mealInfo = [];
                    if ($detailsResponse !== false) {
                        $detailsData = json_decode($detailsResponse, true);
                        $mealInfo = $detailsData['meals'][0] ?? [];
                    }

                    $name = $allMeals[$mealID]['name'];
                    $thumb = $allMeals[$mealID]['thumb'];
                    $instructions = $mealInfo['strInstructions'] ?? '';
                    $category = $mealInfo['strCategory'] ?? 'Lunch';

                    $mealType = 'Lunch';
                    if (preg_match('/egg|pancake|toast|coffee|breakfast/i', $name)) {
                        $mealType = 'Breakfast';
                    } elseif (preg_match('/dinner|steak|pasta|rice|chicken|fish/i', $name)) {
                        $mealType = 'Dinner';
                    }

                    $suggested[$mealID] = [
                        'recipe_name' => $name,
                        'description' => $category,
                        'instructions' => $instructions,
                        'meal_thumb' => $thumb,
                        'meal_type' => $mealType
                    ];
                }

                curl_multi_close($mh);
            }

            // 4️⃣ Include user's saved recipes if they match user's items (simple substring check)
            $userRecipesStmt = $conn->prepare("SELECT recipe_id, recipe_name, description, instructions, meal_type, recipe_image FROM recipes WHERE user_id=?");
            $userRecipesStmt->bind_param("i", $user_id);
            $userRecipesStmt->execute();
            $userRecipesRes = $userRecipesStmt->get_result();

            while ($row = $userRecipesRes->fetch_assoc()) {
                $textToSearch = strtolower(($row['recipe_name'] ?? '') . ' ' . ($row['description'] ?? '') . ' ' . ($row['instructions'] ?? ''));
                $matched = false;
                foreach ($userItems as $it) {
                    if ($it === '') continue;
                    if (preg_match('/\b' . preg_quote($it, '/') . 's?\b/i', $textToSearch)) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    $id = 'user_' . $row['recipe_id'];
                    if (!isset($suggested[$id])) {

                        // 🧩 Normalize meal type
                        $mealType = ucfirst(strtolower($row['meal_type'] ?? 'Lunch'));
                        if (!in_array($mealType, ['Breakfast', 'Lunch', 'Dinner'])) {
                            $mealType = 'Lunch';
                        }

                        // ✅ Convert binary image to Base64 for display
                        $meal_thumb = '';
                        if (!empty($row['recipe_image'])) {
                            $meal_thumb = 'data:image/jpeg;base64,' . base64_encode($row['recipe_image']);
                        }

                        $suggested[$id] = [
                            'recipe_name' => $row['recipe_name'],
                            'description' => $row['description'] ?? '',
                            'instructions' => $row['instructions'] ?? '',
                            'meal_thumb' => $meal_thumb,
                            'meal_type' => $mealType,
                        ];
                    }
                }
            }
            $userRecipesStmt->close();

            echo json_encode(array_values($suggested));
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'invalid_action']);
            break;
    }
    exit;
}

?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Meal Planning & Recipe Suggestions - GrocerEase</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: radial-gradient(circle at top left, #e8f5e9 0%, #c8e6c9 50%, #a5d6a7 100%); min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; padding: 40px 20px; color: #0f1720; }
    .container { max-width: 1300px; width: 100%; margin: 20px auto; background: #ffffff; border-radius: 25px; box-shadow: 0 10px 35px rgba(76, 175, 80, 0.15); padding: 60px 50px; border: 1px solid #c8e6c9; }
    .header-glow { background: linear-gradient(135deg, #c8f7d1, #b2eabf); border-radius: 18px; padding: 30px; text-align: center; margin-bottom: 30px; position: relative; overflow: hidden; }
    .header-glow h1 { color: #2e7d32; font-size: 2.2rem; font-weight: 800; margin: 0; }
    .header-glow::after { content: ""; position: absolute; top: 0; left: -80%; width: 50%; height: 100%; background: linear-gradient(120deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.9) 50%, rgba(255,255,255,0) 100%); transform: rotate(25deg); animation: shimmer 3s infinite linear; }
    @keyframes shimmer { 0% { transform: translateX(-150%) rotate(25deg); } 100% { transform: translateX(150%) rotate(25deg); } }
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


    .subtitle { color: #4e7d4a; text-align:center; margin-bottom: 22px; font-size: 1.05rem; }
    .tabs { display:flex; justify-content:center; gap:12px; margin-bottom: 25px; }
    .tab { border: 2px solid #a5d6a7; background: white; border-radius: 35px; padding: 10px 24px; font-weight: 600; cursor: pointer; transition: all .2s ease; font-size: 1rem; }
    .tab.active { background: linear-gradient(135deg,#81c784,#66bb6a); color: #fff; border: none; box-shadow: 0 0 10px rgba(102,187,106,0.25); }
    .cards.three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
    .card { background: #f9fff9; border-radius: 14px; padding: 20px; box-shadow: 0 6px 15px rgba(0,0,0,0.05); border: 1px solid #c8e6c9; }
    .sug-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
    .sug-item { border: 1px solid #d4edda; border-radius: 10px; padding: 12px 14px; background: white; cursor: pointer; transition: transform .12s ease, border-color .12s ease; }
    .sug-item:hover { border-color: #81c784; transform: translateY(-2px); }
    .sug-item .title { font-weight:700; }
    .sug-item .meta { color: #6b7280; font-size: 13px; }
    .sug-item img { max-height: 200px; object-fit: cover; border-radius: 10px; margin-bottom: 8px; }
    .planner-grid { display: grid; grid-template-columns: repeat(3, minmax(250px, 1fr)); gap: 20px; margin-top: 12px; }
    .day-card { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 5px 18px rgba(76,175,80,0.07); border: 1px solid #c8e6c9; display:flex; flex-direction:column; }
    .day-head { display:flex; justify-content:space-between; align-items:center; font-weight:800; margin-bottom:10px; font-size:1.1rem; }
    .add-day-btn { border: none; background: linear-gradient(135deg,#81c784,#66bb6a); color: #fff; width: 36px; height: 36px; border-radius: 50%; font-size: 18px; cursor:pointer; }
    .meal { border: 1px dashed rgba(0,0,0,0.08); border-radius:10px; padding: 12px; margin-bottom:10px; }
    .meal h3 { margin:0 0 8px 0; font-size: 15px; }
    .meal-list { list-style:none; padding:0; margin:0; display:grid; gap:8px; }
    .meal-item { display:flex; justify-content:space-between; align-items:center; padding:9px 12px; border-radius:8px; border:1px solid rgba(0,0,0,0.04); background:#fff; }
    .meal-item .name { font-weight:600; cursor:pointer; font-size: 14px; }
    .icon-btn { border:1px solid rgba(0,0,0,0.06); padding:6px; border-radius:8px; background:#fff; cursor:pointer; }
    .modal { display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 2000; }
    .modal.show { display: flex; }
    .modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(1px); }
    .modal-dialog { position: relative; background: #fff; width: 550px; max-width: 95%; border-radius: 14px; overflow: hidden; box-shadow: 0 12px 40px rgba(0,0,0,0.25); z-index: 2001; }
    .modal-header { background: linear-gradient(90deg,#81c784,#66bb6a); color: #000; padding: 18px 20px; font-weight: 800; font-size: 19px; }
    .modal-body { padding: 20px 22px; display:flex; flex-direction:column; gap:14px; }
    .modal-body label { font-weight:600; font-size:14px; display:flex; flex-direction:column; gap:8px; }
    .modal-body input, .modal-body select, .modal-body textarea { padding: 11px 14px; border-radius: 8px; border: 1px solid #e6e6e6; font-size: 14px; }
    .modal-actions { display:flex; justify-content:flex-end; gap:10px; padding: 14px 18px; border-top: 1px solid #f1f1f1; }
    .btn { background: linear-gradient(90deg,#81c784,#66bb6a); color:#fff; border:none; padding:10px 14px; border-radius:8px; cursor:pointer; font-weight:600; }
    .btn.ghost { background: transparent; border: 1px solid rgba(0,0,0,0.08); color: #333; }
    .add-tab-content { background: #f9fff9; border-radius: 14px; padding: 20px; box-shadow: 0 6px 15px rgba(0,0,0,0.05); border: 1px solid #c8e6c9; max-width: 900px; margin: 0 auto; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-row .full { grid-column: 1 / -1; }
    .muted { color:#6b7280; font-size:14px; }
    @media (max-width: 900px) { .cards.three { grid-template-columns: 1fr; } .planner-grid { grid-template-columns: 1fr; } .container { padding: 40px 20px; } .form-row { grid-template-columns: 1fr; } }
.modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(5px);
  z-index: 1000;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
}
.modal.active {
  opacity: 1;
  pointer-events: auto;
}
.modal-content {
  background: #ffffff;
  border-radius: 20px;
  width: 90%;
  max-width: 600px;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
  transform: translateY(-20px);
  transition: all 0.3s ease;
  animation: popIn 0.35s ease forwards;
}
@keyframes popIn {
  from {
    opacity: 0;
    transform: translateY(-30px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
.modal-content img {
  width: 100%;
  height: 250px;
  object-fit: cover;
  border-top-left-radius: 20px;
  border-top-right-radius: 20px;
}
.modal-body {
  padding: 25px 30px;
  color: #333;
  line-height: 1.6;
  font-family: 'Poppins', sans-serif;
}
.modal-body h2 {
  color: #006633;
  margin-top: 0;
  margin-bottom: 10px;
}
.modal-body p {
  margin: 6px 0;
}
.modal-body strong {
  color: #004d26;
}
.close-btn {
  position: absolute;
  top: 15px;
  right: 15px;
  background: #006633;
  color: white;
  border: none;
  border-radius: 50%;
  width: 35px;
  height: 35px;
  cursor: pointer;
  font-size: 18px;
  transition: all 0.3s ease;
}
.close-btn:hover {
  background: #004d26;
  transform: scale(1.1);
}
.modal-content::-webkit-scrollbar {
  width: 8px;
}
.modal-content::-webkit-scrollbar-thumb {
  background: #c6c6c6;
  border-radius: 10px;
}
.modal-content::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
.toast {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) scale(0.95);
  background: #2e7d32;
  color: #fff;
  padding: 14px 22px;
  border-radius: 10px;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
  z-index: 3000;
  display: flex;
  align-items: center;
  gap: 10px;
  opacity: 0;
  font-weight: 500;
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.toast.show {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1);
}

/* Optional animation for the checkmark */
.toast svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: #fff;
  stroke-width: 3;
  stroke-linecap: round;
  stroke-linejoin: round;
  animation: drawCheck 0.4s ease forwards;
}

@keyframes drawCheck {
  from { stroke-dasharray: 24; stroke-dashoffset: 24; }
  to { stroke-dasharray: 24; stroke-dashoffset: 0; }
}

/* === Modernized Add Recipe Design === */
#addView .add-tab-content {
  background: linear-gradient(145deg, #f9fff9, #f0fdf4);
  border: 1px solid #c8e6c9;
  border-radius: 20px;
  box-shadow: 0 8px 20px rgba(102, 187, 106, 0.1);
  padding: 40px 45px;
  transition: all 0.3s ease;
}

#addView .add-tab-content:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(102, 187, 106, 0.15);
}

#addView h2 {
  text-align: center;
  color: #1b5e20;
  font-size: 1.8rem;
  font-weight: 700;
  letter-spacing: 0.3px;
  margin-bottom: 25px !important;
}

/* Form inputs and textareas */
#addRecipeForm input,
#addRecipeForm textarea {
  width: 100%;
  border: 1.5px solid #d7e9d7;
  border-radius: 10px;
  padding: 12px 14px;
  font-size: 15px;
  background: #fff;
  color: #1b4332;
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: all 0.25s ease;
}

#addRecipeForm input:focus,
#addRecipeForm textarea:focus {
  border-color: #81c784;
  box-shadow: 0 0 0 3px rgba(129, 199, 132, 0.25);
  outline: none;
  background: #fcfffc;
}

/* Labels */
#addRecipeForm label {
  font-weight: 700;
  color: #2e7d32;
  display: block;
  margin-bottom: 6px;
}

/* Textareas */
#addRecipeForm textarea {
  resize: vertical;
  min-height: 100px;
}

/* Buttons (reuse your .btn and .ghost) */
#addRecipeForm .btn {
  border-radius: 10px;
  padding: 10px 18px;
  font-weight: 600;
  font-size: 15px;
  transition: all 0.25s ease-in-out;
  letter-spacing: 0.3px;
}

#addRecipeForm .btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(129, 199, 132, 0.3);
}

#addRecipeForm .btn.ghost {
  border: 1.5px solid #c8e6c9;
  background: #fff;
  color: #2e7d32;
}

#addRecipeForm .btn.ghost:hover {
  background: #e8f5e9;
}

/* Add subtle animation for submit feedback */
#addMsg {
  text-align: center;
  font-style: italic;
  margin-top: 10px;
  transition: opacity 0.3s ease;
}

/* Grid spacing tweaks */
#addView .form-row {
  gap: 18px;
}

#addView .form-row div {
  display: flex;
  flex-direction: column;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  #addView .add-tab-content {
    padding: 28px 22px;
  }

  #addView h2 {
    font-size: 1.5rem;
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
  <div class="header-glow"><h1>🍽️ Meal Planning & Recipe Suggestions</h1></div>
  <p class="subtitle">Plan your week and discover new meals using your available ingredients.</p>
  <div class="tabs">
    <button class="tab active" data-tab="suggestions">Recipe Suggestions</button>
    <button class="tab" data-tab="planner">Weekly Meal Planner</button>
    <button class="tab" data-tab="add">Add Recipe</button>
  </div>
  <section id="suggestionsView">
    <div class="cards three">
      <article class="card"><h2>🍳 Breakfast</h2><ul id="sugBreakfast" class="sug-list"></ul></article>
      <article class="card"><h2>🥗 Lunch</h2><ul id="sugLunch" class="sug-list"></ul></article>
      <article class="card"><h2>🍝 Dinner</h2><ul id="sugDinner" class="sug-list"></ul></article>
    </div>
    <p class="subtitle">Click a recipe to view instructions or add to your meal planner.</p>
  </section>
  <section id="plannerView" hidden>
    <div id="plannerGrid" class="planner-grid"></div>
  </section>

  <section id="addView" hidden>
    <div class="add-tab-content">
      <h2 style="text-align:center; margin-bottom:14px;">Add Your Recipe</h2>
      <form id="addRecipeForm" enctype="multipart/form-data">
        <div class="form-row">
          <div>
            <label style="font-weight:700; display:block; margin-bottom:6px;">Recipe Name</label>
            <input type="text" id="recipeName" placeholder="e.g. Garlic Butter Shrimp" required />
          </div>
          <div>
            <label style="font-weight:700; display:block; margin-bottom:6px;">Meal Type</label>
            <select id="recipeMealType" required>
              <option value="Breakfast">Breakfast</option>
              <option value="Lunch">Lunch</option>
              <option value="Dinner">Dinner</option>
            </select>
          </div>
          <div>
            <label style="font-weight:700; display:block; margin-bottom:6px;">Upload Food Image</label>
            <input type="file" id="recipeImage" accept="image/*" />
          </div>

          <div class="full">
            <label style="font-weight:700; display:block; margin-bottom:6px;">Ingredients Needed</label>
            <textarea id="recipeDesc" rows="2" placeholder="List ingredients needed here (e.g. shrimp, butter, garlic)" required></textarea>
          </div>
          <div class="full">
            <label style="font-weight:700; display:block; margin-bottom:6px;">Instructions</label>
            <textarea id="recipeInstr" rows="6" placeholder="Write cooking steps here" required></textarea>
          </div>
        </div>
        <div style="text-align:right; margin-top:12px;">
          <button type="button" class="btn ghost" id="cancelAddTab">Cancel</button>
          <button type="submit" class="btn" id="submitAdd">Save Recipe</button>
        </div>
        <p id="addMsg" class="muted" style="margin-top:10px;"></p>
      </form>
    </div>
  </section>

</div>

<div id="toast" class="toast" aria-live="polite" role="status"></div>

<script>
const DAYS=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const MEALS=['Breakfast','Lunch','Dinner'];
const MP_LS_KEY='ge_meal_planner_v1';
const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>Array.from(r.querySelectorAll(s));

function readPlanner(){
  try{
    const d=JSON.parse(localStorage.getItem(MP_LS_KEY))||{};
    for(const day of DAYS){d[day]??={};for(const m of MEALS)d[day][m]??=[];}
    return d;
  }catch{
    return Object.fromEntries(DAYS.map(d=>[d,{Breakfast:[],Lunch:[],Dinner:[]}])); 
  }
}
function writePlanner(p){localStorage.setItem(MP_LS_KEY,JSON.stringify(p));}

// ---- Fetch suggestions ----
async function fetchSuggestions(){
  const map={Breakfast:$('#sugBreakfast'),Lunch:$('#sugLunch'),Dinner:$('#sugDinner')};
  Object.values(map).forEach(ul=>ul.innerHTML='<li>Loading...</li>');
  try{
    const res=await fetch('recipes.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=suggest'});
    const data=await res.json();
    renderSuggestions(data);
  }catch{
    Object.values(map).forEach(ul=>ul.innerHTML='<li>Failed to load recipes.</li>');
  }
}

// ---- Render suggestions ----
function renderSuggestions(data){
  const map={Breakfast:$('#sugBreakfast'),Lunch:$('#sugLunch'),Dinner:$('#sugDinner')};
  for(const meal in map) map[meal].innerHTML='';
  if(!data || !data.length){Object.values(map).forEach(ul=>ul.innerHTML='<li>No matching recipes found.</li>');return;}
  data.forEach(r=>{
    const name=r.recipe_name||'Unnamed';
    const meta=r.description||'';
    const cat=r.meal_type||'Lunch';
    const li=document.createElement('li');
    li.className='sug-item';
    // If the backend sends raw Base64 (no "data:" prefix), add it
const imgSrc = (() => {
  if (!r.meal_thumb) return '';
  // If it already starts with "data:", use it as-is
  if (r.meal_thumb.startsWith('data:')) return r.meal_thumb;
  // If it looks like a URL (external API image), keep it
  if (r.meal_thumb.startsWith('http')) return r.meal_thumb;
  // Otherwise, assume it's base64 binary data from MySQL
  return `data:image/jpeg;base64,${r.meal_thumb}`;
})();

    li.innerHTML = `${imgSrc ? `<img src="${imgSrc}" alt="${name}">` : ''}<div class="title">${name}</div><div class="meta">${meta}</div>`;
    li.onclick=()=>showRecipeModal(r);
    if(!map[cat]) map[cat]=$('#sugLunch');
    map[cat].appendChild(li);
  });
}

// ---- Recipe instructions modal ----
function showRecipeModal(recipe) {
  document.querySelectorAll('.modal').forEach(m => m.remove());

  const modal=document.createElement('div');
  modal.className='modal active';
  modal.innerHTML=`
    <div class="modal-backdrop"></div>
    <div class="modal-content">
      <button class="close-btn" id="closeModal">&times;</button>
      ${recipe.meal_thumb?`<img src="${recipe.meal_thumb}" alt="${recipe.recipe_name}">`:''}
      <div class="modal-body">
        <h2>${recipe.recipe_name}</h2>
        <p><strong>Category:</strong> ${recipe.description || 'N/A'}</p>
        <h3 style="margin-top:15px;">Instructions</h3>
        <p>${recipe.instructions || 'No instructions available.'}</p>
        <div style="text-align:right; margin-top:20px;">
          <button class="btn ghost" id="closeBtn">Close</button>
          <button class="btn" id="addBtn">Add to Meal Planner</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);

  modal.querySelector('#closeModal').onclick=()=>closeRecipeModal(modal);
  modal.querySelector('#closeBtn').onclick=()=>closeRecipeModal(modal);
  modal.querySelector('.modal-backdrop').onclick=()=>closeRecipeModal(modal);

  modal.querySelector('#addBtn').onclick=()=>{
    closeRecipeModal(modal);
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    document.querySelector('.tab[data-tab="planner"]').classList.add('active');
    $('#suggestionsView').hidden=true;
    $('#plannerView').hidden=false;
    setTimeout(()=>openAddMealModal(null, recipe.recipe_name, recipe.meal_type||'', true),300);
  };
}

function closeRecipeModal(modal){modal.classList.remove('active');setTimeout(()=>modal.remove(),300);}

// ---- Add Meal Modal (SINGLE VERSION) ----
function openAddMealModal(day=null,name='',mealType='',fromSuggestion=false){
  document.querySelectorAll('.modal').forEach(m=>m.remove());
  const modal=document.createElement('div');
  modal.className='modal active';
  modal.innerHTML=`
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
      <div class="modal-header">Add Meal</div>
      <div class="modal-body">
        <label>Day<select id="mdl-day">${DAYS.map(d=>`<option>${d}</option>`).join('')}</select></label>
        <label>Meal<select id="mdl-meal">${MEALS.map(m=>`<option>${m}</option>`).join('')}</select></label>
        <label>Recipe<input id="mdl-name" placeholder="Recipe name"></label>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" id="cancel">Cancel</button>
        <button class="btn" id="save">Add</button>
      </div>
    </div>`;
  document.body.appendChild(modal);

  if(day)$('#mdl-day',modal).value=day;
  if(mealType)$('#mdl-meal',modal).value=mealType;
  if(name)$('#mdl-name',modal).value=name;

  $('#cancel',modal).onclick=()=>closeRecipeModal(modal);
  $('.modal-backdrop',modal).onclick=()=>closeRecipeModal(modal);
  $('#save',modal).onclick=()=>{
    const d=$('#mdl-day',modal).value,
          m=$('#mdl-meal',modal).value,
          n=$('#mdl-name',modal).value.trim();
    if(!n)return;
    const p=readPlanner();
    p[d][m].push(n);
    writePlanner(p);
    renderPlanner();
    closeRecipeModal(modal);
  };
}

// ---- Add Recipe Tab Form Handling ----
$('#addRecipeForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const name = $('#recipeName').value.trim();
  const desc = $('#recipeDesc').value.trim();
  const instr = $('#recipeInstr').value.trim();
  const mealType = $('#recipeMealType').value;
const imageFile = $('#recipeImage').files[0];
  const msg = $('#addMsg');

  if(!name){ msg.textContent = 'Please enter a recipe name.'; msg.style.color = 'red'; return; }
  if(!instr){ msg.textContent = 'Please enter instructions.'; msg.style.color = 'red'; return; }

  const formData = new FormData();
formData.append('action', 'add_recipe');
formData.append('recipe_name', name);
formData.append('description', desc);
formData.append('instructions', instr);
formData.append('meal_type', mealType);
formData.append('meal_type', document.getElementById('recipeMealType').value);
if (imageFile) formData.append('recipe_image', imageFile);


  try {
    const res = await fetch('recipes.php', {
  method: 'POST',
  body: formData
});
    const text = await res.text();
    if(text.trim() === 'recipe_added'){
      showToast('Recipe added successfully');
      msg.textContent = '';
      $('#addRecipeForm').reset();
      // Refresh suggestions so newly added recipe can appear if it matches items
      fetchSuggestions();
      // switch to suggestions tab to show users results
      $$('.tab').forEach(x=>x.classList.remove('active'));
      document.querySelector('.tab[data-tab="suggestions"]').classList.add('active');
      $('#addView').hidden = true;
      $('#suggestionsView').hidden = false;
      $('#plannerView').hidden = true;
    } else if (text.trim() === 'invalid_recipe_name') {
      msg.textContent = 'Invalid recipe name.';
      msg.style.color = 'red';
    } else {
      msg.textContent = 'Failed to add recipe.';
      msg.style.color = 'red';
    }
  } catch (err) {
    msg.textContent = 'Failed to add recipe.';
    msg.style.color = 'red';
  }
});

$('#cancelAddTab').onclick = function(){
  $('#addRecipeForm').reset();
  $('#addMsg').textContent = '';
};

// ---- Weekly Planner ----
function renderPlanner(){
  const grid=$('#plannerGrid');
  grid.innerHTML='';
  const p=readPlanner();
  DAYS.forEach(day=>{
    const card=document.createElement('article');
    card.className='day-card';
    card.innerHTML=`<div class="day-head"><span>${day}</span><button class="add-day-btn" title="Add meal">+</button></div>`;
    MEALS.forEach(meal=>{
      const sec=document.createElement('section');
      sec.className='meal';
      sec.innerHTML=`<h3>${meal}</h3><ul class="meal-list"></ul>`;
      const ul=$('.meal-list',sec);
      (p[day][meal]||[]).forEach((n,i)=>ul.appendChild(makeMealItem(day,meal,n,i)));
      card.appendChild(sec);
    });
    $('.add-day-btn',card).onclick=()=>openAddMealModal(day);
    grid.appendChild(card);
  });
}

function makeMealItem(day,meal,name,i){
  const li=document.createElement('li');
  li.className='meal-item';
  li.innerHTML=`<span class="name">${name}</span><button class="icon-btn"><i class="fas fa-trash"></i></button>`;
  $('.icon-btn',li).onclick=()=>removeMeal(day,meal,i);
  return li;
}
function removeMeal(day,meal,i){
  const p=readPlanner();
  p[day][meal].splice(i,1);
  writePlanner(p);
  renderPlanner();
}

// ---- Tab switching ----
$$('.tab').forEach(t=>t.onclick=()=>{
  $$('.tab').forEach(x=>x.classList.remove('active'));
  t.classList.add('active');
  const tab=t.dataset.tab;
  $('#suggestionsView').hidden=tab!=='suggestions';
  $('#plannerView').hidden=tab!=='planner';
  $('#addView').hidden=tab!=='add';
});

renderPlanner();
fetchSuggestions();

// ---- Toast ----
function showToast(message) {
  const toast = $('#toast');
  toast.innerHTML = `
    <svg viewBox="0 0 24 24">
      <path d="M5 13l4 4L19 7" />
    </svg>
    ${message}
  `;
  toast.classList.add('show');

  setTimeout(() => {
    toast.classList.remove('show');
  }, 2500);
}

</script>

</body>
</html>
