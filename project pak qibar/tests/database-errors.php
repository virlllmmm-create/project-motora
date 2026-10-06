<?php
declare(strict_types=1);

require __DIR__ . '/../includes/database-error.php';

$cases = [1049, 1045, 1044, 2002, 2003];
foreach ($cases as $code) {
    $error = new PDOException('Secret credentials and internal server details', 0);
    $error->errorInfo = ['HY000', $code, $error->getMessage()];
    $issue = database_connection_issue($error);
    if ($issue['code'] !== (string) $code || str_contains(implode(' ', $issue), 'Secret credentials')) {
        throw new RuntimeException('Incorrect or unsafe database diagnosis for ' . $code);
    }
}
$missingDriver = database_connection_issue(new PDOException('could not find driver'));
if ($missingDriver['code'] !== 'PDO_MYSQL') {
    throw new RuntimeException('Missing driver was not diagnosed.');
}
$unknown = database_connection_issue(new RuntimeException('Internal path and password'));
if ($unknown['code'] !== 'DB_CONNECTION' || str_contains(implode(' ', $unknown), 'Internal path and password')) {
    throw new RuntimeException('Unknown connection errors expose internal details.');
}
echo "PASS: connection failures are classified without exposing raw exception details.\n";
