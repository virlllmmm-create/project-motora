<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$friendsView = file_get_contents($root . '/pages/views/friends.php');
$expect(is_string($friendsView), 'friend list view can be read');
if (is_string($friendsView)) {
    foreach (['p_friends', 'p_incoming', 'p_outgoing', 'p_blocked'] as $pageKey) {
        $expect(str_contains($friendsView, "'" . $pageKey . "'"), "friend view handles {$pageKey} pagination");
    }
    $expect(str_contains($friendsView, "u.id_user<>?"), 'accepted friend list excludes the current user');
}

$actions = file_get_contents($root . '/includes/actions.php');
$expect(is_string($actions), 'action handler can be read');
if (is_string($actions)) {
    $forgotStart = strpos($actions, "case 'forgot':");
    $resetStart = strpos($actions, "case 'reset':", $forgotStart === false ? 0 : $forgotStart);
    $forgotAction = $forgotStart !== false && $resetStart !== false
        ? substr($actions, $forgotStart, $resetStart - $forgotStart)
        : '';
    $expect(str_contains($forgotAction, 'local_reset_links_enabled()'), 'reset token creation is gated by explicit local-only mode');
    $expect(!str_contains($forgotAction, 'DELETE FROM password_resets'), 'new reset requests do not revoke existing tokens');
    $expect(str_contains($forgotAction, 'create_password_reset_token'), 'forgot flow uses throttled reset token creation');
    $expect(str_contains($actions, "case 'reset':\n                if (!local_reset_links_enabled())"), 'reset submissions are rejected outside explicit local-only mode');
}

$forgotView = file_get_contents($root . '/pages/views/forgot.php');
$expect(is_string($forgotView), 'forgot view can be read');
if (is_string($forgotView)) {
    $expect(str_contains($forgotView, 'local_reset_links_enabled()'), 'reset link display is gated by local-only mode');
}

$notificationView = file_get_contents($root . '/pages/views/notifications.php');
$layout = file_get_contents($root . '/includes/layout.php');
$ajax = file_get_contents($root . '/includes/ajax.php');
$expect(is_string($notificationView) && str_contains($notificationView, 'notification_visibility_sql($uid'), 'notification list filters blocked actors');
$expect(is_string($layout) && str_contains($layout, 'notification_visibility_sql('), 'server-rendered notification badge filters blocked actors');
$expect(is_string($ajax) && str_contains($ajax, 'notification_visibility_sql('), 'AJAX notification count filters blocked actors');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Static regression checks passed.\n");
