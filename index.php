<?php
session_start();
if (isset($_SESSION['user'])) {
    $target = ((int)($_SESSION['user']['role_id'] ?? 3) === 2) ? 'staff_dashboard.php' : 'dashboard.php';
    header("Location: " . $target);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UtiliSense — Welcome</title>
    <link rel="icon" type="image/jpeg" href="assets/images/logo.jpg?v=3">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body class="d-flex flex-column justify-content-between">

    <nav class="navbar py-3 sticky-top px-3 px-lg-5">
        <div class="container-fluid bg-white px-4 py-2 shadow-sm" style="border-radius: 50px;">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2 mb-0" href="index.php" style="color: var(--navy-deep);">
                <img src="assets/images/logo.jpg?v=3" alt="UtiliSense Logo" class="shadow-sm flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover; border-radius: 10px;">
                <span>UtiliSense</span>
            </a>
        </div>
    </nav>

    <header class="container-fluid px-4 px-lg-5 py-4 py-lg-5 my-auto">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 reveal active">
                <span class="badge rounded-pill px-3 py-2 mb-3 fw-semibold" style="background-color: rgba(28, 104, 197, 0.12); color: var(--blue-med);">
                    <i class="bi bi-shield-check me-1"></i> Smart Utility & Bill Tracker
                </span>
                <h1 class="display-4 fw-bold mb-4" style="color: var(--navy-deep); line-height: 1.15;">
                    Hello, Welcome to UtiliSense.
                </h1>
                <div>
                    <button type="button" class="btn btn-pill-navy shadow px-4 py-3" data-bs-toggle="modal" data-bs-target="#getStartedModal">
                        Get Started <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-6 reveal active">
                <div class="floating-element p-4 squircle-card shadow-lg bg-white">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="card-blue-primary p-3 shadow-sm">
                                <small class="opacity-75" style="font-size: 0.75rem;">Electricity (PELCO / Meralco)</small>
                                <h3 class="fw-bold my-1">kWh Tracker</h3>
                                <small class="opacity-75" style="font-size: 0.7rem;">Auto-Computed Rates</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card-aqua-secondary p-3 shadow-sm">
                                <small class="opacity-75" style="font-size: 0.75rem;">Water & Internet</small>
                                <h3 class="fw-bold my-1">m³ & Fiber</h3>
                                <span class="badge bg-white text-dark rounded-pill" style="font-size: 0.65rem;">Pampanga Providers</span>
                            </div>
                        </div>
                    </div>
                    <div class="navy-panel p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold small">Live System Security</span>
                            <span class="badge rounded-pill bg-info text-dark" style="font-size: 0.65rem;">Synced</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle p-2 bg-primary bg-opacity-25 text-info"><i class="bi bi-lightning-fill"></i></span>
                                <div class="small fw-semibold">Unified Login & Role Access Control</div>
                            </div>
                            <span class="fw-bold text-info">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="modal fade" id="getStartedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content squircle-card p-3 border-0">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--navy-deep);">How would you like to continue?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-4">Choose whether you already have an account or need to create a new one.</p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <a href="login.php" class="squircle-card p-4 d-block text-center text-decoration-none h-100 border">
                                <div class="rounded-4 d-inline-flex p-3 mb-2 text-white" style="background: var(--navy-deep);">
                                    <i class="bi bi-box-arrow-in-right fs-4"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Existing Account</h6>
                                <p class="text-muted small mb-0">Sign in with your email and password.</p>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a href="register.php" class="squircle-card p-4 d-block text-center text-decoration-none h-100 border">
                                <div class="rounded-4 d-inline-flex p-3 mb-2 text-white" style="background: var(--aqua-bright);">
                                    <i class="bi bi-person-plus-fill fs-4"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Sign Up</h6>
                                <p class="text-muted small mb-0">Create a new UtiliSense account.</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center py-4 text-muted small">
        UtiliSense Utility & Bill Management System © <?php echo date('Y'); ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>