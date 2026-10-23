<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    redirectByRole();
}

$pageTitle = 'Eco-Loop | E-Waste Collection and Rewards';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">Responsible recycling made simple</span>
        <h1>Recycle Today. Earn Rewards. Protect Tomorrow.</h1>
        <p>Eco-Loop helps households collect old electronics responsibly, schedule convenient pickups, and earn rewards for clean recycling.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?php echo e(APP_ROOT . '/register.php'); ?>">Create account</a>
            <a class="btn btn-secondary" href="<?php echo e(APP_ROOT . '/login.php'); ?>">Sign in</a>
        </div>
        <ul class="feature-list">
            <li><i class="fa-solid fa-circle-check"></i> Household e-waste pickup scheduling</li>
            <li><i class="fa-solid fa-circle-check"></i> Saver and Priority service options</li>
            <li><i class="fa-solid fa-circle-check"></i> Reward points and redemptions</li>
        </ul>
    </div>
    <div class="hero-card">
        <div class="mini-stat">
            <span>Households served</span>
            <strong>2,500+</strong>
        </div>
        <div class="mini-stat">
            <span>Devices collected</span>
            <strong>18,400</strong>
        </div>
        <div class="mini-stat">
            <span>Reward points issued</span>
            <strong>96k</strong>
        </div>
    </div>
</section>

<section class="welcome-grid">
    <div class="feature-card">
        <div class="icon-wrap green"><i class="fa-solid fa-recycle"></i></div>
        <h3>Simple pickups</h3>
        <p>Request a collection, choose your service type, and let the team handle the rest.</p>
    </div>
    <div class="feature-card">
        <div class="icon-wrap green"><i class="fa-solid fa-map-location-dot"></i></div>
        <h3>Smart scheduling</h3>
        <p>View pickup availability and schedule routes based on your preferred date and location.</p>
    </div>
    <div class="feature-card">
        <div class="icon-wrap green"><i class="fa-solid fa-gifts"></i></div>
        <h3>Earn rewards</h3>
        <p>Turn electronics into points and redeem them for practical household essentials.</p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
