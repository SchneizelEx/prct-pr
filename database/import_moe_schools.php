<?php
/**
 * นำเข้ารายชื่อสถานศึกษาทั้งหมดในประเทศไทยจากชุดข้อมูลเปิดกระทรวงศึกษาธิการ (data.go.th: thailand-school)
 * ไม่มีพิกัด — ปล่อย latitude/longitude เป็น NULL ให้แอดมินเติมทีหลัง
 * ข้ามรายการที่มีชื่อซ้ำกับที่มีอยู่แล้ว (เช่นจากการนำเข้า OSM ก่อนหน้า) เพื่อลดการซ้ำซ้อน
 *
 * ใช้ครั้งเดียวตอนติดตั้ง: php database/import_moe_schools.php <path-to-thailand_school.csv>
 */

require_once __DIR__ . '/../includes/db.php';

$csvPath = $argv[1] ?? null;
if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php import_moe_schools.php <thailand_school.csv>\n");
    exit(1);
}

$provinces = [
    'กรุงเทพมหานคร', 'กระบี่', 'กาญจนบุรี', 'กาฬสินธุ์', 'กำแพงเพชร', 'ขอนแก่น', 'จันทบุรี',
    'ฉะเชิงเทรา', 'ชลบุรี', 'ชัยนาท', 'ชัยภูมิ', 'ชุมพร', 'เชียงราย', 'เชียงใหม่', 'ตรัง', 'ตราด',
    'ตาก', 'นครนายก', 'นครปฐม', 'นครพนม', 'นครราชสีมา', 'นครศรีธรรมราช', 'นครสวรรค์', 'นนทบุรี',
    'นราธิวาส', 'น่าน', 'บึงกาฬ', 'บุรีรัมย์', 'ปทุมธานี', 'ประจวบคีรีขันธ์', 'ปราจีนบุรี', 'ปัตตานี',
    'พระนครศรีอยุธยา', 'พังงา', 'พัทลุง', 'พิจิตร', 'พิษณุโลก', 'เพชรบุรี', 'เพชรบูรณ์', 'แพร่',
    'ภูเก็ต', 'มหาสารคาม', 'มุกดาหาร', 'แม่ฮ่องสอน', 'ยโสธร', 'ยะลา', 'ร้อยเอ็ด', 'ระนอง', 'ระยอง',
    'ราชบุรี', 'ลพบุรี', 'ลำปาง', 'ลำพูน', 'เลย', 'ศรีสะเกษ', 'สกลนคร', 'สงขลา', 'สตูล', 'สมุทรปราการ',
    'สมุทรสงคราม', 'สมุทรสาคร', 'สระแก้ว', 'สระบุรี', 'สิงห์บุรี', 'สุโขทัย', 'สุพรรณบุรี', 'สุราษฎร์ธานี',
    'สุรินทร์', 'หนองคาย', 'หนองบัวลำภู', 'อ่างทอง', 'อำนาจเจริญ', 'อุดรธานี', 'อุตรดิตถ์', 'อุทัยธานี',
    'อุบลราชธานี',
];
usort($provinces, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

function extract_province(string $address, array $provinces): ?string
{
    foreach ($provinces as $p) {
        if (mb_strpos($address, $p) !== false) {
            return $p;
        }
    }
    return null;
}

function extract_district(string $address): ?string
{
    if (preg_match('/เขต([ก-๙]+)/u', $address, $m)) {
        return $m[1];
    }
    if (preg_match('/อำเภอ([ก-๙]+)/u', $address, $m)) {
        return $m[1];
    }
    return null;
}

$pdo = get_pdo();

$existsStmt = $pdo->prepare('SELECT 1 FROM schools WHERE name = ? LIMIT 1');
$insertStmt = $pdo->prepare('
    INSERT INTO schools (name, district, province, address, contact_phone)
    VALUES (?, ?, ?, ?, ?)
');

$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle);
// strip BOM from first header cell if present
if (isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
}

$imported = 0;
$skippedDupe = 0;
$skippedEmpty = 0;

while (($row = fgetcsv($handle)) !== false) {
    $name = trim($row[1] ?? '');
    $address = trim($row[2] ?? '');
    $phone = trim($row[3] ?? '');

    if ($name === '') {
        $skippedEmpty++;
        continue;
    }

    $existsStmt->execute([$name]);
    if ($existsStmt->fetch()) {
        $skippedDupe++;
        continue;
    }

    $province = $address !== '' ? extract_province($address, $provinces) : null;
    $district = $address !== '' ? extract_district($address) : null;

    $insertStmt->execute([
        mb_substr($name, 0, 200),
        $district ? mb_substr($district, 0, 100) : null,
        $province ? mb_substr($province, 0, 100) : null,
        $address ?: null,
        $phone ? mb_substr($phone, 0, 20) : null,
    ]);
    $imported++;
}
fclose($handle);

echo "Imported: $imported, Skipped (duplicate name): $skippedDupe, Skipped (empty name): $skippedEmpty\n";
