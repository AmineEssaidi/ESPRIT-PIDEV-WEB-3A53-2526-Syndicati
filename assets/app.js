// Dashboard Application JavaScript

// Application Data
const dashboardData = {
  "kpis": {
    "profit": { "value": "$12,628", "change": "72.8%", "trend": "up" },
    "sales": { "value": "$4,679", "change": "28.42%", "trend": "up" },
    "payments": { "value": "$2,468", "change": "-14.82%", "trend": "down" },
    "transactions": { "value": "$14,857", "change": "28.14%", "trend": "up" }
  },
  "revenue_chart": {
    "labels": ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul"],
    "data": [20, 10, 30, 15, 25, 20, 30],
    "year_2024": [20, 10, 30, 15, 25, 20, 30],
    "year_2023": [15, 8, 25, 12, 20, 15, 25]
  },
  "income_data": {
    "chart_data": [10, 20, 15, 25, 18, 30, 25]
  },
  "transactions": [
    { "type": "PayPal", "description": "Send money", "amount": "+$82.6", "currency": "USD", "icon": "paypal" },
    { "type": "Wallet", "description": "Mac'D", "amount": "+$270.69", "currency": "USD", "icon": "wallet" },
    { "type": "Transfer", "description": "Refund", "amount": "+$637.91", "currency": "USD", "icon": "transfer" },
    { "type": "Credit Card", "description": "Ordered Food", "amount": "-$838.71", "currency": "USD", "icon": "credit-card" },
    { "type": "Wallet", "description": "Starbucks", "amount": "+$203.33", "currency": "USD", "icon": "wallet" },
    { "type": "Mastercard", "description": "Ordered Food", "amount": "-$92.45", "currency": "USD", "icon": "mastercard" }
  ]
};

// Chart instances
let revenueChart = null;
let orderChart = null;
let incomeChart = null;

// Shared date/time update
function updateCurrentDateTime() {
  const dateElement = document.getElementById('currentDate');
  const timeElement = document.getElementById('currentTime');
  if (dateElement) {
    const now = new Date();
    const formattedDate = now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' });
    dateElement.textContent = formattedDate;
  }
  if (timeElement) {
    const now = new Date();
    const formattedTime = now.toLocaleTimeString('en-US', { hour12: false });
    timeElement.textContent = formattedTime;
  }
}

document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart !== 'undefined') {
    initializeCharts();
  }
  initializeMobileMenu();
  initializeMenuToggle();
  populateTransactions();
  initializeMenuInteractions();
  updateCurrentDateTime();
  setInterval(updateCurrentDateTime, 1000);
});

function initializeCharts() {
  initializeRevenueChart();
  initializeOrderChart();
  initializeIncomeChart();
}

function initializeRevenueChart() {
  const ctx = document.getElementById('revenueChart');
  if (!ctx || typeof Chart === 'undefined') return;
  const context = ctx.getContext('2d');
  const gradient = context.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(115, 103, 240, 0.2)');
  gradient.addColorStop(1, 'rgba(115, 103, 240, 0.02)');
  revenueChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: dashboardData.revenue_chart.labels,
      datasets: [{
        label: '2024',
        data: dashboardData.revenue_chart.year_2024,
        borderColor: '#7367F0',
        backgroundColor: gradient,
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 0
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
  });
}

function initializeOrderChart() {
  const ctx = document.getElementById('orderChart');
  if (!ctx || typeof Chart === 'undefined') return;
  orderChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Electronic', 'Fashion', 'Decor'],
      datasets: [{ data: [300, 50, 100], backgroundColor: ['#7367F0', '#00CFE8', '#FF9F43'], borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false } } }
  });
}

function initializeIncomeChart() {
  const ctx = document.getElementById('incomeChart');
  if (!ctx || typeof Chart === 'undefined') return;
  incomeChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
      datasets: [{ data: dashboardData.income_data.chart_data, backgroundColor: '#7367F0', borderRadius: 5, barThickness: 10 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } }
  });
}

