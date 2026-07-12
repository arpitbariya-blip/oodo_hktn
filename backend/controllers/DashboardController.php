<?php
class DashboardController {
    public function getKpis() {
        try {
            $pdo = Database::getConnection();
            
            // 1. Assets Available
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets WHERE lifecycle_status = 'Available'");
            $assetsAvailable = $stmt->fetch()['count'];
            
            // 2. Assets Allocated
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets WHERE lifecycle_status = 'Allocated'");
            $assetsAllocated = $stmt->fetch()['count'];
            
            // 3. Maintenance Today
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE DATE(raised_at) = CURRENT_DATE AND status IN ('Pending', 'Approved', 'Technician Assigned', 'In Progress')");
            $maintenanceToday = $stmt->fetch()['count'];
            
            // 4. Active Bookings
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Approved' AND end_time > NOW()");
            $activeBookings = $stmt->fetch()['count'];
            
            // 5. Pending Transfers
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM transfer_requests WHERE status = 'Requested'");
            $pendingTransfers = $stmt->fetch()['count'];
            
            // 6. Upcoming Returns (within next 7 days)
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM allocations WHERE status = 'Active' AND expected_return_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)");
            $upcomingReturns = $stmt->fetch()['count'];
            
            Response::json([
                'assetsAvailable' => $assetsAvailable,
                'assetsAllocated' => $assetsAllocated,
                'maintenanceToday' => $maintenanceToday,
                'activeBookings' => $activeBookings,
                'pendingTransfers' => $pendingTransfers,
                'upcomingReturns' => $upcomingReturns
            ]);
            
        } catch (Exception $e) {
            Response::error('An internal error occurred', 500);
        }
    }
    
    public function getOverdue() {
        try {
            $pdo = Database::getConnection();
            
            $sql = "
                SELECT 
                    a.asset_tag, 
                    a.name as asset_name,
                    c.name as category_name, 
                    u.name as assigned_to_user,
                    d.name as assigned_to_dept,
                    al.expected_return_date
                FROM allocations al
                JOIN assets a ON al.asset_id = a.id
                JOIN asset_categories c ON a.category_id = c.id
                LEFT JOIN users u ON al.allocated_to_user_id = u.id
                LEFT JOIN departments d ON al.allocated_to_department_id = d.id
                WHERE al.status = 'Overdue'
                ORDER BY al.expected_return_date ASC
                LIMIT 5
            ";
            
            $stmt = $pdo->query($sql);
            $overdue = $stmt->fetchAll();
            
            // Format dates and assigned to
            foreach ($overdue as &$item) {
                $item['assigned_to'] = $item['assigned_to_user'] ?? $item['assigned_to_dept'] ?? 'Unknown';
                $item['due_date_formatted'] = date('M d, Y', strtotime($item['expected_return_date']));
            }
            
            Response::json($overdue);
            
        } catch (Exception $e) {
            Response::error('An internal error occurred', 500);
        }
    }
    
    public function getAlerts() {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5");
            $alerts = $stmt->fetchAll();
            
            foreach ($alerts as &$alert) {
                // simple format
                $alert['created_at_formatted'] = date('M d, Y H:i', strtotime($alert['created_at']));
                // Icon mapping based on type
                $alert['icon'] = 'info';
                $alert['icon_color'] = 'tertiary-fixed-dim';
                if (stripos($alert['type'], 'overdue') !== false) {
                    $alert['icon'] = 'warning';
                    $alert['icon_color'] = 'error';
                } elseif (stripos($alert['type'], 'assigned') !== false || stripos($alert['type'], 'returned') !== false) {
                    $alert['icon'] = 'assignment_returned';
                    $alert['icon_color'] = 'secondary-container';
                }
            }
            
            Response::json($alerts);
        } catch (Exception $e) {
            Response::error('An internal error occurred', 500);
        }
    }
}
