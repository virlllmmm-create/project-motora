<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/actions.php';
require __DIR__ . '/includes/ajax.php';

$publicPages = ['login', 'register', 'forgot', 'reset'];
$views = [
    'login' => __DIR__ . '/pages/views/login.php',
    'register' => __DIR__ . '/pages/views/register.php',
    'forgot' => __DIR__ . '/pages/views/forgot.php',
    'reset' => __DIR__ . '/pages/views/reset.php',
    'dashboard' => __DIR__ . '/pages/views/dashboard.php',
    'search' => __DIR__ . '/pages/views/search.php',
    'communities' => __DIR__ . '/pages/views/communities.php',
    'community' => __DIR__ . '/pages/community.php',
    'community_settings' => __DIR__ . '/pages/community-settings.php',
    'garage' => __DIR__ . '/pages/views/garage.php',
    'motorcycle' => __DIR__ . '/pages/views/motorcycle.php',
    'profile' => __DIR__ . '/pages/views/profile.php',
    'friends' => __DIR__ . '/pages/views/friends.php',
    'messages' => __DIR__ . '/pages/views/messages.php',
    'notifications' => __DIR__ . '/pages/views/notifications.php',
    'settings' => __DIR__ . '/pages/views/settings.php',
];

$page = (string) ($_GET['page'] ?? (current_user() ? 'dashboard' : 'login'));
if (!isset($views[$page])) {
    $page = current_user() ? 'dashboard' : 'login';
}

if (isset($_GET['ajax'])) {
    handle_ajax_request((string) $_GET['ajax']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_action();
}

$user = in_array($page, $publicPages, true) ? current_user() : require_login();
if ($user && in_array($page, ['login', 'register'], true)) {
    go('dashboard');
}

$uid = (int) ($user['id_user'] ?? 0);
$pdo = db();
$content = '';
ob_start();
require $views[$page];
$content = ob_get_clean();

require __DIR__ . '/includes/layout.php';
