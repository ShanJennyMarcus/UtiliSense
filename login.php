<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user'])) {
    $target = ((int)($_SESSION['user']['role_id'] ?? 3) === 2) ? 'staff_dashboard.php' : 'dashboard.php';
    header("Location: " . $target);
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$emailValue = $_COOKIE['remember_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken  = $_POST['csrf_token'] ?? '';
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $password   = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    $emailValue = $email;

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } elseif (empty($email) OR empty($password)) {
        $error = "Please enter both your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, role_id, full_name, email, password_hash, status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user AND password_verify($password, $user['password_hash'])) {
            if (($user['status'] ?? 'active') !== 'active') {
                $error = "Your account is currently inactive. Please contact an administrator.";
            } else {
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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

                $isHttps = (!empty($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] !== 'off');
                if ($rememberMe) {
                    setcookie('remember_email', $user['email'], [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'secure'   => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                } else {
                    setcookie('remember_email', '', [
                        'expires'  => time() - 3600,
                        'path'     => '/',
                        'secure'   => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                $redirectUrl = ($roleId === 2) ? 'staff_dashboard.php' : 'dashboard.php';
                header("Location: " . $redirectUrl);
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
                <img src="https://scontent.fmnl15-1.fna.fbcdn.net/v/t1.15752-9/918140027_1020725383633673_8028145773292022580_n.jpg?stp=dst-jpg_s480x480_tt6&_nc_cat=100&_nc_map=urlgen_bucketless&ccb=1-7&_nc_sid=0024fc&_nc_eui2=AeGKh-NDd49Phi3VRuFVWmSGiqrpj16KYd2KqumPXoph3Xwb4jxDc13LjkyUhv0uMy6CSJXpHotS2GeQcQ9nUAiM&_nc_ohc=zAWSWq7dnJcQ7kNvwGyu_Pl&_nc_oc=AdrJM7KbrnIpGDYR2azmkx8W8aPZ9snlLr8rwb04YFLMWoVTJKpNAY4nc3zGiP6wYC8&_nc_ad=z-m&_nc_cid=0&_nc_zt=23&_nc_ht=scontent.fmnl15-1.fna&_nc_ss=7a22e&oh=03_Q7cD6wGHA9AYHePdzkmDYI04j_o8OQro-JIB3-8NXgVY-2OdyQ&oe=6B28CDD0" alt="UtiliSense Logo" class="rounded-4 mb-3 shadow-sm" style="width: 64px; height: 64px; object-fit: cover;">
                <h3 class="fw-bold mb-1" style="color: var(--navy-deep);">Welcome Back</h3>
                <p class="text-muted small">Enter your credentials to access your dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 py-2 small mb-3">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Email Address</label>
                    <input type="email" name="email" class="form-control custom-input" placeholder="Enter your email" autocomplete="email" value="<?php echo htmlspecialchars($emailValue); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Password</label>
                    <div class="position-relative">
                        <input type="password" name="password" id="loginPass" class="form-control custom-input pe-5" placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" aria-label="Toggle password visibility" onclick="togglePass('loginPass', 'eyeIcon')">
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

    <script src="assets/js/app.js"></script>
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