<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user']['id'];
$uStmt  = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$uStmt->execute([$userId]);
$liveUser = $uStmt->fetch();

if (!$liveUser) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$roleId    = (int)$liveUser['role_id'];
$flashMsg  = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['encode_bill'])) {
    $targetUserId = (int)$_POST['target_user_id'];
    $uType        = $_POST['utility_type'];
    $provName     = $_POST['provider_name'];
    $prevRead     = (float)($_POST['prev_reading'] ?? 0);
    $presRead     = (float)($_POST['pres_reading'] ?? 0);
    $consumption  = max(0, $presRead - $prevRead);
    $amount       = (float)($_POST['amount'] ?? 0);
    $dueDate      = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+15 days'));

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

    log_activity($pdo, $userId, 'ENCODE_BILL', "Staff encoded $uType bill (₱$amount) for User #$targetUserId");
    $flashMsg = "Bill successfully encoded and published to the consumer's dashboard!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['mark_paid_id'])) {
    $billId = (int)$_POST['mark_paid_id'];
    $pdo->prepare("UPDATE utility_bills SET status = 'PAID' WHERE bill_id = ?")->execute([$billId]);
    log_activity($pdo, $userId, 'PAY_BILL', "Staff verified Bill #$billId as PAID");
    $flashMsg = "Bill marked as PAID.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['mark_overdue_id'])) {
    $billId = (int)$_POST['mark_overdue_id'];
    $pdo->prepare("UPDATE utility_bills SET status = 'OVERDUE' WHERE bill_id = ?")->execute([$billId]);
    log_activity($pdo, $userId, 'OVERDUE_BILL', "Staff flagged Bill #$billId as OVERDUE");
    $flashMsg = "Bill flagged as OVERDUE.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['delete_bill_id'])) {
    $billId = (int)$_POST['delete_bill_id'];
    $pdo->prepare("DELETE FROM utility_bills WHERE bill_id = ?")->execute([$billId]);
    log_activity($pdo, $userId, 'DELETE_BILL', "Staff deleted Bill #$billId");
    $flashMsg = "Bill record deleted.";
}

$consumersList = $pdo->query("SELECT user_id, full_name, email, created_at FROM users WHERE role_id = 3 ORDER BY full_name ASC")->fetchAll();
$allSubs       = $pdo->query("SELECT * FROM user_utilities ORDER BY id ASC")->fetchAll();
$consumerSubMap = [];
foreach ($allSubs as $s) {
    $uid = (int)$s['user_id'];
    if (!isset($consumerSubMap[$uid])) {
        $consumerSubMap[$uid] = [];
    }
    $consumerSubMap[$uid][] = $s['utility_type'] . ' (' . $s['provider_name'] . ')';
}

