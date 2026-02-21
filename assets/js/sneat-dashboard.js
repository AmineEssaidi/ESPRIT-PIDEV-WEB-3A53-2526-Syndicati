// Dashboard Application JavaScript

// Application Data
const dashboardData = {
  "kpis": {
    "profit": {
      "value": "$12,628",
      "change": "72.8%",
      "trend": "up"
    },
    "sales": {
      "value": "$4,679",
      "change": "28.42%",
      "trend": "up"
    },
    "payments": {
      "value": "$2,468",
      "change": "-14.82%",
      "trend": "down"
    },
    "transactions": {
      "value": "$14,857",
      "change": "28.14%",
      "trend": "up"
    }
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

// DOM Content Loaded Event
document.addEventListener('DOMContentLoaded', function () {
  initializeCharts();
  initializeMobileMenu();
  initializeMenuToggle();
  populateTransactions();
  initializeMenuInteractions();
});

// Initialize all charts
function initializeCharts() {
  initializeRevenueChart();
  initializeOrderChart();
  initializeIncomeChart();
}

// Revenue Chart
function initializeRevenueChart() {
  const ctx = document.getElementById('revenueChart');
  if (!ctx) return;

  const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
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
        pointRadius: 0,
        pointHoverRadius: 8,
        pointHoverBackgroundColor: '#7367F0',
        pointHoverBorderColor: '#ffffff',
        pointHoverBorderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        x: {
          grid: {
            display: false
          },
          ticks: {
            color: '#A5ACB8',
            font: {
              size: 12
            }
          }
        },
        y: {
          grid: {
            color: '#3A3E5C',
            borderDash: [3, 3]
          },
          ticks: {
            color: '#A5ACB8',
            font: {
              size: 12
            },
            callback: function (value) {
              return value + 'k';
            }
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index'
      },
      elements: {
        point: {
          hoverRadius: 8
        }
      }
    }
  });
}

// Order Statistics Chart (Donut)
function initializeOrderChart() {
  const ctx = document.getElementById('orderChart');
  if (!ctx) return;

  orderChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Electronic', 'Fashion', 'Decor', 'Sports'],
      datasets: [{
        data: [82.5, 23.8, 8.4, 1.0],
        backgroundColor: ['#1FB8CD', '#FFC185', '#B4413C', '#5D878F'],
        borderWidth: 0,
        cutout: '75%'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.label + ': ' + context.parsed + 'k';
            }
          }
        }
      }
    }
  });
}

// Income Chart (Small Line Chart)
function initializeIncomeChart() {
  const ctx = document.getElementById('incomeChart');
  if (!ctx) return;

  const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 80);
  gradient.addColorStop(0, 'rgba(115, 103, 240, 0.3)');
  gradient.addColorStop(1, 'rgba(115, 103, 240, 0.05)');

  incomeChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [{
        data: dashboardData.income_data.chart_data,
        borderColor: '#7367F0',
        backgroundColor: gradient,
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 0,
        pointHoverRadius: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          enabled: false
        }
      },
      scales: {
        x: {
          display: false
        },
        y: {
          display: false
        }
      },
      interaction: {
        intersect: false
      },
      elements: {
        point: {
          hoverRadius: 0
        }
      }
    }
  });
}

// Mobile Menu Functionality
function initializeMobileMenu() {
  const mobileToggle = document.querySelector('.mobile-menu-toggle');
  const sidebar = document.querySelector('.layout-menu');
  const overlay = document.createElement('div');

  overlay.className = 'layout-overlay';
  document.body.appendChild(overlay);

  if (mobileToggle) {
    mobileToggle.addEventListener('click', function (e) {
      e.preventDefault();
      toggleMobileMenu();
    });
  }

  // Close menu when clicking overlay
  overlay.addEventListener('click', function () {
    closeMobileMenu();
  });

  // Close menu on window resize if desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth > 991) {
      closeMobileMenu();
    }
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

// Desktop Menu Toggle
function initializeMenuToggle() {
  const menuToggle = document.querySelector('.layout-menu-toggle:not(.mobile-menu-toggle)');

  if (menuToggle) {
    menuToggle.addEventListener('click', function (e) {
      e.preventDefault();
      // Add collapsed state functionality here if needed
    });
  }
}

// Populate Transactions List
function populateTransactions() {
  const transactionList = document.getElementById('transactionList');
  if (!transactionList) return;

  transactionList.innerHTML = '';

  dashboardData.transactions.forEach(transaction => {
    const listItem = document.createElement('li');
    const isPositive = transaction.amount.startsWith('+');

    listItem.innerHTML = `
      <div class="transaction-icon ${transaction.icon}">
        ${getTransactionIcon(transaction.icon)}
      </div>
      <div class="transaction-details">
        <div class="transaction-type">${transaction.type}</div>
        <div class="transaction-desc">${transaction.description}</div>
      </div>
      <div class="transaction-amount ${isPositive ? 'positive' : 'negative'}">
        ${transaction.amount}
      </div>
    `;

    transactionList.appendChild(listItem);
  });
}

