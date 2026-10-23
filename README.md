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

**Important:** `database/eco_loop_db.sql` is a fresh-install script. It contains `DROP TABLE` statements and will delete existing Eco-Loop tables and their data if re-imported. Do not import it into a database that already contains data; back up the database and use a reviewed incremental migration instead.

1. Start Apache and MySQL from the XAMPP Control Panel.
2. Open phpMyAdmin.
3. Create a database named `eco_loop_db` if it is not created automatically by the script.
4. Import the SQL file from `database/eco_loop_db.sql`.
5. Confirm the tables are created successfully.

For an existing EcoLoop installation, back up the database and apply these migrations once, in order: `20261022_customer_workflows.sql`, `20261022_pickup_contact_snapshot.sql`, then `20261022_distance_rewards_centers.sql`. They preserve existing accounts and pickup data. The latest migration adds distance-price snapshots, center details, configurable reward rates, and item verification tables. Do not import the fresh-install SQL into a database with existing data, and do not run a migration twice.

## Configuration instructions

The default app configuration expects:

- Host: localhost
- Username: root
- Password: empty string
- Database: eco_loop_db

You can override these values with `ECOLOOP_DB_HOST`, `ECOLOOP_DB_USER`, `ECOLOOP_DB_PASSWORD`, and `ECOLOOP_DB_NAME` environment variables. The app base path is derived from the project location under the web document root.

## How to create or reset an administrator securely

There is no public admin registration route, and a fresh database does not include a shared administrator credential. Run the following from the project directory in PowerShell after starting MySQL:

```powershell
$secret = Read-Host 'Enter a new admin password (12+ characters)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try { $env:ECOLOOP_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
php scripts/admin-account.php create
Remove-Item Env:ECOLOOP_ADMIN_PASSWORD
```

The command prompts for the administrator email, name, and contact number and hashes the password. It refuses to create another admin if one already exists. To rotate the password for an existing administrator, use `php scripts/admin-account.php reset` with the same environment variable; the command prompts for the existing admin email. The script is CLI-only and returns 404 from the web server.

If the local database was imported from an older version, it may contain the former shared test admin account. Rotate its password with the `reset` command before continued use. Do not publish the password or reuse a shared credential.

## How to run the project locally

1. Start XAMPP Apache and MySQL.
2. Make sure the project folder is in `C:\xampp\htdocs\eco-loop`.
3. Open `http://localhost/eco-loop/` in a browser.
4. Register a customer account, or create a private administrator account using the CLI instructions above.

## Known limitations and future improvements

The current project includes:

- Shared UI and authentication foundation
- Role-based access control
- Database schema and seed data
- User registration and login flow
- Admin and user dashboards with protected pages
- Customer pickup requests, status tracking, and item verification
- Configurable distance estimates, collection centers, rewards, and redemptions

The distance fee calculation uses an explicitly labeled straight-line estimate from the customer pin to a selected verified center. No road-routing service is configured. If there are no centers, only the configured base fee is used and the form says so. Administrators can configure distance rates and e-waste point rates under Rewards and Pickup Rules, and manage real centers under Collection Points.

The pricing example for 10 km in the attached specification does not match its tier rates: those rates calculate Saver at ₱140 and Priority at ₱295. The implementation follows the tier rates and marginal-kilometer rules.

## Notes

- No public admin registration form is included.
- Passwords are hashed with PHP `password_hash()` and verified using `password_verify()`.
- Session handling, CSRF validation, and protected routes are included in the core auth system.
- Production deployments should never expose root credentials or keep seed accounts active.
