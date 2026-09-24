<?php
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $fullName = trim($_POST['full_name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $role     = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';

        if ($fullName === '') {
            flash_set('error', 'กรุณากรอกชื่อ-นามสกุลบุคลากร');
        } elseif ($username !== '' && $password === '') {
            flash_set('error', 'กรุณากำหนดรหัสผ่านเมื่อระบุชื่อผู้ใช้');
        } else {
            $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
            try {
                $stmt = $pdo->prepare('INSERT INTO staff (full_name, position, phone, username, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$fullName, $position ?: null, $phone ?: null, $username ?: null, $passwordHash, $role]);
                flash_set('success', 'เพิ่มบุคลากรเรียบร้อยแล้ว');
            } catch (PDOException $e) {
                flash_set('error', 'ไม่สามารถเพิ่มได้ ชื่อผู้ใช้นี้อาจถูกใช้ไปแล้ว');
            }
        }
        redirect('staff.php');
    }

    if ($action === 'set_credentials') {
        $id       = (int) ($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $role     = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';

        if ($username === '') {
            flash_set('error', 'กรุณากรอกชื่อผู้ใช้');
        } else {
            try {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE staff SET username = ?, password_hash = ?, role = ? WHERE id = ?');
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE staff SET username = ?, role = ? WHERE id = ?');
                    $stmt->execute([$username, $role, $id]);
                }
                flash_set('success', 'ตั้งค่าบัญชีผู้ใช้เรียบร้อยแล้ว');
            } catch (PDOException $e) {
                flash_set('error', 'ไม่สามารถบันทึกได้ ชื่อผู้ใช้นี้อาจถูกใช้ไปแล้ว');
            }
        }
        redirect('staff.php');
    }

    if ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE staff SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$id]);
        redirect('staff.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM staff WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('success', 'ลบบุคลากรเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            flash_set('error', 'ไม่สามารถลบได้ เนื่องจากมีข้อมูลสายแนะแนวหรือรายงานที่เกี่ยวข้องอยู่');
        }
        redirect('staff.php');
    }
}

$staffList = $pdo->query('SELECT * FROM staff ORDER BY is_active DESC, full_name ASC')->fetchAll();

require_once __DIR__ . '/../includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-people"></i> บุคลากร</h4>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title">เพิ่มบุคลากรใหม่</h6>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <div class="mb-2">
            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">ตำแหน่ง</label>
            <input type="text" name="position" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">เบอร์โทร</label>
            <input type="text" name="phone" class="form-control">
          </div>
          <hr>
          <p class="small text-muted mb-2">บัญชีสำหรับล็อกอิน (ไม่บังคับ — เว้นว่างได้หากยังไม่ให้สิทธิ์เข้าระบบ)</p>
          <div class="mb-2">
            <label class="form-label">ชื่อผู้ใช้</label>
            <input type="text" name="username" class="form-control" autocomplete="off">
          </div>
          <div class="mb-2">
            <label class="form-label">รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">สิทธิ์</label>
            <select name="role" class="form-select">
              <option value="staff">บุคลากร</option>
              <option value="admin">ผู้ดูแลระบบ</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> เพิ่มบุคลากร</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>ชื่อ-นามสกุล</th>
                <th>ตำแหน่ง</th>
                <th>เบอร์โทร</th>
                <th>บัญชีผู้ใช้</th>
                <th>สถานะ</th>
                <th class="text-end">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$staffList): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีข้อมูลบุคลากร</td></tr>
              <?php endif; ?>
              <?php foreach ($staffList as $s): ?>
                <tr class="<?= $s['is_active'] ? '' : 'text-muted' ?>">
                  <td><?= h($s['full_name']) ?></td>
                  <td><?= h($s['position']) ?></td>
                  <td><?= h($s['phone']) ?></td>
                  <td>
                    <?php if ($s['username']): ?>
                      <code><?= h($s['username']) ?></code>
                      <span class="badge bg-<?= $s['role'] === 'admin' ? 'primary' : 'info' ?>"><?= $s['role'] === 'admin' ? 'แอดมิน' : 'บุคลากร' ?></span>
                    <?php else: ?>
                      <span class="text-muted small">ยังไม่ได้ตั้งค่า</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($s['is_active']): ?>
                      <span class="badge bg-success">ใช้งาน</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">ปิดใช้งาน</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" title="ตั้งค่าบัญชีผู้ใช้" data-bs-toggle="modal" data-bs-target="#credModal<?= (int) $s['id'] ?>">
                      <i class="bi bi-key"></i>
                    </button>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" title="เปิด/ปิดใช้งาน">
                        <i class="bi bi-toggle2-on"></i>
                      </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('ยืนยันการลบบุคลากรนี้?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>

                <div class="modal fade" id="credModal<?= (int) $s['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="post">
                        <input type="hidden" name="action" value="set_credentials">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <div class="modal-header">
                          <h6 class="modal-title">ตั้งค่าบัญชีผู้ใช้ — <?= h($s['full_name']) ?></h6>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="mb-2">
                            <label class="form-label">ชื่อผู้ใช้</label>
                            <input type="text" name="username" class="form-control" value="<?= h($s['username']) ?>" autocomplete="off" required>
                          </div>
                          <div class="mb-2">
                            <label class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยน">
                          </div>
                          <div class="mb-2">
                            <label class="form-label">สิทธิ์</label>
                            <select name="role" class="form-select">
                              <option value="staff" <?= $s['role'] === 'staff' ? 'selected' : '' ?>>บุคลากร</option>
                              <option value="admin" <?= $s['role'] === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                            </select>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                          <button type="submit" class="btn btn-primary">บันทึก</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
