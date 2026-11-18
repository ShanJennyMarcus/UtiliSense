<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    if (empty($email) OR empty($password)) {
        $error = "Please enter both your email and password.";
    } else {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.role_id, u.full_name, u.email, u.password_hash, u.status, r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE u.email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user AND password_verify($password, $user['password_hash'])) {
            if (($user['status'] ?? 'active') !== 'active') {
                $error = "Your account is currently inactive. Please contact an administrator.";
            } else {
                session_regenerate_id(true);
                $roleId = (int)$user['role_id'];
                $roleName = ($roleId === 1) ? 'Administrator' : (($roleId === 2) ? 'Staff' : 'Consumer');

                $_SESSION['user'] = [
                    'id'      => $user['user_id'],
                    'name'    => $user['full_name'],
                    'email'   => $user['email'],
                    'role_id' => $roleId,
                    'role'    => $roleName
                ];

                try {
                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);
                } catch (Exception $e) {}
                log_activity($pdo, $user['user_id'], 'LOGIN', "Signed in as $roleName");

                if ($rememberMe) {
                    setcookie('remember_email', $email, time() + (86400 * 30), "/", "", false, true);
                } else {
                    setcookie('remember_email', '', time() - 3600, "/");
                }

                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — UtiliSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex flex-column justify-content-between py-4">

    <div class="container-fluid px-4 px-lg-5">
        <a href="index.php" class="btn squircle-card px-4 py-2 shadow-sm fw-semibold text-decoration-none text-dark bg-white">
            <i class="bi bi-arrow-left me-2"></i>Back to Home
        </a>
    </div>

    <div class="container my-auto py-4">
        <div class="squircle-card p-4 p-md-5 shadow-lg mx-auto" style="max-width: 440px;">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-4 text-white mb-3 shadow-sm" style="width: 56px; height: 56px; background: linear-gradient(135deg, var(--navy-deep), var(--blue-med));">
                    <i class="bi bi-layers-fill fs-3"></i>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--navy-deep);">Welcome Back</h3>
                <p class="text-muted small">Enter your credentials to access your dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 py-2 small mb-3">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Email Address</label>
                    <input type="email" name="email" class="form-control custom-input" placeholder="Enter your email" value="<?php echo htmlspecialchars($_COOKIE['remember_email'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Password</label>
                    <div class="position-relative">
                        <input type="password" name="password" id="loginPass" class="form-control custom-input pe-5" placeholder="••••••••" required>
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" onclick="togglePass('loginPass', 'eyeIcon')">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe" <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                    <label class="form-check-label small text-muted" for="rememberMe">Remember my email</label>
                </div>
                <button type="submit" class="btn btn-pill-navy w-100 py-3 shadow-sm mb-3">
                    Sign In <i class="bi bi-box-arrow-in-right ms-1"></i>
                </button>
                <div class="text-center">
                    <span class="text-muted small">New to UtiliSense?</span>
                    <a href="register.php" class="small fw-bold text-decoration-none ms-1" style="color: var(--blue-med);">Create Account</a>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center text-muted small">UtiliSense Household Manager</div>

    <script>
        function togglePass(id, iconId) {
            const input = document.getElementById(id);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>