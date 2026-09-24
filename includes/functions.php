<?php

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $flash) {
        $type = $flash['type'] === 'error' ? 'danger' : $flash['type'];
        echo '<div class="alert alert-' . h($type) . ' alert-dismissible fade show" role="alert">'
            . h($flash['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
    }
    unset($_SESSION['flash']);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

/**
 * บันทึกไฟล์รูปที่อัพโหลด คืนค่า path สัมพัทธ์ (relative to public/) หรือ null ถ้าไม่มีไฟล์
 * โยน Exception ถ้าไฟล์ไม่ผ่านการตรวจสอบ
 */
function save_uploaded_photo(array $file): string
{
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $maxBytes = 8 * 1024 * 1024; // 8MB

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('กรุณาเลือกรูปถ่าย');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัพโหลดไฟล์ไม่สำเร็จ (code ' . $file['error'] . ')');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('ไฟล์ใหญ่เกินไป (จำกัด 8MB)');
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, WEBP');
    }

    $subdir = 'uploads/reports/' . date('Y/m');
    $absDir = __DIR__ . '/../public/' . $subdir;
    if (!is_dir($absDir)) {
        mkdir($absDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $relPath  = $subdir . '/' . $filename;
    $absPath  = $absDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }

    return $relPath;
}
