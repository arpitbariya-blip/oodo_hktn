<?php
class DashboardController {
    public function getKpis() {
        try {
            $pdo = Database::getConnection();
            
            // Total Assets
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets");
            $totalAssets = $stmt->fetch()['count'];
            
            // Available
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE lifecycle_status = 'Available'");
            $stmt->execute();
            $available = $stmt->fetch()['count'];
            
            // Allocated
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE lifecycle_status = 'Allocated'");
            $stmt->execute();
            $allocated = $stmt->fetch()['count'];
            
            // In Maintenance
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE lifecycle_status = 'Under Maintenance'");
            $stmt->execute();
            $inMaintenance = $stmt->fetch()['count'];
            
            // Overdue
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM allocations WHERE status = 'Overdue'");
            $stmt->execute();
            $overdue = $stmt->fetch()['count'];
            
            // Value (Est)
            $stmt = $pdo->query("SELECT SUM(acquisition_cost) as total_value FROM assets");
            $totalValue = $stmt->fetch()['total_value'] ?? 0;
            
            // Calculate percentages
            $availablePct = $totalAssets > 0 ? round(($available / $totalAssets) * 100, 1) : 0;
            $allocatedPct = $totalAssets > 0 ? round(($allocated / $totalAssets) * 100, 1) : 0;
            
            Response::json([
                'totalAssets' => $totalAssets,
                'available' => $available,
                'availablePct' => $availablePct,
                'allocated' => $allocated,
                'allocatedPct' => $allocatedPct,
                'inMaintenance' => $inMaintenance,
                'overdue' => $overdue,
                'totalValue' => $totalValue
            ]);
            
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
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
            Response::error($e->getMessage(), 500);
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
            Response::error($e->getMessage(), 500);
        }
    }
}
