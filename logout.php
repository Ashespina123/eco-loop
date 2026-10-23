<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the logout button to end your session.');
}

require_csrf();
logoutUser();
session_start();
setFlash('success', 'You have been logged out successfully.');
redirect('login.php');
