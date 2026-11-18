<?php
if (file_exists('config.php')) {
    require_once 'config.php';
}
if (!isset($pdo) AND file_exists('config/database.php')) {
    require_once 'config/database.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (function_exists('requireRole')) {
    requireRole(['consumer']);
}

$consumer_id = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
$full_name   = $_SESSION['full_name'] ?? ($_SESSION['user']['name'] ?? 'Consumer');
$account_no  = $_SESSION['account_number'] ?? ('UTS-' . str_pad((string)$consumer_id, 4, '0', STR_PAD_LEFT));
$msg         = '';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        bill_id INT NOT NULL,
        consumer_id INT NOT NULL,
        amount_paid DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(80) NOT NULL,
        reference_number VARCHAR(80) NOT NULL,
        paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");

$hasBillsTable        = false;
$hasUtilityBillsTable = false;
try {
    $tables               = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasBillsTable        = in_array('bills', $tables);
    $hasUtilityBillsTable = in_array('utility_bills', $tables);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' AND (isset($_POST['pay_now']) OR isset($_POST['quick_mark_paid']))) {
    $bill_id  = (int)($_POST['bill_id'] ?? 0);
    $source   = $_POST['bill_source'] ?? 'bills';
    $method   = trim($_POST['payment_method'] ?? 'Instant Online Settlement');
    $isWalkIn = (strpos($method, 'Nearest Branch') !== false);
    $ref_no   = $isWalkIn
        ? ('UTS-BRANCH-' . strtoupper(substr(md5(uniqid()), 0, 8)))
        : ('UTS-PAY-' . strtoupper(substr(md5(uniqid()), 0, 8)));

    $pdo->beginTransaction();
    try {
        $paidAmount = 0;

        if ($source === 'utility_bills' AND $hasUtilityBillsTable) {
            $stmt = $pdo->prepare("SELECT * FROM utility_bills WHERE bill_id = ? AND user_id = ?");
            $stmt->execute([$bill_id, $consumer_id]);
            $ub = $stmt->fetch();
            if ($ub) {
                $paidAmount = (float)$ub['amount'];
                if (!$isWalkIn) {
                    $pdo->prepare("UPDATE utility_bills SET status = 'PAID' WHERE bill_id = ?")->execute([$bill_id]);
                }
            }
        } elseif ($hasBillsTable) {
            $stmt = $pdo->prepare("SELECT * FROM bills WHERE bill_id = ? AND consumer_id = ?");
            $stmt->execute([$bill_id, $consumer_id]);
            $b = $stmt->fetch();
            if ($b) {
                $paidAmount = (float)($b['amount_due'] ?? $b['total_amount'] ?? $b['amount'] ?? 0);
                if (!$isWalkIn) {
                    $pdo->prepare("UPDATE bills SET status = 'paid' WHERE bill_id = ?")->execute([$bill_id]);
                }
            }
        }

        if ($paidAmount > 0 OR $bill_id > 0) {
            if ($paidAmount <= 0) {
                $paidAmount = (float)($_POST['fallback_amount'] ?? 0);
            }
            $pdo->prepare("INSERT INTO payments (bill_id, consumer_id, amount_paid, payment_method, reference_number) VALUES (?, ?, ?, ?, ?)")
                ->execute([$bill_id, $consumer_id, $paidAmount, $method, $ref_no]);

            if (function_exists('logActivity')) {
                logActivity($pdo, $consumer_id, 'CONSUMER_PAYMENT', "Paid Bill #$bill_id via $method [Ref: $ref_no]");
            } elseif (function_exists('log_activity')) {
                log_activity($pdo, $consumer_id, 'CONSUMER_PAYMENT', "Paid Bill #$bill_id via $method [Ref: $ref_no]");
            }
        }

        $pdo->commit();
        if ($isWalkIn) {
            $msg = "Walk-In Branch Slip Generated ($ref_no). Present this at the nearest branch; your bill remains pending until verified at the counter.";
        } else {
            $msg = "Payment Confirmed via $method! Official Receipt Reference: $ref_no";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Payment Error: " . $e->getMessage();
    }
}

$util_filter = $_GET['utility'] ?? '';
$bills       = [];

if ($hasBillsTable) {
    try {
        $q = "SELECT b.bill_id,
                     ut.utility_name,
                     ut.unit_label,
                     b.billing_period,
                     b.previous_reading,
                     b.present_reading,
                     b.consumption,
                     b.amount_due,
                     b.due_date,
                     LOWER(b.status) AS status,
                     'bills' AS bill_source
              FROM bills b
              JOIN utility_types ut ON b.utility_id = ut.utility_id
              WHERE b.consumer_id = ?";
        $params = [$consumer_id];
        if ($util_filter !== '') {
            $q .= " AND ut.utility_name = ?";
            $params[] = $util_filter;
        }
        $q .= " ORDER BY b.status ASC, b.due_date ASC";
        $billsStmt = $pdo->prepare($q);
        $billsStmt->execute($params);
        $bills = $billsStmt->fetchAll();
    } catch (Exception $e) {}
}

if ($hasUtilityBillsTable) {
    try {
        $q2 = "SELECT bill_id,
                      utility_type AS utility_name,
                      CASE WHEN utility_type = 'Electricity' THEN 'kWh' WHEN utility_type = 'Water' THEN 'm³' ELSE 'Unit' END AS unit_label,
                      provider_name AS billing_period,
                      previous_reading,
                      present_reading,
                      consumption,
                      amount AS amount_due,
                      due_date,
                      CASE WHEN UPPER(status) = 'PAID' THEN 'paid' ELSE 'unpaid' END AS status,
                      'utility_bills' AS bill_source
               FROM utility_bills
               WHERE user_id = ?";
        $params2 = [$consumer_id];
        if ($util_filter !== '') {
            $q2 .= " AND utility_type = ?";
            $params2[] = $util_filter;
        }
        $q2 .= " ORDER BY status ASC, due_date ASC";
        $ubStmt = $pdo->prepare($q2);
        $ubStmt->execute($params2);
        $ubRows = $ubStmt->fetchAll();
        $bills  = array_merge($bills, $ubRows);
    } catch (Exception $e) {}
}

$receipts = [];
try {
    $receiptsStmt = $pdo->prepare("
        SELECT p.*,
               COALESCE(b.billing_period, ub.provider_name, 'Monthly Cycle') AS billing_period,
               COALESCE(ut.utility_name, ub.utility_type, 'Utility Service') AS utility_name
        FROM payments p
        LEFT JOIN bills b ON p.bill_id = b.bill_id
        LEFT JOIN utility_types ut ON b.utility_id = ut.utility_id
        LEFT JOIN utility_bills ub ON p.bill_id = ub.bill_id
        WHERE p.consumer_id = ?
        ORDER BY p.paid_at DESC
    ");
    $receiptsStmt->execute([$consumer_id]);
    $receipts = $receiptsStmt->fetchAll();
} catch (Exception $e) {}

$unpaidTotal = 0;
foreach ($bills as $b) {
    if ($b['status'] !== 'paid') {
        $unpaidTotal += (float)$b['amount_due'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | UtiliSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <?php if (file_exists('assets/css/style.css')): ?>
        <link href="assets/css/style.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="dashboard-layout">

    <aside class="sidebar d-none d-md-flex no-print">
        <div class="mb-4 px-2 d-flex align-items-center gap-2 text-primary fs-4 fw-bold">
            <i class="bi bi-lightning-charge-fill text-warning"></i> UtiliSense
        </div>
        <div class="text-muted small fw-bold mb-2 px-2">Main Menu</div>
        <a href="consumer_dashboard.php" class="sidebar-link active"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a href="#statements" class="sidebar-link"><i class="bi bi-receipt"></i> Statements</a>
        <a href="#receipts" class="sidebar-link"><i class="bi bi-clock-history"></i> History</a>
        
        <div class="text-muted small fw-bold mt-4 mb-2 px-2">Account</div>
        <a href="#statements" class="sidebar-link"><i class="bi bi-wallet2"></i> Payment Methods</a>
        <a href="dashboard.php" class="sidebar-link"><i class="bi bi-person"></i> Main Portal</a>
        
        <div class="mt-auto">
            <div class="card bg-light border-0 p-3 text-center rounded-4">
                <div class="small fw-bold mb-1">Need Help?</div>
                <div class="small text-muted mb-2">Contact our support team</div>
                <a href="logout.php" class="btn btn-danger btn-sm w-100 rounded-pill">Sign Out</a>
            </div>
        </div>
    </aside>

    <nav class="mobile-nav d-md-none no-print">
        <a href="index.php" class="mobile-nav-item"><i class="bi bi-house"></i><span>Home</span></a>
        <a href="#statements" class="mobile-nav-item"><i class="bi bi-receipt"></i><span>Bills</span></a>
        <a href="consumer_dashboard.php" class="mobile-nav-item active"><i class="bi bi-wallet2"></i><span>Pay</span></a>
        <a href="#receipts" class="mobile-nav-item"><i class="bi bi-clock-history"></i><span>History</span></a>
        <a href="logout.php" class="mobile-nav-item"><i class="bi bi-person"></i><span>Profile</span></a>
    </nav>

    <main class="main-content">

        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom no-print">
            <div>
                <h3 class="fw-bold mb-0">Overview</h3>
                <span class="text-muted small">Simplify utility management with UtiliSense.</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="input-group d-none d-lg-flex" style="width: 250px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="statementSearch" class="form-control border-start-0 ps-0" placeholder="Search bills..." onkeyup="filterStatements()">
                </div>
                <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded-pill border">
                    <i class="bi bi-person-circle fs-5 text-secondary"></i>
                    <span class="fw-semibold small"><?= htmlspecialchars($full_name) ?></span>
                </div>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show fw-semibold border-0 shadow-sm rounded-4">
                <i class="bi bi-patch-check-fill me-2"></i><?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4 no-print">
            <div class="col-md-4">
                <div class="card-glass p-3 bg-white rounded-4 shadow-sm">
                    <div class="text-muted small fw-semibold mb-1 border-start border-3 border-primary ps-2">Meter Account Number</div>
                    <div class="fs-4 fw-bold text-dark"><?= htmlspecialchars($account_no) ?></div>
                    <div class="text-success small fw-medium mt-2"><i class="bi bi-arrow-up-right"></i> Active Account</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-glass p-3 bg-white rounded-4 shadow-sm">
                    <div class="text-muted small fw-semibold mb-1 border-start border-3 border-danger ps-2">Total Outstanding Balance</div>
                    <div class="fs-4 fw-bold text-dark">₱<?= number_format($unpaidTotal, 2) ?></div>
                    <div class="text-muted small mt-2">Due across all services</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-glass p-3 bg-white rounded-4 shadow-sm">
                    <div class="text-muted small fw-semibold mb-1 border-start border-3 border-success ps-2">Completed Online Payments</div>
                    <div class="fs-4 fw-bold text-dark"><?= count($receipts) ?> <span class="fs-6 text-muted">Receipts</span></div>
                    <div class="text-success small fw-medium mt-2"><i class="bi bi-shield-check"></i> Instant Digital Verification</div>
                </div>
            </div>
        </div>

        <div class="card-glass p-4 bg-white rounded-4 shadow-sm mb-4" id="statements">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <h5 class="fw-bold mb-0">My Utility Statements</h5>
                <div class="btn-group btn-group-sm">
                    <a href="consumer_dashboard.php" class="btn btn-outline-secondary <?= $util_filter===''?'active':'' ?>">All</a>
                    <a href="?utility=Electricity" class="btn btn-outline-secondary <?= $util_filter==='Electricity'?'active':'' ?>">⚡ Electricity</a>
                    <a href="?utility=Water" class="btn btn-outline-secondary <?= $util_filter==='Water'?'active':'' ?>">💧 Water</a>
                    <a href="?utility=Internet" class="btn btn-outline-secondary <?= $util_filter==='Internet'?'active':'' ?>">🌐 Internet</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="statementsTable">
                    <thead class="border-bottom">
                        <tr class="text-muted small">
                            <th>UTILITY</th>
                            <th>PROVIDER / PERIOD</th>
                            <th>CONSUMPTION</th>
                            <th>DUE DATE</th>
                            <th>AMOUNT DUE</th>
                            <th>STATUS</th>
                            <th class="text-end">PAYMENT ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No utility statements found for your account.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $b):
                                $isPaid = ($b['status'] === 'paid');
                                $bId    = (int)$b['bill_id'];
                                $bSrc   = $b['bill_source'] ?? 'bills';
                                $bName  = $b['utility_name'] ?? 'Utility';
                                $bPer   = $b['billing_period'] ?? 'Current Cycle';
                                $bAmt   = (float)$b['amount_due'];
                            ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($bName) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($bPer) ?></td>
                                <td class="small">
                                    <?php if ((float)($b['consumption'] ?? 0) > 0): ?>
                                        <?= number_format((float)$b['consumption'], 0) . ' ' . htmlspecialchars($b['unit_label'] ?? '') ?>
                                    <?php else: ?>
                                        Fixed Plan
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars($b['due_date']) ?></td>
                                <td class="fw-bold text-dark">₱<?= number_format($bAmt, 2) ?></td>
                                <td>
                                    <?php if ($isPaid): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">PAID</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1">PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (!$isPaid): ?>
                                        <div class="d-inline-flex gap-2">
                                            <button type="button"
                                                    class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold"
                                                    onclick="openPaymentModal(<?= $bId ?>, '<?= htmlspecialchars($bSrc, ENT_QUOTES) ?>', '<?= htmlspecialchars($bName, ENT_QUOTES) ?>', '<?= htmlspecialchars('₱' . number_format($bAmt, 2), ENT_QUOTES) ?>', <?= $bAmt ?>, '<?= htmlspecialchars($bPer, ENT_QUOTES) ?>')">
                                                <i class="bi bi-wallet2 me-1"></i>Pay Online
                                            </button>
                                            <form method="POST" action="consumer_dashboard.php" class="m-0">
                                                <input type="hidden" name="quick_mark_paid" value="1">
                                                <input type="hidden" name="bill_id" value="<?= $bId ?>">
                                                <input type="hidden" name="bill_source" value="<?= htmlspecialchars($bSrc) ?>">
                                                <input type="hidden" name="fallback_amount" value="<?= $bAmt ?>">
                                                <input type="hidden" name="payment_method" value="Direct Consumer Settlement">
                                                <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold">
                                                    <i class="bi bi-check2-circle me-1"></i>Mark Paid
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-success small fw-semibold"><i class="bi bi-check-all me-1"></i>Settled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-glass p-4 bg-white rounded-4 shadow-sm" id="receipts">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Official Digital Receipts</h5>
                <button onclick="window.print()" class="btn btn-light btn-sm border rounded-pill px-3 no-print"><i class="bi bi-printer me-1"></i>Print</button>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-striped align-middle mb-0">
                    <thead class="border-bottom">
                        <tr class="text-muted small">
                            <th>REFERENCE NUMBER</th>
                            <th>UTILITY</th>
                            <th>PERIOD / PROVIDER</th>
                            <th>AMOUNT PAID</th>
                            <th>CHANNEL</th>
                            <th>TIMESTAMP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receipts)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No payments recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($receipts as $r): ?>
                            <tr>
                                <td><code class="fw-bold text-dark bg-light px-2 py-1 rounded"><?= htmlspecialchars($r['reference_number']) ?></code></td>
                                <td class="small fw-semibold"><?= htmlspecialchars($r['utility_name']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($r['billing_period']) ?></td>
                                <td class="fw-bold text-success">₱<?= number_format((float)$r['amount_paid'], 2) ?></td>
                                <td><span class="badge bg-light text-secondary border rounded-pill"><?= htmlspecialchars($r['payment_method']) ?></span></td>
                                <td class="small text-muted"><?= htmlspecialchars($r['paid_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="badge bg-success-subtle text-success rounded-pill mb-1">Instant Digital Checkout</span>
                        <h5 class="modal-title fw-bold">Secure Utility Payment</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="consumer_dashboard.php">
                    <div class="modal-body p-4">
                        <input type="hidden" name="bill_id" id="modalBillId">
                        <input type="hidden" name="bill_source" id="modalBillSource" value="bills">
                        <input type="hidden" name="fallback_amount" id="modalRawAmount" value="0">

                        <div class="bg-light p-3 rounded-4 mb-4 text-center border">
                            <div class="text-muted small mb-1" id="modalUtilityName"></div>
                            <div class="fs-2 fw-bold text-dark mb-1" id="modalAmountDue"></div>
                            <div class="text-muted small" id="modalBillingPeriod"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Payment Method (Online Instant Recommended)</label>
                            <select name="payment_method" id="modalPaymentMethod" class="form-select rounded-3" onchange="togglePaymentDetails()" required>
                                <optgroup label="Instant E-Wallets (No Physical Queue)">
                                    <option value="GCash" selected>GCash Express E-Wallet</option>
                                    <option value="Maya (PayMaya)">Maya (PayMaya) E-Wallet</option>
                                    <option value="GrabPay">GrabPay Wallet</option>
                                    <option value="ShopeePay">ShopeePay Wallet</option>
                                </optgroup>
                                <optgroup label="Digital & Online Banks">
                                    <option value="MariBank (SeaBank)">MariBank (SeaBank Instant Transfer)</option>
                                    <option value="GoTyme Bank">GoTyme Bank</option>
                                    <option value="BDO Online">BDO Unibank Online</option>
                                    <option value="BPI Mobile">BPI Mobile / Online</option>
                                    <option value="UnionBank Online">UnionBank Online</option>
                                    <option value="LandBank iAccess">LandBank iAccess</option>
                                </optgroup>
                                <optgroup label="Cards & QR Ph">
                                    <option value="Debit / Credit Card (Visa / Mastercard)">Debit or Credit Card (Visa / Mastercard)</option>
                                    <option value="QR Ph Instant Scan">QR Ph National QR Scan</option>
                                </optgroup>
                                <optgroup label="Last Resort (Only If You Have No Digital Account)">
                                    <option value="Nearest Branch / Over-the-Counter (Last Resort)">Nearest Physical Branch / Over-the-Counter (Last Option)</option>
                                </optgroup>
                            </select>
                        </div>

                        <div id="digitalAccountGroup" class="mb-2">
                            <label class="form-label small fw-semibold" id="accountInputLabel">Mobile / Account / Card Number</label>
                            <input type="text" id="accountInputField" class="form-control rounded-3" placeholder="e.g. 0917-XXX-XXXX" required>
                            <small class="text-success d-block mt-2">
                                <i class="bi bi-lightning-charge-fill"></i> Settles your bill immediately online—no need to visit a branch.
                            </small>
                        </div>

                        <div id="walkInWarningGroup" style="display: none;">
                            <div class="alert alert-warning rounded-3 small mb-0">
                                <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Last Option Notice:</strong>
                                UtiliSense is designed so you don't have to travel or line up at a physical store. Choose this only if you have no access to GCash, Maya, MariBank, or a bank card.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="submit" name="pay_now" id="confirmPayBtn" class="btn btn-primary w-100 rounded-pill fw-bold py-2">
                            Confirm Instant Online Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPaymentModal(billId, billSource, utilityName, formattedAmount, rawAmount, period) {
            document.getElementById('modalBillId').value = billId;
            document.getElementById('modalBillSource').value = billSource;
            document.getElementById('modalRawAmount').value = rawAmount;
            document.getElementById('modalUtilityName').textContent = utilityName;
            document.getElementById('modalAmountDue').textContent = formattedAmount;
            document.getElementById('modalBillingPeriod').textContent = period;
            document.getElementById('modalPaymentMethod').value = 'GCash';
            togglePaymentDetails();
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        function togglePaymentDetails() {
            const method = document.getElementById('modalPaymentMethod').value;
            const digitalGroup = document.getElementById('digitalAccountGroup');
            const walkInGroup = document.getElementById('walkInWarningGroup');
            const inputField = document.getElementById('accountInputField');
            const inputLabel = document.getElementById('accountInputLabel');
            const btn = document.getElementById('confirmPayBtn');

            if (method.indexOf('Nearest Branch') !== -1) {
                digitalGroup.style.display = 'none';
                walkInGroup.style.display = 'block';
                inputField.removeAttribute('required');
                btn.textContent = 'Generate Walk-In Branch Slip (Last Resort)';
                btn.className = 'btn btn-secondary w-100 rounded-pill fw-bold py-2';
            } else {
                digitalGroup.style.display = 'block';
                walkInGroup.style.display = 'none';
                inputField.setAttribute('required', 'required');
                btn.textContent = 'Confirm Instant Online Payment';
                btn.className = 'btn btn-primary w-100 rounded-pill fw-bold py-2';

                if (method.indexOf('Card') !== -1) {
                    inputLabel.textContent = 'Card Number (Visa / Mastercard)';
                    inputField.placeholder = '•••• •••• •••• 4821';
                } else if (method.indexOf('Bank') !== -1) {
                    inputLabel.textContent = method + ' Account Number';
                    inputField.placeholder = 'Enter bank account number';
                } else {
                    inputLabel.textContent = method + ' Mobile Number';
                    inputField.placeholder = 'e.g. 0917-XXX-XXXX';
                }
            }
        }

        function filterStatements() {
            const q = document.getElementById('statementSearch').value.toLowerCase();
            document.querySelectorAll('#statementsTable tbody tr').forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
    </script>
</body>
</html>