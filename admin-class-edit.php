<?php
session_start();
require_once 'config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$classId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$successMessage = '';
$errorMessage = '';

// Fetch class details
$class = null;
$trainers = [];

try {
    // Fetch class details
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as trainer_name 
        FROM classes c 
        LEFT JOIN trainers t ON c.trainer_id = t.id 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch all trainers for dropdown
    $trainers = $pdo->query("
        SELECT t.*, u.full_name 
        FROM trainers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.is_active = 1 
        ORDER BY u.full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Class edit error: " . $e->getMessage());
}

if(!$class) {
    header('Location: admin-classes.php');
    exit();
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $className = $_POST['class_name'] ?? '';
    $trainerId = $_POST['trainer_id'] ?? '';
    $schedule = $_POST['schedule'] ?? '';
    $duration = $_POST['duration_minutes'] ?? '';
    $maxCapacity = $_POST['max_capacity'] ?? '';
    $location = $_POST['location'] ?? '';
    $classType = $_POST['class_type'] ?? '';
    $difficulty = $_POST['difficulty_level'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE classes SET
                class_name = ?,
                trainer_id = ?,
                schedule = ?,
                duration_minutes = ?,
                max_capacity = ?,
                location = ?,
                class_type = ?,
                difficulty_level = ?,
                description = ?,
                status = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $className,
            $trainerId ?: null,
            $schedule,
            $duration,
            $maxCapacity,
            $location,
            $classType,
            $difficulty,
            $description,
            $status,
            $classId
        ]);
        
        $successMessage = 'Class updated successfully!';
        
        // Refresh class data
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $errorMessage = 'Error updating class: ' . $e->getMessage();
    }
}

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Class | CONQUER Gym Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar - Same as before */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a1f2e 0%, #2d3748 100%);
            color: white;
            position: fixed;
            height: 100vh;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-logo i {
            color: #ff4757;
        }
        
        .admin-badge {
            background: #ff4757;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-avatar.admin {
            background: #667eea;
        }
        
        .user-details h4 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .user-details p {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid #ff4757;
        }
        
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid #ff4757;
        }
        
        .sidebar-nav a i {
            width: 20px;
            margin-right: 0.8rem;
        }
        
        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: #e2e8f0;
            color: #4a5568;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .message.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        
        /* Edit Form */
        .edit-form {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .btn-danger {
            background: #e53e3e;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c53030;
        }
        
        /* Class Stats */
        .class-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-card h4 {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .sidebar-logo span:not(:first-child),
            .sidebar .user-details,
            .sidebar-nav a span,
            .logout-btn span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-dumbbell"></i>
                    <span>CONQUER</span>
                    <span class="admin-badge">ADMIN</span>
                </div>
                <div class="user-profile">
                    <div class="user-avatar admin">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($adminName); ?></h4>
                        <p>System Administrator</p>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="admin-dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admin-members.php">
                    <i class="fas fa-users"></i>
                    <span>Members</span>
                </a>
                <a href="admin-trainers.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Trainers</span>
                </a>
                <a href="admin-classes.php" class="active">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Classes</span>
                </a>
                <a href="admin-payments.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
                <a href="admin-stories.php">
                    <i class="fas fa-trophy"></i>
                    <span>Success Stories</span>
                </a>
                <a href="admin-equipment.php">
                    <i class="fas fa-dumbbell"></i>
                    <span>Equipment</span>
                </a>
                <a href="admin-messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="admin-reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="admin-settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <a href="admin-classes.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Classes
                </a>
            </div>
            
            <?php if($successMessage): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if($errorMessage): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <!-- Class Stats -->
            <div class="class-stats">
                <div class="stat-card">
                    <h4>Current Enrollment</h4>
                    <div class="stat-number"><?php echo htmlspecialchars($class['current_enrollment']); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Max Capacity</h4>
                    <div class="stat-number"><?php echo htmlspecialchars($class['max_capacity']); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Available Spots</h4>
                    <div class="stat-number"><?php echo $class['max_capacity'] - $class['current_enrollment']; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Class Status</h4>
                    <div class="stat-number" style="font-size: 1.2rem; color: <?php echo $class['status'] === 'active' ? '#38a169' : ($class['status'] === 'cancelled' ? '#e53e3e' : '#d69e2e'); ?>;">
                        <?php echo ucfirst($class['status']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form -->
            <form method="POST" class="edit-form">
                <h2 style="margin-bottom: 1.5rem; color: #2d3748;">Edit Class: <?php echo htmlspecialchars($class['class_name']); ?></h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="class_name">Class Name *</label>
                        <input type="text" id="class_name" name="class_name" value="<?php echo htmlspecialchars($class['class_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="class_type">Class Type *</label>
                        <select id="class_type" name="class_type" required>
                            <option value="">Select Type</option>
                            <option value="Yoga" <?php echo $class['class_type'] === 'Yoga' ? 'selected' : ''; ?>>Yoga</option>
                            <option value="HIIT" <?php echo $class['class_type'] === 'HIIT' ? 'selected' : ''; ?>>HIIT</option>
                            <option value="Strength" <?php echo $class['class_type'] === 'Strength' ? 'selected' : ''; ?>>Strength</option>
                            <option value="Cardio" <?php echo $class['class_type'] === 'Cardio' ? 'selected' : ''; ?>>Cardio</option>
                            <option value="CrossFit" <?php echo $class['class_type'] === 'CrossFit' ? 'selected' : ''; ?>>CrossFit</option>
                            <option value="Pilates" <?php echo $class['class_type'] === 'Pilates' ? 'selected' : ''; ?>>Pilates</option>
                            <option value="Zumba" <?php echo $class['class_type'] === 'Zumba' ? 'selected' : ''; ?>>Zumba</option>
                            <option value="Spin" <?php echo $class['class_type'] === 'Spin' ? 'selected' : ''; ?>>Spin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="trainer_id">Trainer</label>
                        <select id="trainer_id" name="trainer_id">
                            <option value="">No Trainer</option>
                            <?php foreach($trainers as $trainer): ?>
                                <option value="<?php echo $trainer['id']; ?>" <?php echo $class['trainer_id'] == $trainer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($trainer['full_name']); ?> - <?php echo htmlspecialchars($trainer['specialty']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty_level">Difficulty Level</label>
                        <select id="difficulty_level" name="difficulty_level">
                            <option value="">Select Difficulty</option>
                            <option value="Beginner" <?php echo $class['difficulty_level'] === 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="Intermediate" <?php echo $class['difficulty_level'] === 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="Advanced" <?php echo $class['difficulty_level'] === 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                            <option value="All Levels" <?php echo $class['difficulty_level'] === 'All Levels' ? 'selected' : ''; ?>>All Levels</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="schedule">Schedule Date & Time *</label>
                        <input type="datetime-local" id="schedule" name="schedule" value="<?php echo date('Y-m-d\TH:i', strtotime($class['schedule'])); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration_minutes">Duration (minutes) *</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" value="<?php echo htmlspecialchars($class['duration_minutes']); ?>" min="15" max="180" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_capacity">Max Capacity *</label>
                        <input type="number" id="max_capacity" name="max_capacity" value="<?php echo htmlspecialchars($class['max_capacity']); ?>" min="1" max="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($class['location']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($class['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo $class['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $class['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="cancelled" <?php echo $class['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-classes.php'">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmCancelClass()">
                        <i class="fas fa-ban"></i>
                        Cancel Class
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmCancelClass() {
            if(confirm('Are you sure you want to cancel this class? All bookings will be notified.')) {
                // In real app, this would submit a form or make AJAX call
                document.getElementById('status').value = 'cancelled';
                document.querySelector('form').submit();
            }
        }
        
        // Auto-update available spots when capacity changes
        document.getElementById('max_capacity').addEventListener('input', function() {
            const currentEnrollment = <?php echo $class['current_enrollment']; ?>;
            const maxCapacity = this.value;
            const availableSpots = maxCapacity - currentEnrollment;
            
            if(availableSpots < 0) {
                alert('Warning: Current enrollment exceeds new capacity!');
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const schedule = document.getElementById('schedule').value;
            const scheduleDate = new Date(schedule);
            const now = new Date();
            
            if(scheduleDate < now) {
                e.preventDefault();
                alert('Schedule date must be in the future!');
                return false;
            }
            
            const maxCapacity = document.getElementById('max_capacity').value;
            const currentEnrollment = <?php echo $class['current_enrollment']; ?>;
            
            if(parseInt(maxCapacity) < currentEnrollment) {
                e.preventDefault();
                alert('Max capacity cannot be less than current enrollment (' + currentEnrollment + ')!');
                return false;
            }
        });
    </script>
</body>
</html>