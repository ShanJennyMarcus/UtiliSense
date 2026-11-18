<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UtiliSense — Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex flex-column justify-content-between">

    <nav class="navbar navbar-expand-lg py-3 sticky-top">
        <div class="container bg-white bg-opacity-75 px-4 py-2 shadow-sm" style="border-radius: 50px; backdrop-filter: blur(12px);">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php" style="color: var(--navy-deep);">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width: 36px; height: 36px; background: var(--blue-med);">
                    <i class="bi bi-layers-fill"></i>
                </span>
                UtiliSense
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navMenu">
                <ul class="navbar-nav align-items-center gap-3 mt-3 mt-lg-0">
              
                    <li class="nav-item"><a class="nav-link fw-medium" href="login.php">Sign In</a></li>
                    <li class="nav-item">
                        <button type="button" class="btn btn-pill-navy py-2 px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#getStartedModal">
                            Get Started
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

 
    <header class="container py-5 my-auto">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 reveal active">
                <h1 class="display-4 fw-bold mb-3" style="color: var(--navy-deep); line-height: 1.15;">
                    Hello, Welcome to UtiliSense.
                </h1>
                <p class="lead text-muted mb-4">
                    Track your electricity, water, internet, and LPG bills in one secure place—built for households and utility teams across Pampanga.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <button type="button" class="btn btn-pill-navy shadow px-4 py-3" data-bs-toggle="modal" data-bs-target="#getStartedModal">
                        Get Started <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                    <a href="login.php" class="btn btn-pill-aqua shadow-sm px-4 py-3">Member Sign In</a>
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
</body>
</html>