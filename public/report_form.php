<?php
require_once __DIR__ . '/../includes/auth.php';

$pdo = get_pdo();
$lineSchoolId = (int) ($_GET['line_school_id'] ?? $_POST['line_school_id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT ls.id AS line_school_id, ls.line_id, sc.name AS school_name,
           ol.line_name, ol.line_date
    FROM line_schools ls
    JOIN schools sc ON sc.id = ls.school_id
    JOIN outreach_lines ol ON ol.id = ls.line_id
    WHERE ls.id = ?
');
$stmt->execute([$lineSchoolId]);
$lineSchool = $stmt->fetch();

if (!$lineSchool) {
    flash_set('error', 'ไม่พบข้อมูลโรงเรียนในสายที่ระบุ');
    redirect('lines.php');
}

$user = require_line_access((int) $lineSchool['line_id']);

$existing = $pdo->prepare('SELECT id FROM visit_reports WHERE line_school_id = ?');
$existing->execute([$lineSchoolId]);
if ($existing->fetch()) {
    flash_set('error', 'โรงเรียนนี้มีรายงานอยู่แล้ว');
    redirect('line_detail.php?id=' . $lineSchool['line_id']);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m2 = (int) ($_POST['m2_count'] ?? 0);
    $m3 = (int) ($_POST['m3_count'] ?? 0);
    $m5 = (int) ($_POST['m5_count'] ?? 0);
    $m6 = (int) ($_POST['m6_count'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    foreach (['m2_count' => $m2, 'm3_count' => $m3, 'm5_count' => $m5, 'm6_count' => $m6] as $label => $val) {
        if ($val < 0) {
            $errors[] = 'จำนวนนักเรียนต้องไม่ติดลบ';
            break;
        }
    }

    $photoPath = null;
    if (!$errors) {
        try {
            $photoPath = save_uploaded_photo($_FILES['photo'] ?? []);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $insert = $pdo->prepare('
            INSERT INTO visit_reports (line_school_id, photo_path, m2_count, m3_count, m5_count, m6_count, reported_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([$lineSchoolId, $photoPath, $m2, $m3, $m5, $m6, $user['id'], $notes ?: null]);
        flash_set('success', 'บันทึกรายงานเรียบร้อยแล้ว');
        redirect('line_detail.php?id=' . $lineSchool['line_id']);
    }
}

require_once __DIR__ . '/../includes/layout_start.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="d-flex align-items-center gap-2 mb-3">
      <a href="line_detail.php?id=<?= (int) $lineSchool['line_id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
      <h5 class="mb-0"><i class="bi bi-camera"></i> บันทึกรายงานผลการแนะแนว</h5>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <p class="mb-1"><strong>สาย:</strong> <?= h($lineSchool['line_name']) ?> (<?= h((new DateTime($lineSchool['line_date']))->format('d/m/Y')) ?>)</p>
        <p class="mb-1"><strong>โรงเรียน:</strong> <?= h($lineSchool['school_name']) ?></p>
        <p class="mb-0"><strong>ผู้รายงาน:</strong> <?= h($user['full_name']) ?></p>
      </div>
    </div>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="card">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label">รูปถ่าย <span class="text-danger">*</span></label>
            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp" required>
            <div class="form-text">รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 8MB</div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
              <label class="form-label">ม.2</label>
              <input type="number" min="0" name="m2_count" class="form-control" value="<?= h($_POST['m2_count'] ?? '0') ?>" required>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label">ม.3</label>
              <input type="number" min="0" name="m3_count" class="form-control" value="<?= h($_POST['m3_count'] ?? '0') ?>" required>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label">ม.5</label>
              <input type="number" min="0" name="m5_count" class="form-control" value="<?= h($_POST['m5_count'] ?? '0') ?>" required>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label">ม.6</label>
              <input type="number" min="0" name="m6_count" class="form-control" value="<?= h($_POST['m6_count'] ?? '0') ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">หมายเหตุ</label>
            <textarea name="notes" class="form-control" rows="2"><?= h($_POST['notes'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg"></i> บันทึกรายงาน</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
