



# Syndicati – Modern Community & Admin Platform

Syndicati is a full-stack community and admin management platform developed by **The Horizon Group**. UI templates are designed by **Mohamed Amine Essaidi**. Syndicati provides a seamless experience for both end-users and administrators, combining a beautiful, interactive frontend with a powerful backend dashboard. Built with Symfony and Twig, it is designed for flexibility, extensibility, and a modern user experience.



## 🚀 Features

### Frontend (User-Facing)
- **Landing Page**: Beautiful, animated hero section welcoming users to Syndicati
- **User Profiles**: Rich profile pages with avatars, bios, skills, projects, and contact info
- **Community Directory**: Showcase of residents, teams, and projects
- **Contact & Support**: Modern contact forms, FAQ, and quick links for help
- **Theme & Language**: Dark/light mode toggle, multi-language support (i18n)
- **Responsive Design**: Mobile-first, works on all devices
- **Interactive UI**: Smooth animations, dynamic navigation, and notifications
- **Public Pages**: About, Our Team, Residence, Forum, Events, and more

### Backend (Admin & Management)
- **Admin Dashboard**: Powerful dashboard for managing users, residences, forums, and events
- **User Management**: Admin tools for managing residents, roles, and permissions
- **Content Management**: Manage community content, announcements, and documentation
- **Analytics & Charts**: Chart.js integration for visualizing data and activity
- **Settings**: Admin and user settings for customization
- **Security**: Symfony-based authentication and access control
- **Modular Structure**: Organized controllers, services, and repositories for easy extension

### Shared
- **Built with Symfony**: Modern PHP framework for robust backend
- **Twig Templates**: Modular, reusable, and maintainable UI
- **Bootstrap 5**: Consistent, modern CSS framework


## 📁 File Structure (Key Parts)

```
├── templates/
│   ├── base.html.twig                  # Main layout template
│   ├── base_symfony.html.twig          # Symfony base layout
│   ├── frontend/
│   │   ├── main-home.html.twig         # Landing page
│   │   ├── profile/profile.html.twig   # User profile page
│   │   ├── about/contact.html.twig     # Contact page
│   │   ├── about/our-team.html.twig    # Team page
│   │   ├── residence/index.html.twig   # Residence info
│   │   ├── forum/index.html.twig       # Community forum
│   │   ├── shared/                     # Shared UI components (navbar, footer, etc.)
│   └── admin/
│       ├── dashboard.html.twig         # Admin dashboard
│       ├── _sidebar.html.twig          # Sidebar navigation
│       ├── _header.html.twig           # Top navigation
│       └── _footer.html.twig           # Footer
├── src/Controller/
│   ├── AdminController.php             # Main admin controller
│   └── Frontend/                      # Frontend controllers (Forum, Residence, etc.)
├── public/
│   ├── css/
│   │   ├── syndicati-admin.css         # Admin/main stylesheet
│   │   └── main-home.css               # Frontend/landing page styles
│   └── js/
│       ├── syndicati-admin.js          # Admin dashboard JS
│       ├── main-home.js                # Frontend/landing page JS
│       └── global-settings.js          # Theme, language, and global UI logic
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
composer require symfony/framework-bundle
composer require symfony/twig-bundle
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

Syndicati uses CSS custom properties for easy theming. Modify the colors in `public/css/syndicati-admin.css`:

```css
:root {
	--bs-primary: #7367f0;      /* Main accent color */
	/* ... */
}
```


### File Modifications

- **Frontend Templates**: Edit Twig templates in `templates/frontend/` for landing, profile, contact, etc.
- **Admin Templates**: Edit Twig templates in `templates/admin/` for dashboard and management
- **Styles**: Edit `public/css/main-home.css` (frontend) and `public/css/syndicati-admin.css` (admin)
- **JavaScript**: Update `public/js/main-home.js` (frontend) and `public/js/syndicati-admin.js` (admin)
- **Controllers**: Modify `src/Controller/Frontend/` (frontend logic) and `src/Controller/AdminController.php` (admin logic)

### Adding Bootstrap Components

The design includes Bootstrap 5 classes. You can use any Bootstrap components:

```html
<button type="button" class="btn btn-primary">Primary Button</button>
```


## 📊 Charts Integration

Syndicati includes Chart.js for data visualization. To add new charts:

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

## 🌐 Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)


## 📝 License

Syndicati is developed by The Horizon Group. UI templates by Mohamed Amine Essaidi. All rights reserved.

## 🤝 Contributing

Feel free to customize and extend this dashboard according to your project needs. The modular structure makes it easy to add new features and components.


## 📞 Support

For support or inquiries, please contact The Horizon Group.

