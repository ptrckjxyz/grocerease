// Data containers (front-end only). Start empty so you can add your own.
// You can modify these via the browser console, e.g.:
//   dashboardData.mostPurchased.push({ name: 'Rice', qty: 10 })
//   dashboardData.waste.push({ name: 'Tomatoes', cost: 2.75 })
const data = {
  mostPurchased: [],
  leastPurchased: [],
  waste: [],
};

function formatCurrency(n) {
  return `$${n.toFixed(2)}`;
}

function renderList(listEl, items, valueKey, isCurrency = false) {
  listEl.innerHTML = '';
  if (!items || items.length === 0) {
    const li = document.createElement('li');
    li.className = 'empty';
    li.textContent = 'No items yet';
    listEl.appendChild(li);
    return;
  }
  items.forEach((item) => {
    const li = document.createElement('li');
    const key = document.createElement('span');
    const val = document.createElement('span');
    key.className = 'key';
    val.className = 'val';
    key.textContent = item.name;
    const v = item[valueKey];
    val.textContent = isCurrency ? formatCurrency(Number(v)) : v;
    li.appendChild(key);
    li.appendChild(val);
    listEl.appendChild(li);
  });
}

function calcWasteTotal(items) {
  return items.reduce((acc, it) => acc + Number(it.cost || 0), 0);
}

function initDashboard() {
  const mostList = document.getElementById('mostList');
  const leastList = document.getElementById('leastList');
  const wasteList = document.getElementById('wasteList');
  const wasteTotal = document.getElementById('wasteTotal');
  const logoutBtn = document.getElementById('logoutBtn');
  const modal = document.getElementById('logoutModal');
  const cancelBtn = document.getElementById('logoutCancel');
  const confirmBtn = document.getElementById('logoutConfirm');

  // Initial render
  renderList(mostList, data.mostPurchased, 'qty');
  renderList(leastList, data.leastPurchased, 'qty');
  renderList(wasteList, data.waste, 'cost', true);
  wasteTotal.textContent = formatCurrency(calcWasteTotal(data.waste));

  // Sorting actions
  document.querySelector('[data-action="sort-most"]').addEventListener('click', () => {
    data.mostPurchased.sort((a, b) => b.qty - a.qty);
    renderList(mostList, data.mostPurchased, 'qty');
  });

  document.querySelector('[data-action="sort-least"]').addEventListener('click', () => {
    data.leastPurchased.sort((a, b) => a.qty - b.qty);
    renderList(leastList, data.leastPurchased, 'qty');
  });

  // Menu active state (front-end only)
  document.querySelectorAll('.menu-item').forEach((a) => {
    a.addEventListener('click', (e) => {
      // Keep links that are real navigations (e.g., Logout to login.html)
      if (a.getAttribute('href') === '#') e.preventDefault();
      document.querySelectorAll('.menu-item').forEach((x) => x.classList.remove('active'));
      a.classList.add('active');
    });
  });

  // Logout modal
  function showModal() {
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
  }
  function hideModal() {
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
  }
  logoutBtn.addEventListener('click', showModal);
  cancelBtn.addEventListener('click', hideModal);
  modal.querySelector('[data-close]').addEventListener('click', hideModal);
  confirmBtn.addEventListener('click', () => {
    // Front-end only: navigate back to login page
    window.location.href = 'login.html';
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideModal(); });

  // Expose a handle for quick manual edits during prototyping
  window.dashboardData = data;
}

// Boot
window.addEventListener('DOMContentLoaded', initDashboard);
