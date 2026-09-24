<?php
/**
 * สร้าง (หรือรีเซ็ตรหัสผ่านของ) บัญชีผู้ดูแลระบบ — ใช้ตอนติดตั้งครั้งแรกบนเครื่องใหม่
 * ที่ยังไม่มีผู้ใช้เลยในตาราง staff (schema.sql ไม่ได้สร้างบัญชีเริ่มต้นให้)
 *
 * ใช้งาน: php database/create_admin.php <username> <password> [full_name]
 * ถ้า username นั้นมีอยู่แล้ว จะอัพเดทรหัสผ่านและตั้งสิทธิ์เป็น admin ให้แทน
 */

require_once __DIR__ . '/../includes/db.php';

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;
$fullName = $argv[3] ?? 'ผู้ดูแลระบบ';

if (!$username || !$password) {
    fwrite(STDERR, "Usage: php database/create_admin.php <username> <password> [full_name]\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "รหัสผ่านควรมีอย่างน้อย 8 ตัวอักษร\n");
    exit(1);
}

$pdo = get_pdo();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM staff WHERE username = ?');
$stmt->execute([$username]);
$existing = $stmt->fetch();

if ($existing) {
    $update = $pdo->prepare('UPDATE staff SET password_hash = ?, role = ?, is_active = 1 WHERE id = ?');
    $update->execute([$hash, 'admin', $existing['id']]);
    echo "อัพเดทผู้ใช้ '$username' เดิมให้เป็นแอดมินพร้อมรหัสผ่านใหม่แล้ว\n";
} else {
    $insert = $pdo->prepare('INSERT INTO staff (full_name, username, password_hash, role) VALUES (?, ?, ?, ?)');
    $insert->execute([$fullName, $username, $hash, 'admin']);
    echo "สร้างบัญชีแอดมิน '$username' เรียบร้อยแล้ว\n";
}
