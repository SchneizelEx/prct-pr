<?php
/**
 * แก้ไขการดึงชื่ออำเภอจากที่อยู่ (address) สำหรับโรงเรียนที่ยังไม่มี district
 * รองรับทั้งรูปแบบเต็ม "อำเภอ.../เขต..." และแบบย่อ "อ. .../ก.ข.เขต..."
 */

require_once __DIR__ . '/../includes/db.php';

function extract_district(string $address): ?string
{
    if (preg_match('/เขต\s*([ก-๙]+)/u', $address, $m)) {
        return $m[1];
    }
    if (preg_match('/อำเภอ\s*([ก-๙]+)/u', $address, $m)) {
        return $m[1];
    }
    if (preg_match('/อ\.\s*([ก-๙]+)/u', $address, $m)) {
        return $m[1];
    }
    return null;
}

$pdo = get_pdo();
$select = $pdo->query("SELECT id, address FROM schools WHERE district IS NULL AND address IS NOT NULL AND address != ''");
$update = $pdo->prepare('UPDATE schools SET district = ? WHERE id = ?');

$fixed = 0;
foreach ($select->fetchAll() as $row) {
    $district = extract_district($row['address']);
    if ($district) {
        $update->execute([mb_substr($district, 0, 100), $row['id']]);
        $fixed++;
    }
}

echo "Fixed: $fixed\n";
