<?php
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = get_pdo()->prepare('
    SELECT id, name, district, province
    FROM schools
    WHERE is_active = 1 AND (name LIKE ? OR district LIKE ? OR province LIKE ?)
    ORDER BY name ASC
    LIMIT 20
');
$like = '%' . $q . '%';
$stmt->execute([$like, $like, $like]);

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
