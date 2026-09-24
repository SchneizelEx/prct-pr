<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$pdo = get_pdo();

/**
 * อ่านและตรวจสอบข้อมูลฟอร์มโรงเรียน (ใช้ร่วมกันทั้งเพิ่มและแก้ไข)
 * คืนค่า [ค่าที่พร้อมบันทึก, ข้อความ error หรือ null]
 */
function parse_school_input(array $post): array
{
    $v = [
        'school_code'   => trim($post['school_code'] ?? ''),
        'name'          => trim($post['name'] ?? ''),
        'district'      => trim($post['district'] ?? ''),
        'province'      => trim($post['province'] ?? ''),
        'address'       => trim($post['address'] ?? ''),
        'latitude'      => trim($post['latitude'] ?? ''),
        'longitude'     => trim($post['longitude'] ?? ''),
        'contact_name'  => trim($post['contact_name'] ?? ''),
        'contact_phone' => trim($post['contact_phone'] ?? ''),
    ];

    if ($v['name'] === '') {
        return [$v, 'กรุณากรอกชื่อโรงเรียน'];
    }
    if ($v['school_code'] !== '' && !preg_match('/^[0-9A-Za-z\-]{1,20}$/', $v['school_code'])) {
        return [$v, 'รหัสโรงเรียนต้องเป็นตัวเลข/ตัวอักษร ไม่เกิน 20 ตัว'];
    }
    if ($v['latitude'] !== '' || $v['longitude'] !== '') {
        if (!is_numeric($v['latitude']) || !is_numeric($v['longitude'])) {
            return [$v, 'กรุณากรอกละติจูดและลองจิจูดให้ครบทั้งคู่ เป็นตัวเลข'];
        }
        if ($v['latitude'] < -90 || $v['latitude'] > 90 || $v['longitude'] < -180 || $v['longitude'] > 180) {
            return [$v, 'ค่าพิกัดอยู่นอกช่วงที่ถูกต้อง (ละติจูด -90 ถึง 90, ลองจิจูด -180 ถึง 180)'];
        }
    }

    foreach ($v as $k => $val) {
        $v[$k] = $val === '' ? null : $val;
    }
    $v['name'] = trim($post['name']);
    return [$v, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $action = $_POST['action'] ?? '';
    $back = isset($_POST['back']) ? '?' . $_POST['back'] : '';

    if ($action === 'create' || $action === 'update') {
        [$v, $error] = parse_school_input($_POST);
        $id = (int) ($_POST['id'] ?? 0);

        if ($error) {
            flash_set('error', $error);
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare('
                        INSERT INTO schools (school_code, name, district, province, address, latitude, longitude, contact_name, contact_phone)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute(array_values($v));
                    flash_set('success', 'เพิ่มโรงเรียนเรียบร้อยแล้ว');
                } else {
                    $stmt = $pdo->prepare('
                        UPDATE schools SET school_code = ?, name = ?, district = ?, province = ?, address = ?,
                               latitude = ?, longitude = ?, contact_name = ?, contact_phone = ?
                        WHERE id = ?
                    ');
                    $stmt->execute([...array_values($v), $id]);
                    flash_set('success', 'แก้ไขข้อมูลโรงเรียนเรียบร้อยแล้ว');
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] === 1062) {
                    flash_set('error', 'รหัสโรงเรียนนี้ถูกใช้กับโรงเรียนอื่นในระบบแล้ว');
                } else {
                    throw $e;
                }
            }
        }
        redirect('schools.php' . ($action === 'update' ? $back : ''));
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
    $where[] = '(name LIKE ? OR district LIKE ? OR province LIKE ? OR school_code LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
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
            <label class="form-label">รหัสโรงเรียน (กระทรวงศึกษาธิการ)</label>
            <input type="text" name="school_code" class="form-control" maxlength="20" placeholder="เช่น 1010720001">
          </div>
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
                <th>รหัส</th>
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
                <tr><td colspan="7" class="text-center text-muted py-4">ไม่พบข้อมูลโรงเรียน</td></tr>
              <?php endif; ?>
              <?php foreach ($schoolList as $sc): ?>
                <tr class="<?= $sc['is_active'] ? '' : 'text-muted' ?>">
                  <td class="small"><?= $sc['school_code'] ? '<code>' . h($sc['school_code']) . '</code>' : '<span class="text-muted">—</span>' ?></td>
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
                  <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-school" title="แก้ไข"
                            data-school="<?= h(json_encode([
                                'id' => (int) $sc['id'], 'school_code' => $sc['school_code'], 'name' => $sc['name'],
                                'district' => $sc['district'], 'province' => $sc['province'], 'address' => $sc['address'],
                                'latitude' => $sc['latitude'], 'longitude' => $sc['longitude'],
                                'contact_name' => $sc['contact_name'], 'contact_phone' => $sc['contact_phone'],
                            ], JSON_UNESCAPED_UNICODE)) ?>">
                      <i class="bi bi-pencil-square"></i>
                    </button>
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

<?php if ($user['role'] === 'admin'): ?>
<!-- Modal: edit school -->
<div class="modal fade" id="editSchoolModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="back" value="<?= h(http_build_query(['q' => $q, 'page' => $page])) ?>">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลโรงเรียน</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">รหัสโรงเรียน (กระทรวงศึกษาธิการ)</label>
              <input type="text" name="school_code" id="edit_school_code" class="form-control" maxlength="20">
            </div>
            <div class="col-md-8">
              <label class="form-label">ชื่อโรงเรียน <span class="text-danger">*</span></label>
              <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">อำเภอ</label>
              <input type="text" name="district" id="edit_district" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">จังหวัด</label>
              <input type="text" name="province" id="edit_province" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">ที่อยู่</label>
              <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">ละติจูด</label>
              <input type="text" name="latitude" id="edit_latitude" class="form-control" placeholder="เช่น 13.7563">
            </div>
            <div class="col-md-6">
              <label class="form-label">ลองจิจูด</label>
              <input type="text" name="longitude" id="edit_longitude" class="form-control" placeholder="เช่น 100.5018">
            </div>
            <div class="col-md-6">
              <label class="form-label">ผู้ประสานงาน</label>
              <input type="text" name="contact_name" id="edit_contact_name" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">เบอร์โทรผู้ประสานงาน</label>
              <input type="text" name="contact_phone" id="edit_contact_phone" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึกการแก้ไข</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalEl = document.getElementById('editSchoolModal');
  var modal = new bootstrap.Modal(modalEl);
  document.querySelectorAll('.btn-edit-school').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var s = JSON.parse(btn.dataset.school);
      ['id', 'school_code', 'name', 'district', 'province', 'address', 'latitude', 'longitude', 'contact_name', 'contact_phone']
        .forEach(function (k) { document.getElementById('edit_' + k).value = s[k] == null ? '' : s[k]; });
      modal.show();
    });
  });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
