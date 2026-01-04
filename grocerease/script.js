// Detect which page
const isDashboard = document.querySelector('#addSample');
const isBudget = document.querySelector('#budgetInput');

// Storage
let storage = JSON.parse(localStorage.getItem('grocerEaseData')) || { purchases: [] };

// ---- Dashboard Logic ----
if (isDashboard) {
  const mostChartCtx = document.getElementById('mostChart').getContext('2d');
  const purchaseTableBody = document.querySelector('#purchaseTable tbody');
  const topList = document.getElementById('topList');
  const addSampleBtn = document.getElementById('addSample');
  const clearBtn = document.getElementById('clearData');

  let mostChart = new Chart(mostChartCtx, {
    type: 'bar',
    data: { labels: [], datasets: [{ label: 'Quantity', data: [], borderRadius: 6, barThickness: 20 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
  });

  function renderTable() {
    purchaseTableBody.innerHTML = '';
    if (storage.purchases.length === 0) {
      purchaseTableBody.innerHTML = '<tr class="muted-row"><td colspan="5">No purchases yet</td></tr>';
      return;
    }
    storage.purchases.slice().reverse().forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${p.item}</td><td>${p.category}</td><td>${p.qty}</td><td>₱${p.price.toFixed(2)}</td><td>${p.date}</td>`;
      purchaseTableBody.appendChild(tr);
    });
  }

  function updateStats() {
    const agg = {};
    storage.purchases.forEach(p => agg[p.item] = (agg[p.item] || 0) + p.qty);
    const sorted = Object.entries(agg).sort((a, b) => b[1] - a[1]).slice(0, 5);
    topList.innerHTML = '';
    sorted.forEach(([item, qty]) => {
      const li = document.createElement('li');
      li.textContent = `${item} — ${qty}`;
      topList.appendChild(li);
    });
    mostChart.data.labels = sorted.map(e => e[0]);
    mostChart.data.datasets[0].data = sorted.map(e => e[1]);
    mostChart.update();
  }

  function addSampleData() {
    const sample = [
      { item: 'Rice (5kg)', category: 'Staples', qty: 2, price: 250, date: '2025-10-01' },
      { item: 'Eggs (dozen)', category: 'Dairy', qty: 3, price: 85, date: '2025-10-02' },
      { item: 'Chicken (kg)', category: 'Meat', qty: 2, price: 180, date: '2025-10-03' }
    ];
    storage.purchases.push(...sample);
    localStorage.setItem('grocerEaseData', JSON.stringify(storage));
    renderTable();
    updateStats();
  }

  function clearData() {
    storage.purchases = [];
    localStorage.setItem('grocerEaseData', JSON.stringify(storage));
    renderTable();
    updateStats();
  }

  addSampleBtn.addEventListener('click', addSampleData);
  clearBtn.addEventListener('click', clearData);
  renderTable();
  updateStats();
}

// ---- Budget Logic ----
if (isBudget) {
  const totalCostElem = document.getElementById('totalCost');
  const budgetInput = document.getElementById('budgetInput');
  const updateBudgetBtn = document.getElementById('updateBudget');
  const budgetStatus = document.getElementById('budgetStatus');
  const altList = document.getElementById('altList');

  let userBudget = parseFloat(localStorage.getItem('userBudget')) || 0;

  function computeTotalCost() {
    const total = storage.purchases.reduce((a, b) => a + b.price, 0);
    totalCostElem.textContent = `₱${total.toFixed(2)}`;
    if (!userBudget) {
      budgetStatus.textContent = "No budget set yet.";
      return;
    }
    if (total > userBudget) {
      budgetStatus.textContent = `Over budget by ₱${(total - userBudget).toFixed(2)}!`;
      budgetStatus.style.color = "#d34";
      suggestAlternatives();
    } else {
      budgetStatus.textContent = `Within budget — ₱${(userBudget - total).toFixed(2)} remaining.`;
      budgetStatus.style.color = "green";
      altList.innerHTML = '<li class="muted-row">No costly items detected</li>';
    }
  }

  function suggestAlternatives() {
    const expensive = storage.purchases.filter(p => p.price > 100);
    const suggestions = {
      "Chicken (kg)": "Tilapia (kg) ₱130/kg",
      "Beef (kg)": "Pork (kg) ₱160/kg",
      "Milk (1L)": "Powdered Milk ₱60",
      "Eggs (dozen)": "Tofu ₱40"
    };
    altList.innerHTML = '';
    expensive.forEach(p => {
      const li = document.createElement('li');
      li.textContent = `${p.item} → ${suggestions[p.item] || "Buy smaller quantity"}`;
      altList.appendChild(li);
    });
  }

  updateBudgetBtn.addEventListener('click', () => {
    userBudget = parseFloat(budgetInput.value);
    localStorage.setItem('userBudget', userBudget);
    computeTotalCost();
  });

  computeTotalCost();
}

// js/dashboard.js

// ===== Active Nav Animation =====
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    item.classList.add('active');
  });
});

// ===== Simple Chart.js Visualization =====
const ctx = document.getElementById('spendingChart');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [{
        label: '₱ Spent',
        data: [150, 200, 120, 300, 180, 260, 90],
        backgroundColor: ['#0a8f5caa'],
        borderRadius: 6,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        y: { grid: { display: false } },
        x: { grid: { display: false } }
      }
    }
  });
}

/*

*/

/*

*/