<?php

require_once 'config.php';
requireRole(['consumer']);

$consumer_id = $_SESSION['user_id'];
$utilities = $pdo->query("SELECT * FROM utility_types")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    if (!empty($_POST['utilities'])) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO consumer_subscriptions (consumer_id, utility_id) VALUES (?, ?)");
            foreach ($_POST['utilities'] as $u_id) {
                $stmt->execute([$consumer_id, (int)$u_id]);
            }
            logActivity($pdo, $consumer_id, 'SET_PREFERENCES', "Selected utility preferences");
            $pdo->commit();
            header("Location: consumer_dashboard.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error saving preferences.";
        }
    } else {
        $error = "Please select at least one utility service.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to UtiliSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="dashboard-layout align-items-center justify-content-center">
    <div class="container" style="max-width: 600px;">
        <div class="card card-uts p-5 text-center shadow-lg">
            <h2 class="fw-bold mb-3">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
            <p class="text-muted mb-4">Before we set up your dashboard, which utility bills do you want to manage with UtiliSense?</p>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3 mb-4 text-start">
                    <?php foreach ($utilities as $u): ?>
                    <div class="col-12">
                        <div class="form-check p-3 border rounded-3 bg-light d-flex align-items-center gap-3">
                            <input class="form-check-input ms-1" style="transform: scale(1.5);" type="checkbox" name="utilities[]" value="<?= $u['utility_id'] ?>" id="util_<?= $u['utility_id'] ?>">
                            <label class="form-check-label fw-bold fs-5 w-100" for="util_<?= $u['utility_id'] ?>" style="cursor: pointer;">
                                <?= htmlspecialchars($u['utility_name']) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="save_preferences" class="btn btn-primary w-100 py-3 fw-bold fs-5 rounded-pill">Continue to Dashboard</button>
            </form>
        </div>
    </div>
</body>
</html>