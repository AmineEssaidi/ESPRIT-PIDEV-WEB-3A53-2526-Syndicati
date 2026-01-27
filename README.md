# Sneat Admin Dashboard for Symfony

A 1:1 recreation of the popular Sneat Vuetify admin dashboard template, adapted for Symfony projects with Twig templating.

## 🚀 Features

- **Exact Visual Recreation**: Matches the original Sneat dashboard design
- **Dark Theme**: Modern dark theme with purple accent colors
- **Responsive Layout**: Mobile-first design that works on all devices
- **Interactive Charts**: Chart.js integration for data visualization
- **Symfony Compatible**: Built specifically for Symfony projects
- **Twig Templates**: Modular template structure following Symfony best practices
- **Bootstrap 5**: Modern CSS framework for consistent styling

## 📁 File Structure

```
├── templates/
│   ├── base.html.twig              # Main layout template
│   └── admin/
│       ├── dashboard.html.twig     # Dashboard page
│       ├── _sidebar.html.twig      # Sidebar navigation
│       ├── _header.html.twig       # Top navigation
│       └── _footer.html.twig       # Footer
├── src/Controller/
│   └── AdminController.php         # Main admin controller
├── public/
│   ├── css/
│   │   └── sneat-admin.css         # Main stylesheet
│   └── js/
│       └── sneat-admin.js          # Dashboard JavaScript
```

## 🛠️ Installation

### 1. Copy Files to Your Symfony Project

Copy all the generated files to your existing Symfony project:

```bash
# Copy templates
cp -r templates/* /path/to/your/symfony/project/templates/

# Copy controller
cp -r src/* /path/to/your/symfony/project/src/

# Copy public assets
cp -r public/* /path/to/your/symfony/project/public/
```

### 2. Install Dependencies

Make sure you have the required dependencies in your Symfony project:

```bash
# Install Symfony framework bundle (if not already installed)
composer require symfony/framework-bundle

# Install Twig bundle (if not already installed)
composer require symfony/twig-bundle

# Install asset component (for asset() function)
composer require symfony/asset
```

### 3. Configure Routes

The controller uses PHP 8 attributes for routing. Make sure your `routes.yaml` is configured to auto-discover routes:

```yaml
# config/routes.yaml
controllers:
    resource: ../src/Controller/
    type: attribute
```

### 4. Configure Assets (Optional)

If you're using Webpack Encore, you can optimize the assets:

```bash
npm install --save-dev @symfony/webpack-encore
npm install --save chart.js
```

Then include the assets in your `webpack.config.js`:

```javascript
// webpack.config.js
Encore
    .addEntry('admin', './assets/admin.js')
    .addStyleEntry('admin-css', './assets/admin.css')
```

## 🎨 Customization

### Changing Colors

The design uses CSS custom properties for easy theming. Modify the colors in `public/css/sneat-admin.css`:

```css
:root {
  --bs-primary: #7367f0;      /* Main accent color */
  --bs-menu-bg: #2b2c40;      /* Sidebar background */
  --bs-body-bg: #f5f5f9;      /* Page background */
  /* ... more variables ... */
}
```

### Adding New Menu Items

Edit `templates/admin/_sidebar.html.twig` to add new navigation items:

```twig
<li class="menu-item">
    <a href="{{ path('your_route') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-your-icon"></i>
        <div>Your Menu Item</div>
    </a>
</li>
```

### Creating New Pages

1. Create a new template in `templates/admin/`
2. Extend the base template:

```twig
{% extends 'base.html.twig' %}

{% block title %}Your Page Title{% endblock %}

{% block content %}
    <!-- Your content here -->
{% endblock %}
```

3. Add a new route in your controller:

```php
#[Route('/admin/your-page', name: 'admin_your_page')]
public function yourPage(): Response
{
    return $this->render('admin/your_page.html.twig');
}
```

## 📊 Charts Integration

The dashboard includes Chart.js for data visualization. To add new charts:

1. Add a canvas element in your template:
```html
<canvas id="myChart" width="400" height="200"></canvas>
```

2. Initialize the chart in JavaScript:
```javascript
const ctx = document.getElementById('myChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        // Your chart data
    },
    options: {
        // Your chart options
    }
});
```

## 🔧 Development

### File Modifications

- **Templates**: Modify Twig templates in `templates/admin/`
- **Styles**: Edit `public/css/sneat-admin.css` for styling changes
- **JavaScript**: Update `public/js/sneat-admin.js` for interactive features
- **Controller**: Modify `src/Controller/AdminController.php` for backend logic

### Adding Bootstrap Components

The design includes Bootstrap 5 classes. You can use any Bootstrap components:

```html
<div class="alert alert-success" role="alert">
    Success message
</div>

<button type="button" class="btn btn-primary">
    Primary Button
</button>
```

## 🌐 Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## 📝 License

This is a free recreation of the Sneat admin template for Symfony projects. The original Sneat template is created by ThemeSelection.

## 🤝 Contributing

Feel free to customize and extend this dashboard according to your project needs. The modular structure makes it easy to add new features and components.

## 📞 Support

This is a template recreation. For issues with the original Sneat template, visit [ThemeSelection](https://themeselection.com).
