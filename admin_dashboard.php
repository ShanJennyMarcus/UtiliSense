<?php

require_once 'config.php';
requireRole(['admin']);

$msg = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role_id = (int)$_POST['role_id'];
    $prefix = ($role_id === 1) ? 'ADM-' : (($role_id === 2) ? 'STF-' : 'UTS-');
    $acct_no = $prefix . rand(1000, 9999);
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, account_number, password_hash) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$role_id, $name, $email, $acct_no, $hash]);
    logActivity($pdo, $_SESSION['user_id'], 'ADMIN_CREATE_USER', "Created user $email ($acct_no)");
    $msg = "User $name ($acct_no) created successfully!";
}

if (isset($_GET['toggle_user'])) {
    $uid = (int)$_GET['toggle_user'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET account_status = IF(account_status='active','inactive','active') WHERE user_id = ?")->execute([$uid]);
        logActivity($pdo, $_SESSION['user_id'], 'ADMIN_UPDATE_USER', "Toggled status for User ID #$uid");
        header("Location: admin_dashboard.php");
        exit();
    }
}

if (isset($_GET['delete_user'])) {
    $uid = (int)$_GET['delete_user'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$uid]);
        logActivity($pdo, $_SESSION['user_id'], 'ADMIN_DELETE_USER', "Deleted User ID #$uid");
        header("Location: admin_dashboard.php");
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate'])) {
    $utility_id = (int)$_POST['utility_id'];
    $new_rate = (float)$_POST['rate_per_unit'];
    $pdo->prepare("UPDATE utility_types SET rate_per_unit = ? WHERE utility_id = ?")->execute([$new_rate, $utility_id]);
    logActivity($pdo, $_SESSION['user_id'], 'UPDATE_UTILITY_RATE', "Updated Utility ID #$utility_id rate to ₱$new_rate");
    $msg = "Utility rate updated successfully!";
}

$users = $pdo->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id ORDER BY u.user_id DESC")->fetchAll();
$utilities = $pdo->query("SELECT * FROM utility_types")->fetchAll();
$logs = $pdo->query("SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.log_id DESC LIMIT 30")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - UtiliSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">⚡💧🌐 UtiliSense Admin Control Panel</a>
        <div>
            <span class="text-white me-3">Admin: <?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="staff_dashboard.php" class="btn btn-outline-info btn-sm me-2">Billing Module</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="row g-4">
      
        <div class="col-md-4">
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="fw-bold">Create User Account</h5>
                <form method="POST">
                    <input type="text" name="full_name" class="form-control mb-2" placeholder="Full Name" required>
                    <input type="email" name="email" class="form-control mb-2" placeholder="Email Address" required>
                    <input type="password" name="password" class="form-control mb-2" placeholder="Password" required>
                    <select name="role_id" class="form-select mb-3" required>
                        <option value="3">Consumer</option>
                        <option value="2">Staff</option>
                        <option value="1">Admin</option>
                    </select>
                    <button type="submit" name="create_user" class="btn btn-primary w-100">Add User</button>
                </form>
            </div>

            <div class="card shadow-sm p-3">
                <h5 class="fw-bold">Configure Utility Rates</h5>
                <?php foreach ($utilities as $ut): ?>
                <form method="POST" class="d-flex align-items-center gap-2 mb-2">
                    <input type="hidden" name="utility_id" value="<?= $ut['utility_id'] ?>">
                    <span class="w-50"><?= htmlspecialchars($ut['utility_name']) ?> (<?= htmlspecialchars($ut['unit_label']) ?>)</span>
                    <input type="number" step="0.01" name="rate_per_unit" value="<?= $ut['rate_per_unit'] ?>" class="form-control form-control-sm" required>
                    <button type="submit" name="update_rate" class="btn btn-sm btn-success">Save</button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>

        
        <div class="col-md-8">
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="fw-bold">User Management (CRUD)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr><th>Acct No.</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($u['account_number'] ?? 'N/A') ?></code></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge bg-secondary"><?= strtoupper($u['role_name']) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $u['account_status'] === 'active' ? 'success' : 'danger' ?>">
                                        <?= strtoupper($u['account_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                        <a href="?toggle_user=<?= $u['user_id'] ?>" class="btn btn-warning btn-sm">Toggle Status</a>
                                        <a href="?delete_user=<?= $u['user_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')">Delete</a>
                                    <?php else: ?>
                                        <small class="text-muted">Current User</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm p-3">
                <h5 class="fw-bold">System Audit Logs</h5>
                <div class="table-responsive" style="max-height: 300px;">
                    <table class="table table-striped table-sm">
                        <thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><?= $l['created_at'] ?></td>
                                <td><?= htmlspecialchars($l['full_name'] ?? 'Guest') ?></td>
                                <td><code><?= htmlspecialchars($l['action']) ?></code></td>
                                <td><?= htmlspecialchars($l['details']) ?></td>
                                <td><?= htmlspecialchars($l['ip_address']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>