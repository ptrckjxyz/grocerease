// Mock data manager (in-memory)
const storage = {
  purchases: [] // each: {item, category, qty, price, date}
};

// DOM references
const mostChartCtx = document.getElementById('mostChart').getContext('2d');
const purchaseTableBody = document.querySelector('#purchaseTable tbody');
const topList = document.getElementById('topList');
const addSampleBtn = document.getElementById('addSample');
const clearBtn = document.getElementById('clearData');

// Setup Chart.js bar chart (initially empty)
let mostChart = new Chart(mostChartCtx, {
  type: 'bar',
  data: {
    labels: [], // item names
    datasets: [{
      label: 'Quantity Purchased',
      data: [],
      borderRadius: 6,
      barThickness: 20
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { mode: 'index', intersect: false }
    },
    scales: {
      x: { ticks: { color: '#2b2b2b' } },
      y: { beginAtZero: true, ticks: { stepSize: 1 } }
    }
  }
});

// UTIL: render purchases table
function renderTable() {
  purchaseTableBody.innerHTML = '';
  if (storage.purchases.length === 0) {
    purchaseTableBody.innerHTML = '<tr class="muted-row"><td colspan="5">No purchases yet — add sample data to preview</td></tr>';
    return;
  }
  storage.purchases.slice().reverse().forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(p.item)}</td>
      <td>${escapeHtml(p.category)}</td>
      <td>${p.qty}</td>
      <td>₱${p.price.toFixed(2)}</td>
      <td>${p.date}</td>
    `;
    purchaseTableBody.appendChild(tr);
  });
}

// UTIL: update top purchased list and chart
function updateStats() {
  if (storage.purchases.length === 0) {
    topList.innerHTML = '<li class="muted-row">No items yet</li>';
    mostChart.data.labels = [];
    mostChart.data.datasets[0].data = [];
    mostChart.update();
    return;
  }

  // aggregate quantities by item
  const agg = {};
  storage.purchases.forEach(p => {
    const key = p.item;
    agg[key] = (agg[key] || 0) + p.qty;
  });

  // create sorted arrays
  const entries = Object.entries(agg).sort((a,b) => b[1]-a[1]);
  const top5 = entries.slice(0, 5);

  // update top list
  topList.innerHTML = '';
  top5.forEach(([item, qty]) => {
    const li = document.createElement('li');
    li.textContent = `${item} — ${qty}`;
    topList.appendChild(li);
  });

  // update chart
  mostChart.data.labels = top5.map(e => e[0]);
  mostChart.data.datasets[0].data = top5.map(e => e[1]);
  mostChart.update();
}

// Adds realistic sample data
function addSampleData() {
  const sample = [
    {item:'Rice (5kg)', category:'Staples', qty:2, price:250, date: '2025-10-01'},
    {item:'Eggs (dozen)', category:'Dairy', qty:3, price:85, date: '2025-10-02'},
    {item:'Chicken (kg)', category:'Meat', qty:2, price:180, date: '2025-10-03'},
    {item:'Banana (bunch)', category:'Fruit', qty:4, price:60, date: '2025-10-04'},
    {item:'Tomato (kg)', category:'Vegetable', qty:1, price:70, date: '2025-10-05'},
    {item:'Rice (5kg)', category:'Staples', qty:1, price:250, date: '2025-10-06'},
    {item:'Eggs (dozen)', category:'Dairy', qty:2, price:85, date: '2025-10-07'},
    {item:'Banana (bunch)', category:'Fruit', qty:2, price:60, date: '2025-10-07'},
    {item:'Milk (1L)', category:'Dairy', qty:1, price:95, date: '2025-10-08'},
  ];
  storage.purchases.push(...sample);
  renderTable();
  updateStats();
}

// Clear data
function clearData() {
  storage.purchases = [];
  renderTable();
  updateStats();
}

// small helper to prevent XSS in table
function escapeHtml(str){
  return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}

// NAV interaction (visual only for this front-end prototype)
document.querySelectorAll('.nav-item').forEach(a=>{
  a.addEventListener('click', (e)=>{
    e.preventDefault();
    document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
    a.classList.add('active');
    // (Optional) show toast or change content area - for prototype we keep static dashboard
  });
});

// bind buttons
addSampleBtn.addEventListener('click', addSampleData);
clearBtn.addEventListener('click', clearData);

// initialize empty render
renderTable();
updateStats();
