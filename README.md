# 🏠 Sophen Residence System

A digital solution for managing off-campus student residences, replacing manual logbooks with an efficient web-based system.

## 🎯 What It Does

This system helps off-campus residences manage:
- Student and visitor check-ins/check-outs
- Security monitoring and alerts
- Visitor tracking and blocking
- Automated email notifications
- PDF report generation

## 🛠️ Built With

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Server**: XAMPP (Apache + MySQL)

## 📋 Prerequisites

- XAMPP (version 8.0 or higher)
- Web browser (Chrome, Firefox, Edge)
- Text editor (VS Code, Sublime, etc.)

## ⚡ Quick Start

### 1. Install XAMPP
Download and install from [apachefriends.org](https://www.apachefriends.org/)

### 2. Clone/Download Project
```bash
# Place project in XAMPP directory
C:\xampp\htdocs\sophen-residence-system
```

### 3. Start XAMPP
- Open XAMPP Control Panel
- Start **Apache**
- Start **MySQL**

### 4. Create Database
1. Open browser: `http://localhost/phpmyadmin`
2. Create new database: `sophen_residence`
3. Import file: `database/sophen_residence.sql`

### 5. Configure Database
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sophen_residence');
```

### 6. Access System
Open browser: `http://localhost/sophen-residence-system`

## 👥 Default Login

**Administrator**
- Username: `admin`
- Password: `password`

**Security**
- Username: `security`
- Password: `security123`

⚠️ **Change these passwords after first login!**

## 📁 Project Structure

```
sophen-residence-system/
├── admin/              # Admin dashboard
├── security/           # Security dashboard
├── visitor/            # Visitor check-in/out
├── config/             # Database & email config
├── includes/           # Reusable PHP files
├── assets/             # CSS, JS, images
├── database/           # SQL files
├── reports/            # Generated PDFs
└── index.php           # Landing page
```

## 🔑 Key Features

### For Visitors
- Digital check-in/check-out
- Email notifications
- Host verification required

### For Security
- Real-time visitor monitoring
- Alert notifications (curfew violations)
- Shift reports
- Check-in/out management

### For Administrators
- Manage residents and rooms
- Block/unblock visitors
- Generate reports (PDF)
- System settings control
- User management

## 📊 Database Tables

Main tables:
- `users` - System users (admin/security)
- `residents` - Students living in residence
- `visitors` - Visitor information
- `visits` - Check-in/out records
- `rooms` - Room assignments
- `visitor_blocks` - Blocked visitors
- `security_alerts` - Security incidents
- `reports` - Generated reports

## 🚀 Usage

1. **Visitors**: Go to `visitor/` to check in/out
2. **Security**: Login at `security/login.php`
3. **Admin**: Login at `admin/login.php`

## 📧 Email Setup (Optional)

Edit `config/email.php` for email notifications:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
```

## 🐛 Troubleshooting

**Can't connect to database?**
- Check XAMPP MySQL is running
- Verify database name in `config/database.php`

**Pages not loading?**
- Check XAMPP Apache is running
- Verify project is in `htdocs` folder

**Email not sending?**
- Configure SMTP settings in `config/email.php`
- Use Gmail App Password (not regular password)

## 📝 License

This project is for educational purposes - Advanced ICT Diploma 2025

## 👨‍💻 Developer

**Fika Fayini** - Student No: 202207134  
Supervisor: Dr. Tite Tuyikeze  
School of Natural Applied Science - CSIT Department

## 🤝 Contributing

1. Fork the project
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Open a pull request

---

**Need help?** Check the full documentation or contact the developer.
