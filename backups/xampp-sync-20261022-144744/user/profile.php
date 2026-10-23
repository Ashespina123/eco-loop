<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'My Profile | Eco-Loop';
$user = currentUser();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Profile</span>
                <h1>My Profile</h1>
            </div>
        </div>
        <div class="content-card">
            <p>Profile management allows customers to update contact information, residential address, and map coordinates once the profile module is enabled.</p>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
