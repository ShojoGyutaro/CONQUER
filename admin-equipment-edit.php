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

$equipmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$successMessage = '';
$errorMessage = '';

// Fetch equipment details
$equipment = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = ?");
    $stmt->execute([$equipmentId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Equipment edit error: " . $e->getMessage());
}

if(!$equipment) {
    header('Location: admin-equipment.php');
    exit();
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipmentName = $_POST['equipment_name'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $purchaseDate = $_POST['purchase_date'] ?? '';
    $lastMaintenance = $_POST['last_maintenance'] ?? '';
    $nextMaintenance = $_POST['next_maintenance'] ?? '';
    $status = $_POST['status'] ?? '';
    $location = $_POST['location'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE equipment SET
                equipment_name = ?,
                brand = ?,
                purchase_date = ?,
                last_maintenance = ?,
                next_maintenance = ?,
                status = ?,
                location = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $equipmentName,
            $brand,
            $purchaseDate ?: null,
            $lastMaintenance ?: null,
            $nextMaintenance ?: null,
            $status,
            $location,
            $equipmentId
        ]);
        
        $successMessage = 'Equipment updated successfully!';
        
        // Refresh equipment data
        $stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = ?");
        $stmt->execute([$equipmentId]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $errorMessage = 'Error updating equipment: ' . $e->getMessage();
    }
}

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Equipment | CONQUER Gym Admin</title>
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
        
        .message.warning {
            background: #feebc8;
            color: #744210;
            border: 1px solid #f6ad55;
        }
        
        /* Equipment Status */
        .equipment-status {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .equipment-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4fd1c5 0%, #319795 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }
        
        .equipment-info {
            flex: 1;
        }
        
        .equipment-info h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-maintenance {
            background: #feebc8;
            color: #744210;
        }
        
        .status-retired {
            background: #fed7d7;
            color: #742a2a;
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
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4fd1c5;
            box-shadow: 0 0 0 3px rgba(79, 209, 197, 0.1);
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
            background: #4fd1c5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #38b2ac;
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
        
        /* Maintenance History */
        .maintenance-history {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .maintenance-history h3 {
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .history-item {
            padding: 1rem;
            background: #f7fafc;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .history-item:last-child {
            margin-bottom: 0;
        }
        
        .history-date {
            font-weight: 600;
            color: #2d3748;
        }
        
        .history-notes {
            color: #718096;
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
            
            .equipment-status {
                flex-direction: column;
                text-align: center;
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
                <a href="admin-classes.php">
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
                <a href="admin-equipment.php" class="active">
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
                <a href="admin-equipment.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Equipment
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
            
            <?php 
            // Check if maintenance is due soon
            if($equipment['next_maintenance'] && strtotime($equipment['next_maintenance']) <= strtotime('+7 days')): ?>
                <div class="message warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Maintenance due on <?php echo date('F j, Y', strtotime($equipment['next_maintenance'])); ?> (in <?php echo floor((strtotime($equipment['next_maintenance']) - time()) / (60 * 60 * 24)); ?> days)
                </div>
            <?php endif; ?>
            
            <!-- Equipment Status -->
            <div class="equipment-status">
                <div class="equipment-icon">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <div class="equipment-info">
                    <h2><?php echo htmlspecialchars($equipment['equipment_name']); ?></h2>
                    <span class="status-badge status-<?php echo $equipment['status']; ?>">
                        <?php echo ucfirst($equipment['status']); ?>
                    </span>
                    <p style="margin-top: 0.5rem; color: #718096;">
                        <?php echo htmlspecialchars($equipment['brand']); ?> • Located in <?php echo htmlspecialchars($equipment['location']); ?>
                        <?php if($equipment['purchase_date']): ?>
                            • Purchased <?php echo date('F j, Y', strtotime($equipment['purchase_date'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- Edit Form -->
            <form method="POST" class="edit-form">
                <h2 style="margin-bottom: 1.5rem; color: #2d3748;">Edit Equipment Details</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="equipment_name">Equipment Name *</label>
                        <input type="text" id="equipment_name" name="equipment_name" value="<?php echo htmlspecialchars($equipment['equipment_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="brand">Brand</label>
                        <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($equipment['brand']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="purchase_date">Purchase Date</label>
                        <input type="date" id="purchase_date" name="purchase_date" value="<?php echo $equipment['purchase_date']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($equipment['location']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="last_maintenance">Last Maintenance Date</label>
                        <input type="date" id="last_maintenance" name="last_maintenance" value="<?php echo $equipment['last_maintenance']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="next_maintenance">Next Maintenance Date</label>
                        <input type="date" id="next_maintenance" name="next_maintenance" value="<?php echo $equipment['next_maintenance']; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo $equipment['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="maintenance" <?php echo $equipment['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="retired" <?php echo $equipment['status'] === 'retired' ? 'selected' : ''; ?>>Retired</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-equipment.php'">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmRetire()">
                        <i class="fas fa-trash"></i>
                        Retire Equipment
                    </button>
                </div>
            </form>
            
            <!-- Maintenance History -->
            <div class="maintenance-history">
                <h3>Maintenance History</h3>
                
                <?php if($equipment['last_maintenance']): ?>
                    <div class="history-item">
                        <div>
                            <div class="history-date">Last Maintenance</div>
                            <div class="history-notes"><?php echo date('F j, Y', strtotime($equipment['last_maintenance'])); ?></div>
                        </div>
                        <div style="color: #38a169;">
                            <i class="fas fa-check"></i> Completed
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if($equipment['next_maintenance']): ?>
                    <div class="history-item">
                        <div>
                            <div class="history-date">Next Scheduled</div>
                            <div class="history-notes"><?php echo date('F j, Y', strtotime($equipment['next_maintenance'])); ?></div>
                        </div>
                        <div style="color: <?php echo strtotime($equipment['next_maintenance']) <= strtotime('+7 days') ? '#d69e2e' : '#4299e1'; ?>;">
                            <i class="fas fa-calendar"></i> Scheduled
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Sample additional history -->
                <div class="history-item">
                    <div>
                        <div class="history-date">Routine Check</div>
                        <div class="history-notes">October 15, 2023</div>
                    </div>
                    <div style="color: #38a169;">
                        <i class="fas fa-check"></i> Completed
                    </div>
                </div>
                
                <div class="history-item">
                    <div>
                        <div class="history-date">Belt Replacement</div>
                        <div class="history-notes">July 30, 2023</div>
                    </div>
                    <div style="color: #38a169;">
                        <i class="fas fa-check"></i> Completed
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function confirmRetire() {
            if(confirm('Are you sure you want to retire this equipment? It will be marked as retired and removed from active use.')) {
                document.getElementById('status').value = 'retired';
                document.querySelector('form').submit();
            }
        }
        
        // Auto-set next maintenance date based on last maintenance
        document.getElementById('last_maintenance').addEventListener('change', function() {
            const lastMaintenance = this.value;
            if(lastMaintenance) {
                const lastDate = new Date(lastMaintenance);
                const nextDate = new Date(lastDate);
                nextDate.setMonth(nextDate.getMonth() + 6); // 6 months later
                
                const nextFormatted = nextDate.toISOString().split('T')[0];
                document.getElementById('next_maintenance').value = nextFormatted;
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const lastMaintenance = document.getElementById('last_maintenance').value;
            const nextMaintenance = document.getElementById('next_maintenance').value;
            
            if(lastMaintenance && nextMaintenance) {
                const lastDate = new Date(lastMaintenance);
                const nextDate = new Date(nextMaintenance);
                
                if(nextDate <= lastDate) {
                    e.preventDefault();
                    alert('Next maintenance date must be after last maintenance date!');
                    return false;
                }
            }
        });
        
        // Show/hide maintenance warning based on next maintenance date
        function checkMaintenanceDate() {
            const nextMaintenance = document.getElementById('next_maintenance').value;
            if(nextMaintenance) {
                const nextDate = new Date(nextMaintenance);
                const today = new Date();
                const diffTime = nextDate - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if(diffDays <= 7) {
                    alert(`Maintenance due in ${diffDays} days! Consider scheduling maintenance soon.`);
                }
            }
        }
        
        document.getElementById('next_maintenance').addEventListener('change', checkMaintenanceDate);
    </script>
</body>
</html>