<?php
/**
 * นำเข้าข้อมูลโรงเรียนจาก OpenStreetMap (Overpass API) พร้อมพิกัดละติจูด/ลองจิจูด
 * ใช้ครั้งเดียวตอนติดตั้ง: php database/import_osm_schools.php <path-to-osm-json>
 */

require_once __DIR__ . '/../includes/db.php';

$jsonPath = $argv[1] ?? null;
if (!$jsonPath || !is_file($jsonPath)) {
    fwrite(STDERR, "Usage: php import_osm_schools.php <osm_schools.json>\n");
    exit(1);
}

$data = json_decode(file_get_contents($jsonPath), true);
if (!$data || !isset($data['elements'])) {
    fwrite(STDERR, "Invalid OSM JSON file\n");
    exit(1);
}

$pdo = get_pdo();
$insert = $pdo->prepare('
    INSERT INTO schools (name, district, province, latitude, longitude, contact_phone)
    VALUES (?, ?, ?, ?, ?, ?)
');

$imported = 0;
$skipped = 0;

foreach ($data['elements'] as $el) {
    $tags = $el['tags'] ?? [];
    $name = $tags['name'] ?? $tags['name:th'] ?? null;
    if (!$name) {
        $skipped++;
        continue;
    }

    if ($el['type'] === 'node') {
        $lat = $el['lat'] ?? null;
        $lon = $el['lon'] ?? null;
    } else {
        $lat = $el['center']['lat'] ?? null;
        $lon = $el['center']['lon'] ?? null;
    }
    if ($lat === null || $lon === null) {
        $skipped++;
        continue;
    }

    $district = $tags['addr:district'] ?? $tags['addr:subdistrict'] ?? null;
    $province = $tags['addr:province'] ?? null;
    $phone = $tags['phone'] ?? $tags['contact:phone'] ?? null;

    $insert->execute([
        mb_substr($name, 0, 200),
        $district ? mb_substr($district, 0, 100) : null,
        $province ? mb_substr($province, 0, 100) : null,
        round((float) $lat, 7),
        round((float) $lon, 7),
        $phone ? mb_substr($phone, 0, 20) : null,
    ]);
    $imported++;
}

echo "Imported: $imported, Skipped (no name/coords): $skipped\n";
