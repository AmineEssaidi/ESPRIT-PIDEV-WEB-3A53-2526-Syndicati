# Create the sidebar partial template
sidebar_template = '''<!-- Sidebar -->
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <!-- App Brand -->
    <div class="app-brand demo">
        <a href="{{ path('admin_dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <svg width="25" viewBox="0 0 25 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13.7918 6.52326V41.5835H25V0H13.7918V6.52326Z" fill="#7367F0"/>
                    <path d="M11.2082 0H0V35.0618H11.2082V0Z" fill="#7367F0"/>
                </svg>
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-2">Sneat</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <!-- Dashboards -->
        <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div data-i18n="Dashboards">Dashboards</div>
                <div class="badge badge-center rounded-pill bg-danger w-px-20 h-px-20">5</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item active">
                    <a href="{{ path('admin_dashboard') }}" class="menu-link">
                        <div data-i18n="Analytics">Analytics</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div data-i18n="CRM">CRM</div>
                        <div class="badge badge-center rounded-pill bg-label-primary ms-auto">Pro</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div data-i18n="eCommerce">eCommerce</div>
                        <div class="badge badge-center rounded-pill bg-label-primary ms-auto">Pro</div>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Front Pages -->
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-store"></i>
                <div data-i18n="Front Pages">Front Pages</div>
                <div class="badge badge-center rounded-pill bg-label-primary ms-auto">Pro</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item">
                    <a href="#" class="menu-link" target="_blank">
                        <div data-i18n="Landing">Landing</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link" target="_blank">
                        <div data-i18n="Pricing">Pricing</div>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Apps & Pages -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Apps &amp; Pages</span>
        </li>
        <li class="menu-item">
            <a href="#" class="menu-link">
                <i class="menu-icon tf-icons bx bx-envelope"></i>
                <div data-i18n="Email">Email</div>
                <div class="badge badge-center rounded-pill bg-label-primary ms-auto">Pro</div>
            </a>
        </li>
        <li class="menu-item">
            <a href="#" class="menu-link">
                <i class="menu-icon tf-icons bx bx-chat"></i>
                <div data-i18n="Chat">Chat</div>
                <div class="badge badge-center rounded-pill bg-label-primary ms-auto">Pro</div>
            </a>
        </li>
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div data-i18n="Users">Users</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div data-i18n="List">List</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div data-i18n="View">View</div>
                    </a>
                </li>
            </ul>
        </li>

        <!-- User Interface -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">User Interface</span>
        </li>
        <li class="menu-item">
            <a href="#" class="menu-link">
                <i class="menu-icon tf-icons bx bx-box"></i>
                <div data-i18n="Basic">Basic</div>
            </a>
        </li>
        <li class="menu-item">
            <a href="#" class="menu-link">
                <i class="menu-icon tf-icons bx bx-collection"></i>
                <div data-i18n="Tables">Tables</div>
            </a>
        </li>
    </ul>
</aside>'''

# Write sidebar template
with open('templates/admin/_sidebar.html.twig', 'w') as f:
    f.write(sidebar_template)
    
print("✓ Created templates/admin/_sidebar.html.twig")