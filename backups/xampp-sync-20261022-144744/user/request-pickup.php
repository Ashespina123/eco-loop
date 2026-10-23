<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'Request Pickup | Eco-Loop';
$user = currentUser();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Pickup request</span>
                <h1>Request E-Waste Pickup</h1>
            </div>
        </div>
        <div class="content-card">
            <p>The pickup request workflow, service selection, map-based coordination, and submission logic are being built in the next stage of this application.</p>
            <p>Users can select saver or priority pickup pricing, upload item images, and confirm their preferred schedule once the request form is completed.</p>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
