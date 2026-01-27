# Create the main JavaScript file (simplified version)
js_content = '''/*
 * Sneat Admin Dashboard JavaScript
 * Handles menu interactions, charts, and responsive behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeMenu();
    initializeCharts();
    initializeDropdowns();
});

// Menu functionality
function initializeMenu() {
    const menuToggle = document.querySelector('.layout-menu-toggle');
    const layoutMenu = document.querySelector('.layout-menu');
    const layoutOverlay = document.querySelector('.layout-overlay');
    
    // Mobile menu toggle
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            layoutMenu.classList.toggle('show');
            layoutOverlay.classList.toggle('show');
        });
    }
    
    // Close menu on overlay click
    if (layoutOverlay) {
        layoutOverlay.addEventListener('click', function() {
            layoutMenu.classList.remove('show');
            layoutOverlay.classList.remove('show');
        });
    }
    
    // Handle menu item clicks
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        const menuLink = item.querySelector('.menu-link');
        if (menuLink && !menuLink.classList.contains('menu-toggle')) {
            menuLink.addEventListener('click', function() {
                // Remove active class from all menu items
                menuItems.forEach(mi => mi.classList.remove('active'));
                // Add active class to clicked item
                item.classList.add('active');
            });
        }
    });
    
    // Handle submenu toggles
    const menuToggles = document.querySelectorAll('.menu-toggle');
    menuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const menuItem = this.closest('.menu-item');
            const submenu = menuItem.querySelector('.menu-sub');
            
            if (submenu) {
                const isOpen = menuItem.classList.contains('open');
                
                // Close all other submenus
                menuToggles.forEach(t => {
                    const mi = t.closest('.menu-item');
                    if (mi !== menuItem) {
                        mi.classList.remove('open');
                        const sm = mi.querySelector('.menu-sub');
                        if (sm) sm.style.display = 'none';
                    }
                });
                
                // Toggle current submenu
                if (isOpen) {
                    menuItem.classList.remove('open');
                    submenu.style.display = 'none';
                } else {
                    menuItem.classList.add('open');
                    submenu.style.display = 'block';
                }
            }
        });
    });
}

// Chart initialization
function initializeCharts() {
    // Revenue Chart
    const revenueCanvas = document.getElementById('totalRevenueChart');
    if (revenueCanvas && typeof Chart !== 'undefined') {
        const revenueCtx = revenueCanvas.getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: '2024',
                    data: [20, 10, 30, 15, 25, 20, 30],
                    borderColor: '#7367f0',
                    backgroundColor: 'rgba(115, 103, 240, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: '2023',
                    data: [15, 8, 25, 12, 20, 15, 25],
                    borderColor: '#a8aaae',
                    backgroundColor: 'rgba(168, 170, 174, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(168, 170, 174, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 6
                    }
                }
            }
        });
    }
    
    // Order Statistics Chart (Donut)
    const orderCanvas = document.getElementById('orderStatisticsChart');
    if (orderCanvas && typeof Chart !== 'undefined') {
        const orderCtx = orderCanvas.getContext('2d');
        new Chart(orderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Electronic', 'Fashion', 'Decor', 'Sports'],
                datasets: [{
                    data: [85, 15, 50, 25],
                    backgroundColor: [
                        '#7367f0',
                        '#71dd37',
                        '#03c3ec',
                        '#8a8d93'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Income Chart
    const incomeCanvas = document.getElementById('incomeChart');
    if (incomeCanvas && typeof Chart !== 'undefined') {
        const incomeCtx = incomeCanvas.getContext('2d');
        new Chart(incomeCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    data: [10, 20, 15, 25, 18, 30, 25],
                    borderColor: '#7367f0',
                    backgroundColor: 'rgba(115, 103, 240, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4
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
                        display: false
                    },
                    y: {
                        display: false
                    }
                },
                elements: {
                    line: {
                        borderWidth: 2
                    }
                }
            }
        });
    }
}

// Dropdown functionality
function initializeDropdowns() {
    const dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.nextElementSibling;
            if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(dd => {
                    if (dd !== dropdown) {
                        dd.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                dropdown.classList.toggle('show');
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
}

// Utility functions
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

function formatPercentage(value) {
    return `${value > 0 ? '+' : ''}${value}%`;
}

// Export functions for global access
window.SneatAdmin = {
    initializeMenu,
    initializeCharts,
    initializeDropdowns,
    formatCurrency,
    formatPercentage
};'''

# Write JS file
with open('public/js/sneat-admin.js', 'w') as f:
    f.write(js_content)
    
print("✓ Created public/js/sneat-admin.js")