<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$pdo = get_pdo();
$isAdmin = $user['role'] === 'admin';
$lineFilter = $isAdmin ? '' : 'AND EXISTS (SELECT 1 FROM line_staff lst2 WHERE lst2.line_id = ol.id AND lst2.staff_id = ?)';
$lineFilterParams = $isAdmin ? [] : [$user['id']];

$stmt = $pdo->prepare("
    SELECT ol.*,
           COUNT(DISTINCT ls.school_id) AS school_count,
           COUNT(DISTINCT vr.id) AS reported_count
    FROM outreach_lines ol
    LEFT JOIN line_schools ls ON ls.line_id = ol.id
    LEFT JOIN visit_reports vr ON vr.line_school_id = ls.id
    WHERE ol.line_date >= CURDATE() $lineFilter
    GROUP BY ol.id
    ORDER BY ol.line_date ASC
    LIMIT 5
");
$stmt->execute($lineFilterParams);
$upcoming = $stmt->fetchAll();

$counts = $pdo->query('
    SELECT
        (SELECT COUNT(*) FROM staff WHERE is_active = 1) AS staff_count,
        (SELECT COUNT(*) FROM schools WHERE is_active = 1) AS school_count,
        (SELECT COUNT(*) FROM outreach_lines) AS line_count,
        (SELECT COUNT(*) FROM visit_reports) AS report_count
')->fetch();

$gradeTotalsSql = "
    SELECT COALESCE(SUM(vr.m2_count),0) AS m2, COALESCE(SUM(vr.m3_count),0) AS m3,
           COALESCE(SUM(vr.m5_count),0) AS m5, COALESCE(SUM(vr.m6_count),0) AS m6
    FROM visit_reports vr
";
if (!$isAdmin) {
    $gradeTotalsSql .= '
    JOIN line_schools ls ON ls.id = vr.line_school_id
    JOIN outreach_lines ol ON ol.id = ls.line_id
    WHERE EXISTS (SELECT 1 FROM line_staff lst2 WHERE lst2.line_id = ol.id AND lst2.staff_id = ?)';
}
$stmt = $pdo->prepare($gradeTotalsSql);
$stmt->execute($isAdmin ? [] : [$user['id']]);
$gradeTotals = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT ol.line_name, ol.line_date, sc.name AS school_name, ls.id AS line_school_id, ol.id AS line_id
    FROM line_schools ls
    JOIN outreach_lines ol ON ol.id = ls.line_id
    JOIN schools sc ON sc.id = ls.school_id
    LEFT JOIN visit_reports vr ON vr.line_school_id = ls.id
    WHERE vr.id IS NULL AND ol.line_date <= CURDATE() $lineFilter
    ORDER BY ol.line_date ASC
    LIMIT 10
");
$stmt->execute($lineFilterParams);
$pendingSchools = $stmt->fetchAll();

$statusLabel = ['planned' => 'วางแผน', 'in_progress' => 'กำลังดำเนินการ', 'completed' => 'เสร็จสิ้น'];
$statusColor = ['planned' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success'];

require_once __DIR__ . '/../includes/layout_start.php';
?>

<h4 class="mb-4"><i class="bi bi-speedometer2"></i> แดชบอร์ด</h4>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small"><i class="bi bi-people"></i> บุคลากร</div>
      <div class="fs-3 fw-bold"><?= (int) $counts['staff_count'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small"><i class="bi bi-building"></i> โรงเรียน</div>
      <div class="fs-3 fw-bold"><?= (int) $counts['school_count'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small"><i class="bi bi-signpost-split"></i> สายทั้งหมด</div>
      <div class="fs-3 fw-bold"><?= (int) $counts['line_count'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small"><i class="bi bi-camera"></i> รายงานแล้ว</div>
      <div class="fs-3 fw-bold"><?= (int) $counts['report_count'] ?></div>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="card-title"><i class="bi bi-calendar-event"></i> สายที่จะออกเร็วๆ นี้</h6>
        <?php if (!$upcoming): ?>
          <p class="text-muted mb-0">ไม่มีสายที่กำลังจะมาถึง</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>วันที่</th><th>ชื่อสาย</th><th>ความคืบหน้า</th><th>สถานะ</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($upcoming as $l): ?>
                <tr>
                  <td><?= h((new DateTime($l['line_date']))->format('d/m/Y')) ?></td>
                  <td><?= h($l['line_name']) ?></td>
                  <td><?= (int) $l['reported_count'] ?> / <?= (int) $l['school_count'] ?></td>
                  <td><span class="badge bg-<?= $statusColor[$l['status']] ?>"><?= $statusLabel[$l['status']] ?></span></td>
                  <td><a href="line_detail.php?id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-primary">ดู</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h6 class="card-title"><i class="bi bi-exclamation-triangle"></i> โรงเรียนที่ยังไม่ได้รายงาน (ถึงกำหนดแล้ว)</h6>
        <?php if (!$pendingSchools): ?>
          <p class="text-muted mb-0">ไม่มีรายการค้างรายงาน</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($pendingSchools as $p): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong><?= h($p['school_name']) ?></strong>
                  <div class="small text-muted"><?= h($p['line_name']) ?> — <?= h((new DateTime($p['line_date']))->format('d/m/Y')) ?></div>
                </div>
                <a href="report_form.php?line_school_id=<?= (int) $p['line_school_id'] ?>" class="btn btn-sm btn-success">
                  <i class="bi bi-camera"></i> รายงาน
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title"><i class="bi bi-graph-up"></i> จำนวนนักเรียนที่สนใจสะสม</h6>
        <table class="table">
          <tbody>
            <tr><td>มัธยมศึกษาปีที่ 2</td><td class="text-end fw-bold"><?= number_format($gradeTotals['m2']) ?></td></tr>
            <tr><td>มัธยมศึกษาปีที่ 3</td><td class="text-end fw-bold"><?= number_format($gradeTotals['m3']) ?></td></tr>
            <tr><td>มัธยมศึกษาปีที่ 5</td><td class="text-end fw-bold"><?= number_format($gradeTotals['m5']) ?></td></tr>
            <tr><td>มัธยมศึกษาปีที่ 6</td><td class="text-end fw-bold"><?= number_format($gradeTotals['m6']) ?></td></tr>
            <tr class="table-active"><td>รวม</td><td class="text-end fw-bold"><?= number_format($gradeTotals['m2'] + $gradeTotals['m3'] + $gradeTotals['m5'] + $gradeTotals['m6']) ?></td></tr>
          </tbody>
        </table>
        <a href="reports.php" class="btn btn-outline-primary w-100"><i class="bi bi-bar-chart"></i> ดูรายงานทั้งหมด</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
