<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$pdo = get_pdo();
$isAdmin = $user['role'] === 'admin';

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';
$schoolId = (int) ($_GET['school_id'] ?? 0);

$where  = [];
$params = [];

if ($dateFrom !== '') {
    $where[] = 'ol.line_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'ol.line_date <= ?';
    $params[] = $dateTo;
}
if ($schoolId > 0) {
    $where[] = 'sc.id = ?';
    $params[] = $schoolId;
}
if (!$isAdmin) {
    $where[] = 'EXISTS (SELECT 1 FROM line_staff lst2 WHERE lst2.line_id = ol.id AND lst2.staff_id = ?)';
    $params[] = $user['id'];
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT vr.*, sc.name AS school_name, ol.line_name, ol.line_date, st.full_name AS reported_by_name
    FROM visit_reports vr
    JOIN line_schools ls ON ls.id = vr.line_school_id
    JOIN schools sc ON sc.id = ls.school_id
    JOIN outreach_lines ol ON ol.id = ls.line_id
    JOIN staff st ON st.id = vr.reported_by
    $whereSql
    ORDER BY ol.line_date DESC, sc.name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportList = $stmt->fetchAll();

$totals = ['m2' => 0, 'm3' => 0, 'm5' => 0, 'm6' => 0];
foreach ($reportList as $r) {
    $totals['m2'] += (int) $r['m2_count'];
    $totals['m3'] += (int) $r['m3_count'];
    $totals['m5'] += (int) $r['m5_count'];
    $totals['m6'] += (int) $r['m6_count'];
}
$grandTotal = array_sum($totals);

// จำกัดตัวเลือกให้เหลือเฉพาะโรงเรียนที่เคยถูกกำหนดในสาย (ไม่ดึงทั้งฐานข้อมูล 11,000+ แห่งมาลง dropdown)
$schoolOptionsSql = '
    SELECT DISTINCT sc.id, sc.name
    FROM schools sc
    JOIN line_schools ls ON ls.school_id = sc.id
';
$schoolOptionsParams = [];
if (!$isAdmin) {
    $schoolOptionsSql .= '
    JOIN outreach_lines ol2 ON ol2.id = ls.line_id
    WHERE EXISTS (SELECT 1 FROM line_staff lst3 WHERE lst3.line_id = ol2.id AND lst3.staff_id = ?)';
    $schoolOptionsParams[] = $user['id'];
}
$schoolOptionsSql .= ' ORDER BY sc.name';
$stmt = $pdo->prepare($schoolOptionsSql);
$stmt->execute($schoolOptionsParams);
$schoolOptions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/layout_start.php';
?>

<h4 class="mb-3"><i class="bi bi-bar-chart"></i> รายงานผลการออกแนะแนว</h4>

<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">จากวันที่</label>
        <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">ถึงวันที่</label>
        <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">โรงเรียน</label>
        <select name="school_id" class="form-select">
          <option value="0">-- ทั้งหมด --</option>
          <?php foreach ($schoolOptions as $sc): ?>
            <option value="<?= (int) $sc['id'] ?>" <?= $schoolId === (int) $sc['id'] ? 'selected' : '' ?>><?= h($sc['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> กรอง</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small">ม.2</div>
      <div class="fs-3 fw-bold text-primary"><?= number_format($totals['m2']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small">ม.3</div>
      <div class="fs-3 fw-bold text-primary"><?= number_format($totals['m3']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small">ม.5</div>
      <div class="fs-3 fw-bold text-info"><?= number_format($totals['m5']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="text-muted small">ม.6</div>
      <div class="fs-3 fw-bold text-info"><?= number_format($totals['m6']) ?></div>
    </div></div>
  </div>
</div>
<p class="text-muted">รวมทั้งหมด <?= number_format($grandTotal) ?> คน จาก <?= count($reportList) ?> รายงาน</p>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>รูป</th>
            <th>วันที่</th>
            <th>สาย</th>
            <th>โรงเรียน</th>
            <th>ม.2</th>
            <th>ม.3</th>
            <th>ม.5</th>
            <th>ม.6</th>
            <th>ผู้รายงาน</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$reportList): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">ไม่พบข้อมูลรายงาน</td></tr>
          <?php endif; ?>
          <?php foreach ($reportList as $r): ?>
            <tr>
              <td><a href="<?= h($r['photo_path']) ?>" target="_blank"><img src="<?= h($r['photo_path']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:.375rem;"></a></td>
              <td><?= h((new DateTime($r['line_date']))->format('d/m/Y')) ?></td>
              <td><?= h($r['line_name']) ?></td>
              <td><?= h($r['school_name']) ?></td>
              <td><?= (int) $r['m2_count'] ?></td>
              <td><?= (int) $r['m3_count'] ?></td>
              <td><?= (int) $r['m5_count'] ?></td>
              <td><?= (int) $r['m6_count'] ?></td>
              <td><?= h($r['reported_by_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
