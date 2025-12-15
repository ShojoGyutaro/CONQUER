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

// Get classes with filters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$whereClauses = [];
$params = [];

if($type) {
    $whereClauses[] = "c.class_type = ?";
    $params[] = $type;
}

if($status === 'upcoming') {
    $whereClauses[] = "c.schedule > NOW() AND c.status = 'active'";
} elseif ($status === 'past') {
    $whereClauses[] = "c.schedule < NOW()";
} elseif ($status === 'cancelled') {
    $whereClauses[] = "c.status = 'cancelled'";
} elseif (empty($status) || $status === 'all') {
    // Show all classes (no date filter)
    $whereClauses[] = "c.status IN ('active', 'cancelled')";
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

try {
    // Get classes with proper table joins
    $sql = "
        SELECT 
            c.*, 
            COALESCE(u.full_name, 'No Trainer Assigned') as trainer_name,
            (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.id AND b.status = 'confirmed') as enrollment_count
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        $whereSQL
        ORDER BY c.schedule DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalClasses = count($classes);
    
    // Get counts for all status types
    $upcomingQuery = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE schedule > NOW() AND status = 'active'");
    $upcomingQuery->execute();
    $upcomingCount = $upcomingQuery->fetchColumn();
    
    $pastQuery = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE schedule < NOW() AND status = 'active'");
    $pastQuery->execute();
    $pastCount = $pastQuery->fetchColumn();
    
    $cancelledQuery = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE status = 'cancelled'");
    $cancelledQuery->execute();
    $cancelledCount = $cancelledQuery->fetchColumn();
    
    $allQuery = $pdo->prepare("SELECT COUNT(*) FROM classes");
    $allQuery->execute();
    $allCount = $allQuery->fetchColumn();
    
} catch (PDOException $e) {
    // Log error and show empty state
    error_log("Classes query error: " . $e->getMessage());
    $classes = [];
    $totalClasses = 0;
    $upcomingCount = 0;
    $pastCount = 0;
    $cancelledCount = 0;
    $allCount = 0;
    echo "<!-- Database Error: " . htmlspecialchars($e->getMessage()) . " -->";
}

// Get distinct class types for filter
$classTypes = [];
try {
    $typeStmt = $pdo->query("SELECT DISTINCT class_type FROM classes WHERE class_type IS NOT NULL AND class_type != '' ORDER BY class_type");
    $classTypes = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $classTypes = ['yoga', 'hiit', 'strength', 'cardio', 'crossfit', 'pilates'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | Admin Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Additional styles for classes management */
        .classes-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .classes-table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 1.25rem 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .classes-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
            color: #495057;
        }
        
        .classes-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .classes-table small {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.4;
        }
        
        /* Status tabs */
        .status-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: #f8f9fa;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            color: #495057;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-tab:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .status-tab.active {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.2);
        }
        
        .status-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        /* Class type badges */
        .class-type {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }
        
        .type-yoga { 
            background: rgba(155, 89, 182, 0.15); 
            color: #9b59b6; 
            border: 1px solid rgba(155, 89, 182, 0.3);
        }
        
        .type-hiit { 
            background: rgba(231, 76, 60, 0.15); 
            color: #e74c3c; 
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .type-strength { 
            background: rgba(52, 152, 219, 0.15); 
            color: #3498db; 
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        
        .type-cardio { 
            background: rgba(46, 204, 113, 0.15); 
            color: #2ecc71; 
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        .type-crossfit { 
            background: rgba(81, 236, 236, 0.15); 
            color: #00cec9; 
            border: 1px solid rgba(81, 236, 236, 0.3);
        }
        
        .type-pilates { 
            background: rgba(255, 165, 2, 0.15); 
            color: #ffa502; 
            border: 1px solid rgba(255, 165, 2, 0.3);
        }
        
        .type-others { 
            background: rgba(162, 155, 254, 0.15); 
            color: #6c5ce7; 
            border: 1px solid rgba(162, 155, 254, 0.3);
        }
        
        /* Enrollment progress */
        .enrollment-progress {
            width: 80px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .enrollment-fill {
            height: 100%;
            background: linear-gradient(90deg, #2ed573 0%, #1dd1a1 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .enrollment-fill.warning {
            background: linear-gradient(90deg, #ffa502 0%, #e69500 100%);
        }
        
        .enrollment-fill.danger {
            background: linear-gradient(90deg, #ff4757 0%, #ff2e43 100%);
        }
        
        /* Button styles */
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #f8f9fa;
            color: #495057;
            transition: all 0.3s;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            border: 1px solid #dee2e6;
            min-width: 40px;
        }
        
        .btn-sm:hover {
            background: #e9ecef;
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        .btn-sm.btn-success {
            background: #2ed573;
            color: white;
            border-color: #2ed573;
        }
        
        .btn-sm.btn-success:hover {
            background: #25c464;
            border-color: #25c464;
        }
        
        .btn-sm.btn-warning {
            background: #ffa502;
            color: white;
            border-color: #ffa502;
        }
        
        .btn-sm.btn-warning:hover {
            background: #e69500;
            border-color: #e69500;
        }
        
        .btn-sm.btn-danger {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
        }
        
        .btn-sm.btn-danger:hover {
            background: #ff2e43;
            border-color: #ff2e43;
        }
        
        .btn-sm.btn-info {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .btn-sm.btn-info:hover {
            background: #2980b9;
            border-color: #2980b9;
        }
        
        /* Filter select */
        .status-tabs select {
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            color: #495057;
            margin-left: auto;
        }
        
        .status-tabs select:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        /* Schedule cell styling */
        .schedule-cell {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .schedule-date {
            font-weight: 600;
            color: #2f3542;
            font-size: 0.95rem;
        }
        
        .schedule-time {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        /* Class status badge */
        .class-status {
            display: inline-block;
            padding: 0.3rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .status-cancelled {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        .status-completed {
            background: rgba(116, 125, 140, 0.15);
            color: #747d8c;
            border: 1px solid rgba(116, 125, 140, 0.3);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 0;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #e9ecef;
            margin-bottom: 1rem;
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Search results info */
        .search-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #495057;
        }
        
        /* Class difficulty badge */
        .difficulty-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .difficulty-beginner {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .difficulty-intermediate {
            background: rgba(255, 165, 2, 0.15);
            color: #ffa502;
            border: 1px solid rgba(255, 165, 2, 0.3);
        }
        
        .difficulty-advanced {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .classes-table {
                min-width: 800px;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .status-tabs {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-tabs select {
                margin-left: 0;
                width: 100%;
            }
            
            .search-info {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
            
            .action-buttons {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search classes by name, type, trainer..." id="searchInput">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-class.php'">
                    <i class="fas fa-plus"></i> Schedule Class
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Manage Classes</h1>
                    <p>Schedule and manage fitness classes</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $upcomingCount; ?></h3>
                        <p>Upcoming</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $pastCount; ?></h3>
                        <p>Completed</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $allCount; ?></h3>
                        <p>Total</p>
                    </div>
                </div>
            </div>

            <!-- Status Tabs - CORRECTED -->
<div class="status-tabs">
    <button class="status-tab <?php echo $status === 'upcoming' ? 'active' : ''; ?>" 
            onclick="window.location.href='?status=upcoming<?php echo $type ? '&type=' . urlencode($type) : ''; ?>'">
        <i class="fas fa-calendar-alt"></i> Upcoming 
        <span class="status-badge"><?php echo $upcomingCount; ?></span>
    </button>
    <button class="status-tab <?php echo $status === 'past' ? 'active' : ''; ?>" 
            onclick="window.location.href='?status=past<?php echo $type ? '&type=' . urlencode($type) : ''; ?>'">
        <i class="fas fa-history"></i> Past 
        <span class="status-badge"><?php echo $pastCount; ?></span>
    </button>
    <button class="status-tab <?php echo $status === 'cancelled' ? 'active' : ''; ?>" 
            onclick="window.location.href='?status=cancelled<?php echo $type ? '&type=' . urlencode($type) : ''; ?>'">
        <i class="fas fa-ban"></i> Cancelled 
        <span class="status-badge"><?php echo $cancelledCount; ?></span>
    </button>
    <button class="status-tab <?php echo empty($status) || $status === 'all' ? 'active' : ''; ?>" 
            onclick="window.location.href='?<?php echo $type ? 'type=' . urlencode($type) : ''; ?>'">
        <i class="fas fa-list"></i> All Classes 
        <span class="status-badge"><?php echo $allCount; ?></span>
    </button>
    
    <select onchange="window.location.href='?<?php echo $status ? 'status=' . urlencode($status) . '&' : ''; ?>type='+this.value">
        <option value="">All Types</option>
        <?php foreach($classTypes as $classType): ?>
            <option value="<?php echo strtolower($classType); ?>" <?php echo $type === strtolower($classType) ? 'selected' : ''; ?>>
                <?php echo ucfirst($classType); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>  

            <!-- Classes Table -->
<div class="content-card">
    <div class="card-header">
        <h3>
            <?php 
            if($status === 'upcoming') echo 'Upcoming Classes';
            elseif($status === 'past') echo 'Past Classes';
            elseif($status === 'cancelled') echo 'Cancelled Classes';
            elseif(empty($status) || $status === 'all') echo 'All Classes';
            else echo 'Classes';
            ?>
        </h3>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <span style="font-size: 0.9rem; color: #6c757d;">
                <i class="fas fa-filter"></i> 
                <?php if($status): ?>
                    Status: <strong><?php echo ucfirst($status); ?></strong>
                <?php endif; ?>
                <?php if($type): ?>
                    <?php echo $status ? '|' : ''; ?> Type: <strong><?php echo ucfirst($type); ?></strong>
                <?php endif; ?>
                | Showing: <?php echo $totalClasses; ?> classes
            </span>
        </div>
    </div>
                
                <div class="card-body">
                    <?php if($totalClasses > 0): ?>
                        <div id="searchInfo" class="search-info" style="display: none;">
                            <span id="resultsCount">0 classes found</span>
                            <button class="btn-sm" onclick="clearSearch()">
                                <i class="fas fa-times"></i> Clear Search
                            </button>
                        </div>
                        
                        <div class="table-container">
                            <table class="classes-table" id="classesTable">
                                <thead>
                                    <tr>
                                        <th>Class Name</th>
                                        <th>Type & Difficulty</th>
                                        <th>Trainer</th>
                                        <th>Schedule & Location</th>
                                        <th>Duration</th>
                                        <th>Enrollment</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($classes as $class): 
                                        $enrollmentCount = $class['enrollment_count'] ?? 0;
                                        $maxCapacity = $class['max_capacity'] ?? 20;
                                        $enrollmentPercent = $maxCapacity > 0 ? ($enrollmentCount / $maxCapacity) * 100 : 0;
                                        
                                        // Determine progress bar color
                                        $progressClass = '';
                                        if($enrollmentPercent >= 90) {
                                            $progressClass = 'danger';
                                        } elseif($enrollmentPercent >= 75) {
                                            $progressClass = 'warning';
                                        }
                                        
                                        // Determine class status
                                        $currentTime = time();
                                        $scheduleTime = strtotime($class['schedule']);
                                        $isPast = $scheduleTime < $currentTime;
                                        $statusClass = $class['status'] ?? 'active';
                                        
                                        if($statusClass === 'cancelled') {
                                            $statusText = 'Cancelled';
                                            $statusBadge = 'status-cancelled';
                                        } elseif($isPast) {
                                            $statusText = 'Completed';
                                            $statusBadge = 'status-completed';
                                        } else {
                                            $statusText = 'Active';
                                            $statusBadge = 'status-active';
                                        }
                                        
                                        // Format difficulty
                                        $difficulty = $class['difficulty_level'] ?? 'Intermediate';
                                        $difficultyClass = strtolower($difficulty);
                                    ?>
                                        <tr data-class-name="<?php echo strtolower(htmlspecialchars($class['class_name'])); ?>" 
                                            data-class-type="<?php echo strtolower(htmlspecialchars($class['class_type'])); ?>"
                                            data-trainer-name="<?php echo strtolower(htmlspecialchars($class['trainer_name'])); ?>"
                                            data-location="<?php echo strtolower(htmlspecialchars($class['location'] ?? '')); ?>"
                                            data-difficulty="<?php echo strtolower($difficulty); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($class['class_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($class['description'] ?? 'No description'); ?></small>
                                            </td>
                                            <td>
                                                <span class="class-type type-<?php echo strtolower($class['class_type']); ?>">
                                                    <?php echo ucfirst($class['class_type']); ?>
                                                </span>
                                                <br>
                                                <span class="difficulty-badge difficulty-<?php echo $difficultyClass; ?>">
                                                    <?php echo $difficulty; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($class['trainer_name']); ?></strong>
                                            </td>
                                            <td>
                                                <div class="schedule-cell">
                                                    <span class="schedule-date"><?php echo date('M j, Y', strtotime($class['schedule'])); ?></span>
                                                    <span class="schedule-time"><?php echo date('g:i A', strtotime($class['schedule'])); ?></span>
                                                    <?php if(!empty($class['location'])): ?>
                                                        <small><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($class['location']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: #2f3542;"><?php echo $class['duration_minutes'] ?? 60; ?> mins</span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="enrollment-progress">
                                                        <div class="enrollment-fill <?php echo $progressClass; ?>" 
                                                             style="width: <?php echo min($enrollmentPercent, 100); ?>%"></div>
                                                    </div>
                                                    <span style="font-weight: 600; font-size: 0.9rem; min-width: 50px;">
                                                        <?php echo $enrollmentCount; ?>/<?php echo $maxCapacity; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="class-status <?php echo $statusBadge; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-sm btn-info" 
                                                            onclick="window.location.href='admin-class-view.php?id=<?php echo $class['id']; ?>'"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if($statusClass === 'active' && !$isPast): ?>
                                                        <button class="btn-sm btn-warning" 
                                                                onclick="window.location.href='admin-edit-class.php?id=<?php echo $class['id']; ?>'"
                                                                title="Edit Class">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn-sm btn-success" 
                                                                onclick="window.location.href='admin-class-bookings.php?id=<?php echo $class['id']; ?>'"
                                                                title="View Bookings">
                                                            <i class="fas fa-users"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if($statusClass === 'active' && !$isPast): ?>
                                                        <button class="btn-sm btn-danger" 
                                                                onclick="cancelClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>')"
                                                                title="Cancel Class">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3 style="color: #495057; margin: 1rem 0 0.5rem;">
                                <?php 
                                if($status === 'cancelled') echo 'No Cancelled Classes';
                                elseif($status === 'past') echo 'No Past Classes';
                                elseif($status === 'upcoming') echo 'No Upcoming Classes';
                                else echo 'No Classes Found';
                                ?>
                            </h3>
                            <p style="margin-bottom: 2rem;">
                                <?php 
                                if($type) {
                                    echo "No $status " . ucfirst($type) . " classes found. Try a different filter.";
                                } else {
                                    echo "No $status classes scheduled.";
                                }
                                ?>
                            </p>
                            <?php if($status === 'upcoming' || empty($status)): ?>
                                <button class="btn-primary" onclick="window.location.href='admin-add-class.php'">
                                    <i class="fas fa-plus"></i> Schedule Your First Class
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchInfo = document.getElementById('searchInfo');
        const resultsCount = document.getElementById('resultsCount');
        
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#classesTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const className = row.getAttribute('data-class-name');
                const classType = row.getAttribute('data-class-type');
                const trainerName = row.getAttribute('data-trainer-name');
                const location = row.getAttribute('data-location');
                const difficulty = row.getAttribute('data-difficulty');
                const rowText = row.textContent.toLowerCase();
                
                const matches = searchTerm === '' || 
                    className.includes(searchTerm) || 
                    classType.includes(searchTerm) || 
                    trainerName.includes(searchTerm) ||
                    location.includes(searchTerm) ||
                    difficulty.includes(searchTerm) ||
                    rowText.includes(searchTerm);
                
                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update search info
            if (searchTerm.length > 0) {
                searchInfo.style.display = 'flex';
                resultsCount.textContent = visibleCount + ' classes found for "' + searchTerm + '"';
            } else {
                searchInfo.style.display = 'none';
            }
            
            // Show no results message if needed
            const cardBody = document.querySelector('.card-body');
            const tableContainer = cardBody.querySelector('.table-container');
            let noResultsMsg = cardBody.querySelector('.no-results');
            
            if (visibleCount === 0 && searchTerm.length > 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'empty-state no-results';
                    noResultsMsg.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Matching Classes</h3>
                        <p>No classes found matching "${searchTerm}"</p>
                    `;
                    if (tableContainer) {
                        tableContainer.parentNode.insertBefore(noResultsMsg, tableContainer.nextSibling);
                    } else {
                        cardBody.appendChild(noResultsMsg);
                    }
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
        
        function clearSearch() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
        }
        
        function cancelClass(classId, className) {
            if (confirm('Are you sure you want to cancel "' + className + '"? This action cannot be undone.')) {
                // Send AJAX request to cancel class
                fetch('admin-cancel-class.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + classId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Class cancelled successfully!');
                        window.location.reload();
                    } else {
                        alert('Error cancelling class: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error cancelling class. Please try again.');
                });
            }
        }
        
        // Add keyboard shortcut for search (Ctrl+F)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
            }
        });
        
        // Auto-focus search on page load if there are many classes
        <?php if($totalClasses > 10): ?>
            setTimeout(() => {
                searchInput.focus();
            }, 500);
        <?php endif; ?>
    </script>
</body>
</html>