<?php
declare(strict_types=1);

// Connection-local tables keep these action checks away from live rider data.
$root = getenv('MOTORA_GARAGE_TEST_ROOT') ?: dirname(__DIR__);
$actions = getenv('MOTORA_GARAGE_TEST_ACTIONS') ?: $root . '/includes/actions.php';
require $root . '/config/database.php';
require $root . '/includes/functions.php';
require $actions;
$pdo = db();
$pdo->exec("CREATE TEMPORARY TABLE `user` (
    id_user INT PRIMARY KEY,nama VARCHAR(100),username VARCHAR(50),email VARCHAR(150),
    role VARCHAR(30) DEFAULT 'rider',city VARCHAR(100),region VARCHAR(100),bio TEXT,
    profile_photo VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE motorcycles (
    id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,brand VARCHAR(80) NOT NULL,
    model VARCHAR(100) NOT NULL,type VARCHAR(80) DEFAULT '',year SMALLINT NULL,
    color VARCHAR(80) NULL,plate_number VARCHAR(20) NULL,description TEXT NULL,
    main_photo VARCHAR(255) NULL,is_primary TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$pdo->exec('CREATE TEMPORARY TABLE motorcycle_photos (id INT AUTO_INCREMENT PRIMARY KEY,motorcycle_id INT,path VARCHAR(255)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO `user`(id_user,nama,username,email) VALUES(100,'Garage Rider','garage_rider','garage@example.test')");
$_SESSION = ['user_id' => 100, 'csrf' => 'garage-test-token'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_FILES = [];
$editing = ($argv[1] ?? 'add') === 'edit';
if ($editing) {
    $pdo->exec("INSERT INTO motorcycles(id,user_id,brand,model,type,year,color,plate_number,description,is_primary)
        VALUES(1,100,'Honda','Old model','Sport',2020,'Merah','B 1234 XY','Cerita motor lama',1)");
}
$_POST = [
    'csrf' => $_SESSION['csrf'], 'action' => $editing ? 'motor_edit' : 'motor_add',
    'id' => 1, 'brand' => 'Yamaha', 'model' => 'R15 V4', 'type' => 'Sport',
    'year' => '2024', 'is_primary' => 'on',
];
register_shutdown_function(static function () use ($pdo, $editing): void {
    $row = $pdo->query('SELECT * FROM motorcycles WHERE user_id=100')->fetch();
    $expectedLegacy = $editing ? ['Merah', 'B 1234 XY', 'Cerita motor lama'] : [null, null, null];
    $valid = $row && $row['brand'] === 'Yamaha' && $row['model'] === 'R15 V4'
        && (int) $row['year'] === 2024 && (int) $row['is_primary'] === 1
        && [$row['color'], $row['plate_number'], $row['description']] === $expectedLegacy
        && ($_SESSION['flash']['type'] ?? '') === 'success';
    if (!$valid) {
        fwrite(STDERR, "FAIL: simplified garage action " . ($editing ? 'edit' : 'add') . "\n");
        exit(1);
    }
    echo 'PASS: ' . ($editing ? 'editing preserves existing color, plate and description' : 'adding works without color, plate and description') . "\n";
});
handle_action();
