<?php
// login.php - FIXED VERSION
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with secure parameters
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

// Regenerate session ID to prevent fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    // Check user type and redirect accordingly
    if(isset($_SESSION['user_type'])) {
        if($_SESSION['user_type'] === 'admin') {
            header('Location: admin-dashboard.php');
        } else {
            header('Location: user-dashboard.php');
        }
    } else {
        header('Location: index.php');
    }
    exit();
}

// Database connection
$error = ''; // Initialize error variable
$pdo = null;

try {
    // Get database instance and connection
    require_once 'config/database.php';
    $database = Database::getInstance();
    $pdo = $database->getConnection();
} catch (PDOException $e) {
    $error = 'Database connection failed. Please try again later.';
    error_log("Login DB Connection Error: " . $e->getMessage());
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if(empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif(!$pdo) {
        $error = 'System error: Database connection failed.';
    } else {
        try {
            // Prepare SQL statement - check by email OR username
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, full_name, user_type, is_active FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user) {
                // Check if user is active
                if(!$user['is_active']) {
                    $error = 'Your account has been deactivated. Please contact support.';
                }
                // Verify password
                elseif(password_verify($password, $user['password_hash'])) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_attempts'] = 0; // Reset login attempts
                    
                    // Set remember me cookie if requested
                    if($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        setcookie('remember_token', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
                        
                        // Store token in database - check if table exists first
                        try {
                            // Check if remember_tokens table exists
                            $tableCheck = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
                            if($tableCheck->rowCount() > 0) {
                                $tokenStmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                                $hashedToken = hash('sha256', $token);
                                $expiryDate = date('Y-m-d H:i:s', $expiry);
                                $tokenStmt->execute([$user['id'], $hashedToken, $expiryDate]);
                            }
                        } catch (Exception $e) {
                            error_log("Remember token error: " . $e->getMessage());
                            // Continue login even if token storage fails
                        }
                    }
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    // Set success message
                    $_SESSION['login_success'] = true;
                    
                    // Redirect based on user type
                    if($user['user_type'] === 'admin') {
                        header('Location: admin-dashboard.php');
                    } else {
                        header('Location: user-dashboard.php');
                    }
                    exit();
                } else {
                    $error = 'Invalid email or password';
                    // Log failed login attempt
                    error_log("Failed login attempt for email: $email from IP: " . $_SERVER['REMOTE_ADDR']);
                    
                    // Track failed attempts (prevent brute force)
                    if (!isset($_SESSION['login_attempts'])) {
                        $_SESSION['login_attempts'] = 1;
                    } else {
                        $_SESSION['login_attempts']++;
                    }
                    
                    // Lock account after 5 failed attempts
                    if ($_SESSION['login_attempts'] >= 5) {
                        $error = 'Too many failed attempts. Please try again in 15 minutes.';
                        // Implement a delay
                        sleep(min($_SESSION['login_attempts'] - 4, 10)); // Delay increases with attempts
                    }
                }
            } else {
                $error = 'Invalid email or password';
                // Still track attempts for non-existent users
                if (!isset($_SESSION['login_attempts'])) {
                    $_SESSION['login_attempts'] = 1;
                } else {
                    $_SESSION['login_attempts']++;
                }
            }
        } catch(PDOException $e) {
            error_log("Database error in login.php: " . $e->getMessage());
            $error = 'Unable to process your request. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="login-styles.css">
    
    <style>
        /* Additional inline styles for login page only */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }
        
        .login-container {
            animation: fadeIn 0.5s ease;
            display: flex;
            max-width: 1200px;
            width: 100%;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            min-height: 700px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Alert animations */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <div class="login-container">
        <!-- Left side - Brand & Features -->
        <div class="login-left" style="flex: 1; background: linear-gradient(135deg, #264653 0%, #2a9d8f 100%); color: white; padding: 60px 40px; display: flex; flex-direction: column; justify-content: center;">
            <div class="login-brand" style="margin-bottom: 60px;">
                <div class="logo-icon" style="width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; backdrop-filter: blur(10px);">
                    <i class="fas fa-dumbbell" style="font-size: 2.5rem; color: #e9c46a;"></i>
                </div>
                <h1 style="font-family: 'Montserrat', sans-serif; font-size: 3.5rem; font-weight: 900; margin-bottom: 10px; letter-spacing: -1px;">CONQUER</h1>
                <p style="font-size: 1.1rem; opacity: 0.9; font-weight: 500;">Welcome back to your fitness journey</p>
            </div>
            
            <div class="login-features" style="display: flex; flex-direction: column; gap: 30px;">
                <div class="feature" style="display: flex; align-items: flex-start; gap: 15px;">
                    <i class="fas fa-chart-line" style="font-size: 1.5rem; color: #e9c46a; margin-top: 5px;"></i>
                    <div>
                        <h3 style="font-size: 1.2rem; margin-bottom: 5px; font-weight: 600;">Track Progress</h3>
                        <p style="opacity: 0.8; line-height: 1.5;">Monitor your fitness journey with detailed analytics</p>
                    </div>
                </div>
                <div class="feature" style="display: flex; align-items: flex-start; gap: 15px;">
                    <i class="fas fa-calendar-alt" style="font-size: 1.5rem; color: #e9c46a; margin-top: 5px;"></i>
                    <div>
                        <h3 style="font-size: 1.2rem; margin-bottom: 5px; font-weight: 600;">Book Classes</h3>
                        <p style="opacity: 0.8; line-height: 1.5;">Reserve spots in your favorite group sessions</p>
                    </div>
                </div>
                <div class="feature" style="display: flex; align-items: flex-start; gap: 15px;">
                    <i class="fas fa-users" style="font-size: 1.5rem; color: #e9c46a; margin-top: 5px;"></i>
                    <div>
                        <h3 style="font-size: 1.2rem; margin-bottom: 5px; font-weight: 600;">Join Community</h3>
                        <p style="opacity: 0.8; line-height: 1.5;">Connect with fellow fitness enthusiasts</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right side - Login Form -->
        <div class="login-right" style="flex: 1; padding: 60px 40px; display: flex; align-items: center; justify-content: center;">
            <div class="login-form-container" style="width: 100%; max-width: 400px;">
                <div class="form-header" style="margin-bottom: 30px; text-align: center;">
                    <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 10px; color: #264653;">Member Login</h2>
                    <p style="color: #6c757d; font-size: 1rem;">Enter your credentials to access your account</p>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-error" id="errorAlert" style="background: rgba(230, 57, 70, 0.1); border: 1px solid rgba(230, 57, 70, 0.3); color: #e63946; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; animation: slideIn 0.3s ease;">
                        <i class="fas fa-exclamation-circle" style="margin-top: 2px;"></i>
                        <span style="flex: 1;"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                    <div class="alert alert-success" style="background: rgba(42, 157, 143, 0.1); border: 1px solid rgba(42, 157, 143, 0.3); color: #2a9d8f; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px;">
                        <i class="fas fa-check-circle" style="margin-top: 2px;"></i>
                        <span>Registration successful! Please login.</span>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['message']) && $_GET['message'] == 'loggedout'): ?>
                    <div class="alert alert-success" style="background: rgba(42, 157, 143, 0.1); border: 1px solid rgba(42, 157, 143, 0.3); color: #2a9d8f; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px;">
                        <i class="fas fa-check-circle" style="margin-top: 2px;"></i>
                        <span>You have been successfully logged out.</span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="login-form" id="loginForm">
                    <div class="input-group" style="margin-bottom: 20px;">
                        <label for="email" style="display: block; margin-bottom: 8px; font-weight: 600; color: #264653; font-size: 0.9rem;">
                            <i class="fas fa-envelope" style="margin-right: 8px;"></i>
                            <span>Email Address or Username</span>
                        </label>
                        <input type="text" id="email" name="email" required 
                               placeholder="you@example.com or username"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               autocomplete="username email"
                               style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease; background: #f8fafc;"
                               onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)';"
                               onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    
                    <div class="input-group" style="margin-bottom: 20px; position: relative;">
                        <label for="password" style="display: block; margin-bottom: 8px; font-weight: 600; color: #264653; font-size: 0.9rem;">
                            <i class="fas fa-lock" style="margin-right: 8px;"></i>
                            <span>Password</span>
                        </label>
                        <input type="password" id="password" name="password" required 
                               placeholder="••••••••"
                               autocomplete="current-password"
                               style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease; background: #f8fafc; padding-right: 50px;"
                               onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)';"
                               onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        <button type="button" class="toggle-password" onclick="togglePassword()"
                                style="position: absolute; right: 16px; top: 42px; background: none; border: none; color: #6c757d; cursor: pointer; padding: 8px;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="form-options" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                        <label class="checkbox" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="remember" id="remember"
                                   style="width: 18px; height: 18px; accent-color: #667eea;">
                            <span style="font-size: 0.9rem; color: #6c757d;">Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-link" 
                           style="color: #667eea; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.3s;">
                           Forgot password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginButton"
                            style="width: 100%; padding: 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 30px;">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In</span>
                    </button>
                </form>
                
                <div class="form-footer" style="text-align: center;">
                    <p style="color: #6c757d; margin-bottom: 20px;">
                        Don't have an account? 
                        <a href="register.php" style="color: #667eea; text-decoration: none; font-weight: 600;">Sign up now</a>
                    </p>
                    
                    <div class="divider" style="position: relative; margin: 30px 0; text-align: center;">
                        <hr style="border: none; border-top: 1px solid #e2e8f0;">
                        <span style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: white; padding: 0 15px; color: #6c757d; font-size: 0.85rem;">
                            or continue with
                        </span>
                    </div>
                    
                    <div class="social-login" style="display: flex; gap: 15px; margin-bottom: 30px;">
                        <button type="button" class="social-btn google" onclick="socialLogin('google')"
                                style="flex: 1; padding: 14px; border: 2px solid #e2e8f0; background: white; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; color: #333;">
                            <i class="fab fa-google" style="color: #DB4437;"></i>
                            Google
                        </button>
                        <button type="button" class="social-btn facebook" onclick="socialLogin('facebook')"
                                style="flex: 1; padding: 14px; border: 2px solid #e2e8f0; background: white; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; color: #333;">
                            <i class="fab fa-facebook" style="color: #4267B2;"></i>
                            Facebook
                        </button>
                    </div>
                    
                    <a href="index.html" class="back-home"
                       style="display: inline-flex; align-items: center; gap: 8px; color: #6c757d; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.3s;">
                        <i class="fas fa-arrow-left"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password i');
            
            if(passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const loginButton = document.getElementById('loginButton');
            
            // Basic validation
            if(!email || !password) {
                e.preventDefault();
                showAlert('Please fill in all fields', 'error');
                return false;
            }
            
            // Show loading state
            loginButton.classList.add('loading');
            loginButton.disabled = true;
            loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Signing in...</span>';
            
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            return true;
        });
        
        // Show alert message
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlert = document.querySelector('.alert');
            if(existingAlert) {
                existingAlert.remove();
            }
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.style.cssText = type === 'error' 
                ? 'background: rgba(230, 57, 70, 0.1); border: 1px solid rgba(230, 57, 70, 0.3); color: #e63946; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; animation: slideIn 0.3s ease;'
                : 'background: rgba(42, 157, 143, 0.1); border: 1px solid rgba(42, 157, 143, 0.3); color: #2a9d8f; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; animation: slideIn 0.3s ease;';
            
            const icon = type === 'error' ? 'exclamation-circle' : 'check-circle';
            alertDiv.innerHTML = `
                <i class="fas fa-${icon}" style="margin-top: 2px;"></i>
                <span style="flex: 1;">${message}</span>
            `;
            
            // Insert alert
            const formHeader = document.querySelector('.form-header');
            formHeader.parentNode.insertBefore(alertDiv, formHeader.nextSibling);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if(alertDiv.parentNode) {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transform = 'translateY(-10px)';
                    setTimeout(() => alertDiv.remove(), 300);
                }
            }, 5000);
        }
        
        // Social login functions
        function socialLogin(provider) {
            showAlert(`Redirecting to ${provider} login...`, 'success');
            // Implement social login redirect here
            // window.location.href = `auth/${provider}.php`;
        }
        
        // Auto-hide error alert after 5 seconds
        const errorAlert = document.getElementById('errorAlert');
        if(errorAlert) {
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-10px)';
                setTimeout(() => errorAlert.remove(), 300);
            }, 5000);
        }
        
        // Focus email field on page load
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if(emailField && !emailField.value) {
                emailField.focus();
            }
            
            // Add button hover effects
            const loginBtn = document.getElementById('loginButton');
            loginBtn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.4)';
            });
            
            loginBtn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>