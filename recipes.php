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
  <title>🍽️ Meal Planning & Recipe Suggestions</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: radial-gradient(circle at top left, #e8f5e9 0%, #c8e6c9 50%, #a5d6a7 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 40px 20px;
      color: #0f1720;
    }

    .container {
      max-width: 1300px;
      width: 100%;
      margin: 20px auto;
      background: #ffffff;
      border-radius: 25px;
      box-shadow: 0 10px 35px rgba(76, 175, 80, 0.15);
      padding: 60px 50px;
      border: 1px solid #c8e6c9;
    }

    .header-glow {
      background: linear-gradient(135deg, #c8f7d1, #b2eabf);
      border-radius: 18px;
      padding: 30px;
      text-align: center;
      margin-bottom: 30px;
      position: relative;
      overflow: hidden;
    }

    .header-glow h1 {
      color: #2e7d32;
      font-size: 2.2rem;
      font-weight: 800;
      margin: 0;
    }

    .header-glow::after {
      content: "";
      position: absolute;
      top: 0;
      left: -80%;
      width: 50%;
      height: 100%;
      background: linear-gradient(120deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.9) 50%, rgba(255,255,255,0) 100%);
      transform: rotate(25deg);
      animation: shimmer 3s infinite linear;
    }

    @keyframes shimmer {
      0% { transform: translateX(-150%) rotate(25deg); }
      100% { transform: translateX(150%) rotate(25deg); }
    }

    .back-arrow {
      position: fixed;
      top: 20px;
      left: 20px;
      width: 45px;
      height: 45px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      cursor: pointer;
      color: #388e3c;
      font-size: 20px;
      z-index: 1000;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .back-arrow:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .subtitle { color: #4e7d4a; text-align:center; margin-bottom: 22px; font-size: 1.05rem; }

    .tabs {
      display:flex;
      justify-content:center;
      gap:12px;
      margin-bottom: 25px;
    }

    .tab {
      border: 2px solid #a5d6a7;
      background: white;
      border-radius: 35px;
      padding: 10px 24px;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s ease;
      font-size: 1rem;
    }

    .tab.active {
      background: linear-gradient(135deg,#81c784,#66bb6a);
      color: #fff;
      border: none;
      box-shadow: 0 0 10px rgba(102,187,106,0.25);
    }

    .cards.three {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 22px;
    }

    .card {
      background: #f9fff9;
      border-radius: 14px;
      padding: 20px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.05);
      border: 1px solid #c8e6c9;
    }

    .sug-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
    .sug-item {
      border: 1px solid #d4edda;
      border-radius: 10px;
      padding: 12px 14px;
      background: white;
      cursor: pointer;
      transition: transform .12s ease, border-color .12s ease;
    }

    .sug-item:hover { border-color: #81c784; transform: translateY(-2px); }
    .sug-item .title { font-weight:700; }
    .sug-item .meta { color: #6b7280; font-size: 13px; }

    .planner-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 12px;
    }

    .day-card {
      background: #fff;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 5px 18px rgba(76,175,80,0.07);
      border: 1px solid #c8e6c9;
      display:flex;
      flex-direction:column;
    }

    .day-head { display:flex; justify-content:space-between; align-items:center; font-weight:800; margin-bottom:10px; font-size:1.1rem; }

    .add-day-btn {
      border: none;
      background: linear-gradient(135deg,#81c784,#66bb6a);
      color: #fff;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      font-size: 18px;
      cursor:pointer;
    }

    .meal { border: 1px dashed rgba(0,0,0,0.08); border-radius:10px; padding: 12px; margin-bottom:10px; }
    .meal h3 { margin:0 0 8px 0; font-size: 15px; }
    .meal-list { list-style:none; padding:0; margin:0; display:grid; gap:8px; }
    .meal-item { display:flex; justify-content:space-between; align-items:center; padding:9px 12px; border-radius:8px; border:1px solid rgba(0,0,0,0.04); background:#fff; }
    .meal-item .name { font-weight:600; cursor:pointer; font-size: 14px; }
    .icon-btn { border:1px solid rgba(0,0,0,0.06); padding:6px; border-radius:8px; background:#fff; cursor:pointer; }

    .modal {
      display: none;
      position: fixed;
      inset: 0;
      align-items: center;
      justify-content: center;
      z-index: 2000;
    }

    .modal.show { display: flex; }
    .modal-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.45);
      backdrop-filter: blur(1px);
    }

    .modal-dialog {
      position: relative;
      background: #fff;
      width: 550px;
      max-width: 95%;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,0.25);
      z-index: 2001;
    }

    .modal-header {
      background: linear-gradient(90deg,#81c784,#66bb6a);
      color: #000;
      padding: 18px 20px;
      font-weight: 800;
      font-size: 19px;
    }

    .modal-body {
      padding: 20px 22px;
      display:flex;
      flex-direction:column;
      gap:14px;
    }

    .modal-body label { font-weight:600; font-size:14px; display:flex; flex-direction:column; gap:8px; }
    .modal-body input, .modal-body select {
      padding: 11px 14px;
      border-radius: 8px;
      border: 1px solid #e6e6e6;
      font-size: 14px;
    }

    .modal-actions {
      display:flex;
      justify-content:flex-end;
      gap:10px;
      padding: 14px 18px;
      border-top: 1px solid #f1f1f1;
    }

    .btn {
      background: linear-gradient(90deg,#81c784,#66bb6a);
      color:#fff;
      border:none;
      padding:10px 14px;
      border-radius:8px;
      cursor:pointer;
      font-weight:600;
    }

    .btn.ghost {
      background: transparent;
      border: 1px solid rgba(0,0,0,0.08);
      color: #333;
    }

    @media (max-width: 900px) {
      .cards.three { grid-template-columns: 1fr; }
      .planner-grid { grid-template-columns: 1fr; }
      .container { padding: 40px 20px; }
    }
  </style>
</head>

<body>
  <a href="dashboard.php" class="back-arrow" title="Back to Dashboard"><i class="fas fa-arrow-left"></i></a>

  <div class="container">
    <div class="header-glow">
      <h1>🍽️ Meal Planning & Recipe Suggestions</h1>
    </div>

    <p class="subtitle">Plan your week and discover new meals using smart suggestions.</p>

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
      <p class="subtitle">Tip: Click a recipe to add it to the planner.</p>
    </section>

    <section id="plannerView" hidden>
      <div id="plannerGrid" class="planner-grid"></div>
    </section>
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

    function readPlanner(){
      try {
        const d = JSON.parse(localStorage.getItem(MP_LS_KEY)) || {};
        for(const day of DAYS){ d[day] ??= {}; for(const m of MEALS) d[day][m] ??= []; }
        return d;
      } catch {
        return Object.fromEntries(DAYS.map(d=>[d,{Breakfast:[],Lunch:[],Dinner:[]}]));
      }
    }

    function writePlanner(p){ localStorage.setItem(MP_LS_KEY, JSON.stringify(p)); }

    function renderSuggestions(){
      const map={Breakfast:$('#sugBreakfast'),Lunch:$('#sugLunch'),Dinner:$('#sugDinner')};
      for(const meal in RECIPES){
        map[meal].innerHTML='';
        RECIPES[meal].forEach(r=>{
          const li=document.createElement('li'); li.className='sug-item';
          li.innerHTML=`<div class="title">${r.name}</div><div class="meta">${r.meta}</div>`;
          li.onclick=()=>openAddMealModal(null,r.name,meal,true);
          map[meal].appendChild(li);
        });
      }
    }

    function renderPlanner(){
      const grid=$('#plannerGrid'); grid.innerHTML='';
      const p=readPlanner();
      DAYS.forEach(day=>{
        const card=document.createElement('article'); card.className='day-card';
        card.innerHTML=`<div class="day-head"><span>${day}</span><button class="add-day-btn" title="Add meal">+</button></div>`;
        MEALS.forEach(meal=>{
          const sec=document.createElement('section'); sec.className='meal';
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
      const li=document.createElement('li'); li.className='meal-item';
      li.innerHTML=`<span class="name">${name}</span><span class="actions"><button class="icon-btn">🗑️</button></span>`;
      const nameEl=$('.name',li);
      nameEl.onclick=()=>{
        const input=document.createElement('input'); input.value=name; nameEl.replaceWith(input); input.focus();
        input.onblur=()=>save(input.value); input.onkeydown=e=>{ if(e.key==='Enter') save(input.value); };
        function save(v){ const p=readPlanner(); p[day][meal][i]=v.trim()||name; writePlanner(p); renderPlanner(); }
      };
      $('.icon-btn',li).onclick=()=>{ const p=readPlanner(); p[day][meal].splice(i,1); writePlanner(p); renderPlanner(); };
      return li;
    }

    // ✅ Modal: "Day" field only for Recipe Suggestions
    function openAddMealModal(day, preName = '', preType = 'Breakfast', fromSuggestion = false) {
      const today = new Date().toLocaleDateString('en-US', { weekday: 'long' });
      const m = document.createElement('div');
      m.className = 'modal show';

      const dayField = fromSuggestion
        ? `<label>Day
            <select id="mealDay">
              ${DAYS.map(d => `<option ${d === today ? 'selected' : ''}>${d}</option>`).join('')}
            </select>
          </label>`
        : '';

      m.innerHTML = `
        <div class="modal-backdrop" data-close></div>
        <div class="modal-dialog">
          <div class="modal-header">${fromSuggestion ? 'Add to Planner' : day ? `Add Meal for ${day}` : 'Add to Planner'}</div>
          <div class="modal-body">
            ${dayField}
            ${!fromSuggestion ? `
              <label>Meal Type
                <select id="mealType">
                  ${MEALS.map(x => `<option ${x === preType ? 'selected' : ''}>${x}</option>`).join('')}
                </select>
              </label>` : ''}
            <label>Meal Name
              <input id="mealName" value="${preName}" placeholder="e.g. Chicken Curry"/>
            </label>
          </div>
          <div class="modal-actions">
            <button class="btn ghost" data-close>Cancel</button>
            <button id="addConfirm" class="btn">Add</button>
          </div>
        </div>`;

      document.body.appendChild(m);
      const close = () => m.remove();
      m.querySelectorAll('[data-close]').forEach(b => b.onclick = close);
      m.querySelector('.modal-backdrop').onclick = close;

      $('#addConfirm', m).onclick = () => {
        const d = fromSuggestion ? $('#mealDay', m).value : day;
        const type = fromSuggestion ? preType : $('#mealType', m).value;
        const name = $('#mealName', m).value.trim();
        if (!name) return;
        const p = readPlanner();
        p[d][type].push(name);
        writePlanner(p);
        close();
        renderPlanner();
        switchTab('planner');
      };
    }

    function switchTab(which){
      const sug = which === 'suggestions';
      $('#suggestionsView').hidden = !sug;
      $('#plannerView').hidden = sug;
      $$('.tab').forEach(t=>t.classList.remove('active'));
      $(`.tab[data-tab="${which}"]`).classList.add('active');
      if(!sug) renderPlanner();
    }

    document.addEventListener('DOMContentLoaded', ()=>{
      renderSuggestions();
      renderPlanner();
      $$('.tab').forEach(t=>t.onclick=()=>switchTab(t.dataset.tab));
    });
  </script>
</body>
</html>

