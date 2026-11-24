<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int)$_SESSION['user']['id'];

$uStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$uStmt->execute([$userId]);
$liveUser = $uStmt->fetch();

if (!$liveUser) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$roleId = (int)$liveUser['role_id'];
$role   = ($roleId === 1) ? 'Administrator' : (($roleId === 2) ? 'Staff' : 'Consumer');

if ($role === 'Staff') {
    header("Location: staff_dashboard.php");
    exit();
}

$_SESSION['user']['name']    = $liveUser['full_name'];
$_SESSION['user']['role_id'] = $roleId;
$_SESSION['user']['role']    = $role;
$currentUser                 = $_SESSION['user'];

$flashMsg  = $_SESSION['flash_msg'] ?? "";
$flashType = $_SESSION['flash_type'] ?? "success";
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $_SESSION['flash_msg']  = "Invalid session token. Please refresh and try again.";
        $_SESSION['flash_type'] = "danger";
        header("Location: dashboard.php");
        exit();
    }

    if ($role === 'Administrator') {
        if (isset($_POST['change_role_user_id'])) {
            $targetId = (int)$_POST['change_role_user_id'];
            $newRole  = (int)$_POST['new_role_id'];
            if ($targetId !== $userId AND in_array($newRole, [1, 2, 3], true)) {
                $pdo->prepare("UPDATE users SET role_id = ? WHERE user_id = ?")->execute([$newRole, $targetId]);
                log_activity($pdo, $userId, 'UPDATE_ROLE', "Changed User #$targetId to role_id $newRole");
                $_SESSION['flash_msg'] = "User role updated successfully!";
            }
            header("Location: dashboard.php");
            exit();
        }
        if (isset($_POST['toggle_user_id'])) {
            $targetId  = (int)$_POST['toggle_user_id'];
            $newStatus = ($_POST['current_status'] === 'active') ? 'inactive' : 'active';
            if ($targetId !== $userId) {
                $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?")->execute([$newStatus, $targetId]);
                log_activity($pdo, $userId, 'UPDATE_STATUS', "Set User #$targetId status to $newStatus");
                $_SESSION['flash_msg'] = "User status updated to $newStatus.";
            }
            header("Location: dashboard.php");
            exit();
        }
    }

    if ($role === 'Consumer' AND isset($_POST['save_my_utilities'])) {
        $selectedTypes = $_POST['utilities'] ?? [];
        $providers     = $_POST['provider'] ?? [];
        $rates         = [
            'Meralco'                       => 11.85,
            'PELCO I'                       => 11.10,
            'PELCO II'                      => 10.90,
            'PELCO III'                     => 11.05,
            'SFELAPCO (San Fernando)'       => 12.05,
            'Angeles Electric Corp (AEC)'   => 11.95,
            'PrimeWater Pampanga'           => 38.50,
            'Angeles City Water District'   => 36.00,
            'San Fernando Water District'   => 37.20,
            'Mabalacat City Water District' => 36.80,
            'Converge ICT FiberX'           => 1500.00,
            'PLDT Home Fiber'               => 1699.00,
            'Globe At Home'                 => 1499.00,
        ];

        $pdo->prepare("DELETE FROM user_utilities WHERE user_id = ?")->execute([$userId]);
        $insertSub = $pdo->prepare("INSERT INTO user_utilities (user_id, utility_type, provider_name, rate_per_unit) VALUES (?, ?, ?, ?)");

        foreach ($selectedTypes as $uType) {
            if (!in_array($uType, ['Electricity', 'Water', 'Internet'], true)) continue;
            $provName = $providers[$uType] ?? 'Standard Provider';
            $rateVal  = $rates[$provName] ?? 0.00;
            $insertSub->execute([$userId, $uType, $provName, $rateVal]);
        }

        log_activity($pdo, $userId, 'UPDATE_UTILITIES', "Updated subscribed utilities (" . implode(', ', $selectedTypes) . ")");
        $_SESSION['flash_msg'] = "Your utility preferences have been saved!";
        header("Location: dashboard.php");
        exit();
    }

    if ($role !== 'Consumer' AND isset($_POST['encode_bill'])) {
        $targetUserId = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : $userId;
        $uType        = $_POST['utility_type'] ?? 'Electricity';
        $provName     = trim($_POST['provider_name'] ?? '');
        $prevRead     = (float)($_POST['prev_reading'] ?? 0);
        $presRead     = (float)($_POST['pres_reading'] ?? 0);
        $consumption  = max(0, $presRead - $prevRead);
        $amount       = max(0, (float)($_POST['amount'] ?? 0));
        $dueDate      = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+15 days'));

        if (in_array($uType, ['Electricity', 'Water', 'Internet'], true) AND $amount > 0) {
            $checkSub = $pdo->prepare("SELECT id FROM user_utilities WHERE user_id = ? AND utility_type = ?");
            $checkSub->execute([$targetUserId, $uType]);
            if ($checkSub->rowCount() == 0) {
                $pdo->prepare("INSERT INTO user_utilities (user_id, utility_type, provider_name, rate_per_unit) VALUES (?, ?, ?, 0)")
                    ->execute([$targetUserId, $uType, $provName]);
            }

            $stmtBill = $pdo->prepare("
                INSERT INTO utility_bills 
                (user_id, utility_type, provider_name, previous_reading, present_reading, consumption, amount, due_date, status, encoded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)
            ");
            $stmtBill->execute([$targetUserId, $uType, $provName, $prevRead, $presRead, $consumption, $amount, $dueDate, $userId]);

            log_activity($pdo, $userId, 'ENCODE_BILL', "Encoded $uType bill (₱$amount) for User ID #$targetUserId");
            $_SESSION['flash_msg'] = "Bill encoded and published to the consumer's dashboard!";
        } else {
            $_SESSION['flash_msg']  = "Please enter a valid bill amount greater than ₱0.00.";
            $_SESSION['flash_type'] = "warning";
        }
        header("Location: dashboard.php");
        exit();
    }

    if ($role === 'Consumer' AND isset($_POST['submit_bill_payment'])) {
        $billId     = (int)$_POST['pay_bill_id'];
        $channel    = trim($_POST['payment_channel'] ?? 'GCash');
        $identifier = trim($_POST['account_identifier'] ?? '');
        $isWalkIn   = (strpos($channel, 'Nearest Branch') !== false);
        $refNo      = $isWalkIn
            ? ('UTS-BRANCH-' . strtoupper(substr(md5(uniqid((string)$billId, true)), 0, 8)))
            : ('UTS-PAY-' . strtoupper(substr(md5(uniqid((string)$billId, true)), 0, 8)));

        $bCheck = $pdo->prepare("SELECT * FROM utility_bills WHERE bill_id = ? AND user_id = ? AND status != 'PAID' LIMIT 1");
        $bCheck->execute([$billId, $userId]);
        $foundBill = $bCheck->fetch();

        if ($foundBill) {
            $amtPaid = (float)$foundBill['amount'];
            $mode    = $isWalkIn ? 'WALK_IN_BRANCH' : 'ONLINE_INSTANT';

            $payStmt = $pdo->prepare("
                INSERT INTO bill_payments (bill_id, user_id, payment_channel, account_identifier, reference_no, amount_paid, payment_mode)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $payStmt->execute([$billId, $userId, $channel, $identifier, $refNo, $amtPaid, $mode]);

            if ($isWalkIn) {
                log_activity($pdo, $userId, 'WALKIN_SLIP', "Generated walk-in branch payment slip ($refNo) for Bill #$billId");
                $_SESSION['flash_msg'] = "Walk-In Branch Slip #$refNo generated! Your bill stays PENDING until paid at the physical branch and verified by Staff.";
            } else {
                $pdo->prepare("UPDATE utility_bills SET status = 'PAID' WHERE bill_id = ? AND status != 'PAID'")->execute([$billId]);
                log_activity($pdo, $userId, 'ONLINE_PAYMENT', "Paid Bill #$billId (₱$amtPaid) via $channel [Ref: $refNo]");
                $_SESSION['flash_msg'] = "Payment successful via $channel! Official Receipt Ref: $refNo — Your bill has been automatically marked as PAID.";
            }
        } else {
            $_SESSION['flash_msg']  = "This bill is already paid or unavailable.";
            $_SESSION['flash_type'] = "warning";
        }
        header("Location: dashboard.php");
        exit();
    }
}

$subStmt = $pdo->prepare("SELECT * FROM user_utilities WHERE user_id = ? AND utility_type IN ('Electricity', 'Water', 'Internet') ORDER BY id ASC");
$subStmt->execute([$userId]);
$mySubscriptions = $subStmt->fetchAll();
$subscribedMap = [];
foreach ($mySubscriptions as $sub) {
    $subscribedMap[$sub['utility_type']] = $sub;
}

$savedElec  = $subscribedMap['Electricity']['provider_name'] ?? 'Meralco';
$savedWater = $subscribedMap['Water']['provider_name'] ?? 'PrimeWater Pampanga';
$savedNet   = $subscribedMap['Internet']['provider_name'] ?? 'Converge ICT FiberX';

if ($role === 'Consumer') {
    $billStmt = $pdo->prepare("SELECT * FROM utility_bills WHERE user_id = ? ORDER BY created_at DESC");
    $billStmt->execute([$userId]);
} else {
    $billStmt = $pdo->query("
        SELECT b.*, u.full_name, u.email 
        FROM utility_bills b 
        JOIN users u ON b.user_id = u.user_id 
        ORDER BY b.created_at DESC
    ");
}
$allBills = $billStmt->fetchAll();

if ($role === 'Consumer') {
    $myReceiptsStmt = $pdo->prepare("
        SELECT p.*, b.utility_type, b.provider_name
        FROM bill_payments p
        JOIN utility_bills b ON p.bill_id = b.bill_id
        WHERE p.user_id = ?
        ORDER BY p.paid_at DESC
    ");
    $myReceiptsStmt->execute([$userId]);
} else {
    $myReceiptsStmt = $pdo->query("
        SELECT p.*, b.utility_type, b.provider_name
        FROM bill_payments p
        JOIN utility_bills b ON p.bill_id = b.bill_id
        ORDER BY p.paid_at DESC
    ");
}
$myReceipts = $myReceiptsStmt->fetchAll();

$latestBillByType = [];
$categoryTotals   = ['Electricity' => 0, 'Water' => 0, 'Internet' => 0];
$totalSpent       = 0;
$pendingCount     = 0;
$paidCount        = 0;

foreach ($allBills as $b) {
    $type = $b['utility_type'];
    if ($role !== 'Consumer' OR (int)$b['user_id'] === $userId) {
        $totalSpent += (float)$b['amount'];
        if (isset($categoryTotals[$type])) {
            $categoryTotals[$type] += (float)$b['amount'];
        }
        if ($b['status'] === 'PAID') {
            $paidCount++;
        } else {
            $pendingCount++;
        }
    }
    if ((int)$b['user_id'] === $userId AND !isset($latestBillByType[$type])) {
        $latestBillByType[$type] = $b;
    }
}

$highestUtility = "None yet";
$highestVal     = 0;
foreach ($categoryTotals as $catName => $val) {
    if ($val > $highestVal) {
        $highestVal     = $val;
        $highestUtility = $catName . " (₱" . number_format($val, 2) . ")";
    }
}

$consumersList = [];
$allUsersList  = [];
$auditLogsList = [];

if ($role === 'Administrator') {
    $consumersList = $pdo->query("SELECT user_id, full_name, email FROM users WHERE role_id = 3 ORDER BY full_name ASC")->fetchAll();
    $allUsersList  = $pdo->query("SELECT * FROM users ORDER BY user_id ASC")->fetchAll();
    $auditLogsList = $pdo->query("
        SELECT a.*, u.full_name 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.user_id 
        ORDER BY a.log_id DESC LIMIT 40
    ")->fetchAll();
}

$firstName = trim(explode(' ', $currentUser['name'])[0], ',');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($role); ?> Dashboard — UtiliSense</title>
    <link rel="icon" type="image/jpeg" href="assets/images/logo.jpg?v=3">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=6">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="container-fluid py-3 px-3 px-md-4 w-100">
        
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 portal-header">
            <a href="dashboard.php" class="d-flex align-items-center gap-3 text-decoration-none flex-shrink-0">
                <?php if (file_exists(__DIR__ . '/assets/images/logo.jpg')): ?>
                    <img src="assets/images/logo.jpg?v=3" alt="UtiliSense Logo" class="shadow-sm flex-shrink-0" style="width: 44px; height: 44px; object-fit: cover; border-radius: 12px;">
                <?php else: ?>
                    <span class="d-inline-flex align-items-center justify-content-center rounded-4 text-white shadow flex-shrink-0" style="width: 46px; height: 46px; background: linear-gradient(135deg, var(--blue-med), var(--navy-deep));">
                        <i class="bi bi-layers-fill fs-5"></i>
                    </span>
                <?php endif; ?>
                <div>
                    <h4 class="fw-bold mb-0 text-nowrap" style="color: var(--navy-deep); letter-spacing: -0.5px;">UtiliSense</h4>
                    <span class="text-muted small d-block text-nowrap" style="font-size: 0.75rem;">Pampanga Smart Utility Portal</span>
                </div>
            </a>

            <div class="portal-nav-wrap">
                <ul class="morph-nav-dock mb-0" id="dashTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="morph-nav-btn active" data-bs-toggle="pill" data-bs-target="#tab-home" type="button" role="tab">
                            <i class="bi bi-grid-1x2-fill"></i>
                            <span class="nav-label"><?php echo ($role === 'Administrator') ? 'Admin Center' : 'Overview'; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="morph-nav-btn" data-bs-toggle="pill" data-bs-target="#tab-transactions" type="button" role="tab">
                            <i class="bi bi-receipt-cutoff"></i>
                            <span class="nav-label">Bills & Receipts</span>
                        </button>
                    </li>
                    <?php if ($role === 'Administrator'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="morph-nav-btn" data-bs-toggle="pill" data-bs-target="#tab-audit" type="button" role="tab">
                            <i class="bi bi-shield-check"></i>
                            <span class="nav-label">Audit Logs</span>
                        </button>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item" role="presentation">
                        <button class="morph-nav-btn" data-bs-toggle="pill" data-bs-target="#tab-profile" type="button" role="tab">
                            <i class="bi bi-person-fill"></i>
                            <span class="nav-label">Profile</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded-pill shadow-sm border">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 34px; height: 34px; background: var(--navy-deep); font-size: 0.85rem;">
                    <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                </div>
                <div class="pe-2 d-none d-sm-block">
                    <div class="fw-bold small lh-1" style="color: var(--navy-deep);"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                    <span class="badge rounded-pill <?php echo $role === 'Administrator' ? 'bg-danger' : 'bg-primary'; ?>" style="font-size: 0.62rem;">
                        <?php echo htmlspecialchars($role); ?>
                    </span>
                </div>
                <a href="logout.php" class="btn btn-sm btn-light rounded-circle text-danger border" title="Sign Out">
                    <i class="bi bi-power"></i>
                </a>
            </div>
        </header>

        <?php if (!empty($flashMsg)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show rounded-4 py-2 px-4 mb-4 shadow-sm border-0">
                <i class="bi bi-check-circle-fill me-1"></i> <?php echo htmlspecialchars($flashMsg); ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="tab-content" id="dashTabContent">

            <div class="tab-pane fade show active" id="tab-home" role="tabpanel">
                
                <div class="bento-hero mb-4">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-5">
                            <span class="badge rounded-pill px-3 py-1 mb-2" style="background: rgba(56, 189, 248, 0.22); color: #38BDF8; font-size: 0.72rem;">
                                <i class="bi <?php echo $role === 'Administrator' ? 'bi-shield-lock-fill' : 'bi-stars'; ?> me-1"></i>
                                <?php echo strtoupper(htmlspecialchars($role)); ?> COMMAND CENTER
                            </span>
                            <h2 class="fw-bold mb-3">Hello, <?php echo htmlspecialchars($firstName); ?>!</h2>
                            <?php if ($role === 'Administrator'): ?>
                                <p class="text-white-50 small mb-3">
                                    Manage all registered Pampanga user accounts, assign Staff/Admin permissions, encode bills, and audit security logs.
                                </p>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($role === 'Consumer'): ?>
                                    <button class="btn btn-pill-aqua btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#chooseUtilitiesModal">
                                        <i class="bi bi-sliders me-1"></i> Choose My Utilities
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-light btn-sm rounded-pill px-3 py-2 fw-bold" style="color: var(--navy-deep);" data-bs-toggle="modal" data-bs-target="#encodeBillModal">
                                        <i class="bi bi-plus-circle-fill me-1 text-primary"></i> Encode Consumer Bill
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <div class="hero-stat-pill h-100">
                                        <div class="text-white-50 small mb-1" style="font-size: 0.7rem;">
                                            <?php echo ($role === 'Consumer') ? 'MY TOTAL RECORDED' : 'SYSTEM BILLING VOLUME'; ?>
                                        </div>
                                        <h4 class="fw-bold mb-0 text-break" style="font-size: clamp(1rem, 1.45vw, 1.35rem);">₱<?php echo number_format($totalSpent, 2); ?></h4>
                                        <small class="text-info" style="font-size: 0.7rem;"><i class="bi bi-database-check"></i> Live MySQL Total</small>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="hero-stat-pill h-100">
                                        <div class="text-white-50 small mb-1" style="font-size: 0.7rem;">
                                            <?php echo ($role === 'Administrator') ? 'REGISTERED ACCOUNTS' : 'HIGHEST EXPENSE'; ?>
                                        </div>
                                        <h5 class="fw-bold mb-0 text-warning text-truncate">
                                            <?php echo ($role === 'Administrator') ? count($allUsersList) . ' Active Users' : htmlspecialchars($highestUtility); ?>
                                        </h5>
                                        <small class="text-white-50" style="font-size: 0.7rem;">
                                            <?php echo ($role === 'Administrator') ? count($consumersList) . ' Consumers' : 'Top Category'; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="hero-stat-pill h-100">
                                        <div class="text-white-50 small mb-1" style="font-size: 0.7rem;">PAYMENT STATUS</div>
                                        <h4 class="fw-bold mb-0"><?php echo (int)$pendingCount; ?> <span class="fs-6 fw-normal text-white-50">Pending</span></h4>
                                        <small class="fw-semibold" style="font-size: 0.72rem; color: #6EE7B7;"><?php echo (int)$paidCount; ?> Paid Bills</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($role === 'Administrator'): ?>
                    <div class="row g-4 mb-4">
                        <div class="col-xl-8">
                            <div class="squircle-card p-4 shadow-sm bg-white h-100">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div>
                                        <h5 class="fw-bold mb-0" style="color: var(--navy-deep);">
                                            <i class="bi bi-people-fill text-primary me-2"></i>System User & Role Management
                                        </h5>
                                        <small class="text-muted">Promote accounts to Staff/Admin or toggle active status</small>
                                    </div>
                                    <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2">
                                        <?php echo count($allUsersList); ?> Total Accounts
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Full Name</th><th>Email</th><th>Assign Role</th><th>Status</th><th>Action</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allUsersList as $u): $uRoleId = (int)$u['role_id']; ?>
                                            <tr>
                                                <td class="fw-bold">#<?php echo (int)$u['user_id']; ?></td>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                <td>
                                                    <form method="POST" action="dashboard.php" class="d-flex gap-1 m-0">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="change_role_user_id" value="<?php echo (int)$u['user_id']; ?>">
                                                        <select name="new_role_id" class="form-select form-select-sm rounded-pill" style="width: 140px;" <?php echo ((int)$u['user_id'] === $userId) ? 'disabled' : ''; ?>>
                                                            <option value="1" <?php echo ($uRoleId === 1) ? 'selected' : ''; ?>>Administrator</option>
                                                            <option value="2" <?php echo ($uRoleId === 2) ? 'selected' : ''; ?>>Staff</option>
                                                            <option value="3" <?php echo ($uRoleId === 3) ? 'selected' : ''; ?>>Consumer</option>
                                                        </select>
                                                        <?php if ((int)$u['user_id'] !== $userId): ?>
                                                            <button type="submit" class="btn btn-sm btn-pill-navy py-1 px-3">Save</button>
                                                        <?php endif; ?>
                                                    </form>
                                                </td>
                                                <td>
                                                    <span class="badge rounded-pill bg-<?php echo ($u['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                                        <?php echo strtoupper(htmlspecialchars($u['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ((int)$u['user_id'] !== $userId): ?>
                                                        <form method="POST" action="dashboard.php" class="m-0">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="toggle_user_id" value="<?php echo (int)$u['user_id']; ?>">
                                                            <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($u['status']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                                <?php echo ($u['status'] === 'active') ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Current Admin</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="navy-panel p-4 shadow h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0"><i class="bi bi-shield-check text-info me-2"></i>Recent Security Audit</h5>
                                    <span class="badge rounded-pill bg-info text-dark"><?php echo count($auditLogsList); ?> Logs</span>
                                </div>
                                <div style="max-height: 340px; overflow-y: auto;">
                                    <?php foreach (array_slice($auditLogsList, 0, 8) as $log):
                                        $actType = $log['action_type'] ?? $log['action'] ?? 'EVENT';
                                        $actDesc = $log['description'] ?? $log['details'] ?? $log['action_description'] ?? 'System activity recorded';
                                        $actUser = $log['full_name'] ?? (!empty($log['user_id']) ? 'User #' . $log['user_id'] : 'System');
                                    ?>
                                        <div class="py-2 border-bottom border-light border-opacity-10 small">
                                            <div class="d-flex justify-content-between">
                                                <span class="fw-bold text-info"><?php echo htmlspecialchars((string)$actType); ?></span>
                                                <span class="text-white-50" style="font-size: 0.7rem;"><?php echo htmlspecialchars((string)($log['created_at'] ?? '')); ?></span>
                                            </div>
                                            <div class="text-white small"><?php echo htmlspecialchars((string)$actDesc); ?></div>
                                            <small class="text-white-50" style="font-size: 0.68rem;">By: <?php echo htmlspecialchars((string)$actUser); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0" style="color: var(--navy-deep);">
                            <i class="bi bi-lightning-charge-fill text-primary me-1"></i> Your Subscribed Utility Cards
                        </h5>
                        <small class="text-muted">Pay any pending bill online to automatically settle it</small>
                    </div>

                    <div class="row g-4 mb-4">
                        <?php if (empty($mySubscriptions)): ?>
                            <div class="col-12">
                                <div class="squircle-card p-5 text-center bg-white h-100 d-flex flex-column justify-content-center align-items-center">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: rgba(28, 104, 197, 0.1); color: var(--blue-med);">
                                        <i class="bi bi-sliders fs-3"></i>
                                    </div>
                                    <h5 class="fw-bold mb-1" style="color: var(--navy-deep);">Your dashboard starts clean at ₱0.00</h5>
                                    <p class="text-muted small mb-3" style="max-width: 460px;">
                                        Select which bills you hold (Electricity, Water, or Internet) and your Pampanga provider to create your live cards.
                                    </p>
                                    <button class="btn btn-pill-navy btn-sm px-4" data-bs-toggle="modal" data-bs-target="#chooseUtilitiesModal">
                                        Choose My Utilities Now
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php
                            $cardStyles = ['card-blue-primary', 'card-aqua-secondary', 'card-indigo-tertiary'];
                            foreach ($mySubscriptions as $idx => $sub):
                                $uType      = $sub['utility_type'];
                                $provider   = $sub['provider_name'];
                                $bill       = $latestBillByType[$uType] ?? null;
                                $cardClass  = $cardStyles[$idx % count($cardStyles)];
                                $iconClass  = ($uType === 'Electricity') ? 'bi-lightning-charge-fill' : (($uType === 'Water') ? 'bi-droplet-fill' : 'bi-wifi');
                                $displayAmt = $bill ? (float)$bill['amount'] : 0;
                            ?>
                            <div class="col-12 col-md-4">
                                <div class="<?php echo $cardClass; ?> p-4 shadow-sm h-100 d-flex flex-column justify-content-between">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <span class="badge bg-white bg-opacity-25 text-white rounded-pill mb-2" style="font-size: 0.68rem;">
                                                <?php echo htmlspecialchars($provider); ?>
                                            </span>
                                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($uType); ?></h6>
                                        </div>
                                        <div class="utility-icon-orb"><i class="bi <?php echo $iconClass; ?>"></i></div>
                                    </div>
                                    <div class="my-2">
                                        <small class="opacity-75 d-block" style="font-size: 0.72rem;">Current Recorded Amount</small>
                                        <h2 class="fw-bold mb-0">₱<?php echo number_format($displayAmt, 2); ?></h2>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-3 mt-2 border-top border-white border-opacity-10">
                                        <span class="small opacity-75" style="font-size: 0.74rem;">
                                            <?php echo $bill ? 'Due ' . date('M j, Y', strtotime($bill['due_date'])) : 'No active bill yet'; ?>
                                        </span>
                                        <?php if ($bill): ?>
                                            <?php if ($bill['status'] === 'PAID'): ?>
                                                <span class="badge bg-success rounded-pill px-2 py-1" style="font-size: 0.68rem;">
                                                    <i class="bi bi-check-circle-fill me-1"></i>PAID
                                                </span>
                                            <?php else: ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-warning fw-bold text-dark rounded-pill py-0 px-3 shadow-sm"
                                                        style="font-size: 0.72rem;"
                                                        onclick="openPayModal(<?php echo (int)$bill['bill_id']; ?>, '<?php echo htmlspecialchars($uType . ' — ' . $provider, ENT_QUOTES); ?>', <?php echo $displayAmt; ?>)">
                                                    <i class="bi bi-wallet2 me-1"></i>Pay Now
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-white bg-opacity-25 text-white rounded-pill" style="font-size: 0.65rem;">₱0.00</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="navy-panel p-4 shadow h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h5 class="fw-bold mb-0">
                                        <?php echo ($role === 'Consumer') ? 'Recent Transactions & Alerts' : 'All Consumer Billing Feed'; ?>
                                    </h5>
                                    <small class="text-white-50">
                                        <?php echo ($role === 'Consumer') ? 'Pay online to automatically mark bills as PAID' : 'Live monitoring of encoded consumer statements and payment statuses'; ?>
                                    </small>
                                </div>
                                <span class="badge rounded-pill px-3 py-2" style="background: rgba(56, 189, 248, 0.2); color: #38BDF8;">
                                    <?php echo count($allBills); ?> Records
                                </span>
                            </div>

                            <?php if (count($allBills) > 0): ?>
                                <?php foreach (array_slice($allBills, 0, 5) as $b): ?>
                                    <div class="d-flex justify-content-between align-items-center py-3 border-bottom border-light border-opacity-10">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(43, 169, 224, 0.18); color: #38BDF8;">
                                                <i class="bi bi-receipt fs-5"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($b['utility_type'] . ' — ' . $b['provider_name']); ?>
                                                    <?php if ($role !== 'Consumer'): ?>
                                                        <span class="badge bg-info text-dark ms-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars($b['full_name'] ?? ''); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-white-50 small">
                                                    Due: <?php echo date('M j, Y', strtotime($b['due_date'])); ?>
                                                    <?php if ($b['consumption'] > 0): ?>
                                                        • <?php echo number_format($b['consumption'], 0); ?> <?php echo $b['utility_type'] === 'Electricity' ? 'kWh' : 'm³'; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold fs-5">₱<?php echo number_format($b['amount'], 2); ?></div>
                                            <?php if ($role === 'Consumer' AND $b['status'] !== 'PAID'): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-info py-1 px-3 rounded-pill text-dark fw-bold shadow-sm"
                                                        style="font-size: 0.72rem;"
                                                        onclick="openPayModal(<?php echo (int)$b['bill_id']; ?>, '<?php echo htmlspecialchars($b['utility_type'] . ' — ' . $b['provider_name'], ENT_QUOTES); ?>', <?php echo (float)$b['amount']; ?>)">
                                                    <i class="bi bi-credit-card-2-front-fill me-1"></i> Pay Bill
                                                </button>
                                            <?php elseif ($b['status'] === 'PAID'): ?>
                                                <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1" style="font-size: 0.68rem;">
                                                    <i class="bi bi-check-circle-fill me-1"></i>PAID
                                                </span>
                                            <?php elseif ($b['status'] === 'OVERDUE'): ?>
                                                <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 0.68rem;">OVERDUE</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1" style="font-size: 0.68rem;">PENDING</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5 text-white-50">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    No bills encoded yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="squircle-card p-4 shadow-sm h-100 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold mb-0" style="color: var(--navy-deep);">Spend Analytics & Breakdown</h5>
                                    <small class="text-muted">Compare utility proportions across categories</small>
                                </div>
                                <span class="badge rounded-pill px-3 py-2 text-white" style="background: var(--navy-deep);">Live Charts</span>
                            </div>
                            <div class="row g-3 align-items-center">
                                <div class="col-sm-5 text-center">
                                    <div style="height: 200px;"><canvas id="doughnutChart"></canvas></div>
                                </div>
                                <div class="col-sm-7">
                                    <div style="height: 200px;"><canvas id="waveChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-transactions" role="tabpanel">
                <div class="squircle-card p-4 shadow-sm bg-white mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h4 class="fw-bold mb-0" style="color: var(--navy-deep);">All Recorded Bills Ledger</h4>
                        <input type="text" id="tableSearch" class="form-control custom-input py-2" style="max-width: 260px;" placeholder="Search utility, provider..." onkeyup="filterTable()">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="historyTable">
                            <thead class="table-light">
                                <tr>
                                    <?php if ($role !== 'Consumer'): ?><th>Consumer</th><?php endif; ?>
                                    <th>Utility</th>
                                    <th>Company</th>
                                    <th>Consumption</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <?php if ($role === 'Consumer'): ?><th class="text-end">Payment</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allBills)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No bills encoded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($allBills as $bill): ?>
                                    <tr>
                                        <?php if ($role !== 'Consumer'): ?>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($bill['full_name'] ?? 'User'); ?></td>
                                        <?php endif; ?>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($bill['utility_type']); ?></td>
                                        <td><?php echo htmlspecialchars($bill['provider_name']); ?></td>
                                        <td><?php echo $bill['consumption'] > 0 ? number_format($bill['consumption'], 0) : '-'; ?></td>
                                        <td class="fw-bold">₱<?php echo number_format($bill['amount'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($bill['due_date'])); ?></td>
                                        <td>
                                            <span class="badge rounded-pill bg-<?php echo $bill['status'] === 'PAID' ? 'success' : ($bill['status'] === 'OVERDUE' ? 'danger' : 'warning text-dark'); ?>">
                                                <?php echo htmlspecialchars($bill['status']); ?>
                                            </span>
                                        </td>
                                        <?php if ($role === 'Consumer'): ?>
                                        <td class="text-end">
                                            <?php if ($bill['status'] !== 'PAID'): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-pill-aqua py-1 px-3"
                                                        onclick="openPayModal(<?php echo (int)$bill['bill_id']; ?>, '<?php echo htmlspecialchars($bill['utility_type'] . ' — ' . $bill['provider_name'], ENT_QUOTES); ?>', <?php echo (float)$bill['amount']; ?>)">
                                                    Pay Online
                                                </button>
                                            <?php else: ?>
                                                <span class="text-success small fw-bold"><i class="bi bi-patch-check-fill me-1"></i>Paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="squircle-card p-4 shadow-sm bg-white">
                    <h4 class="fw-bold mb-3" style="color: var(--navy-deep);">Official Digital Payment Receipts</h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference No.</th>
                                    <th>Utility & Provider</th>
                                    <th>Payment Channel</th>
                                    <th>Account / Mobile</th>
                                    <th>Amount Paid</th>
                                    <th>Mode</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myReceipts)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No payments completed yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($myReceipts as $r): ?>
                                    <tr>
                                        <td><code class="fw-bold text-primary"><?php echo htmlspecialchars($r['reference_no']); ?></code></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($r['utility_type'] . ' — ' . $r['provider_name']); ?></td>
                                        <td><span class="badge bg-light text-dark border rounded-pill"><?php echo htmlspecialchars($r['payment_channel']); ?></span></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($r['account_identifier'] ?: 'Branch Walk-In'); ?></td>
                                        <td class="fw-bold text-success">₱<?php echo number_format((float)$r['amount_paid'], 2); ?></td>
                                        <td>
                                            <span class="badge rounded-pill bg-<?php echo $r['payment_mode'] === 'ONLINE_INSTANT' ? 'success' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($r['payment_mode']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted"><?php echo htmlspecialchars($r['paid_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($role === 'Administrator'): ?>
            <div class="tab-pane fade" id="tab-audit" role="tabpanel">
                <div class="squircle-card p-4 shadow-sm bg-white">
                    <h4 class="fw-bold mb-3" style="color: var(--navy-deep);">Full System Security Audit Logs</h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="table-light">
                                <tr><th>Timestamp</th><th>User</th><th>Action</th><th>Description</th><th>IP Address</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogsList as $log):
                                    $actType = $log['action_type'] ?? $log['action'] ?? 'EVENT';
                                    $actDesc = $log['description'] ?? $log['details'] ?? $log['action_description'] ?? 'System activity recorded';
                                    $actUser = $log['full_name'] ?? (!empty($log['user_id']) ? 'User #' . $log['user_id'] : 'System');
                                ?>
                                <tr>
                                    <td class="text-muted"><?php echo htmlspecialchars((string)($log['created_at'] ?? '')); ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars((string)$actUser); ?></td>
                                    <td><span class="badge bg-dark rounded-pill px-3 py-1"><?php echo htmlspecialchars((string)$actType); ?></span></td>
                                    <td><?php echo htmlspecialchars((string)$actDesc); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars((string)($log['ip_address'] ?? '127.0.0.1')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

           <div class="tab-pane fade" id="tab-profile" role="tabpanel">
                <div class="squircle-card p-5 shadow-sm bg-white mx-auto text-center" style="max-width: 480px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white mb-3 shadow" style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--blue-med), var(--navy-deep));">
                        <i class="bi bi-person-fill display-5"></i>
                    </div>
                    <h4 class="fw-bold mb-1" style="color: var(--navy-deep);"><?php echo htmlspecialchars($currentUser['name']); ?></h4>
                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                    <span class="badge rounded-pill px-3 py-2 mb-4" style="background: var(--navy-deep);"><?php echo htmlspecialchars($role); ?> Account</span>

                    <div class="text-start bg-light rounded-4 p-3 border small">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Account ID</span>
                            <span class="fw-bold" style="color: var(--navy-deep);">#<?php echo (int)$liveUser['user_id']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Account Status</span>
                            <span class="badge rounded-pill bg-success"><?php echo strtoupper(htmlspecialchars($liveUser['status'] ?? 'active')); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Last Login</span>
                            <span class="fw-semibold"><?php echo !empty($liveUser['last_login']) ? date('M j, Y g:i A', strtotime($liveUser['last_login'])) : 'Active Session'; ?></span>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span class="text-muted">Member Since</span>
                            <span class="fw-semibold"><?php echo !empty($liveUser['created_at']) ? date('M j, Y', strtotime($liveUser['created_at'])) : '—'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
    <div class="modal fade" id="chooseUtilitiesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content squircle-card border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--navy-deep);">Choose Your Subscribed Utilities</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" action="dashboard.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="save_my_utilities" value="1">
                        <div class="p-3 bg-light rounded-4 mb-3 border">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities[]" value="Electricity" id="uElec" <?php echo isset($subscribedMap['Electricity']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="uElec">Electricity Bill</label>
                            </div>
                            <select class="form-select custom-input py-2" name="provider[Electricity]">
                                <option value="Meralco" <?php echo ($savedElec === 'Meralco') ? 'selected' : ''; ?>>Meralco (₱11.85 / kWh)</option>
                                <option value="PELCO II" <?php echo ($savedElec === 'PELCO II') ? 'selected' : ''; ?>>PELCO II Pampanga (₱10.90 / kWh)</option>
                                <option value="PELCO I" <?php echo ($savedElec === 'PELCO I') ? 'selected' : ''; ?>>PELCO I Pampanga (₱11.10 / kWh)</option>
                                <option value="PELCO III" <?php echo ($savedElec === 'PELCO III') ? 'selected' : ''; ?>>PELCO III Pampanga (₱11.05 / kWh)</option>
                                <option value="SFELAPCO (San Fernando)" <?php echo ($savedElec === 'SFELAPCO (San Fernando)') ? 'selected' : ''; ?>>SFELAPCO San Fernando (₱12.05 / kWh)</option>
                                <option value="Angeles Electric Corp (AEC)" <?php echo ($savedElec === 'Angeles Electric Corp (AEC)') ? 'selected' : ''; ?>>Angeles Electric Corp (₱11.95 / kWh)</option>
                            </select>
                        </div>
                        <div class="p-3 bg-light rounded-4 mb-3 border">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities[]" value="Water" id="uWater" <?php echo isset($subscribedMap['Water']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="uWater">Water Bill</label>
                            </div>
                            <select class="form-select custom-input py-2" name="provider[Water]">
                                <option value="PrimeWater Pampanga" <?php echo ($savedWater === 'PrimeWater Pampanga') ? 'selected' : ''; ?>>PrimeWater Pampanga (₱38.50 / m³)</option>
                                <option value="Angeles City Water District" <?php echo ($savedWater === 'Angeles City Water District') ? 'selected' : ''; ?>>Angeles City Water District (₱36.00 / m³)</option>
                                <option value="San Fernando Water District" <?php echo ($savedWater === 'San Fernando Water District') ? 'selected' : ''; ?>>City of San Fernando Water District (₱37.20 / m³)</option>
                                <option value="Mabalacat City Water District" <?php echo ($savedWater === 'Mabalacat City Water District') ? 'selected' : ''; ?>>Mabalacat City Water District (₱36.80 / m³)</option>
                            </select>
                        </div>
                        <div class="p-3 bg-light rounded-4 mb-4 border">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities[]" value="Internet" id="uNet" <?php echo isset($subscribedMap['Internet']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="uNet">Internet / WiFi Bill</label>
                            </div>
                            <select class="form-select custom-input py-2" name="provider[Internet]">
                                <option value="Converge ICT FiberX" <?php echo ($savedNet === 'Converge ICT FiberX') ? 'selected' : ''; ?>>Converge ICT FiberX (₱1,500 / mo)</option>
                                <option value="PLDT Home Fiber" <?php echo ($savedNet === 'PLDT Home Fiber') ? 'selected' : ''; ?>>PLDT Home Fiber (₱1,699 / mo)</option>
                                <option value="Globe At Home" <?php echo ($savedNet === 'Globe At Home') ? 'selected' : ''; ?>>Globe At Home (₱1,499 / mo)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-pill-navy w-100">Save Selected Utilities</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <?php if ($role !== 'Consumer'): ?>
    <div class="modal fade" id="encodeBillModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content squircle-card border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--navy-deep);">Encode Consumer Utility Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" action="dashboard.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="encode_bill" value="1">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Select Consumer Account</label>
                            <select class="form-select custom-input py-2" name="target_user_id" required>
                                <?php foreach ($consumersList as $c): ?>
                                    <option value="<?php echo (int)$c['user_id']; ?>">
                                        <?php echo htmlspecialchars($c['full_name'] . ' (' . $c['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Utility Type</label>
                            <select class="form-select custom-input py-2" name="utility_type" id="calcUtilityType" onchange="syncCalcProviders()">
                                <option value="Electricity">Electricity</option>
                                <option value="Water">Water</option>
                                <option value="Internet">Internet</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Company / Provider & Rate</label>
                            <select class="form-select custom-input py-2" name="provider_name" id="calcProvider" onchange="computeReadingMath()"></select>
                        </div>
                        <div id="meterReadingBox">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted">Previous Reading</label>
                                    <input type="number" step="0.01" class="form-control custom-input py-2" name="prev_reading" id="calcPrev" value="0" oninput="computeReadingMath()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted">Present Reading</label>
                                    <input type="number" step="0.01" class="form-control custom-input py-2" name="pres_reading" id="calcPres" value="0" oninput="computeReadingMath()">
                                </div>
                            </div>
                            <div class="alert alert-info rounded-4 py-2 px-3 small mb-3 d-flex justify-content-between">
                                <span>(Present − Previous) × Rate</span>
                                <strong id="calcUnitsLabel">0 kWh</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Due Date</label>
                            <input type="date" class="form-control custom-input py-2" name="due_date" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Total Bill Amount (₱)</label>
                            <input type="number" step="0.01" class="form-control custom-input fw-bold fs-5 text-primary" name="amount" id="calcAmount" value="0.00" required>
                        </div>
                        <button type="submit" class="btn btn-pill-aqua w-100">Save Bill to Database</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPayModal(billId, billerText, amount) {
            const modalEl = document.getElementById('payBillModal');
            if (!modalEl) return;
            document.getElementById('payBillIdInput').value = billId;
            document.getElementById('payBillerLabel').textContent = billerText;
            document.getElementById('payAmountLabel').textContent = '₱' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('paymentChannelSelect').value = 'GCash';
            handlePaymentChannelChange();
            new bootstrap.Modal(modalEl).show();
        }

        function handlePaymentChannelChange() {
            const selectEl = document.getElementById('paymentChannelSelect');
            if (!selectEl) return;
            const channel = selectEl.value;
            const onlineBox = document.getElementById('onlineFieldsBox');
            const walkInBox = document.getElementById('walkInNoticeBox');
            const accLabel = document.getElementById('accountFieldLabel');
            const accInput = document.getElementById('accountFieldInput');
            const submitBtn = document.getElementById('paySubmitBtn');

            if (channel.indexOf('Nearest Branch') !== -1) {
                onlineBox.style.display = 'none';
                walkInBox.style.display = 'block';
                accInput.removeAttribute('required');
                submitBtn.className = 'btn btn-pill-navy w-100 py-3 shadow-sm';
                submitBtn.innerHTML = '<i class="bi bi-printer-fill me-1"></i> Generate Walk-In Payment Slip (Last Resort)';
            } else {
                onlineBox.style.display = 'block';
                walkInBox.style.display = 'none';
                accInput.setAttribute('required', 'required');
                submitBtn.className = 'btn btn-pill-aqua w-100 py-3 shadow-sm';
                submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Pay Now & Automatically Mark as Paid';

                if (channel.indexOf('Card') !== -1) {
                    accLabel.textContent = 'Cardholder Name & Last 4 Digits of Card';
                    accInput.placeholder = 'e.g. Juan Dela Cruz •••• 4821';
                } else if (channel.indexOf('Bank') !== -1) {
                    accLabel.textContent = channel + ' Account Number';
                    accInput.placeholder = 'Enter your bank account number';
                } else {
                    accLabel.textContent = channel + ' Mobile Number';
                    accInput.placeholder = 'e.g. 0917-XXX-XXXX';
                }
            }
        }

        const PROVIDER_RATES = {
            'Electricity': [
                { name: 'Meralco', rate: 11.85, unit: 'kWh' },
                { name: 'PELCO II', rate: 10.90, unit: 'kWh' },
                { name: 'PELCO I', rate: 11.10, unit: 'kWh' },
                { name: 'PELCO III', rate: 11.05, unit: 'kWh' },
                { name: 'SFELAPCO (San Fernando)', rate: 12.05, unit: 'kWh' },
                { name: 'Angeles Electric Corp (AEC)', rate: 11.95, unit: 'kWh' }
            ],
            'Water': [
                { name: 'PrimeWater Pampanga', rate: 38.50, unit: 'm³' },
                { name: 'Angeles City Water District', rate: 36.00, unit: 'm³' },
                { name: 'San Fernando Water District', rate: 37.20, unit: 'm³' },
                { name: 'Mabalacat City Water District', rate: 36.80, unit: 'm³' }
            ],
            'Internet': [
                { name: 'Converge ICT FiberX', rate: 1500.00, unit: 'Plan' },
                { name: 'PLDT Home Fiber', rate: 1699.00, unit: 'Plan' },
                { name: 'Globe At Home', rate: 1499.00, unit: 'Plan' }
            ]
        };

        function syncCalcProviders() {
            const typeEl = document.getElementById('calcUtilityType');
            const select = document.getElementById('calcProvider');
            const meterBox = document.getElementById('meterReadingBox');
            if (!typeEl) return;
            const type = typeEl.value;
            select.innerHTML = '';
            (PROVIDER_RATES[type] ? PROVIDER_RATES[type] : []).forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.name;
                opt.setAttribute('data-rate', p.rate);
                opt.setAttribute('data-unit', p.unit);
                opt.textContent = `${p.name} (₱${p.rate.toFixed(2)} / ${p.unit})`;
                select.appendChild(opt);
            });
            meterBox.style.display = (type === 'Electricity' ? true : (type === 'Water')) ? 'block' : 'none';
            computeReadingMath();
        }

        function computeReadingMath() {
            const typeEl = document.getElementById('calcUtilityType');
            const select = document.getElementById('calcProvider');
            if (!typeEl) return;
            const type = typeEl.value;
            const selectedOpt = select.options[select.selectedIndex];
            if (!selectedOpt) return;
            const rate = parseFloat(selectedOpt.getAttribute('data-rate'));
            const unit = selectedOpt.getAttribute('data-unit');
            if (type === 'Electricity' ? true : (type === 'Water')) {
                const prev = parseFloat(document.getElementById('calcPrev').value);
                const pres = parseFloat(document.getElementById('calcPres').value);
                const safePrev = isNaN(prev) ? 0 : prev;
                const safePres = isNaN(pres) ? 0 : pres;
                const consumed = Math.max(0, safePres - safePrev);
                document.getElementById('calcUnitsLabel').textContent = `${consumed} ${unit}`;
                document.getElementById('calcAmount').value = (consumed * rate).toFixed(2);
            } else {
                document.getElementById('calcAmount').value = rate.toFixed(2);
            }
        }

        syncCalcProviders();

        const dbTotals = <?php echo json_encode(array_values($categoryTotals)); ?>;
        const dbLabels = ['Electricity', 'Water', 'Internet'];
        const hasSpend = dbTotals.some(v => v > 0);

        new Chart(document.getElementById('doughnutChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: hasSpend ? dbLabels : ['No Bills Yet'],
                datasets: [{
                    data: hasSpend ? dbTotals : [1],
                    backgroundColor: hasSpend ? ['#0B1E43', '#1C68C5', '#2BA9E0'] : ['#E2E8F0'],
                    borderWidth: 0,
                    cutout: '72%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }
            }
        });

        const ctxWave = document.getElementById('waveChart').getContext('2d');
        new Chart(ctxWave, {
            type: 'line',
            data: {
                labels: dbLabels,
                datasets: [{
                    data: dbTotals,
                    borderColor: '#1C68C5',
                    backgroundColor: 'rgba(43, 169, 224, 0.18)',
                    borderWidth: 3,
                    tension: 0.42,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#0B1E43'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }
            }
        });

        function filterTable() {
            const q = document.getElementById('tableSearch').value.toLowerCase();
            document.querySelectorAll('#historyTable tbody tr').forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>