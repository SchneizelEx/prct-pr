<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$authUser = current_user();

function nav_active(string $page, string $current): string
{
    return $page === $current ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? h($pageTitle) . ' - ' : '' ?>ระบบติดตามการออกประชาสัมพันธ์รับนักเรียนใหม่</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css?v=<?= filemtime(__DIR__ . '/../public/assets/style.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><i class="bi bi-signpost-2"></i> ระบบแนะแนวรับนักเรียน</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link<?= nav_active('index.php', $currentPage) ?>" href="index.php"><i class="bi bi-speedometer2"></i> แดชบอร์ด</a></li>
        <li class="nav-item"><a class="nav-link<?= nav_active('lines.php', $currentPage) ?>" href="lines.php"><i class="bi bi-signpost-split"></i> สายแนะแนว</a></li>
        <?php if ($authUser && $authUser['role'] === 'admin'): ?>
        <li class="nav-item"><a class="nav-link<?= nav_active('staff.php', $currentPage) ?>" href="staff.php"><i class="bi bi-people"></i> บุคลากร</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link<?= nav_active('schools.php', $currentPage) ?>" href="schools.php"><i class="bi bi-building"></i> โรงเรียน</a></li>
        <li class="nav-item"><a class="nav-link<?= nav_active('reports.php', $currentPage) ?>" href="reports.php"><i class="bi bi-bar-chart"></i> รายงานผล</a></li>
      </ul>
      <?php if ($authUser): ?>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= h($authUser['full_name']) ?>
            <span class="badge bg-light text-dark ms-1"><?= $authUser['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'บุคลากร' ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a></li>
          </ul>
        </li>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container py-4">
<?php flash_render(); ?>
