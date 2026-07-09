<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DENR Digital System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/denrlogo.png" />
</head>

<body>
    <div class="floating-elements">
        <!-- Change floating-login link to trigger login modal -->
        <a href="#" class="floating-login" data-bs-toggle="modal" data-bs-target="#loginModal" title="Login">
            <i class="fas fa-user-circle"></i>
        </a>
        <div class="social-links">
            <a href="https://www.facebook.com/DENR9Official" class="social-link" data-bs-toggle="tooltip"
                data-bs-placement="left" title="Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="social-link" data-bs-placement="left" title="About Us" data-bs-toggle="modal"
                data-bs-target="#aboutModal">
                <i class="fas fa-info-circle"></i>
            </a>
        </div>
    </div>

    <!-- About Us Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-4">
                    <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="about-content">
                        <div class="about-logo">
                            <img src="assets/img/logo.png" alt="DENR Logo" class="img-fluid">
                        </div>
                        <div class="about-text">
                            <h3>DENR REGION IX</h3>
                            <h4>CENTRALIZED DIGITAL SYSTEM FOR PRIVATE TREE PLANTATION REGISTRATION AND PERMIT
                                MANAGEMENT</h4>
                            <p>
                                is an official digital platform of the Department of Environment and Natural Resources –
                                Region IX, designed to streamline the registration, mapping, and permitting of private
                                tree plantations across the Zamboanga Peninsula.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content login-content">
                <div class="modal-body p-4">
                    <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="login-form">
                        <div class="login-header">
                            <img src="assets/img/denrlogo.png" alt="DENR Logo" class="login-logo">
                            <h3>Welcome Back!</h3>
                            <p>Log in to access the DENR Digital Portal</p>
                        </div>
                        <form action="#" method="POST">
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" placeholder="Email" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" placeholder="Password" required>
                                </div>
                            </div>
                            <div class="form-group d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <a href="#" class="forgot-password">Forgot Password?</a>
                            </div>
                            <button type="submit" class="btn login-btn">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="hero">
        <div class="hero-overlay"></div>
        <div class="forest-overlay"></div>

        <div class="container position-relative">
            <div class="row min-vh-100 justify-content-center align-items-center">
                <div class="col-lg-5 text-center text-lg-start order-2 order-lg-1">
                    <div class="decorative-image">
                        <img src="assets/img/asset-bg.png" alt="Digital Forest Management" class="img-fluid">
                    </div>
                </div>
                <div class="col-lg-7 text-center text-lg-start order-1 order-lg-2">
                    <div class="logos-container">
                        <div class="logo">
                            <img src="assets/img/denrlogo.png" alt="DENR Logo" class="img-fluid">
                        </div>
                    </div>

                    <div class="hero-content">
                        <h1 class="main-title text-center">DENR REGION IX</h1>
                        <div class="subtitle-wrapper">
                            <p class="subtitle text-center">Centralized Digital System for Private Tree<br>
                                Plantation Registration and Permit Management</p>
                        </div>

                        <div class="cta-container">
                            <a href="#" class="cta-button primary">
                                <i class="fas fa-user-plus"></i>
                                <span>Start Registration</span>
                            </a>
                            <a href="#" class="cta-button secondary">
                                <i class="fas fa-file-alt"></i>
                                <span>Apply for Permit</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        &copy; 2025 DENR REGION IX. All Rights Reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
</body>

</html>