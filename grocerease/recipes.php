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
            $cost = is_numeric($_POST['estimated_cost'] ?? null) ? (float) $_POST['estimated_cost'] : 0.0;

            // Validate input
            if ($name === '' || strlen($name) > 255) {
                echo "invalid_recipe_name";
                exit;
            }

            $stmt = $conn->prepare(
                "INSERT INTO recipes (user_id, recipe_name, description, instructions, estimated_cost) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isssd", $user_id, $name, $desc, $instr, $cost);
            echo $stmt->execute() ? "recipe_added" : "error";
            $stmt->close();
            break;

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

            // 2️⃣ Step 1: Get meals by ingredients
            $allMeals = [];
            foreach ($userItems as $ingredient) {
                $apiUrl = "https://www.themealdb.com/api/json/v1/1/filter.php?i=" . urlencode($ingredient);
                $response = @file_get_contents($apiUrl, false, $context);
                if ($response === false) continue;

                $data = json_decode($response, true);
                if (!isset($data['meals']) || !$data['meals']) continue;

                // Limit to top 3 per ingredient
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

            if (empty($allMeals)) {
                echo json_encode([]);
                exit;
            }

            // 3️⃣ Step 2: Fetch details
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

                // Classify meal type
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
    .back-arrow { position: fixed; top: 20px; left: 20px; width: 45px; height: 45px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); cursor: pointer; color: #388e3c; font-size: 20px; z-index: 1000; transition: all 0.3s ease; text-decoration: none; }
    .back-arrow:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
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
    .modal-body input, .modal-body select { padding: 11px 14px; border-radius: 8px; border: 1px solid #e6e6e6; font-size: 14px; }
    .modal-actions { display:flex; justify-content:flex-end; gap:10px; padding: 14px 18px; border-top: 1px solid #f1f1f1; }
    .btn { background: linear-gradient(90deg,#81c784,#66bb6a); color:#fff; border:none; padding:10px 14px; border-radius:8px; cursor:pointer; font-weight:600; }
    .btn.ghost { background: transparent; border: 1px solid rgba(0,0,0,0.08); color: #333; }
    @media (max-width: 900px) { .cards.three { grid-template-columns: 1fr; } .planner-grid { grid-template-columns: 1fr; } .container { padding: 40px 20px; } }
/* Modal overlay (background dim) */
/* ===== MODAL OVERLAY ===== */
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

/* ===== MODAL CONTENT ===== */
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

/* Smooth entrance animation */
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

/* ===== HEADER IMAGE ===== */
.modal-content img {
  width: 100%;
  height: 250px;
  object-fit: cover;
  border-top-left-radius: 20px;
  border-top-right-radius: 20px;
}

/* ===== TEXT CONTENT ===== */
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

/* ===== CLOSE BUTTON ===== */
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

/* ===== SCROLLBAR STYLE ===== */
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

</style>
</head>
<body>
<a href="dashboard.php" class="back-arrow" title="Back to Dashboard"><i class="fas fa-arrow-left"></i></a>
<div class="container">
  <div class="header-glow"><h1>🍽️ Meal Planning & Recipe Suggestions</h1></div>
  <p class="subtitle">Plan your week and discover new meals using your available ingredients.</p>
  <div class="tabs">
    <button class="tab active" data-tab="suggestions">Recipe Suggestions</button>
    <button class="tab" data-tab="planner">Weekly Meal Planner</button>
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
</div>

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
  if(!data.length){Object.values(map).forEach(ul=>ul.innerHTML='<li>No matching recipes found.</li>');return;}
  data.forEach(r=>{
    const name=r.recipe_name||'Unnamed';
    const meta=r.description||'';
    const cat=r.meal_type||'Lunch';
    const li=document.createElement('li');
    li.className='sug-item';
    li.innerHTML=`${r.meal_thumb?`<img src="${r.meal_thumb}" alt="${name}">`:''}<div class="title">${name}</div><div class="meta">${meta}</div>`;
    li.onclick=()=>showRecipeModal(r);
    map[cat].appendChild(li);
  });
}

// ---- Recipe instructions modal ----
function showRecipeModal(recipe) {
  document.querySelectorAll('.modal').forEach(m => m.remove());

  const modal=document.createElement('div');
  modal.className='modal';
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
  requestAnimationFrame(()=>modal.classList.add('active'));

  // Close
  modal.querySelector('#closeModal').onclick=()=>closeRecipeModal(modal);
  modal.querySelector('#closeBtn').onclick=()=>closeRecipeModal(modal);
  modal.querySelector('.modal-backdrop').onclick=()=>closeRecipeModal(modal);

  // Add to planner
  modal.querySelector('#addBtn').onclick=()=>{
    closeRecipeModal(modal);
    // Switch to Weekly Planner tab
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    document.querySelector('.tab[data-tab="planner"]').classList.add('active');
    $('#suggestionsView').hidden=true;
    $('#plannerView').hidden=false;
    // Open Add Meal modal
    setTimeout(()=>openAddMealModal(null, recipe.recipe_name, recipe.meal_type||'', true),300);
  };
}

function closeRecipeModal(modal){modal.classList.remove('active');setTimeout(()=>modal.remove(),300);}

// ---- Add Meal Modal (SINGLE VERSION) ----
function openAddMealModal(day=null,name='',mealType='',fromSuggestion=false){
  document.querySelectorAll('.modal').forEach(m=>m.remove());
  const modal=document.createElement('div');
  modal.className='modal';
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
  requestAnimationFrame(()=>modal.classList.add('active'));

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
});

renderPlanner();
fetchSuggestions();
</script>

</body>
</html>
