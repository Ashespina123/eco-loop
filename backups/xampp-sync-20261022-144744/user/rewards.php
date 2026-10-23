<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'My Rewards | Eco-Loop';
$user = currentUser();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Rewards</span>
                <h1>My Rewards</h1>
            </div>
        </div>
        <div class="content-card">
            <p>Reward points, eligible products, and redemption processing are implemented as a secure database-backed feature in the rewards stage.</p>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
