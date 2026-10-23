<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'Redemption History | Eco-Loop';
$user = currentUser();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">History</span>
                <h1>Redemption History</h1>
            </div>
        </div>
        <div class="content-card">
            <p>Users can track their redemption requests, status, and collection instructions once the points ledger and rewards stage is active.</p>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
