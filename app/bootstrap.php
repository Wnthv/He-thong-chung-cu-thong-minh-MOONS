<?php
require __DIR__ . '/core/core.php';
require __DIR__ . '/core/icons.php';
require __DIR__ . '/services/services_a.php';
require __DIR__ . '/services/services_b.php';
require __DIR__ . '/services/services_c.php';
require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/ResidentController.php';
require __DIR__ . '/controllers/AdminController.php';

date_default_timezone_set(cfg('timezone'));
if (PHP_SAPI !== 'cli') {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_name('MOONSSID');
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
}
