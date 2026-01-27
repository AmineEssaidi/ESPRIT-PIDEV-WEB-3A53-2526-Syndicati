# Create the main Twig base template
base_template = '''<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}Sneat - Dashboard{% endblock %}</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/sneat-admin.css') }}" rel="stylesheet">
    
    {% block stylesheets %}{% endblock %}
</head>
<body class="layout-menu-fixed">
    <div class="layout-wrapper layout-content-navbar">
        <!-- Sidebar -->
        {% include 'admin/_sidebar.html.twig' %}
        
        <div class="layout-page">
            <!-- Header -->
            {% include 'admin/_header.html.twig' %}
            
            <!-- Main Content -->
            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">
                    {% block content %}{% endblock %}
                </div>
                
                <!-- Footer -->
                {% include 'admin/_footer.html.twig' %}
                
                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>
    
    <div class="layout-overlay layout-menu-toggle"></div>
    
    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/sneat-admin.js') }}"></script>
    
    {% block javascripts %}{% endblock %}
</body>
</html>'''

# Write base template
with open('templates/base.html.twig', 'w') as f:
    f.write(base_template)
    
print("✓ Created templates/base.html.twig")