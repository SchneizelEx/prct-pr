<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name         = trim($_POST['name'] ?? '');
        $district     = trim($_POST['district'] ?? '');
        $province     = trim($_POST['province'] ?? '');
        $latitude     = trim($_POST['latitude'] ?? '');
        $longitude    = trim($_POST['longitude'] ?? '');
        $contactName  = trim($_POST['contact_name'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');

        if ($name === '') {
            flash_set('error', 'กรุณากรอกชื่อโรงเรียน');
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO schools (name, district, province, latitude, longitude, contact_name, contact_phone)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $name,
                $district ?: null,
                $province ?: null,
                $latitude !== '' ? $latitude : null,
                $longitude !== '' ? $longitude : null,
                $contactName ?: null,
                $contactPhone ?: null,
            ]);
            flash_set('success', 'เพิ่มโรงเรียนเรียบร้อยแล้ว');
        }
        redirect('schools.php');
    }

    if ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE schools SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$id]);
        redirect('schools.php' . (isset($_POST['back']) ? '?' . $_POST['back'] : ''));
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM schools WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('success', 'ลบโรงเรียนเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            flash_set('error', 'ไม่สามารถลบได้ เนื่องจากมีข้อมูลสายแนะแนวที่เกี่ยวข้องอยู่');
        }
        redirect('schools.php' . (isset($_POST['back']) ? '?' . $_POST['back'] : ''));
    }
}

$q = trim($_GET['q'] ?? '');
$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR district LIKE ? OR province LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM schools $whereSql");
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare("
    SELECT * FROM schools
    $whereSql
    ORDER BY is_active DESC, name ASC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$schoolList = $listStmt->fetchAll();

$withCoords = (int) $pdo->query('SELECT COUNT(*) FROM schools WHERE latitude IS NOT NULL')->fetchColumn();

require_once __DIR__ . '/../includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-building"></i> โรงเรียนเป้าหมาย</h4>
  <span class="text-muted small">ทั้งหมด <?= number_format($totalCount) ?> แห่ง (มีพิกัด <?= number_format($withCoords) ?> แห่ง)</span>
</div>

<div class="row g-4">
  <?php if ($user['role'] === 'admin'): ?>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title">เพิ่มโรงเรียนใหม่</h6>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <div class="mb-2">
            <label class="form-label">ชื่อโรงเรียน <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">อำเภอ</label>
            <input type="text" name="district" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">จังหวัด</label>
            <input type="text" name="province" class="form-control">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">ละติจูด</label>
              <input type="text" name="latitude" class="form-control" placeholder="เช่น 13.7563">
            </div>
            <div class="col-6">
              <label class="form-label">ลองจิจูด</label>
              <input type="text" name="longitude" class="form-control" placeholder="เช่น 100.5018">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">ผู้ประสานงาน</label>
            <input type="text" name="contact_name" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">เบอร์โทรผู้ประสานงาน</label>
            <input type="text" name="contact_phone" class="form-control">
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> เพิ่มโรงเรียน</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= $user['role'] === 'admin' ? 'col-lg-8' : 'col-12' ?>">
    <div class="card">
      <div class="card-body">
        <form method="get" class="mb-3">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="ค้นหาชื่อโรงเรียน / อำเภอ / จังหวัด" value="<?= h($q) ?>">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i> ค้นหา</button>
            <?php if ($q !== ''): ?>
              <a href="schools.php" class="btn btn-outline-secondary">ล้าง</a>
            <?php endif; ?>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>ชื่อโรงเรียน</th>
                <th>อำเภอ/จังหวัด</th>
                <th>พิกัด</th>
                <th>ผู้ประสานงาน</th>
                <th>สถานะ</th>
                <?php if ($user['role'] === 'admin'): ?><th class="text-end">จัดการ</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!$schoolList): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">ไม่พบข้อมูลโรงเรียน</td></tr>
              <?php endif; ?>
              <?php foreach ($schoolList as $sc): ?>
                <tr class="<?= $sc['is_active'] ? '' : 'text-muted' ?>">
                  <td><?= h($sc['name']) ?></td>
                  <td><?= h(trim(($sc['district'] ?? '') . ' ' . ($sc['province'] ?? ''))) ?></td>
                  <td>
                    <?php if ($sc['latitude'] !== null && $sc['longitude'] !== null): ?>
                      <a href="https://www.google.com/maps?q=<?= h($sc['latitude']) ?>,<?= h($sc['longitude']) ?>" target="_blank" rel="noopener" class="small">
                        <i class="bi bi-geo-alt"></i> แผนที่
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= h($sc['contact_name']) ?>
                    <?php if ($sc['contact_phone']): ?>
                      <div class="small text-muted"><?= h($sc['contact_phone']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($sc['is_active']): ?>
                      <span class="badge bg-success">ใช้งาน</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">ปิดใช้งาน</span>
                    <?php endif; ?>
                  </td>
                  <?php if ($user['role'] === 'admin'): ?>
                  <td class="text-end">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="id" value="<?= (int) $sc['id'] ?>">
                      <input type="hidden" name="back" value="<?= h(http_build_query(['q' => $q, 'page' => $page])) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" title="เปิด/ปิดใช้งาน">
                        <i class="bi bi-toggle2-on"></i>
                      </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('ยืนยันการลบโรงเรียนนี้?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $sc['id'] ?>">
                      <input type="hidden" name="back" value="<?= h(http_build_query(['q' => $q, 'page' => $page])) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1):
          $windowStart = max(1, $page - 3);
          $windowEnd = min($totalPages, $page + 3);
        ?>
        <nav>
          <ul class="pagination pagination-sm justify-content-center flex-wrap">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= h(http_build_query(['q' => $q, 'page' => 1])) ?>">&laquo;</a>
            </li>
            <?php if ($windowStart > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= h(http_build_query(['q' => $q, 'page' => $p])) ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
            <?php if ($windowEnd < $totalPages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= h(http_build_query(['q' => $q, 'page' => $totalPages])) ?>">&raquo;</a>
            </li>
          </ul>
          <p class="text-center text-muted small">หน้า <?= $page ?> จาก <?= $totalPages ?></p>
        </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
