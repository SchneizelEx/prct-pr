<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $lineDate = $_POST['line_date'] ?? '';
        $lineName = trim($_POST['line_name'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');
        $staffIds  = array_map('intval', $_POST['staff_ids'] ?? []);
        $schoolIds = array_map('intval', $_POST['school_ids'] ?? []);
        // ผู้สร้างต้องอยู่ในสายเสมอ ไม่เช่นนั้นจะเข้าดูสายที่เพิ่งสร้างเองไม่ได้
        $staffIds[] = $user['id'];

        $errors = [];
        if ($lineDate === '' || !DateTime::createFromFormat('Y-m-d', $lineDate)) {
            $errors[] = 'กรุณาเลือกวันที่ออกแนะแนว';
        }
        if ($lineName === '') {
            $errors[] = 'กรุณากรอกชื่อสาย';
        }
        if (!$schoolIds) {
            $errors[] = 'กรุณาเลือกโรงเรียนเป้าหมายอย่างน้อย 1 แห่ง';
        }

        if ($errors) {
            foreach ($errors as $e) {
                flash_set('error', $e);
            }
            redirect('lines.php');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO outreach_lines (line_date, line_name, notes, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$lineDate, $lineName, $notes ?: null, $user['id']]);
            $lineId = (int) $pdo->lastInsertId();

            $staffStmt = $pdo->prepare('INSERT IGNORE INTO line_staff (line_id, staff_id) VALUES (?, ?)');
            foreach (array_unique($staffIds) as $sid) {
                $staffStmt->execute([$lineId, $sid]);
            }

            $schoolStmt = $pdo->prepare('INSERT IGNORE INTO line_schools (line_id, school_id, visit_order) VALUES (?, ?, ?)');
            $order = 1;
            foreach (array_unique($schoolIds) as $scid) {
                $schoolStmt->execute([$lineId, $scid, $order++]);
            }

            $pdo->commit();
            flash_set('success', 'สร้างสายออกแนะแนวเรียบร้อยแล้ว');
            redirect('line_detail.php?id=' . $lineId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', 'เกิดข้อผิดพลาด: ไม่สามารถสร้างสายได้');
            redirect('lines.php');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT created_by FROM outreach_lines WHERE id = ?');
        $stmt->execute([$id]);
        $createdBy = $stmt->fetchColumn();

        if ($user['role'] !== 'admin' && (int) $createdBy !== $user['id']) {
            flash_set('error', 'คุณลบได้เฉพาะสายที่คุณสร้างเอง');
            redirect('lines.php');
        }

        $stmt = $pdo->prepare('DELETE FROM outreach_lines WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('success', 'ลบสายแนะแนวเรียบร้อยแล้ว');
        redirect('lines.php');
    }
}

$isAdmin = $user['role'] === 'admin';
$staffOptions = $pdo->query('SELECT id, full_name, position FROM staff WHERE is_active = 1 ORDER BY full_name')->fetchAll();

$linesSql = '
    SELECT ol.*,
           COUNT(DISTINCT ls.school_id) AS school_count,
           COUNT(DISTINCT lst.staff_id) AS staff_count,
           COUNT(DISTINCT vr.id) AS reported_count
    FROM outreach_lines ol
    LEFT JOIN line_schools ls ON ls.line_id = ol.id
    LEFT JOIN line_staff lst ON lst.line_id = ol.id
    LEFT JOIN visit_reports vr ON vr.line_school_id = ls.id
';
$linesParams = [];
if (!$isAdmin) {
    $linesSql .= ' WHERE EXISTS (SELECT 1 FROM line_staff lst2 WHERE lst2.line_id = ol.id AND lst2.staff_id = ?)';
    $linesParams[] = $user['id'];
}
$linesSql .= ' GROUP BY ol.id ORDER BY ol.line_date DESC, ol.id DESC';
$stmt = $pdo->prepare($linesSql);
$stmt->execute($linesParams);
$lines = $stmt->fetchAll();

require_once __DIR__ . '/../includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-signpost-split"></i> สายออกแนะแนว</h4>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLineModal">
    <i class="bi bi-plus-lg"></i> ตั้งสายใหม่
  </button>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>วันที่</th>
            <th>ชื่อสาย</th>
            <th>บุคลากร</th>
            <th>โรงเรียน</th>
            <th>ความคืบหน้ารายงาน</th>
            <th>สถานะ</th>
            <th class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$lines): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีสายออกแนะแนว</td></tr>
          <?php endif; ?>
          <?php foreach ($lines as $l): ?>
            <tr>
              <td><?= h((new DateTime($l['line_date']))->format('d/m/Y')) ?></td>
              <td><a href="line_detail.php?id=<?= (int) $l['id'] ?>"><?= h($l['line_name']) ?></a></td>
              <td><?= (int) $l['staff_count'] ?> คน</td>
              <td><?= (int) $l['school_count'] ?> แห่ง</td>
              <td>
                <?= (int) $l['reported_count'] ?> / <?= (int) $l['school_count'] ?>
                <?php if ($l['school_count'] > 0 && $l['reported_count'] == $l['school_count']): ?>
                  <span class="badge bg-success">ครบ</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                $statusLabel = ['planned' => 'วางแผน', 'in_progress' => 'กำลังดำเนินการ', 'completed' => 'เสร็จสิ้น'];
                $statusColor = ['planned' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success'];
                ?>
                <span class="badge bg-<?= $statusColor[$l['status']] ?>"><?= $statusLabel[$l['status']] ?></span>
              </td>
              <td class="text-end">
                <a href="line_detail.php?id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i> ดู
                </a>
                <?php if ($isAdmin || (int) $l['created_by'] === $user['id']): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('ยืนยันการลบสายนี้? รายงานที่เกี่ยวข้องจะถูกลบด้วย');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: create line -->
<div class="modal fade" id="createLineModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-signpost-2"></i> ตั้งสายออกแนะแนวใหม่</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">วันที่ออกแนะแนว <span class="text-danger">*</span></label>
              <input type="date" name="line_date" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">ชื่อสาย <span class="text-danger">*</span></label>
              <input type="text" name="line_name" class="form-control" placeholder="เช่น สายเหนือ 1" required>
            </div>
            <div class="col-12">
              <label class="form-label">หมายเหตุ</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">เลือกบุคลากรเพิ่มเติม (เลือกได้หลายคน)</label>
              <p class="small text-muted mb-1">คุณจะถูกเพิ่มเข้าสายนี้โดยอัตโนมัติ</p>
              <div class="staff-pick-list">
                <?php if (!$staffOptions): ?>
                  <p class="text-muted small mb-0">ยังไม่มีบุคลากรอื่น</p>
                <?php endif; ?>
                <?php foreach ($staffOptions as $s): ?>
                  <?php if ((int) $s['id'] === $user['id']) continue; ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="staff_ids[]" value="<?= (int) $s['id'] ?>" id="st_<?= (int) $s['id'] ?>">
                    <label class="form-check-label" for="st_<?= (int) $s['id'] ?>">
                      <?= h($s['full_name']) ?>
                      <?php if ($s['position']): ?><span class="text-muted small"> (<?= h($s['position']) ?>)</span><?php endif; ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">เลือกโรงเรียนเป้าหมาย (เลือกได้หลายแห่ง) <span class="text-danger">*</span></label>
              <div class="school-picker">
                <input type="text" class="form-control school-search-input mb-2" placeholder="พิมพ์ชื่อโรงเรียน / อำเภอ / จังหวัด อย่างน้อย 2 ตัวอักษร">
                <div class="list-group school-search-results mb-2" style="max-height:180px; overflow-y:auto;"></div>
                <div class="school-selected-list"></div>
                <div class="school-selected-inputs"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> สร้างสาย</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
