<?php
declare(strict_types=1);

// Standalone regression suite. Every table is connection-local and shadows the
// live table before any page/action is evaluated; no live account is modified.
$profileTestRoot = getenv('MOTORA_PROFILE_TEST_ROOT') ?: ($argv[1] ?? dirname(__DIR__));
$profileTestView = getenv('MOTORA_PROFILE_TEST_VIEW') ?: ($argv[2] ?? $profileTestRoot . '/pages/views/profile.php');
$profileTestActions = getenv('MOTORA_PROFILE_TEST_ACTIONS') ?: ($argv[3] ?? $profileTestRoot . '/includes/actions.php');
$profileTestSettings = getenv('MOTORA_PROFILE_TEST_SETTINGS') ?: ($argv[4] ?? $profileTestRoot . '/pages/views/settings.php');
$profileTestWarnings = [];
error_reporting(E_ALL);
set_error_handler(function (int $level, string $message, string $file, int $line) use (&$profileTestWarnings): never {
    $profileTestWarnings[] = $message;
    throw new ErrorException($message, 0, $level, $file, $line);
});
require $profileTestRoot . '/config/database.php';
require $profileTestRoot . '/includes/functions.php';

function profile_test_tables(PDO $pdo): void
{
    $tables = [
        '`user`' => "id_user INT PRIMARY KEY,nama VARCHAR(100) NOT NULL,username VARCHAR(50) NOT NULL UNIQUE,email VARCHAR(150) NOT NULL UNIQUE,role VARCHAR(30) NOT NULL DEFAULT 'rider',city VARCHAR(100) NULL,region VARCHAR(100) NULL,bio TEXT NULL,profile_photo VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,password_hash VARCHAR(255) NOT NULL DEFAULT ''",
        'user_settings' => "user_id INT PRIMARY KEY,profile_visibility VARCHAR(20) NOT NULL DEFAULT 'public',motorcycles_visibility VARCHAR(20) NOT NULL DEFAULT 'public',gallery_visibility VARCHAR(20) NOT NULL DEFAULT 'public',show_city TINYINT NOT NULL DEFAULT 1,is_searchable TINYINT NOT NULL DEFAULT 1,notify_friend_requests TINYINT NOT NULL DEFAULT 1,notify_community TINYINT NOT NULL DEFAULT 1,notify_messages TINYINT NOT NULL DEFAULT 1,notify_activity TINYINT NOT NULL DEFAULT 1",
        'friend_requests' => "id INT AUTO_INCREMENT PRIMARY KEY,sender_id INT NOT NULL,receiver_id INT NOT NULL,status VARCHAR(20) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_direction(sender_id,receiver_id)",
        'motorcycles' => "id INT PRIMARY KEY,user_id INT NOT NULL,brand VARCHAR(80) NOT NULL,model VARCHAR(100) NOT NULL,type VARCHAR(80) NOT NULL DEFAULT '',year SMALLINT NULL,color VARCHAR(80) NULL,plate_number VARCHAR(20) NULL,description TEXT NULL,main_photo VARCHAR(255) NULL,is_primary TINYINT NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'motorcycle_photos' => 'id INT PRIMARY KEY,motorcycle_id INT NOT NULL,path VARCHAR(255) NOT NULL',
        'communities' => 'id INT PRIMARY KEY,owner_id INT NOT NULL,name VARCHAR(120) NOT NULL,city VARCHAR(100) NULL,image VARCHAR(255) NULL,motorcycle_type VARCHAR(80) NULL,is_private TINYINT NOT NULL DEFAULT 0',
        'community_members' => 'community_id INT NOT NULL,user_id INT NOT NULL,status VARCHAR(20) NOT NULL,PRIMARY KEY(community_id,user_id)',
        'notifications' => 'id INT AUTO_INCREMENT PRIMARY KEY,recipient_id INT NOT NULL,actor_id INT NULL,type VARCHAR(40) NOT NULL,entity_id INT NULL,body VARCHAR(255) NOT NULL,read_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($tables as $table => $columns) {
        $pdo->exec('CREATE TEMPORARY TABLE ' . $table . ' (' . $columns . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}

$pdo = db();
profile_test_tables($pdo);
$pdo->exec("INSERT INTO `user` (id_user,nama,username,email,city,region,bio,profile_photo) VALUES
    (100,'Original Rider','original_rider','original@example.test','Original City','Original Region','Original bio','uploads/profile/fixture-old.jpg'),
    (101,'Target Rider','target_rider','target@example.test','Visible City','Visible Region','Profile fixture bio',NULL),
    (102,'Public Friend','public_friend','public@example.test',NULL,NULL,NULL,NULL),
    (103,'Private Friend','private_friend','private@example.test',NULL,NULL,NULL,NULL),
    (104,'Blocked Friend','blocked_friend','blocked@example.test',NULL,NULL,NULL,NULL),
    (105,'Legacy Rider','legacy_rider','legacy@example.test',NULL,NULL,NULL,NULL),
    (106,'Pending Rider','pending_rider','pending@example.test',NULL,NULL,NULL,NULL)");
$pdo->exec('INSERT INTO user_settings(user_id) VALUES (100),(101),(102),(103),(104),(106)');
$_SESSION = ['user_id' => 100, 'csrf' => 'profile-regression-token'];

if (getenv('MOTORA_PROFILE_TEST_WORKER') === '1') {
    $payload = json_decode(base64_decode((string) getenv('MOTORA_PROFILE_TEST_PAYLOAD'), true), true, 512, JSON_THROW_ON_ERROR);
    $workerAction = $payload['action'] ?? 'profile_save';
    if (!in_array($workerAction, ['profile_save', 'settings_save', 'password_change'], true)) {
        throw new RuntimeException('Only isolated settings-page actions may run in this worker.');
    }
    $_POST = ['action' => $workerAction, 'csrf' => $_SESSION['csrf']] + $payload['post'];
    // Only absent/no-file/error upload fixtures are allowed: no file can be moved.
    $_FILES = [];
    if (isset($payload['upload_error'])) {
        $_FILES['profile_photo'] = ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => $payload['upload_error']];
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    unset($_SERVER['HTTP_REFERER']);
    if (isset($payload['referer'])) {
        $_SERVER['HTTP_REFERER'] = $payload['referer'];
    }
    $before = $pdo->query('SELECT * FROM `user` WHERE id_user=100')->fetch();
    ob_start();
    register_shutdown_function(function () use ($pdo, $before, &$profileTestWarnings): void {
        $unexpectedOutput = ob_get_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'before' => $before,
            'after' => $pdo->query('SELECT * FROM `user` WHERE id_user=100')->fetch(),
            'flash' => $_SESSION['flash'] ?? null,
            'draft' => $_SESSION['profile_draft'] ?? null,
            'warnings' => $profileTestWarnings,
            'unexpected_output' => $unexpectedOutput,
        ], JSON_THROW_ON_ERROR);
    });
    require $profileTestActions;
    handle_action();
    exit;
}

$profileTestChecks = 0;
function profile_check(bool $condition, string $message): void
{
    global $profileTestChecks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $profileTestChecks++;
    echo "PASS: {$message}\n";
}

function profile_render(array $query): array
{
    global $profileTestView;
    $_GET = $query;
    $uid = 100;
    $user = current_user();
    $pdo = db();
    http_response_code(200);
    ob_start();
    try {
        require $profileTestView;
        $html = ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return ['html' => $html, 'status' => http_response_code(), 'friends' => $profileFriends ?? null, 'communities' => $profileCommunities ?? null, 'community_rows' => $profileCommunityRows ?? [], 'motors' => $profileMotors ?? []];
}

function profile_save_worker(array $post, array $extras = []): array
{
    global $profileTestRoot, $profileTestActions;
    $cgi = dirname(PHP_BINARY) . '/php-cgi' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    if (!is_file($cgi)) {
        throw new RuntimeException('PHP CGI is required to verify actual redirect headers.');
    }
    $env = getenv();
    $env['REDIRECT_STATUS'] = '1';
    $env['REQUEST_METHOD'] = 'GET';
    $env['SCRIPT_FILENAME'] = __FILE__;
    $env['MOTORA_PROFILE_TEST_WORKER'] = '1';
    $env['MOTORA_PROFILE_TEST_ROOT'] = $profileTestRoot;
    $env['MOTORA_PROFILE_TEST_ACTIONS'] = $profileTestActions;
    $env['MOTORA_PROFILE_TEST_PAYLOAD'] = base64_encode(json_encode(['post' => $post] + $extras, JSON_THROW_ON_ERROR));
    $process = proc_open([$cgi, '-f', __FILE__], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $profileTestRoot, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch isolated profile worker.');
    }
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || $stderr !== '') {
        throw new RuntimeException('Profile worker failed: ' . $stderr . $raw);
    }
    $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Worker did not emit CGI headers and fixture JSON: ' . $raw);
    }
    $result = json_decode($parts[1], true, 512, JSON_THROW_ON_ERROR);
    $result['headers'] = $parts[0];
    profile_check($result['warnings'] === [] && $result['unexpected_output'] === '', 'profile save runs without PHP warnings or unexpected output');
    profile_check(str_contains($result['headers'], 'Location: index.php?page=settings'), 'profile save returns to settings without depending on a referer');
    return $result;
}

