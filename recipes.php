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











<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>GrocerEase • Meal Planning & Recipe Suggestions</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet" />

  <style>
    :root {
      --line: #e5e7eb;
      --muted: #6b7280;
      --brand: #1ae691;
    }
    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      background: #f9fafb;
      color: #111827;
    }
    .app-shell { display: flex; min-height: 100vh; }
    .sidebar {
      width: 260px; background: #fff;
      border-right: 1px solid var(--line);
      display: flex; flex-direction: column;
      padding: 16px;
    }
    .brand .logo {
      display: flex; align-items: center;
      font-weight: 800; font-size: 20px;
      gap: 8px; margin-bottom: 24px;
    }
    .logo-img { width: 32px; height: 32px; }
    .menu { display: flex; flex-direction: column; gap: 4px; }
    .menu-section {
      margin: 12px 0 4px; font-size: 12px;
      font-weight: 700; color: var(--muted); text-transform: uppercase;
    }
    .menu-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 8px;
      text-decoration: none; color: #111827; font-weight: 600;
    }
    .menu-item:hover { background: #f3f4f6; }
    .menu-item.active { background: var(--brand); color: #000; }

    .main { flex: 1; padding: 24px; overflow-y: auto; }
    .topbar h1 { margin: 0; font-size: 24px; font-weight: 800; }
    .lede { color: var(--muted); margin-top: 4px; margin-bottom: 20px; }

    .tabs { display: flex; gap: 8px; align-items: center; margin: 8px 0 16px; }
    .tab {
      border: 1px solid var(--line); background: #fff; border-radius: 999px;
      padding: 8px 14px; font-weight: 700; cursor: pointer;
    }
    .tab.active { background: var(--brand); box-shadow: 0 0 0 4px rgba(26,230,145,.25); }
    .tabs .spacer { flex: 1; }

    /* Recipe Suggestion Cards */
    .cards.three { 
      display: grid; 
      grid-template-columns: repeat(3, minmax(220px, 1fr)); 
      gap: 16px; 
      align-items: start; 
    }
    .cards.three article {
      background: linear-gradient(135deg, #d8f6e1, #f4fff9);
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }
    .sug-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
    .sug-item {
      border: 1px solid var(--line); border-radius: 10px;
      padding: 10px 12px; background: #fff; cursor: pointer;
    }
    .sug-item:hover { border-color: #cbd5e1; transform: translateY(-2px); transition: all 0.15s ease; }
    .sug-item .title { font-weight: 700; }
    .sug-item .meta { color: var(--muted); font-size: 12px; }
    .footnote { color: var(--muted); margin-top: 16px; }

    .planner-grid { display: grid; grid-template-columns: repeat(3, minmax(280px, 1fr)); gap: 16px; }
    .day-card {
      background: #fff; border: 1px solid var(--line);
      border-radius: 14px; padding: 12px; display: flex; flex-direction: column;
    }
    .day-head {
      display: flex; align-items: center; justify-content: space-between;
      font-weight: 800; margin-bottom: 8px;
    }
    .add-day-btn {
      border: none; background: var(--brand); color: #000;
      border-radius: 50%; width: 34px; height: 34px;
      font-size: 20px; font-weight: bold; cursor: pointer;
    }
    .meal {
      border: 1px dashed var(--line); border-radius: 12px;
      padding: 10px; margin-bottom: 8px;
    }
    .meal h3 { margin: 0 0 6px; font-size: 14px; }
    .meal-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
    .meal-item {
      display: flex; align-items: center; justify-content: space-between;
      border: 1px solid var(--line); border-radius: 10px;
      padding: 8px 10px; background: #fff;
    }
    .meal-item .name { font-weight: 600; cursor: pointer; }
    .meal-item input {
      border: 1px solid var(--line); border-radius: 6px;
      padding: 6px; width: 100%;
    }
    .meal-item .actions { display: inline-flex; gap: 6px; }
    .icon-btn {
      border: 1px solid var(--line); background: #fff;
      border-radius: 8px; padding: 6px; cursor: pointer;
    }

    /* Modal Styling */
    .modal {
      display: none; position: fixed; inset: 0;
      align-items: center; justify-content: center; z-index: 100;
    }
    .modal.show { display: flex; }
    .modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
    .modal-dialog {
      position: relative; background: #fff;
      width: 520px; max-width: 90%;
      border-radius: 14px; overflow: hidden;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
      z-index: 1;
    }
    .modal-header {
      background: var(--brand);
      color: #000; padding: 16px 20px;
      font-weight: 800; font-size: 18px;
    }
    .modal-body {
      padding: 20px;
      display: flex; flex-direction: column; gap: 12px;
    }
    .modal-body label {
      font-weight: 600; font-size: 14px;
      display: flex; flex-direction: column; gap: 6px;
    }
    .modal-body input, .modal-body select {
      padding: 10px 12px;
      border-radius: 10px; border: 1px solid var(--line);
      font-size: 14px;
    }
    .modal-actions {
      display: flex; justify-content: flex-end; gap: 10px;
      padding: 16px 20px; border-top: 1px solid var(--line);
    }
    .btn { padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
    .btn-primary { background: var(--brand); }
    .btn-ghost { background: #f3f4f6; }
  </style>
</head>

<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="logo">
          <img class="logo-img" src="./assets/logo.png" alt="GrocerEase logo" />
          <span>GrocerEase</span>
        </div>
      </div>
      <nav class="menu" aria-label="Main">
        <a class="menu-item" href="./dashboard.html"><span>🏠</span>Dashboard</a>
        <div class="menu-section">Management</div>
        <a class="menu-item" href="./grocery.html"><span>🧾</span>Grocery List</a>
        <a class="menu-item active" href="#"><span>🍳</span>Meal Planning</a>
        <a class="menu-item" href="./inventory.html"><span>📦</span>Inventory</a>
        <a class="menu-item" href="#"><span>💲</span>Budgeting</a>
        <div class="menu-section">Settings</div>
        <button id="logoutBtn" class="menu-item" type="button"><span>↪</span>Logout</button>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar"><h1>Meal Planning & Recipe Suggestions</h1></header>
      <p class="lede">Plan your week and discover new meals using smart suggestions.</p>

      <div class="tabs">
        <button class="tab active" data-tab="suggestions">Recipe Suggestions</button>
        <button class="tab" data-tab="planner">Weekly Meal Planner</button>
        <div class="spacer"></div>
      </div>

      <section id="suggestionsView">
        <div class="cards three">
          <article><h2>🍳 Breakfast</h2><ul id="sugBreakfast" class="sug-list"></ul></article>
          <article><h2>🥗 Lunch</h2><ul id="sugLunch" class="sug-list"></ul></article>
          <article><h2>🍝 Dinner</h2><ul id="sugDinner" class="sug-list"></ul></article>
        </div>
        <p class="footnote">Tip: Click a recipe to add it to the planner.</p>
      </section>

      <section id="plannerView" hidden>
        <div id="plannerGrid" class="planner-grid"></div>
      </section>
    </main>
  </div>

  <script>
    const MP_LS_KEY='ge_meal_planner_v1';
    const DAYS=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const MEALS=['Breakfast','Lunch','Dinner'];
    const $=(s,r=document)=>r.querySelector(s);
    const $$=(s,r=document)=>Array.from(r.querySelectorAll(s));

    const RECIPES={
      Breakfast:[{name:'Avocado Toast',meta:'15m • Avocado • Bread • Egg'},{name:'Greek Yogurt Parfait',meta:'10m • Yogurt • Berries • Granola'},{name:'Oatmeal Bowl',meta:'10m • Oats • Banana • Honey'}],
      Lunch:[{name:'Chicken Salad',meta:'20m • Chicken • Lettuce • Dressing'},{name:'Veggie Stir-fry',meta:'25m • Broccoli • Carrot • Soy Sauce'},{name:'Quinoa Bowl',meta:'25m • Quinoa • Chickpeas • Spinach'}],
      Dinner:[{name:'Salmon Teriyaki',meta:'30m • Salmon • Teriyaki • Rice'},{name:'Pasta Primavera',meta:'30m • Pasta • Veggies • Parmesan'},{name:'Beef Tacos',meta:'25m • Beef • Tortillas • Salsa'}]
    };

    function readPlanner(){try{const d=JSON.parse(localStorage.getItem(MP_LS_KEY))||{};
      for(const day of DAYS){d[day]??={};for(const m of MEALS)d[day][m]??=[];}return d;}
      catch{return Object.fromEntries(DAYS.map(d=>[d,{Breakfast:[],Lunch:[],Dinner:[]}]))}}
    function writePlanner(p){localStorage.setItem(MP_LS_KEY,JSON.stringify(p));}

    function renderSuggestions(){
      const map={Breakfast:$('#sugBreakfast'),Lunch:$('#sugLunch'),Dinner:$('#sugDinner')};
      for(const meal in RECIPES){
        map[meal].innerHTML='';
        RECIPES[meal].forEach(r=>{
          const li=document.createElement('li');li.className='sug-item';
          li.innerHTML=`<div class="title">${r.name}</div><div class="meta">${r.meta}</div>`;
          li.onclick=()=>openAddMealModal(null,r.name,meal,true);
          map[meal].appendChild(li);
        });
      }
    }

    function renderPlanner(){
      const grid=$('#plannerGrid');grid.innerHTML='';
      const p=readPlanner();
      DAYS.forEach(day=>{
        const card=document.createElement('article');card.className='day-card';
        card.innerHTML=`<div class="day-head"><span>${day}</span><button class="add-day-btn" title="Add meal">+</button></div>`;
        MEALS.forEach(meal=>{
          const sec=document.createElement('section');sec.className='meal';
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
      const li=document.createElement('li');li.className='meal-item';
      li.innerHTML=`<span class="name">${name}</span><span class="actions"><button class="icon-btn">🗑️</button></span>`;
      const nameEl=$('.name',li);
      nameEl.onclick=()=>{
        const input=document.createElement('input');input.value=name;nameEl.replaceWith(input);input.focus();
        input.onblur=()=>save(input.value);input.onkeydown=e=>{if(e.key==='Enter')save(input.value);};
        function save(v){const p=readPlanner();p[day][meal][i]=v.trim()||name;writePlanner(p);renderPlanner();}
      };
      $('.icon-btn',li).onclick=()=>{const p=readPlanner();p[day][meal].splice(i,1);writePlanner(p);renderPlanner();};
      return li;
    }

    function openAddMealModal(day,preName='',preType='Breakfast',fromSuggestion=false){
      const today=new Date().toLocaleDateString('en-US',{weekday:'long'});
      const m=document.createElement('div');m.className='modal show';
      m.innerHTML=`
        <div class="modal-backdrop" data-close></div>
        <div class="modal-dialog">
          <div class="modal-header">${fromSuggestion?'Add to Planner':day?`Add Meal for ${day}`:'Add to Planner'}</div>
          <div class="modal-body">
            <label>Day<select id="mealDay">${DAYS.map(d=>`<option ${d===today?'selected':''}>${d}</option>`).join('')}</select></label>
            ${!fromSuggestion?`<label>Meal Type<select id="mealType">${MEALS.map(x=>`<option ${x===preType?'selected':''}>${x}</option>`).join('')}</select></label>`:''}
            <label>Meal Name<input id="mealName" value="${preName}" placeholder="e.g. Chicken Curry"/></label>
          </div>
          <div class="modal-actions">
            <button class="btn btn-ghost" data-close>Cancel</button>
            <button id="addConfirm" class="btn btn-primary">Add</button>
          </div>
        </div>`;
      document.body.appendChild(m);
      const close=()=>m.remove();
      m.querySelectorAll('[data-close]').forEach(b=>b.onclick=close);
      m.querySelector('.modal-backdrop').onclick=close;
      $('#addConfirm',m).onclick=()=>{
        const d=$('#mealDay',m).value;
        const type=fromSuggestion?preType:$('#mealType',m).value;
        const name=$('#mealName',m).value.trim();
        if(!name)return;
        const p=readPlanner();
        p[d][type].push(name);
        writePlanner(p);
        close();
        renderPlanner();
        switchTab('planner');
      };
    }

    function switchTab(which){
      const sug=which==='suggestions';
      $('#suggestionsView').hidden=!sug;$('#plannerView').hidden=sug;
      $$('.tab').forEach(t=>t.classList.remove('active'));
      $(`.tab[data-tab="${which}"]`).classList.add('active');
      if(!sug)renderPlanner();
    }

    document.addEventListener('DOMContentLoaded',()=>{renderSuggestions();renderPlanner();$$('.tab').forEach(t=>t.onclick=()=>switchTab(t.dataset.tab));});
  </script>
</body>
</html>
