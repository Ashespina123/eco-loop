<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

$appRoot = defined('APP_ROOT') ? APP_ROOT : '/eco-loop';
$assetBase = $appRoot . '/assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) : 'Eco-Loop'; ?></title>
    <meta name="description" content="Eco-Loop is an e-waste collection and rewards platform for households and communities.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e($assetBase . '/css/style.css'); ?>">
</head>
<body>
    <div class="site-shell">
        <header class="topbar">
            <div class="brand-block">
                <div class="brand-mark">E</div>
                <div>
                    <div class="brand-name">Eco-Loop</div>
                    <small>Recycle Today. Earn Rewards. Protect Tomorrow.</small>
                </div>
            </div>
            <nav class="header-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo e(APP_ROOT . '/logout.php'); ?>" class="btn btn-outline">Logout</a>
                <?php else: ?>
                    <a href="<?php echo e(APP_ROOT . '/login.php'); ?>" class="btn btn-outline">Login</a>
                    <a href="<?php echo e(APP_ROOT . '/register.php'); ?>" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </nav>
        </header>

        <?php renderFlash(); ?>
