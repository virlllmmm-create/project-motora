<?php
declare(strict_types=1);

// Every fixture table is connection-local and shadows its live counterpart
// before the view runs. No rider data or uploaded files are changed.
$searchTestRoot = dirname(__DIR__);
require $searchTestRoot . '/config/database.php';
require $searchTestRoot . '/includes/functions.php';

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$pdo = db();
$pdo->exec('CREATE TEMPORARY TABLE `user` (
    id_user INT PRIMARY KEY, nama VARCHAR(100) NOT NULL, username VARCHAR(50) NOT NULL,
    city VARCHAR(100) NULL, region VARCHAR(100) NULL, profile_photo VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec("CREATE TEMPORARY TABLE user_settings (
    user_id INT PRIMARY KEY, is_searchable TINYINT NOT NULL DEFAULT 1,
    show_city TINYINT NOT NULL DEFAULT 1,
    profile_visibility VARCHAR(20) NOT NULL DEFAULT 'public',
    motorcycles_visibility VARCHAR(20) NOT NULL DEFAULT 'public',
    gallery_visibility VARCHAR(20) NOT NULL DEFAULT 'public'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec('CREATE TEMPORARY TABLE friend_requests (
    id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL,
    status VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TEMPORARY TABLE motorcycles (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    brand VARCHAR(80) NOT NULL, model VARCHAR(100) NOT NULL, type VARCHAR(80) NOT NULL,
    main_photo VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$insertRider = $pdo->prepare('INSERT INTO `user`(id_user,nama,username,city,region) VALUES(?,?,?,?,?)');
$insertSettings = $pdo->prepare('INSERT INTO user_settings(user_id,is_searchable,show_city,profile_visibility,motorcycles_visibility,gallery_visibility) VALUES(?,?,?,?,?,?)');
$addRider = static function (
    int $id,
    string $name,
    string $username,
    ?string $city = null,
    ?string $region = null,
    array $preferences = [],
    bool $withSettings = true,
) use ($insertRider, $insertSettings): void {
    $insertRider->execute([$id, $name, $username, $city, $region]);
    if ($withSettings) {
        $insertSettings->execute([
            $id,
            $preferences['is_searchable'] ?? 1,
            $preferences['show_city'] ?? 1,
            $preferences['profile_visibility'] ?? 'public',
            $preferences['motorcycles_visibility'] ?? 'public',
            $preferences['gallery_visibility'] ?? 'public',
        ]);
    }
};
$insertBike = $pdo->prepare('INSERT INTO motorcycles(user_id,brand,model,type,main_photo) VALUES(?,?,?,?,?)');

$addRider(100, 'Viewer Self Needle', 'viewer_self');
$addRider(101, 'Nadia Ardi', 'nadia_handle', 'Bandung', 'Jawa Barat');
$addRider(102, 'Hidden Location Rider', 'hidden_location', 'SecretCityNeedle', 'SecretRegionNeedle', ['show_city' => 0]);
$addRider(103, 'Private Garage Rider', 'private_garage', 'Surakarta', null, ['motorcycles_visibility' => 'private']);
$addRider(104, 'Private Profile Needle', 'private_profile', 'ProfileCityNeedle', null, ['profile_visibility' => 'private']);
$addRider(105, 'Disabled Search Needle', 'disabled_search', 'DisabledCityNeedle', null, ['is_searchable' => 0]);
$addRider(106, 'Viewer Blocked Needle', 'viewer_blocked');
$addRider(107, 'Blocked Viewer Needle', 'blocked_viewer');
$addRider(108, 'Accepted Friend Needle', 'accepted_friend', 'Friend City', null, [
    'profile_visibility' => 'friends', 'motorcycles_visibility' => 'friends', 'gallery_visibility' => 'friends',
]);
$addRider(109, 'Stranger Garage Rider', 'stranger_garage', null, null, ['motorcycles_visibility' => 'friends']);
$addRider(110, 'Stranger Profile Needle', 'stranger_profile', null, null, ['profile_visibility' => 'friends']);
$addRider(111, 'Legacy Rider', 'legacy_rider', 'LegacyCityNeedle', 'LegacyRegionNeedle', [], false);
$addRider(112, 'Hidden Gallery Rider', 'hidden_gallery', null, null, ['gallery_visibility' => 'private']);
$insertBike->execute([101, 'HondaNeedle', 'StreetFoxNeedle', 'RoadsterNeedle', 'uploads/motor/public-fixture.jpg']);
$insertBike->execute([101, 'HondaNeedle', 'SecondBike', 'RoadsterNeedle', null]);
$insertBike->execute([103, 'SecretBrandNeedle', 'SecretModelNeedle', 'SecretTypeNeedle', 'uploads/motor/private-fixture.jpg']);
$insertBike->execute([104, 'VisibleBrand', 'PrivateProfileBikeNeedle', 'Sport', null]);
$insertBike->execute([105, 'VisibleBrand', 'DisabledBikeNeedle', 'Sport', null]);
$insertBike->execute([106, 'VisibleBrand', 'BlockedBikeNeedle', 'Sport', null]);
$insertBike->execute([107, 'VisibleBrand', 'BlockedBikeNeedle', 'Sport', null]);
$insertBike->execute([108, 'Yamaha', 'FriendBikeNeedle', 'Touring', 'uploads/motor/friend-fixture.jpg']);
$insertBike->execute([109, 'Yamaha', 'StrangerBikeNeedle', 'Touring', 'uploads/motor/stranger-fixture.jpg']);
$insertBike->execute([111, 'LegacyBrand', 'LegacyBikeNeedle', 'Touring', 'uploads/motor/legacy-fixture.jpg']);
$insertBike->execute([112, 'Ducati', 'PublicBikePrivatePhotoNeedle', 'Sport', 'uploads/motor/hidden-gallery-fixture.jpg']);
$pdo->exec("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES
    (100,106,'blocked'),(107,100,'blocked'),(108,100,'accepted')");

$searchTestChecks = 0;
function search_check(bool $condition, string $message): void
{
    global $searchTestChecks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $searchTestChecks++;
    echo "PASS: {$message}\n";
}

function search_render(array $params): array
{
    global $searchTestRoot;
    $_GET = $params;
    $uid = 100;
    $pdo = db();
    ob_start();
    try {
        require $searchTestRoot . '/pages/views/search.php';
        $html = ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
    $document = new DOMDocument();
    $previousErrors = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }
    return [
        'html' => $html,
        'ids' => array_map('intval', array_column($results, 'id_user')),
        'total' => $total,
        'pagination' => $pagination,
        'rows' => $results,
        'xpath' => new DOMXPath($document),
    ];
}

foreach ([
    'Nadia' => 101,
    'nadia_handle' => 101,
    'Bandung' => 101,
    'Jawa Barat' => 101,
    'HondaNeedle' => 101,
    'StreetFoxNeedle' => 101,
    'RoadsterNeedle' => 101,
] as $term => $expectedId) {
    $result = search_render(['q' => $term]);
    search_check($result['ids'] === [$expectedId] && $result['total'] === 1, 'global term matches public ' . $term . ' without duplicate riders');
}
$result = search_render(['q' => '  Nadia  ']);
search_check($result['ids'] === [101] && $result['xpath']->query('//input[@name="q"]')->item(0)?->getAttribute('value') === 'Nadia', 'keyword whitespace is trimmed and the active value is retained');

foreach (['SecretCityNeedle', 'SecretRegionNeedle', 'SecretBrandNeedle', 'SecretModelNeedle', 'SecretTypeNeedle'] as $term) {
    $result = search_render(['q' => $term]);
    search_check($result['total'] === 0 && $result['ids'] === [], 'private field cannot reveal a rider through term ' . $term);
}
$result = search_render(['q' => 'Hidden Location Rider']);
search_check($result['ids'] === [102] && !str_contains($result['html'], 'SecretCityNeedle') && !str_contains($result['html'], 'SecretRegionNeedle'), 'matching a public name still hides the private city and region');
$result = search_render(['q' => 'Private Garage Rider']);
search_check($result['ids'] === [103] && $result['rows'][0]['bikes'] === null && $result['rows'][0]['photos'] === null, 'matching a public name cannot expose a private garage or its photos');
search_check(!str_contains($result['html'], 'SecretModelNeedle') && !str_contains($result['html'], 'private-fixture.jpg'), 'private motorcycle details stay absent from rendered result cards');

foreach ([
    'Viewer Self Needle', 'Private Profile Needle', 'ProfileCityNeedle', 'PrivateProfileBikeNeedle',
    'Disabled Search Needle', 'DisabledCityNeedle', 'DisabledBikeNeedle',
    'Viewer Blocked Needle', 'Blocked Viewer Needle', 'BlockedBikeNeedle', 'Stranger Profile Needle',
] as $term) {
    $result = search_render(['q' => $term]);
    search_check($result['total'] === 0 && $result['ids'] === [], 'profile access/searchability/block rules also apply to global term ' . $term);
}
$result = search_render(['q' => 'FriendBikeNeedle']);
search_check($result['ids'] === [108] && str_contains($result['html'], 'friend-fixture.jpg'), 'accepted friendship permits matching and displaying friend-visible bikes and gallery');
$result = search_render(['q' => 'StrangerBikeNeedle']);
search_check($result['total'] === 0, 'a stranger cannot match a motorcycle visible only to friends');
$result = search_render(['q' => 'Stranger Garage Rider']);
search_check($result['ids'] === [109] && $result['rows'][0]['bikes'] === null && $result['rows'][0]['photos'] === null, 'a stranger public profile does not expose its friend-visible garage');
$result = search_render(['q' => 'LegacyBikeNeedle']);
search_check($result['ids'] === [111] && str_contains($result['html'], 'legacy-fixture.jpg'), 'legacy rider without settings uses public motorcycle defaults');
$result = search_render(['q' => 'LegacyCityNeedle']);
search_check($result['ids'] === [111], 'legacy rider without settings uses visible city defaults');
$result = search_render(['q' => 'PublicBikePrivatePhotoNeedle']);
search_check($result['ids'] === [112] && $result['rows'][0]['photos'] === null && !str_contains($result['html'], 'hidden-gallery-fixture.jpg'), 'global bike matching respects gallery privacy independently of motorcycle visibility');

$result = search_render(['q' => 'Nadia', 'brand' => 'HondaNeedle', 'city' => 'Bandung']);
search_check($result['ids'] === [101], 'global keyword composes with existing motorcycle and location filters');
$result = search_render(['q' => 'Nadia', 'model' => 'NoSuchModel']);
search_check($result['total'] === 0, 'a matching name does not bypass an explicit motorcycle filter');
$result = search_render(['q' => 'Nadia', 'city' => 'NoSuchCity']);
search_check($result['total'] === 0, 'a matching name does not bypass an explicit city filter');
$result = search_render(['q' => "' OR 1=1 --"]);
search_check($result['total'] === 0, 'SQL-like keyword is treated as a search value and cannot widen results');

$hostileTerm = '"><img src=x onerror=alert(1)><script>alert(1)</script>';
$result = search_render(['q' => $hostileTerm]);
search_check($result['xpath']->query('//input[@name="q"]')->item(0)?->getAttribute('value') === $hostileTerm, 'hostile keyword round-trips as the exact input value');
search_check($result['xpath']->query('//script | //*[@onerror or @onload]')->length === 0, 'hostile keyword cannot create executable markup');

$pageTerm = 'Grid " & <rider>';
for ($number = 1; $number <= 11; $number++) {
    $addRider(200 + $number, $pageTerm . ' ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'grid_' . $number, 'PageCity');
}
$addRider(300, $pageTerm . ' Private', 'grid_private', 'PageCity', null, ['profile_visibility' => 'private']);
$addRider(301, $pageTerm . ' Disabled', 'grid_disabled', 'PageCity', null, ['is_searchable' => 0]);
$addRider(302, $pageTerm . ' Blocked', 'grid_blocked', 'PageCity');
$pdo->exec("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES(302,100,'blocked')");

$first = search_render(['q' => $pageTerm, 'city' => 'PageCity', 'p' => 1]);
$second = search_render(['q' => $pageTerm, 'city' => 'PageCity', 'p' => 2]);
search_check($first['total'] === 11 && $second['total'] === 11 && $first['pagination']['total_pages'] === 2, 'pagination counts only accessible matching riders');
search_check($first['ids'] === range(201, 209) && $second['ids'] === [210, 211], 'global search has stable, disjoint pages with nine results per page');
search_check($first['xpath']->query('//input[@name="q"]')->item(0)?->getAttribute('value') === $pageTerm && $second['xpath']->query('//input[@name="q"]')->item(0)?->getAttribute('value') === $pageTerm, 'escaped keyword persists in the filter input on every page');
$links = $first['xpath']->query('//nav[contains(concat(" ",normalize-space(@class)," ")," pagination ")]//a');
search_check($links->length === 2, 'global search renders links for both result pages');
foreach ($links as $link) {
    $queryString = parse_url($link->getAttribute('href'), PHP_URL_QUERY);
    parse_str((string) $queryString, $linkParams);
    search_check(($linkParams['q'] ?? null) === $pageTerm && ($linkParams['city'] ?? null) === 'PageCity' && ($linkParams['page'] ?? null) === 'search', 'pagination link retains the escaped global keyword and existing filters');
}
search_check($first['xpath']->query('//script | //*[@onerror or @onload]')->length === 0, 'special characters in matching rider names stay escaped in cards and links');
$result = search_render(['q' => $pageTerm, 'p' => 999]);
search_check($result['pagination']['page'] === 2 && $result['ids'] === [210, 211], 'out-of-range page clamps to the final matching page');
$result = search_render(['q' => $pageTerm, 'p' => 0]);
search_check($result['pagination']['page'] === 1 && $result['ids'] === range(201, 209), 'nonpositive page starts at the first matching page');

echo "{$searchTestChecks} global search regression checks passed; all database fixtures were temporary.\n";