// Get transaction icon based on type
function getTransactionIcon(iconType) {
  const icons = {
    'paypal': '<i class="fab fa-paypal"></i>',
    'wallet': '<i class="fas fa-wallet"></i>',
    'transfer': '<i class="fas fa-exchange-alt"></i>',
    'credit-card': '<i class="fas fa-credit-card"></i>',
    'mastercard': '<i class="fab fa-cc-mastercard"></i>'
  };

  return icons[iconType] || '<i class="fas fa-dollar-sign"></i>';
}

// Menu Interactions
function initializeMenuInteractions() {
  const menuItems = document.querySelectorAll('.menu-item');

  menuItems.forEach(item => {
    const menuLink = item.querySelector('.menu-link');
    const menuSub = item.querySelector('.menu-sub');

    if (menuLink && menuSub) {
      menuLink.addEventListener('click', function (e) {
        e.preventDefault();

        // Close other open menus
        menuItems.forEach(otherItem => {
          if (otherItem !== item) {
            otherItem.classList.remove('open');
          }
        });

        // Toggle current menu
        item.classList.toggle('open');
      });
    }

    // Handle sub-menu item clicks
    const subMenuLinks = item.querySelectorAll('.menu-sub .menu-link');
    subMenuLinks.forEach(subLink => {
      subLink.addEventListener('click', function (e) {
        e.preventDefault();

        // Remove active class from all menu items
        document.querySelectorAll('.menu-item').forEach(menuItem => {
          menuItem.classList.remove('active');
        });

        // Add active class to parent menu item
        item.classList.add('active');

        // Close mobile menu if open
        if (window.innerWidth <= 991) {
          closeMobileMenu();
        }
      });
    });
  });
}

// Utility Functions
function formatNumber(num) {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M';
  } else if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K';
  }
  return num.toString();
}

function formatCurrency(amount, currency = 'USD') {
  const formatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  });
  return formatter.format(amount);
}

// Safe Event Listeners
document.addEventListener('change', function (e) {
  if (e.target && typeof e.target.matches === 'function' && e.target.matches('select.form-select')) {
    const selectedYear = e.target.value;
    updateRevenueChart(selectedYear);
  }
});

function updateRevenueChart(year) {
  if (!revenueChart) return;

  const data = year === '2023' ? dashboardData.revenue_chart.year_2023 : dashboardData.revenue_chart.year_2024;
  revenueChart.data.datasets[0].data = data;
  revenueChart.data.datasets[0].label = year;
  revenueChart.update('active');
}

// Smooth scrolling for anchor links
document.addEventListener('click', function (e) {
  const target = e.target;
  if (target && typeof target.matches === 'function' && target.matches('a[href^="#"]')) {
    const href = target.getAttribute('href');
    if (href === '#') return;

    e.preventDefault();
    const targetEl = document.querySelector(href);
    if (targetEl) {
      targetEl.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  }
});

// Card hover effects
document.addEventListener('mouseenter', function (e) {
  const target = e.target;
  if (target && typeof target.matches === 'function' && (target.matches('.card') || target.closest('.card'))) {
    const card = target.matches('.card') ? target : target.closest('.card');
    if (card) card.style.transform = 'translateY(-2px)';
  }
}, true);

document.addEventListener('mouseleave', function (e) {
  const target = e.target;
  if (target && typeof target.matches === 'function' && (target.matches('.card') || target.closest('.card'))) {
    const card = target.matches('.card') ? target : target.closest('.card');
    if (card) card.style.transform = 'translateY(0)';
  }
}, true);

// Loading animation for charts
function showChartLoader(containerId) {
  const container = document.getElementById(containerId);
  if (container) {
    container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  }
}

// Error handling for charts
Chart.defaults.plugins.legend.onClick = function (e, legendItem) {
  // Custom legend click handler
};

// Responsive chart updates
window.addEventListener('resize', function () {
  if (revenueChart) revenueChart.resize();
  if (orderChart) orderChart.resize();
  if (incomeChart) incomeChart.resize();
});

// Performance optimization - Intersection Observer for charts
if ('IntersectionObserver' in window) {
  const chartObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const chartId = entry.target.id;
        if (chartId === 'revenueChart' && !revenueChart) {
          initializeRevenueChart();
        } else if (chartId === 'orderChart' && !orderChart) {
          initializeOrderChart();
        } else if (chartId === 'incomeChart' && !incomeChart) {
          initializeIncomeChart();
        }
      }
    });
  }, { threshold: 0.1 });

  document.addEventListener('DOMContentLoaded', function () {
    const charts = document.querySelectorAll('canvas[id$="Chart"]');
    charts.forEach(chart => chartObserver.observe(chart));
  });
}

// Export functions for potential use
window.DashboardApp = {
  initializeCharts,
  toggleMobileMenu,
  closeMobileMenu,
  updateRevenueChart,
  formatNumber,
  formatCurrency
};