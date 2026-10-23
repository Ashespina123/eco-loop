# Eco-Loop

Eco-Loop is a household electronic waste (e-waste) collection and rewards web application built for local XAMPP deployment. It provides a secure registration and login flow, protected role-based dashboards, and database-backed foundation for pickup, rewards, notification, and reporting features.

## Project overview

Eco-Loop is designed for a modern recycling startup experience with:

- User registration and login
- Role-based access for users, administrators, and collectors
- Pickup request workflow foundation
- Reward points and redemption framework
- Database design for all major modules
- Responsive HTML, CSS, and JavaScript front end
- Session-based security and CSRF protection

## Required software

- XAMPP for Windows
- Apache and MySQL enabled in XAMPP
- PHP 8 or newer
- A browser such as Chrome or Edge

## XAMPP setup instructions

1. Install XAMPP and start Apache and MySQL.
2. Place this project in the web root:
   - C:\xampp\htdocs\eco-loop
3. Open phpMyAdmin in the browser:
   - http://localhost/phpmyadmin
4. Import the file:
   - database\eco_loop_db.sql
5. Open the project locally:
   - http://localhost/eco-loop/

## Database import instructions

1. Start Apache and MySQL from the XAMPP Control Panel.
2. Open phpMyAdmin.
3. Create a database named `eco_loop_db` if it is not created automatically by the script.
4. Import the SQL file from `database/eco_loop_db.sql`.
5. Confirm the tables are created successfully.

## Configuration instructions

The default app configuration expects:

- Host: localhost
- Username: root
- Password: empty string
- Database: eco_loop_db

You can update these values in `config/database.php` if your local MySQL credentials are different.

## How to create the first administrator securely

The database seed includes a default administrator account for local testing:

- Email: admin@ecoloop.test
- Password: Admin@123

Use this only in a local development environment. After setup, update or remove the seed account for production use.

## How to run the project locally

1. Start XAMPP Apache and MySQL.
2. Make sure the project folder is in `C:\xampp\htdocs\eco-loop`.
3. Open `http://localhost/eco-loop/` in a browser.
4. Register a new user account or log in with the seed admin account.

## Test accounts

Safe sample accounts included in the database seed:

- Admin: admin@ecoloop.test / Admin@123

## Known limitations and future improvements

This project is the first development stage and includes:

- Shared UI and authentication foundation
- Role-based access control
- Database schema and seed data
- User registration and login flow
- Admin and user dashboards with protected pages
- Placeholder management pages for later stages

Planned next improvements include:

- Full pickup request creation and status workflow
- Saver and Priority pricing logic and route scheduling
- Interactive Leaflet map integration
- Collector assignment and status tracking
- Reward points ledger and redemption processing
- Notifications and reporting exports
- Responsive admin management screens with full CRUD operations

## Notes

- No public admin registration form is included.
- Passwords are hashed with PHP `password_hash()` and verified using `password_verify()`.
- Session handling, CSRF validation, and protected routes are included in the core auth system.
- Production deployments should never expose root credentials or keep seed accounts active.
