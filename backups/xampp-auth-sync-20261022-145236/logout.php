<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('success', 'You have been logged out successfully.');
redirect('login.php');
