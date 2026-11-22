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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken       = $_POST['csrf_token'] ?? '';
    $fullName        = trim($_POST['full_name'] ?? '');
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } elseif (empty($fullName) OR empty($email) OR empty($password) OR empty($confirmPassword)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = "An account with that email already exists.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (role_id, full_name, email, password_hash, status, last_login)
                VALUES (3, ?, ?, ?, 'active', NOW())
            ");

            if ($stmt->execute([$fullName, $email, $passwordHash])) {
                $newUserId = (int)$pdo->lastInsertId();
                log_activity($pdo, $newUserId, 'REGISTER', "New consumer account registered: $email");

                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['user'] = [
                    'id'      => $newUserId,
                    'name'    => $fullName,
                    'email'   => $email,
                    'role_id' => 3,
                    'role'    => 'Consumer'
                ];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — UtiliSense</title>
    <link rel="icon" type="image/jpeg" href="assets/images/logo.jpg?v=3">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body class="d-flex flex-column justify-content-between py-4">

    <div class="container-fluid px-4 px-lg-5">
        <a href="index.php" class="btn squircle-card px-4 py-2 shadow-sm fw-semibold text-decoration-none text-dark bg-white">
            <i class="bi bi-arrow-left me-2"></i>Back to Home
        </a>
    </div>

    <div class="container my-auto py-4">
        <div class="squircle-card p-4 p-md-5 shadow-lg mx-auto" style="max-width: 460px;">
            <div class="text-center mb-4">
                <img src="assets/images/logo.jpg?v=3" alt="UtiliSense Logo" class="rounded-4 mb-3 shadow-sm" style="width: 64px; height: 64px; object-fit: cover;">
                <h3 class="fw-bold mb-1" style="color: var(--navy-deep);">Create Account</h3>
                <p class="text-muted small">Register your UtiliSense profile</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 py-2 small mb-3">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Full Name</label>
                    <input type="text" name="full_name" class="form-control custom-input" placeholder="Enter your full name" autocomplete="name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Email Address</label>
                    <input type="email" name="email" class="form-control custom-input" placeholder="Enter your email" autocomplete="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Password (min. 6 characters)</label>
                    <div class="position-relative">
                        <input type="password" name="password" id="regPass" class="form-control custom-input pe-5" placeholder="Create a password" autocomplete="new-password" minlength="6" required>
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" aria-label="Toggle password visibility" onclick="togglePass('regPass', 'regEye1')">
                            <i class="bi bi-eye" id="regEye1"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Confirm Password</label>
                    <div class="position-relative">
                        <input type="password" name="confirm_password" id="regConfirmPass" class="form-control custom-input pe-5" placeholder="Re-enter your password" autocomplete="new-password" minlength="6" required>
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" aria-label="Toggle confirm password visibility" onclick="togglePass('regConfirmPass', 'regEye2')">
                            <i class="bi bi-eye" id="regEye2"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-pill-aqua w-100 py-3 shadow-sm mb-3">
                    Register Account <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <div class="text-center">
                    <span class="text-muted small">Already have an account?</span>
                    <a href="login.php" class="small fw-bold text-decoration-none ms-1" style="color: var(--navy-deep);">Sign In</a>
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