function profile_settings_render(?array $draft, ?array $displayUser = null): array
{
    global $profileTestSettings;
    $uid = 100;
    $user = $displayUser ?? current_user();
    $pdo = db();
    if ($draft === null) {
        unset($_SESSION['profile_draft']);
    } else {
        $_SESSION['profile_draft'] = $draft;
    }
    ob_start();
    try {
        require $profileTestSettings;
        $html = ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $priorXmlMode = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($priorXmlMode);
    $xpath = new DOMXPath($document);
    $values = [];
    foreach (['nama', 'username', 'email', 'city', 'region', 'bio'] as $field) {
        $node = $xpath->query('//input[@name="' . $field . '"] | //textarea[@name="' . $field . '"]')->item(0);
        if (!$node instanceof DOMElement) {
            throw new RuntimeException('Missing edit form field: ' . $field);
        }
        $values[$field] = $node->tagName === 'textarea' ? $node->textContent : $node->getAttribute('value');
    }
    return ['html' => $html, 'values' => $values, 'xpath' => $xpath];
}

$pdo->exec("UPDATE user_settings SET profile_visibility='private' WHERE user_id=103");
$pdo->exec("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES
    (100,101,'accepted'),(101,100,'accepted'),(101,101,'accepted'),(101,102,'accepted'),
    (102,101,'accepted'),(101,103,'accepted'),(101,104,'accepted'),(104,100,'blocked')");
$insertCommunity = $pdo->prepare("INSERT INTO communities(id,owner_id,name,city,is_private) VALUES(?,102,?,'Community City',0)");
$insertMember = $pdo->prepare("INSERT INTO community_members(community_id,user_id,status) VALUES(?,101,'approved')");
for ($i = 1; $i <= 13; $i++) {
    $insertCommunity->execute([$i, 'Circle ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
    $insertMember->execute([$i]);
}
$pdo->exec("INSERT INTO communities(id,owner_id,name,is_private) VALUES (14,102,'Hidden private circle',1),(15,104,'Hidden blocked-owner circle',0)");
$pdo->exec("INSERT INTO community_members(community_id,user_id,status) VALUES(14,101,'approved'),(15,101,'approved')");
$pdo->exec("INSERT INTO motorcycles(id,user_id,brand,model,type,main_photo) VALUES
    (1,101,'Honda','Profile bike','Sport','uploads/motor/profile-main.jpg'),
    (2,105,'Yamaha','Legacy bike','Touring','uploads/motor/legacy-main.jpg')");
$pdo->exec("INSERT INTO motorcycle_photos(id,motorcycle_id,path) VALUES(1,1,'uploads/motor/profile-main.jpg'),(2,1,'uploads/motor/profile-detail.jpg')");

// Optional local visual fixture export, still using only the temporary tables.
$profileRenderDir = getenv('MOTORA_PROFILE_RENDER_DIR');
if ($profileRenderDir !== false && $profileRenderDir !== '') {
    $exportRoot = realpath($profileRenderDir);
    $temporaryRoot = realpath(sys_get_temp_dir());
    if (!$exportRoot || !$temporaryRoot || !str_starts_with(strtolower($exportRoot . DIRECTORY_SEPARATOR), strtolower($temporaryRoot . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Visual fixture export must target an existing temporary directory.');
    }
    $page = getenv('MOTORA_PROFILE_RENDER_PAGE') ?: 'profile';
    if (!in_array($page, ['profile', 'settings'], true)) {
        throw new RuntimeException('Only profile and settings fixtures may be exported.');
    }
    $renderId = (int) (getenv('MOTORA_PROFILE_RENDER_ID') ?: 101);
    $bikeSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 420"><defs><linearGradient id="bg" x2="1" y2="1"><stop stop-color="#232923"/><stop offset="1" stop-color="#809077"/></linearGradient></defs><rect width="720" height="420" fill="url(#bg)"/><circle cx="200" cy="294" r="65" fill="#141914" stroke="#d9dbcc" stroke-width="13"/><circle cx="523" cy="294" r="65" fill="#141914" stroke="#d9dbcc" stroke-width="13"/><path d="m200 294 104-87 113 88H200m216 0 67-119 40 118M304 207l29 88M433 153h57l-6 22M259 190h100l49 29H275z" fill="none" stroke="#ee784d" stroke-width="19" stroke-linejoin="round"/><path d="M317 206c-5-55 29-72 72-64l44 39-26 38" fill="#e79969"/><path d="M250 180h93" stroke="#171e16" stroke-width="24" stroke-linecap="round"/></svg>';
    $fixturePhoto = 'data:image/svg+xml,' . rawurlencode($bikeSvg);
    // Both tables were created as TEMPORARY above. Production photo columns
    // store short paths; only visual fixture export needs full inline SVG data.
    $pdo->exec('ALTER TABLE motorcycles MODIFY main_photo TEXT NULL');
    $pdo->exec('ALTER TABLE motorcycle_photos MODIFY path TEXT NOT NULL');
    $pdo->prepare('UPDATE motorcycles SET main_photo=? WHERE id IN (1,2)')->execute([$fixturePhoto]);
    $pdo->prepare('UPDATE motorcycle_photos SET path=?')->execute([$fixturePhoto]);
    $pdo->exec("UPDATE `user` SET profile_photo=NULL WHERE id_user=100");
    $pdo->prepare("INSERT INTO motorcycles(id,user_id,brand,model,type,year,main_photo,is_primary) VALUES(3,100,'Honda','CB 150R','Sport',2024,?,1),(4,100,'Yamaha','NMAX','Skuter',2023,?,0)")->execute([$fixturePhoto, $fixturePhoto]);
    $pdo->exec("INSERT INTO community_members(community_id,user_id,status) VALUES(1,100,'approved'),(2,100,'approved'),(3,100,'approved')");
    $pdo->exec("UPDATE `user` SET nama='Aditya Pratama',username='aditya_rides',city='Bandung',region='Jawa Barat',bio='Rider harian, penikmat sunmori, dan selalu mencari teman seperjalanan. Honda CB 150R jadi kawan untuk mengeksplorasi rute baru.' WHERE id_user=100");
    $uid = 100;
    $user = current_user();
    $_GET = ['id' => (string) $renderId];
    ob_start();
    require $page === 'profile' ? $profileTestView : $profileTestSettings;
    $content = ob_get_clean();
    ob_start();
    require getenv('MOTORA_PROFILE_TEST_LAYOUT') ?: $profileTestRoot . '/includes/layout.php';
    $fixtureHtml = ob_get_clean();
    $fixtureFile = $exportRoot . DIRECTORY_SEPARATOR . 'ui-' . $page . ($page === 'profile' ? '-' . $renderId : '') . '.html';
    file_put_contents($fixtureFile, $fixtureHtml);
    echo $fixtureFile . "\n";
    exit;
}

$page = profile_render(['id' => '101']);
profile_check($page['status'] === 200 && str_contains($page['html'], 'Target Rider'), 'public profile renders successfully under strict PHP warning handling');
profile_check($page['friends'] === 2, 'friend count excludes self, private/blocked peers and reverse-direction duplicates');
profile_check($page['communities'] === 13 && count($page['community_rows']) === 12, 'community total counts every accessible circle while cards remain bounded to 12');
profile_check(!str_contains($page['html'], 'Hidden private circle') && !str_contains($page['html'], 'Hidden blocked-owner circle'), 'profile hides inaccessible community details');
profile_check(str_contains($page['html'], 'uploads/motor/profile-main.jpg') && str_contains($page['html'], 'uploads/motor/profile-detail.jpg'), 'gallery includes main and additional visible motorcycle photos');
profile_check(substr_count($page['html'], 'src="uploads/motor/profile-main.jpg"') === 2, 'main motorcycle image appears once in gallery despite duplicated photo row');
$page = profile_render(['id' => '105']);
profile_check($page['status'] === 200 && str_contains($page['html'], 'Legacy bike') && str_contains($page['html'], 'uploads/motor/legacy-main.jpg'), 'legacy rider without settings still has visible public garage and gallery');
$pdo->exec("UPDATE `user` SET bio='0' WHERE id_user=105");
$page = profile_render(['id' => '105']);
profile_check(str_contains($page['html'], '>0</p>'), 'literal bio value zero remains visible instead of showing the empty-profile prompt');

$pdo->exec("UPDATE user_settings SET show_city=0,motorcycles_visibility='private',gallery_visibility='private' WHERE user_id=101");
$page = profile_render(['id' => '101']);
profile_check(!str_contains($page['html'], 'Visible City') && !str_contains($page['html'], 'Visible Region'), 'hidden city and region do not leak through rendered profile');
profile_check($page['motors'] === [] && !str_contains($page['html'], 'Profile bike') && !str_contains($page['html'], 'uploads/motor/profile-'), 'private motorcycle and gallery details stay hidden');
$pdo->exec("UPDATE user_settings SET motorcycles_visibility='public',gallery_visibility='private' WHERE user_id=101");
$page = profile_render(['id' => '101']);
profile_check(str_contains($page['html'], 'Profile bike') && !str_contains($page['html'], 'uploads/motor/profile-'), 'public motorcycle details do not expose a private gallery');
$pdo->exec("UPDATE user_settings SET profile_visibility='private' WHERE user_id=101");
$page = profile_render(['id' => '101']);
profile_check($page['status'] === 404 && !str_contains($page['html'], 'Target Rider'), 'private profile responds unavailable without identity details');
$pdo->exec("UPDATE user_settings SET profile_visibility='public' WHERE user_id=101");
$pdo->exec("UPDATE friend_requests SET status='blocked' WHERE sender_id=101 AND receiver_id=100");
$page = profile_render(['id' => '101']);
profile_check($page['status'] === 404 && !str_contains($page['html'], 'Target Rider'), 'a block overrides a simultaneous accepted friendship');

foreach ([['id' => ['101']], ['id' => '101oops'], ['id' => '-1'], ['id' => '0'], ['id' => '999999999999999999999'], ['id' => '999999']] as $query) {
    $page = profile_render($query);
    profile_check($page['status'] === 404 && !str_contains($page['html'], 'Target Rider'), 'malformed or missing rider IDs return a clean unavailable profile');
}
$page = profile_render([]);
profile_check($page['status'] === 200 && str_contains($page['html'], 'Original Rider') && str_contains($page['html'], 'page=settings'), 'own profile defaults to signed-in rider and provides an edit link');
$pdo->exec("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES(106,100,'pending')");
$page = profile_render(['id' => '106']);
profile_check(str_contains($page['html'], 'value="accept"') && str_contains($page['html'], 'value="reject"'), 'incoming friend request can be accepted or rejected directly from the profile');
$pdo->exec("UPDATE friend_requests SET sender_id=100,receiver_id=106 WHERE sender_id=106 AND receiver_id=100");
$page = profile_render(['id' => '106']);
profile_check(str_contains($page['html'], 'value="cancel"'), 'outgoing friend request offers a cancellation action');
$pdo->exec('DELETE FROM friend_requests WHERE sender_id=100 AND receiver_id=106');
$pdo->exec('UPDATE user_settings SET is_searchable=0 WHERE user_id=106');
$page = profile_render(['id' => '106']);
profile_check(!str_contains($page['html'], 'value="friend_request"'), 'rider who disables discovery is not offered an unusable friend-request action');

$validPost = ['nama' => '  Updated Rider  ', 'username' => '  UPDATED_RIDER  ', 'email' => '  UPDATED@EXAMPLE.TEST  ', 'city' => '  Bandung  ', 'region' => '  Jawa Barat  ', 'bio' => '  Salam rider!  '];
$saved = profile_save_worker($validPost);
profile_check($saved['flash']['type'] === 'success' && $saved['draft'] === null, 'successful edit clears the temporary form draft');
profile_check($saved['after']['nama'] === 'Updated Rider' && $saved['after']['username'] === 'updated_rider' && $saved['after']['email'] === 'updated@example.test' && $saved['after']['city'] === 'Bandung' && $saved['after']['bio'] === 'Salam rider!', 'valid edit persists normalized identity and profile fields');
profile_check($saved['after']['profile_photo'] === $saved['before']['profile_photo'], 'saving without a replacement photo preserves the current photo');
$settings = profile_settings_render(null, $saved['after']);
profile_check($settings['values']['nama'] === 'Updated Rider' && $settings['values']['email'] === 'updated@example.test', 'settings displays persisted profile data after a successful edit clears its draft');
$saved = profile_save_worker(['nama' => 'Original Rider', 'username' => 'original_rider', 'email' => 'original@example.test', 'city' => '', 'region' => '', 'bio' => ''], ['upload_error' => UPLOAD_ERR_NO_FILE]);
profile_check($saved['flash']['type'] === 'success' && $saved['after']['city'] === null && $saved['after']['bio'] === null, 'unchanged identity and empty optional fields save with the browser no-file upload value');
$saved = profile_save_worker(array_replace($validPost, ['city' => '0', 'region' => '0', 'bio' => '0']));
profile_check($saved['after']['city'] === '0' && $saved['after']['region'] === '0' && $saved['after']['bio'] === '0', 'optional text value zero is preserved instead of silently cleared');

$invalidCases = [
    'duplicate username' => ['post' => ['username' => 'target_rider'], 'field' => 'Username'],
    'duplicate email' => ['post' => ['email' => 'target@example.test'], 'field' => 'Email'],
    'empty name' => ['post' => ['nama' => '   '], 'field' => 'Nama'],
    'too short name' => ['post' => ['nama' => 'A'], 'field' => 'Nama'],
    'oversized name' => ['post' => ['nama' => str_repeat('界', 101)], 'field' => 'Nama'],
    'oversized city' => ['post' => ['city' => str_repeat('B', 101)], 'field' => 'Kota'],
    'oversized region' => ['post' => ['region' => str_repeat('R', 101)], 'field' => 'Daerah'],
    'oversized bio' => ['post' => ['bio' => str_repeat('B', 1001)], 'field' => 'Bio'],
    'oversized email' => ['post' => ['email' => str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 24) . '.test'], 'field' => 'email'],
    'malformed username' => ['post' => ['username' => 'invalid user'], 'field' => 'Username'],
    'invalid email' => ['post' => ['email' => 'invalid-email'], 'field' => 'email'],
];
foreach (['nama', 'username', 'email', 'city', 'region', 'bio'] as $field) {
    $invalidCases['array value for ' . $field] = ['post' => [$field => ['unexpected']], 'field' => 'teks yang valid'];
}
foreach ($invalidCases as $label => $case) {
    $submitted = array_replace($validPost, $case['post']);
    $result = profile_save_worker($submitted);
    profile_check($result['after'] === $result['before'], $label . ' leaves all persisted account fields unchanged');
    profile_check(($result['flash']['type'] ?? '') === 'error' && stripos($result['flash']['message'], $case['field']) !== false, $label . ' provides a useful field-specific error');
    profile_check(($result['draft']['user_id'] ?? null) === 100 && $result['draft']['values']['username'] === (is_string($submitted['username']) ? mb_strtolower(trim($submitted['username'])) : '') && $result['draft']['values']['bio'] === (is_string($submitted['bio']) ? trim($submitted['bio']) : ''), $label . ' preserves safe submitted values for correction');
    if ($label === 'duplicate username') {
        $settings = profile_settings_render($result['draft']);
        profile_check($settings['values'] === $result['draft']['values'], 'failed edit returns all six entered fields to the actual settings form');
    }
}
$result = profile_save_worker($validPost, ['upload_error' => UPLOAD_ERR_INI_SIZE, 'referer' => 'http://localhost/index.php?page=profile&id=100']);
profile_check($result['after'] === $result['before'] && ($result['flash']['type'] ?? '') === 'error' && $result['draft'] !== null, 'failed photo upload rolls back the edit and keeps entered text available');
$settings = profile_settings_render(['user_id' => 101, 'values' => ['nama' => 'Other account draft', 'email' => 'other-draft@example.test']]);
profile_check($settings['values']['nama'] === 'Original Rider' && $settings['values']['email'] === 'original@example.test', 'form draft belonging to another rider is ignored');
$nullUser = current_user();
foreach (['city', 'region', 'bio', 'profile_photo'] as $field) {
    $nullUser[$field] = null;
}
$settings = profile_settings_render(null, $nullUser);
profile_check($settings['values']['city'] === '' && $settings['values']['region'] === '' && $settings['values']['bio'] === '', 'nullable optional profile fields render as blank form values without warnings');
$attack = '\"><img src=x onerror=alert(1)></textarea><script>alert("fixture")</script>';
$attackDraft = ['user_id' => 100, 'values' => array_fill_keys(['nama', 'username', 'email', 'city', 'region', 'bio'], $attack)];
$settings = profile_settings_render($attackDraft);
profile_check($settings['values'] === $attackDraft['values'], 'quote and HTML characters survive the edit draft as literal editable text');
profile_check($settings['xpath']->query('//script | //*[@onerror or @onload]')->length === 0, 'draft values cannot create executable HTML or break out of inputs and textarea');
$settings = profile_settings_render(['user_id' => 100, 'values' => ['nama' => ['unexpected'], 'bio' => ['unexpected']]]);
profile_check($settings['values']['nama'] === 'Original Rider' && $settings['values']['bio'] === 'Original bio', 'malformed draft fields fall back to account values without array warnings');
foreach ([
    'settings_save' => ['profile_visibility' => 'invalid-value'],
    'password_change' => ['current_password' => 'incorrect-current-password', 'new_password' => 'fixture-new-password', 'confirm_password' => 'fixture-new-password'],
] as $action => $post) {
    $result = profile_save_worker($post, ['action' => $action]);
    profile_check($result['after'] === $result['before'] && ($result['flash']['type'] ?? '') === 'error', 'failed ' . $action . ' keeps the account unchanged and returns to the same settings page');
}
profile_check($profileTestWarnings === [], 'all rendered profile cases complete without PHP warnings');
echo "{$profileTestChecks} profile regression checks passed; all database fixtures were temporary.\n";
