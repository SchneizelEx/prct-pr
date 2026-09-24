<?php
require_once __DIR__ . '/bootstrap.php';

function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        if (empty($_SESSION['user_id'])) {
            $user = null;
        } else {
            $stmt = get_pdo()->prepare('SELECT id, full_name, username, role, position FROM staff WHERE id = ? AND is_active = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        redirect('login.php');
    }
    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash_set('error', 'หน้านี้สำหรับผู้ดูแลระบบเท่านั้น');
        redirect('index.php');
    }
    return $user;
}

/**
 * ตรวจสอบว่าบุคลากรที่ล็อกอินอยู่ถูกมอบหมายในสายที่ระบุหรือไม่ (แอดมินผ่านเสมอ)
 */
function require_line_access(int $lineId): array
{
    $user = require_login();
    if ($user['role'] === 'admin') {
        return $user;
    }
    $stmt = get_pdo()->prepare('SELECT 1 FROM line_staff WHERE line_id = ? AND staff_id = ?');
    $stmt->execute([$lineId, $user['id']]);
    if (!$stmt->fetch()) {
        flash_set('error', 'คุณไม่ได้รับมอบหมายในสายนี้');
        redirect('lines.php');
    }
    return $user;
}
