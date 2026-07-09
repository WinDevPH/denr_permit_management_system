<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DENR Digital System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/denrlogo.png" />
</head>

<body class="denr-wp-body">
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="site-brand" aria-label="DENR Region IX - Home">
                <img src="assets/img/denrlogo.png" alt="" class="site-logo" />
                <div class="site-brand-text">
                    <span class="site-name">DENR Region IX</span>
                    <span class="site-tagline">Digital System</span>
                </div>
            </a>
            <div class="header-right">
                <a href="#" class="header-avatar" aria-label="Profile" title="Profile">
                    <svg class="header-avatar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <!-- About Us Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-wp">
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
                            <h4>DENR REGION IX CENTRALIZED DIGITAL SYSTEM FOR TREE REGISTRATION AND PERMITS IN PRIVATE AND LANDS</h4>
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
            <div class="modal-content modal-wp login-content">
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
                        <!-- Login Form -->
                        <form action="handlers/login.php" method="POST" id="loginForm">
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" id="loginEmail" class="form-control" placeholder="Email"
                                        autocomplete="email" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" id="loginPassword" class="form-control"
                                        placeholder="Password" autocomplete="current-password" required
                                        minlength="1" maxlength="128">
                                    <button type="button" class="password-toggle" onclick="togglePassword(this)"
                                        aria-label="Show password as plain text. Only use in a private place.">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <a href="#" class="forgot-password">Forgot Password?</a>
                            </div>
                            <button type="submit" class="btn login-btn">Sign In</button>
                            <div class="text-center mt-3">
                                <small class="text-muted">Don't have an account?
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#registrationModal"
                                        data-bs-dismiss="modal">Register</a>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registrationModal" tabindex="-1" aria-labelledby="registrationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-wp registration-content">
                <div class="modal-body p-4">
                    <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="registration-form">
                        <div class="registration-header">
                            <img src="assets/img/denrlogo.png" alt="DENR Logo" class="registration-logo">
                            <h3>Create Account</h3>
                            <p>Register as a Landowner to start your application</p>
                        </div>
                        <!-- Registration Form -->
                        <form action="handlers/register.php" method="POST" id="registrationForm">
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="full_name" placeholder="Full Name"
                                        required>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" placeholder="Email Address"
                                        required>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" class="form-control" name="contact_number" id="registerContactNumber"
                                        placeholder="Digits only (e.g. 09171234567)" required maxlength="15" autocomplete="tel"
                                        inputmode="numeric" pattern="[0-9]{7,15}" title="7–15 digits, numbers only">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="registerPassword" class="form-label small mb-1">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock" aria-hidden="true"></i></span>
                                    <input type="password" id="registerPassword" name="password"
                                        class="form-control" placeholder="Enter a secure password"
                                        autocomplete="new-password" minlength="6" maxlength="128" required
                                        aria-describedby="registerPasswordHint registerPasswordPolicy">
                                    <button type="button" class="password-toggle" onclick="togglePassword(this)"
                                        aria-label="Show password as plain text. Only use in a private place.">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <small id="registerPasswordHint" class="form-text text-muted">Use at least 6 characters.</small>
                                <span id="registerPasswordPolicy" class="visually-hidden">Password must be at least 6 characters.</span>
                            </div>
                            <div class="form-group">
                                <label for="registerConfirmPassword" class="form-label small mb-1">Confirm password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock" aria-hidden="true"></i></span>
                                    <input type="password" id="registerConfirmPassword" name="confirm_password"
                                        class="form-control" placeholder="Re-enter your password"
                                        autocomplete="new-password" minlength="6" maxlength="128" required
                                        aria-describedby="registerConfirmHint">
                                    <button type="button" class="password-toggle" onclick="togglePassword(this)"
                                        aria-label="Show confirm password as plain text. Only use in a private place.">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <small id="registerConfirmHint" class="form-text text-muted">Must match your password above.</small>
                            </div>
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">I agree to the Terms and
                                        Conditions</label>
                                </div>
                            </div>
                            <button type="submit" class="btn registration-btn">Create Account</button>
                            <div class="text-center mt-3">
                                <small class="text-muted">Already have an account?
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal"
                                        data-bs-dismiss="modal">Sign In</a>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Services Modal -->
    <div class="modal fade" id="servicesModal" tabindex="-1" aria-labelledby="servicesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-wp">
                <div class="modal-body p-4">
                    <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="services-content">
                        <h3 class="text-center mb-4">Our Services</h3>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="service-card">
                                    <div class="service-icon">
                                        <i class="fas fa-seedling"></i>
                                    </div>
                                    <h4>Plantation Registration</h4>
                                    <ul>
                                        <li>Register your private tree plantation</li>
                                        <li>Record plantation details and location</li>
                                        <li>Track registration status</li>
                                        <li>Update plantation information</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="service-card">
                                    <div class="service-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <h4>Permit Management</h4>
                                    <ul>
                                        <li>Apply for cutting permits</li>
                                        <li>Request registration certificates</li>
                                        <li>Monitor permit application status</li>
                                        <li>View permit history</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="site-main">
        <div class="hero-block">
            <div class="hero-container">
                <div class="hero-grid">
                    <div class="hero-media-wrap">
                        <figure class="hero-media">
                            <img src="assets/img/asset-bg.png" alt="Digital Forest Management" width="560" height="400">
                        </figure>
                    </div>
                    <div class="hero-content">
                        <div class="hero-brand">
                            <img src="assets/img/denrlogo.png" alt="DENR Logo" class="hero-logo" width="96" height="96">
                        </div>
                        <h1 class="hero-title">DENR Region IX</h1>
                        <p class="hero-desc">DENR REGION IX CENTRALIZED DIGITAL SYSTEM FOR TREE REGISTRATION AND PERMITS IN PRIVATE AND LANDS</p>
                        <div class="hero-actions">
                            <a href="#" class="btn-wp btn-wp-primary" data-bs-toggle="modal" data-bs-target="#registrationModal">
                                <svg class="btn-wp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                                Start Registration
                            </a>
                            <a href="#" class="btn-wp btn-wp-secondary" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <svg class="btn-wp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                Continue to Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-bottom">
                <p class="footer-copyright">&copy; 2025 DENR Region IX. All rights reserved.</p>
                <nav class="footer-nav" aria-label="Footer navigation">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">About</a>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#servicesModal">Services</a>
                    <a href="https://www.facebook.com/DENR9Official" target="_blank" rel="noopener">Facebook</a>
                </nav>
            </div>
        </div>
    </footer>

    <!-- Loading Animation Overlay -->
    <div class="loading-overlay">
        <div class="loading-circle">
            <div class="loading-circle-outer"></div>
            <div class="loading-circle-inner"></div>
            <div class="loading-percentage">0%</div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>

    <!-- Success Notification -->
    <div class="custom-notification success">
        <div class="notification-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="notification-content">
            <h6 class="notification-title">Success</h6>
            <p class="notification-message"></p>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Error Notification -->
    <div class="custom-notification error">
        <div class="notification-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="notification-content">
            <h6 class="notification-title">Error</h6>
            <p class="notification-message"></p>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        function showLoading() {
            const overlay = document.querySelector('.loading-overlay');
            overlay.style.display = 'flex';
            let progress = 0;
            const percentage = overlay.querySelector('.loading-percentage');

            return setInterval(() => {
                if (progress < 90) {
                    progress += Math.random() * 15;
                    percentage.textContent = Math.round(progress) + '%';
                }
            }, 100);
        }

        function hideLoading() {
            const overlay = document.querySelector('.loading-overlay');
            const percentage = overlay.querySelector('.loading-percentage');
            percentage.textContent = '100%';

            setTimeout(() => {
                overlay.style.display = 'none';
                percentage.textContent = '0%';
            }, 500);
        }

        function showNotification(type, message) {
            const notification = document.querySelector(`.custom-notification.${type}`);
            notification.querySelector('.notification-message').textContent = message;
            notification.classList.add('show');

            // Auto hide after 3 seconds
            setTimeout(() => {
                hideNotification(type);
            }, 3000);

            // Close button handler
            notification.querySelector('.notification-close').onclick = () => hideNotification(type);
        }

        function hideNotification(type) {
            const notification = document.querySelector(`.custom-notification.${type}`);
            notification.classList.remove('show');
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const loadingTimer = showLoading();

            fetch('handlers/login.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    clearInterval(loadingTimer);
                    if (data.status === 'success') {
                        hideLoading();
                        showNotification('success', 'Login successful!');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    } else {
                        hideLoading();
                        showNotification('error', data.message);
                    }
                })
                .catch(error => {
                    clearInterval(loadingTimer);
                    hideLoading();
                    showNotification('error', 'An error occurred. Please try again.');
                });
        });

        // Update password validation function
        function validatePasswords() {
            const password = document.querySelector('#registrationForm input[name="password"]');
            const confirmPassword = document.querySelector('#registrationForm input[name="confirm_password"]');
            const passwordMatch = password.value === confirmPassword.value;

            if (confirmPassword.value !== '') {
                if (!passwordMatch) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                    // Clear any existing error notifications
                    const errorNotification = document.querySelector('.custom-notification.error');
                    if (errorNotification.classList.contains('show')) {
                        hideNotification('error');
                    }
                }
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const password = this.querySelector('input[name="password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            const contactEl = this.querySelector('input[name="contact_number"]');
            if (contactEl) {
                const digits = contactEl.value.replace(/\D/g, '');
                if (digits.length < 7 || digits.length > 15) {
                    showNotification('error', 'Contact number must be 7–15 digits only (no letters).');
                    return;
                }
                contactEl.value = digits;
            }

            if (password.length < 6) {
                showNotification('error', 'Password must be at least 6 characters.');
                return;
            }
            if (password !== confirmPassword) {
                showNotification('error', 'Passwords do not match!');
                return;
            }

            const loadingTimer = showLoading();
            const form = this;

            fetch('handlers/register.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    clearInterval(loadingTimer);
                    hideLoading();
                    if (data.status === 'success') {
                        showNotification('success', 'Registration successful!');
                        form.reset();
                        setTimeout(() => {
                            document.querySelector('[data-bs-target="#loginModal"]').click();
                        }, 1500);
                    } else {
                        showNotification('error', data.message || 'Registration failed.');
                    }
                })
                .catch(() => {
                    clearInterval(loadingTimer);
                    hideLoading();
                    showNotification('error', 'An error occurred. Please try again.');
                });
        });

        // Update event listeners for password fields
        const passwordInput = document.querySelector('#registrationForm input[name="password"]');
        const confirmPasswordInput = document.querySelector('#registrationForm input[name="confirm_password"]');

        passwordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);

        (function digitsOnlyContact() {
            var el = document.getElementById('registerContactNumber');
            if (!el) return;
            function strip() {
                el.value = String(el.value || '').replace(/\D/g, '').slice(0, 15);
            }
            el.addEventListener('input', strip);
            el.addEventListener('paste', function() {
                setTimeout(strip, 0);
            });
        })();

        // Reset validation state when modal is hidden
        document.getElementById('registrationModal').addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('registrationForm');
            form.reset();
            form.querySelector('input[name="confirm_password"]').setCustomValidity('');
            // Reset password toggle icons
            form.querySelectorAll('.password-toggle i').forEach(icon => {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            });
            // Reset input types to password
            form.querySelectorAll('input[type="text"]').forEach(input => {
                if (input.name === 'password' || input.name === 'confirm_password') {
                    input.type = 'password';
                }
            });
        });

        function togglePassword(button) {
            const input = button.previousElementSibling;
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Clear forms when modals are hidden
        document.getElementById('loginModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('loginForm').reset();
        });

        document.getElementById('registrationModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('registrationForm').reset();
        });
    </script>
</body>

</html>