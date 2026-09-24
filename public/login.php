<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = get_pdo()->prepare('SELECT * FROM staff WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $redirectTo = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        redirect($redirectTo ?: 'index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ - ระบบติดตามการออกประชาสัมพันธ์รับนักเรียนใหม่</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center" style="min-height:100vh; background:#eef1f6;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="text-center mb-4">
        <i class="bi bi-signpost-2 text-primary" style="font-size:2.5rem;"></i>
        <h5 class="mt-2">ระบบติดตามการออกประชาสัมพันธ์<br>รับนักเรียนใหม่</h5>
      </div>
      <div class="card">
        <div class="card-body p-4">
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php endif; ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">ชื่อผู้ใช้</label>
              <input type="text" name="username" class="form-control" autofocus required>
            </div>
            <div class="mb-3">
              <label class="form-label">รหัสผ่าน</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
