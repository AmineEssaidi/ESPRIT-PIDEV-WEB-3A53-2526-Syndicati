# Create the main dashboard template
dashboard_template = '''{% extends 'base.html.twig' %}

{% block title %}Dashboard - Sneat Admin{% endblock %}

{% block content %}
<div class="row">
    <!-- Congratulations card -->
    <div class="col-md-12 col-lg-8 col-xl-8 order-0 mb-4">
        <div class="card">
            <div class="d-flex align-items-end row">
                <div class="col-sm-7">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Congratulations John! 🎉</h5>
                        <p class="mb-4">
                            You have done <span class="fw-medium">72%</span> more sales today. Check your new raising badge in
                            your profile.
                        </p>
                        <a href="#" class="btn btn-sm btn-outline-primary">View Badges</a>
                    </div>
                </div>
                <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                        <img src="https://demos.themeselection.com/sneat-bootstrap-html-admin-template/assets/img/illustrations/man-with-laptop-light.png"
                             height="140" alt="View Badge User" data-app-dark-img="illustrations/man-with-laptop-dark.png"
                             data-app-light-img="illustrations/man-with-laptop-light.png"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profit card -->
    <div class="col-lg-4 col-md-4 order-1 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between">
                    <div class="avatar flex-shrink-0">
                        <img src="https://demos.themeselection.com/sneat-bootstrap-html-admin-template/assets/img/icons/unicons/chart-success.png" 
                             alt="chart success" class="rounded"/>
                    </div>
                    <div class="dropdown">
                        <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                            <a class="dropdown-item" href="#">View More</a>
                            <a class="dropdown-item" href="#">Delete</a>
                        </div>
                    </div>
                </div>
                <span>Profit</span>
                <h3 class="card-title mb-2">$12,628</h3>
                <small class="text-success fw-medium"><i class="bx bx-up-arrow-alt"></i> +72.80%</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sales & Transactions -->
    <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between">
                    <div class="avatar flex-shrink-0">
                        <img src="https://demos.themeselection.com/sneat-bootstrap-html-admin-template/assets/img/icons/unicons/wallet-info.png" 
                             alt="wallet info" class="rounded"/>
                    </div>
                    <div class="dropdown">
                        <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#">View More</a>
                            <a class="dropdown-item" href="#">Delete</a>
                        </div>
                    </div>
                </div>
                <span class="fw-medium d-block mb-1">Sales</span>
                <h3 class="card-title text-nowrap mb-1">$4,679</h3>
                <small class="text-success fw-medium"><i class="bx bx-up-arrow-alt"></i> +28.42%</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4 col-xl-4 order-1 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between">
                    <div class="avatar flex-shrink-0">
                        <img src="https://demos.themeselection.com/sneat-bootstrap-html-admin-template/assets/img/icons/unicons/paypal.png" 
                             alt="paypal" class="rounded"/>
                    </div>
                    <div class="dropdown">
                        <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#">View More</a>
                            <a class="dropdown-item" href="#">Delete</a>
                        </div>
                    </div>
                </div>
                <span class="fw-medium d-block mb-1">Payments</span>
                <h3 class="card-title text-nowrap mb-1">$2,468</h3>
                <small class="text-danger fw-medium"><i class="bx bx-down-arrow-alt"></i> -14.82%</small>
            </div>
        </div>
    </div>
    
    <!-- Total Revenue Chart -->
    <div class="col-12 col-lg-8 order-2 order-md-3 order-lg-2 mb-4">
        <div class="card">
            <div class="row row-bordered g-0">
                <div class="col-md-8">
                    <h5 class="card-header m-0 me-2 pb-3">Total Revenue</h5>
                    <div id="totalRevenueChart" class="px-2"></div>
                </div>
                <div class="col-md-4">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    2022
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#">2022</a></li>
                                    <li><a class="dropdown-item" href="#">2021</a></li>
                                    <li><a class="dropdown-item" href="#">2020</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex px-xxl-4 px-lg-2 p-4 gap-xxl-3 gap-lg-1 gap-3 justify-content-between">
                        <div class="d-flex">
                            <div class="me-2">
                                <span class="badge bg-label-primary p-2">
                                    <i class="bx bx-dollar text-primary"></i>
                                </span>
                            </div>
                            <div class="d-flex flex-column">
                                <small>2022</small>
                                <h6 class="mb-0">$32.5k</h6>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="me-2">
                                <span class="badge bg-label-info p-2">
                                    <i class="bx bx-wallet text-info"></i>
                                </span>
                            </div>
                            <div class="d-flex flex-column">
                                <small>2021</small>
                                <h6 class="mb-0">$41.2k</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Statistics -->
    <div class="col-md-6 col-lg-4 order-2 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between pb-0">
                <div class="card-title mb-0">
                    <h5 class="m-0 me-2">Order Statistics</h5>
                    <small class="text-muted">42.82k Total Sales</small>
                </div>
                <div class="dropdown">
                    <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                        <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#">View More</a>
                        <a class="dropdown-item" href="#">Delete</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex flex-column align-items-center gap-1">
                        <h2 class="mb-2">8,258</h2>
                        <span>Total Orders</span>
                    </div>
                    <div id="orderStatisticsChart"></div>
                </div>
                <ul class="p-0 m-0">
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-mobile-alt"></i>
                            </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Electronic</h6>
                                <small class="text-muted">Mobile, Earbuds, TV</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">82.5k</small>
                            </div>
                        </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-closet"></i>
                            </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Fashion</h6>
                                <small class="text-muted">T-shirt, Jeans, Shoes</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">23.8k</small>
                            </div>
                        </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-home-alt"></i>
                            </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Decor</h6>
                                <small class="text-muted">Fine Art, Dining</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">849</small>
                            </div>
                        </div>
                    </li>
                    <li class="d-flex">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-secondary">
                                <i class="bx bx-football"></i>
                            </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Sports</h6>
                                <small class="text-muted">Football, Cricket Kit</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">99</small>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Transactions -->
<div class="row">
    <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title m-0 me-2">Transactions</h5>
                <div class="dropdown">
                    <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                        <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#">View More</a>
                        <a class="dropdown-item" href="#">Delete</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <ul class="p-0 m-0">
                    {% for transaction in transactions %}
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <img src="https://demos.themeselection.com/sneat-bootstrap-html-admin-template/assets/img/icons/unicons/{{ transaction.icon }}.png" 
                                 alt="{{ transaction.type }}" class="rounded"/>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <small class="text-muted d-block mb-1">{{ transaction.type }}</small>
                                <h6 class="mb-0">{{ transaction.description }}</h6>
                            </div>
                            <div class="user-progress d-flex align-items-center gap-1">
                                <h6 class="mb-0">{{ transaction.amount }}</h6>
                                <span class="text-muted">{{ transaction.currency }}</span>
                            </div>
                        </div>
                    </li>
                    {% endfor %}
                </ul>
            </div>
        </div>
    </div>

    <!-- Income -->
    <div class="col-md-12 col-lg-8 col-xl-8 order-1 mb-4">
        <div class="card">
            <div class="d-flex align-items-center row">
                <div class="col-sm-7">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Total Income</h5>
                        <h2 class="mb-1">$459.1k</h2>
                        <p class="mb-0">
                            <span class="fw-medium me-2">$39k</span>
                            <small class="text-muted">less than last week</small>
                        </p>
                        <div class="mt-4">
                            <canvas id="incomeChart" width="400" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                        <img src="https://demos.themeselection.com/sneat-bootstrap-html-admin-template/assets/img/illustrations/card-advance-sale.png"
                             height="140" alt="view sales"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}

{% block javascripts %}
<script>
// Dashboard specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts here if needed
    initDashboardCharts();
});

function initDashboardCharts() {
    // Revenue Chart
    const revenueCtx = document.getElementById('totalRevenueChart');
    if (revenueCtx) {
        // Initialize ApexCharts or Chart.js here
    }
    
    // Order Statistics Chart
    const orderCtx = document.getElementById('orderStatisticsChart');
    if (orderCtx) {
        // Initialize donut chart here
    }
    
    // Income Chart
    const incomeCtx = document.getElementById('incomeChart');
    if (incomeCtx) {
        new Chart(incomeCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    data: [10, 20, 15, 25, 18, 30, 25],
                    borderColor: '#7367f0',
                    backgroundColor: 'rgba(115, 103, 240, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
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
                }
            }
        });
    }
}
</script>
{% endblock %}'''

# Write dashboard template
with open('templates/admin/dashboard.html.twig', 'w') as f:
    f.write(dashboard_template)
    
print("✓ Created templates/admin/dashboard.html.twig")