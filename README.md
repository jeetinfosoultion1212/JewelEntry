# JewelEntry - Jewelry Store Management System

A comprehensive web-based jewelry store management system built with PHP, MySQL, and modern web technologies.

## Features

- 📦 Inventory Management
- 💰 Sales & Billing
- 👥 Customer Management
- 📊 Analytics & Reporting
- 🔄 Gold Rate Management
- 🎯 Lucky Draw Schemes
- 💳 Gold Saving Plans
- 🔧 Repair Management
- 📱 Mobile Responsive Design

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for PHP dependencies)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/JewelEntry.git
cd JewelEntry
```

2. Install PHP dependencies:
```bash
composer install
```

3. Create a MySQL database and import the schema:
```bash
mysql -u your_username -p your_database < database/schema.sql
```

4. Copy the config template and update with your database credentials:
```bash
cp config/config.example.php config/config.php
```

5. Update the database configuration in `config/config.php` with your credentials.

6. Set up your web server (Apache/Nginx) to point to the project directory.

7. Access the application through your web browser.

## Configuration

- Database settings are in `config/config.php`
- Application settings can be modified in the admin panel
- Email settings for notifications
- SMS gateway configuration (if enabled)

## Security

- All passwords are hashed using secure algorithms
- SQL injection prevention using prepared statements
- XSS protection implemented
- CSRF protection enabled
- Session security measures in place

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, email support@jewelentry.com or join our WhatsApp support group.

## Acknowledgments

- Developed by Prosenjit Tech Hub
- Special thanks to all contributors
- Built with modern web technologies 