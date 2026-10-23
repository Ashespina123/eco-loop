<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pageTitle = 'Rewards Management | Eco-Loop';
$user = currentUser();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Rewards</span>
                <h1>Rewards Management</h1>
            </div>
        </div>
        <div class="content-card">
            <p>Admins can add reward products, update stock levels, and control point requirements for each item in the catalog.</p>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
