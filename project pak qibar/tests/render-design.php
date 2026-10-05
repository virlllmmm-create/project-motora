<?php
declare(strict_types=1);

// A CLI-only visual fixture exporter. It never loads the database configuration,
// starts a session, registers an application route, or writes application data.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$designRoot = dirname(__DIR__);
$designPages = ['login', 'register', 'forgot', 'reset', 'dashboard', 'search', 'communities', 'garage', 'friends', 'messages', 'notifications', 'profile', 'settings'];
$designPage = $argv[1] ?? '';
if ($designPage === '') {
    $designOutput = $designRoot . '/.design-preview';
    if (!is_dir($designOutput) && !mkdir($designOutput, 0775, true)) {
        throw new RuntimeException('Could not create the local preview directory.');
    }
    foreach ($designPages as $designPageName) {
        // Each process renders the real layout once, including its functions.
        $process = proc_open([PHP_BINARY, __FILE__, $designPageName], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $designRoot);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a preview renderer.');
        }
        fclose($pipes[0]);
        $html = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $errors !== '') {
            throw new RuntimeException('Preview failed for ' . $designPageName . ': ' . $errors);
        }
        if (file_put_contents($designOutput . '/' . $designPageName . '.html', $html) === false) {
            throw new RuntimeException('Could not write the preview.');
        }
        fwrite(STDOUT, 'Rendered ' . $designPageName . ".html\n");
    }
    exit;
}
if (!in_array($designPage, $designPages, true)) {
    throw new InvalidArgumentException('Choose a supported preview page.');
}
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
date_default_timezone_set('Asia/Jakarta');

final class DesignFixtureStatement extends PDOStatement
{
    private array $rows = [];
    private int $position = 0;

    public function __construct(private string $fixtureQuery) {}

    public function execute(?array $params = null): bool
    {
        $query = preg_replace('/\s+/', ' ', trim($this->fixtureQuery));
        $this->position = 0;
        $this->rows = [];
        if (preg_match('/^SELECT COUNT\(/i', $query)) {
            $this->rows = [['total' => 0]];
        } elseif (preg_match('/^SELECT .+ FROM `user` WHERE id_user=\?$/i', $query)) {
            $this->rows = ($params[0] ?? null) === 900001 ? [design_fixture_user()] : [];
        } elseif (preg_match('/^SELECT \* FROM user_settings WHERE user_id=\?$/i', $query)) {
            $this->rows = [design_fixture_settings()];
        } elseif (preg_match('/^SELECT `([a-z_]+)` FROM user_settings WHERE user_id=\?$/i', $query, $match)) {
            $this->rows = [[$match[1] => design_fixture_settings()[$match[1]] ?? null]];
        }
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[$this->position++] ?? false;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fetch();
        return $row === false ? false : (array_values($row)[$column] ?? false);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = array_slice($this->rows, $this->position);
        $this->position = count($this->rows);
        return $mode === PDO::FETCH_COLUMN ? array_column($rows, $args[0] ?? 0) : $rows;
    }
}

final class DesignFixtureDatabase extends PDO
{
    // Deliberately does not call PDO's constructor: there is no connection.
    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!preg_match('/^\s*SELECT\b/i', $query) && trim($query) !== 'INSERT IGNORE INTO user_settings(user_id) VALUES(?)') {
            throw new LogicException('The visual fixture cannot perform database writes.');
        }
        // ensure_settings() may request INSERT IGNORE; the fixture statement
        // treats that exact operation as a no-op without persistence.
        return new DesignFixtureStatement($query);
    }
}

function db(): PDO
{
    static $fixture = null;
    return $fixture ??= new DesignFixtureDatabase();
}

function design_fixture_user(): array
{
    return ['id_user' => 900001, 'nama' => 'Raka Pradana', 'username' => 'raka_rides', 'email' => 'raka@example.test', 'role' => 'rider', 'city' => 'Bandung', 'region' => 'Jawa Barat', 'bio' => 'Penikmat jalan pagi dan cerita dari teman seperjalanan.', 'profile_photo' => null, 'created_at' => '2026-10-01 09:00:00'];
}

function design_fixture_settings(): array
{
    return ['user_id' => 900001, 'profile_visibility' => 'public', 'motorcycles_visibility' => 'public', 'gallery_visibility' => 'public', 'show_city' => 1, 'is_searchable' => 1, 'notify_friend_requests' => 1, 'notify_community' => 1, 'notify_messages' => 1, 'notify_activity' => 1];
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SESSION = ['csrf' => 'inert-design-preview'];
$_GET = [];
$_POST = [];
$_FILES = [];
$page = $designPage;
if (!in_array($page, ['login', 'register', 'forgot', 'reset'], true)) {
    $_SESSION['user_id'] = 900001;
}
require $designRoot . '/includes/functions.php';
$user = current_user();
$uid = (int) ($user['id_user'] ?? 0);
$pdo = db();
ob_start();
require $designRoot . '/pages/views/' . $page . '.php';
$content = ob_get_clean();
ob_start();
require $designRoot . '/includes/layout.php';
$rendered = ob_get_clean();

// Rewrite only the rendered document; application templates remain unchanged.
libxml_use_internal_errors(true);
$document = new DOMDocument();
$document->loadHTML('<?xml encoding="UTF-8">' . $rendered, LIBXML_NONET);
libxml_clear_errors();
$xpath = new DOMXPath($document);
foreach ($xpath->query('//*[@data-delivery-poll or @data-chat or @data-poll-url]') as $pollNode) {
    $pollNode->parentNode?->removeChild($pollNode);
}
foreach ($document->getElementsByTagName('*') as $element) {
    foreach (['src', 'href', 'poster', 'data-src'] as $attribute) {
        if (!$element->hasAttribute($attribute)) continue;
        $value = $element->getAttribute($attribute);
        if (str_starts_with($value, 'assets/')) {
            $element->setAttribute($attribute, '../' . $value);
        } elseif ($attribute === 'href' && str_starts_with($value, 'index.php?')) {
            $parts = parse_url($value);
            parse_str($parts['query'] ?? '', $query);
            $destination = $query['page'] ?? '';
            $element->setAttribute('href', in_array($destination, $designPages, true) ? $destination . '.html' . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '') : '#design-preview-notice');
        }
    }
}
foreach ($document->getElementsByTagName('form') as $form) {
    $form->setAttribute('action', '#design-preview-notice');
    $form->setAttribute('method', 'get');
    $form->setAttribute('onsubmit', 'return false;');
    $form->setAttribute('data-design-preview', '');
    $form->removeAttribute('enctype');
    foreach ($form->getElementsByTagName('*') as $control) {
        // Even with JavaScript disabled no form field can send a value.
        $control->removeAttribute('name');
        if ($control->tagName === 'button' && (!$control->hasAttribute('type') || $control->getAttribute('type') === 'submit')) {
            $control->setAttribute('type', 'button');
        }
    }
}
$body = $document->getElementsByTagName('body')->item(0);
$notice = $document->createElement('p', 'Pratinjau desain · data contoh. Formulir tidak menyimpan perubahan.');
$notice->setAttribute('id', 'design-preview-notice');
$notice->setAttribute('class', 'sr-only');
$body->insertBefore($notice, $body->firstChild);
$previewScript = $document->createElement('script', 'document.addEventListener("submit",function(event){event.preventDefault();event.stopImmediatePropagation();},true);');
$body->insertBefore($previewScript, $body->firstChild);
$output = $document->saveHTML();
echo preg_replace('/<\?xml encoding="UTF-8">\s*/', '', $output);
