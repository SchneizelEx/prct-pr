<?php
require_once __DIR__ . '/../includes/auth.php';

$pdo = get_pdo();
$lineId = (int) ($_GET['id'] ?? 0);

$line = $pdo->prepare('SELECT * FROM outreach_lines WHERE id = ?');
$line->execute([$lineId]);
$line = $line->fetch();

if (!$line) {
    flash_set('error', 'ไม่พบสายออกแนะแนวที่ระบุ');
    redirect('lines.php');
}

$user = require_line_access($lineId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['planned', 'in_progress', 'completed'], true)) {
            $stmt = $pdo->prepare('UPDATE outreach_lines SET status = ? WHERE id = ?');
            $stmt->execute([$status, $lineId]);
            flash_set('success', 'อัพเดทสถานะสายเรียบร้อยแล้ว');
        }
        redirect('line_detail.php?id=' . $lineId);
    }

    if ($action === 'remove_school') {
        $lineSchoolId = (int) ($_POST['line_school_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM line_schools WHERE id = ? AND line_id = ?');
        $stmt->execute([$lineSchoolId, $lineId]);
        flash_set('success', 'นำโรงเรียนออกจากสายเรียบร้อยแล้ว');
        redirect('line_detail.php?id=' . $lineId);
    }

    if ($action === 'add_schools') {
        $schoolIds = array_map('intval', $_POST['school_ids'] ?? []);
        if ($schoolIds) {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(visit_order), 0) FROM line_schools WHERE line_id = ?');
            $stmt->execute([$lineId]);
            $order = (int) $stmt->fetchColumn();

            $insert = $pdo->prepare('INSERT IGNORE INTO line_schools (line_id, school_id, visit_order) VALUES (?, ?, ?)');
            foreach (array_unique($schoolIds) as $scid) {
                $insert->execute([$lineId, $scid, ++$order]);
            }
            flash_set('success', 'เพิ่มโรงเรียนเข้าสายเรียบร้อยแล้ว');
        }
        redirect('line_detail.php?id=' . $lineId);
    }
}

$staffList = $pdo->prepare('
    SELECT s.* FROM staff s
    JOIN line_staff lst ON lst.staff_id = s.id
    WHERE lst.line_id = ?
    ORDER BY s.full_name
');
$staffList->execute([$lineId]);
$staffList = $staffList->fetchAll();

$schools = $pdo->prepare('
    SELECT ls.id AS line_school_id, sc.id AS school_id, sc.name, sc.district, sc.province,
           vr.id AS report_id, vr.photo_path, vr.m2_count, vr.m3_count, vr.m5_count, vr.m6_count,
           vr.reported_at, st.full_name AS reported_by_name
    FROM line_schools ls
    JOIN schools sc ON sc.id = ls.school_id
    LEFT JOIN visit_reports vr ON vr.line_school_id = ls.id
    LEFT JOIN staff st ON st.id = vr.reported_by
    WHERE ls.line_id = ?
    ORDER BY ls.visit_order, sc.name
');
$schools->execute([$lineId]);
$schools = $schools->fetchAll();

$existingSchoolIds = array_column($schools, 'school_id');

$statusLabel = ['planned' => 'วางแผน', 'in_progress' => 'กำลังดำเนินการ', 'completed' => 'เสร็จสิ้น'];
$statusColor = ['planned' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success'];

require_once __DIR__ . '/../includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="bi bi-signpost-2"></i> <?= h($line['line_name']) ?></h4>
    <div class="text-muted">
      <i class="bi bi-calendar-event"></i> <?= h((new DateTime($line['line_date']))->format('d/m/Y')) ?>
      <span class="badge bg-<?= $statusColor[$line['status']] ?> ms-2"><?= $statusLabel[$line['status']] ?></span>
    </div>
    <?php if ($line['notes']): ?><p class="mt-2 mb-0"><?= nl2br(h($line['notes'])) ?></p><?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <form method="post" class="d-flex gap-2">
      <input type="hidden" name="action" value="update_status">
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach ($statusLabel as $key => $label): ?>
          <option value="<?= $key ?>" <?= $line['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="lines.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับ</a>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h6 class="card-title"><i class="bi bi-people"></i> บุคลากรประจำสาย</h6>
    <?php if (!$staffList): ?>
      <p class="text-muted mb-0">ไม่มีบุคลากรในสายนี้</p>
    <?php else: ?>
      <?php foreach ($staffList as $s): ?>
        <span class="badge bg-light text-dark border me-1 mb-1"><i class="bi bi-person"></i> <?= h($s['full_name']) ?></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h6 class="mb-0"><i class="bi bi-building"></i> โรงเรียนเป้าหมายในสายนี้</h6>
  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
    <i class="bi bi-plus-lg"></i> เพิ่มโรงเรียน
  </button>
</div>

<div class="row g-3">
  <?php if (!$schools): ?>
    <p class="text-muted">ยังไม่มีโรงเรียนในสายนี้</p>
  <?php endif; ?>
  <?php foreach ($schools as $sc): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <h6 class="card-title mb-1"><?= h($sc['name']) ?></h6>
            <?php if (!$sc['report_id']): ?>
              <form method="post" onsubmit="return confirm('นำโรงเรียนนี้ออกจากสาย?');">
                <input type="hidden" name="action" value="remove_school">
                <input type="hidden" name="line_school_id" value="<?= (int) $sc['line_school_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-x"></i></button>
              </form>
            <?php endif; ?>
          </div>
          <p class="text-muted small mb-2"><?= h(trim(($sc['district'] ?? '') . ' ' . ($sc['province'] ?? ''))) ?></p>

          <?php if ($sc['report_id']): ?>
            <div class="d-flex gap-2 align-items-start">
              <img src="<?= h($sc['photo_path']) ?>" class="report-photo-thumb" alt="รูปถ่าย">
              <div>
                <span class="badge bg-primary badge-count">ม.2: <?= (int) $sc['m2_count'] ?></span><br>
                <span class="badge bg-primary badge-count mt-1">ม.3: <?= (int) $sc['m3_count'] ?></span><br>
                <span class="badge bg-info badge-count mt-1">ม.5: <?= (int) $sc['m5_count'] ?></span><br>
                <span class="badge bg-info badge-count mt-1">ม.6: <?= (int) $sc['m6_count'] ?></span>
              </div>
            </div>
            <p class="small text-muted mt-2 mb-0">
              รายงานโดย <?= h($sc['reported_by_name']) ?><br>
              <?= h((new DateTime($sc['reported_at']))->format('d/m/Y H:i')) ?>
            </p>
          <?php else: ?>
            <span class="badge bg-warning text-dark mb-2"><i class="bi bi-hourglass-split"></i> ยังไม่ได้รายงาน</span><br>
            <a href="report_form.php?line_school_id=<?= (int) $sc['line_school_id'] ?>" class="btn btn-sm btn-success mt-1">
              <i class="bi bi-camera"></i> บันทึกรายงาน
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal: add school -->
<div class="modal fade" id="addSchoolModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="add_schools">
        <div class="modal-header">
          <h5 class="modal-title">เพิ่มโรงเรียนเข้าสาย</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="school-picker" data-exclude-ids="<?= h(implode(',', $existingSchoolIds)) ?>">
            <input type="text" class="form-control school-search-input mb-2" placeholder="พิมพ์ชื่อโรงเรียน / อำเภอ / จังหวัด อย่างน้อย 2 ตัวอักษร">
            <div class="list-group school-search-results mb-2" style="max-height:220px; overflow-y:auto;"></div>
            <div class="school-selected-list"></div>
            <div class="school-selected-inputs"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">เพิ่ม</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