$allBills = $pdo->query("
    SELECT b.*, u.full_name, u.email 
    FROM utility_bills b 
    JOIN users u ON b.user_id = u.user_id 
    ORDER BY b.created_at DESC
")->fetchAll();

$pendingBills = 0;
$paidBills    = 0;
$totalBilled  = 0;
foreach ($allBills as $b) {
    $totalBilled += (float)$b['amount'];
    if ($b['status'] === 'PAID') {
        $paidBills++;
    } else {
        $pendingBills++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Billing Workstation — UtiliSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="container-fluid py-3 px-3 px-md-4 w-100">

        <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <a href="staff_dashboard.php" class="d-flex align-items-center gap-3 text-decoration-none">
                <span class="d-inline-flex align-items-center justify-content-center rounded-4 text-white shadow" style="width: 46px; height: 46px; background: linear-gradient(135deg, #059669, #0B1E43);">
                    <i class="bi bi-speedometer2 fs-5"></i>
                </span>
                <div>
                    <h4 class="fw-bold mb-0" style="color: var(--navy-deep);">UtiliSense Staff Portal</h4>
                    <span class="text-muted small" style="font-size: 0.75rem;">Meter Reading, Billing Computation & Payment Verification</span>
                </div>
            </a>

            <ul class="morph-nav-dock mb-0" id="staffTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="morph-nav-btn active" data-bs-toggle="pill" data-bs-target="#tab-encoder" type="button" role="tab">
                        <i class="bi bi-calculator-fill"></i>
                        <span class="nav-label">Encode Readings</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="morph-nav-btn" data-bs-toggle="pill" data-bs-target="#tab-queue" type="button" role="tab">
                        <i class="bi bi-clipboard2-check-fill"></i>
                        <span class="nav-label">Payment Queue</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="morph-nav-btn" data-bs-toggle="pill" data-bs-target="#tab-consumers" type="button" role="tab">
                        <i class="bi bi-people-fill"></i>
                        <span class="nav-label">Consumers</span>
                    </button>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 bg-white bg-opacity-75 px-3 py-2 rounded-pill shadow-sm border">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 34px; height: 34px; background: #059669; font-size: 0.85rem;">
                    <?php echo strtoupper(substr($liveUser['full_name'], 0, 1)); ?>
                </div>
                <div class="pe-2 d-none d-sm-block">
                    <div class="fw-bold small lh-1" style="color: var(--navy-deep);"><?php echo htmlspecialchars($liveUser['full_name']); ?></div>
                    <span class="badge rounded-pill bg-success" style="font-size: 0.62rem;">Billing Staff</span>
                </div>
                <a href="logout.php" class="btn btn-sm btn-light rounded-circle text-danger border" title="Sign Out">
                    <i class="bi bi-power"></i>
                </a>
            </div>
        </header>

        <?php if (!empty($flashMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 py-2 px-4 mb-4 shadow-sm border-0">
                <i class="bi bi-check-circle-fill me-1"></i> <?php echo htmlspecialchars($flashMsg); ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="squircle-card p-3 bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted fw-bold d-block" style="font-size: 0.7rem;">REGISTERED CONSUMERS</small>
                        <h3 class="fw-bold mb-0" style="color: var(--navy-deep);"><?php echo count($consumersList); ?> Households</h3>
                    </div>
                    <span class="rounded-4 p-3 bg-primary-subtle text-primary fs-4"><i class="bi bi-houses-fill"></i></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="squircle-card p-3 bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted fw-bold d-block" style="font-size: 0.7rem;">AWAITING PAYMENT</small>
                        <h3 class="fw-bold mb-0 text-warning"><?php echo (int)$pendingBills; ?> Pending</h3>
                    </div>
                    <span class="rounded-4 p-3 bg-warning-subtle text-warning fs-4"><i class="bi bi-hourglass-split"></i></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="squircle-card p-3 bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted fw-bold d-block" style="font-size: 0.7rem;">VERIFIED PAID BILLS</small>
                        <h3 class="fw-bold mb-0 text-success"><?php echo (int)$paidBills; ?> Settled</h3>
                    </div>
                    <span class="rounded-4 p-3 bg-success-subtle text-success fs-4"><i class="bi bi-patch-check-fill"></i></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="squircle-card p-3 bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted fw-bold d-block" style="font-size: 0.7rem;">TOTAL BILLED VOLUME</small>
                        <h3 class="fw-bold mb-0 text-primary">₱<?php echo number_format($totalBilled, 2); ?></h3>
                    </div>
                    <span class="rounded-4 p-3 bg-info-subtle text-info fs-4"><i class="bi bi-cash-stack"></i></span>
                </div>
            </div>
        </div>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="tab-encoder" role="tabpanel">
                <div class="row g-4">
                    
                    <div class="col-xl-5">
                        <div class="squircle-card p-4 shadow-sm bg-white h-100">
                            <h5 class="fw-bold mb-1" style="color: var(--navy-deep);">
                                <i class="bi bi-speedometer text-success me-2"></i>Encode Consumer Meter Reading & Bill
                            </h5>
                            <p class="text-muted small mb-4">Select a consumer, enter their meter readings, and publish their statement.</p>

                            <form method="POST" action="staff_dashboard.php">
                                <input type="hidden" name="encode_bill" value="1">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">1. Select Consumer Household</label>
                                    <select class="form-select custom-input py-2" name="target_user_id" id="staffTargetConsumer" required>
                                        <?php foreach ($consumersList as $c): ?>
                                            <option value="<?php echo (int)$c['user_id']; ?>">
                                                <?php echo htmlspecialchars($c['full_name'] . ' — ' . $c['email']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold">2. Utility Type</label>
                                        <select class="form-select custom-input py-2" name="utility_type" id="calcUtilityType" onchange="syncCalcProviders()">
                                            <option value="Electricity">Electricity (kWh)</option>
                                            <option value="Water">Water (m³)</option>
                                            <option value="Internet">Internet (Fixed Plan)</option>
                                            <option value="Cooking Gas (LPG)">Cooking Gas (LPG)</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold">3. Pampanga Provider</label>
                                        <select class="form-select custom-input py-2" name="provider_name" id="calcProvider" onchange="computeReadingMath()"></select>
                                    </div>
                                </div>

                                <div id="meterReadingBox" class="p-3 rounded-4 bg-light border mb-3">
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold text-secondary">Previous Meter Reading</label>
                                            <input type="number" step="0.01" class="form-control custom-input py-2 bg-white" name="prev_reading" id="calcPrev" value="1200" oninput="computeReadingMath()">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold text-secondary">Present Meter Reading</label>
                                            <input type="number" step="0.01" class="form-control custom-input py-2 bg-white" name="pres_reading" id="calcPres" value="1420" oninput="computeReadingMath()">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small pt-2 border-top">
                                        <span class="text-muted">Net Consumption (Present − Previous):</span>
                                        <span class="badge bg-primary rounded-pill px-3 py-1 fs-6" id="calcUnitsLabel">220 kWh</span>
                                    </div>
                                </div>

                                <div class="row g-2 mb-4">
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold">4. Payment Due Date</label>
                                        <input type="date" class="form-control custom-input py-2" name="due_date" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold">5. Total Computed Bill (₱)</label>
                                        <input type="number" step="0.01" class="form-control custom-input py-2 fw-bold fs-5 text-primary" name="amount" id="calcAmount" value="2607.00" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-pill-navy w-100 py-3 shadow-sm">
                                    <i class="bi bi-send-check-fill me-2"></i>Publish Statement to Consumer Dashboard
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-xl-7">
                        <div class="navy-panel p-4 shadow h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold mb-0"><i class="bi bi-list-check text-info me-2"></i>Live Billing & Verification Queue</h5>
                                    <small class="text-white-50">Manage encoded consumer bills, verify payments, or remove test entries</small>
                                </div>
                                <span class="badge rounded-pill bg-info text-dark"><?php echo count($allBills); ?> Statements</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0 small" style="--bs-table-bg: transparent;">
                                    <thead>
                                        <tr class="text-info border-bottom border-secondary">
                                            <th>Consumer</th>
                                            <th>Utility & Company</th>
                                            <th>Reading</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($allBills)): ?>
                                            <tr><td colspan="7" class="text-center text-white-50 py-5">No consumer bills encoded yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($allBills as $b): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($b['full_name'] ?? 'Consumer'); ?></td>
                                                <td><?php echo htmlspecialchars($b['utility_type'] . ' (' . $b['provider_name'] . ')'); ?></td>
                                                <td class="text-white-50">
                                                    <?php echo ($b['consumption'] > 0) ? number_format($b['consumption'], 0) . ' ' . ($b['utility_type'] === 'Electricity' ? 'kWh' : 'm³') : 'Fixed'; ?>
                                                </td>
                                                <td class="fw-bold text-info">₱<?php echo number_format($b['amount'], 2); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($b['due_date'])); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill bg-<?php echo $b['status'] === 'PAID' ? 'success' : ($b['status'] === 'OVERDUE' ? 'danger' : 'warning text-dark'); ?>">
                                                        <?php echo htmlspecialchars($b['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <?php if ($b['status'] !== 'PAID'): ?>
                                                            <form method="POST" action="staff_dashboard.php" class="m-0">
                                                                <input type="hidden" name="mark_paid_id" value="<?php echo (int)$b['bill_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success rounded-pill py-0 px-2" style="font-size: 0.7rem;">Verify Paid</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($b['status'] === 'PENDING'): ?>
                                                            <form method="POST" action="staff_dashboard.php" class="m-0">
                                                                <input type="hidden" name="mark_overdue_id" value="<?php echo (int)$b['bill_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-warning rounded-pill py-0 px-2 text-dark" style="font-size: 0.7rem;">Overdue</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" action="staff_dashboard.php" class="m-0">
                                                            <input type="hidden" name="delete_bill_id" value="<?php echo (int)$b['bill_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2" style="font-size: 0.7rem;">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="tab-pane fade" id="tab-queue" role="tabpanel">
                <div class="squircle-card p-4 shadow-sm bg-white">
                    <h4 class="fw-bold mb-3" style="color: var(--navy-deep);">All Consumer Billing Statements</h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Bill #</th>
                                    <th>Consumer Name</th>
                                    <th>Utility</th>
                                    <th>Provider</th>
                                    <th>Previous → Present</th>
                                    <th>Consumption</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allBills as $b): ?>
                                <tr>
                                    <td>#<?php echo (int)$b['bill_id']; ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($b['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($b['utility_type']); ?></td>
                                    <td><?php echo htmlspecialchars($b['provider_name']); ?></td>
                                    <td><?php echo number_format($b['previous_reading'], 0) . ' → ' . number_format($b['present_reading'], 0); ?></td>
                                    <td><?php echo number_format($b['consumption'], 0); ?></td>
                                    <td class="fw-bold text-primary">₱<?php echo number_format($b['amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($b['due_date'])); ?></td>
                                    <td><span class="badge rounded-pill bg-<?php echo $b['status'] === 'PAID' ? 'success' : 'warning text-dark'; ?>"><?php echo htmlspecialchars($b['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-consumers" role="tabpanel">
                <div class="squircle-card p-4 shadow-sm bg-white">
                    <h4 class="fw-bold mb-3" style="color: var(--navy-deep);">Registered Household Consumers & Subscribed Utilities</h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Consumer Name</th>
                                    <th>Email Address</th>
                                    <th>Subscribed Pampanga Utilities</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consumersList as $c):
                                    $cid  = (int)$c['user_id'];
                                    $subs = $consumerSubMap[$cid] ?? [];
                                ?>
                                <tr>
                                    <td class="fw-bold">#<?php echo $cid; ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($c['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['email']); ?></td>
                                    <td>
                                        <?php if (empty($subs)): ?>
                                            <span class="text-muted small">No utilities selected by consumer yet</span>
                                        <?php else: ?>
                                            <?php foreach ($subs as $sLabel): ?>
                                                <span class="badge rounded-pill bg-primary-subtle text-primary me-1"><?php echo htmlspecialchars($sLabel); ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
            ],
            'Cooking Gas (LPG)': [
                { name: 'Petron Gasul (11kg)', rate: 985.00, unit: 'Tank' },
                { name: 'Solane LPG (11kg)', rate: 990.00, unit: 'Tank' }
            ]
        };

        function syncCalcProviders() {
            const type = document.getElementById('calcUtilityType').value;
            const select = document.getElementById('calcProvider');
            const meterBox = document.getElementById('meterReadingBox');
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
            const type = document.getElementById('calcUtilityType').value;
            const select = document.getElementById('calcProvider');
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
    </script>
</body>
</html>