function initializeMobileMenu() {
  const mobileToggle = document.querySelector('.mobile-menu-toggle');
  const existingOverlay = document.querySelector('.layout-overlay');

  if (!existingOverlay) {
    const overlay = document.createElement('div');
    overlay.className = 'layout-overlay';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', closeMobileMenu);
  } else {
    existingOverlay.addEventListener('click', closeMobileMenu);
  }

  if (mobileToggle) {
    mobileToggle.addEventListener('click', function (e) {
      e.preventDefault();
      toggleMobileMenu();
    });
  }

  window.addEventListener('resize', function () {
    if (window.innerWidth > 991) closeMobileMenu();
  });
}

function toggleMobileMenu() {
  const sidebar = document.querySelector('.layout-menu');
  const overlay = document.querySelector('.layout-overlay');
  if (!sidebar || !overlay) return;
  sidebar.classList.toggle('show');
  overlay.classList.toggle('show');
  document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
}

function closeMobileMenu() {
  const sidebar = document.querySelector('.layout-menu');
  const overlay = document.querySelector('.layout-overlay');
  if (sidebar) sidebar.classList.remove('show');
  if (overlay) overlay.classList.remove('show');
  document.body.style.overflow = '';
}

function initializeMenuToggle() {
  const menuToggle = document.querySelector('.menu-toggle');
  if (menuToggle) {
    menuToggle.addEventListener('click', function (e) {
      e.preventDefault();
      document.body.classList.toggle('layout-menu-expanded');
    });
  }
}

function populateTransactions() {
  const container = document.getElementById('transactionsList');
  if (!container) return;
  container.innerHTML = dashboardData.transactions.map(t => `
    <div class="transaction-item d-flex align-items-center mb-3">
      <div class="avatar flex-shrink-0 me-3">
        <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-${t.icon}"></i></span>
      </div>
      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
        <div class="me-2">
          <h6 class="mb-0">${t.type}</h6>
          <small class="text-muted">${t.description}</small>
        </div>
        <div class="user-progress d-flex align-items-center gap-1">
          <h6 class="mb-0">${t.amount}</h6>
          <span class="text-muted">${t.currency}</span>
        </div>
      </div>
    </div>
  `).join('');
}

function initializeMenuInteractions() {
  const menuItems = document.querySelectorAll('.menu-item');
  menuItems.forEach(item => {
    item.addEventListener('mouseenter', function () {
      if (!document.body.classList.contains('layout-menu-collapsed')) {
        const subMenu = this.querySelector('.menu-sub');
        if (subMenu) subMenu.style.display = 'block';
      }
    });
    item.addEventListener('mouseleave', function () {
      const subMenu = this.querySelector('.menu-sub');
      if (subMenu) subMenu.style.display = 'none';
    });
  });
}

// Safe Event Listeners
document.addEventListener('change', function (e) {
  if (e.target instanceof Element && e.target.matches('select.form-select')) {
    updateRevenueChart(e.target.value);
  }
});

function updateRevenueChart(year) {
  if (!revenueChart) return;
  const data = year === '2023' ? dashboardData.revenue_chart.year_2023 : dashboardData.revenue_chart.year_2024;
  revenueChart.data.datasets[0].data = data;
  revenueChart.data.datasets[0].label = year;
  revenueChart.update();
}

document.addEventListener('click', function (e) {
  if (e.target instanceof Element && e.target.matches('a[href^="#"]')) {
    const href = e.target.getAttribute('href');
    if (href === '#') return;
    const target = document.querySelector(href);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
});

document.addEventListener('mouseenter', function (e) {
  if (e.target instanceof Element && (e.target.matches('.card') || e.target.closest('.card'))) {
    const card = e.target.matches('.card') ? e.target : e.target.closest('.card');
    card.style.transform = 'translateY(-2px)';
  }
}, true);

document.addEventListener('mouseleave', function (e) {
  if (e.target instanceof Element && (e.target.matches('.card') || e.target.closest('.card'))) {
    const card = e.target.matches('.card') ? e.target : e.target.closest('.card');
    card.style.transform = 'translateY(0)';
  }
}, true);