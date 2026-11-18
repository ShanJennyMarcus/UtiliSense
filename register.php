<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName        = trim($_POST['full_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) OR empty($email) OR empty($password) OR empty($confirmPassword)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $error = "An account with that email already exists.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (role_id, full_name, email, password_hash, status)
                VALUES (3, ?, ?, ?, 'active')
            ");

            if ($stmt->execute([$fullName, $email, $passwordHash])) {
                $newUserId = $pdo->lastInsertId();
                log_activity($pdo, $newUserId, 'REGISTER', "New consumer account registered: $email");
                $success = "Registration successful! You can now sign in.";
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
        <div class="squircle-card p-4 p-md-5 shadow-lg mx-auto" style="max-width: 460px;">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-4 text-white mb-3 shadow-sm" style="width: 56px; height: 56px; background: linear-gradient(135deg, var(--blue-med), var(--aqua-bright));">
                    <i class="bi bi-person-plus-fill fs-3"></i>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--navy-deep);">Create Account</h3>
                <p class="text-muted small">Register your UtiliSense profile</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 py-2 small mb-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success rounded-4 py-2 small mb-3">
                    <?php echo htmlspecialchars($success); ?> <a href="login.php" class="fw-bold">Sign In</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Full Name</label>
                    <input type="text" name="full_name" class="form-control custom-input" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Email Address</label>
                    <input type="email" name="email" class="form-control custom-input" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Password (min. 6 characters)</label>
                    <input type="password" name="password" class="form-control custom-input" placeholder="Create a password" minlength="6" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small" style="color: var(--navy-deep);">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control custom-input" placeholder="Re-enter your password" minlength="6" required>
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
</body>
</html>