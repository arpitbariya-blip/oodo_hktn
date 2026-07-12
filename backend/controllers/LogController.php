<?php
class LogController {

    public function getLogs() {
        try {
            $db = Database::getConnection();
            $moduleFilter = $_GET['module'] ?? '';
            
            $sql = "
                SELECT l.*, u.name as user_name 
                FROM activity_logs l
                LEFT JOIN users u ON l.user_id = u.id
            ";
            $params = [];
            
            if ($moduleFilter && $moduleFilter !== 'All Actions') {
                $sql .= " WHERE l.module = ?";
                $params[] = $moduleFilter;
            }
            
            $sql .= " ORDER BY l.created_at DESC LIMIT 50";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::json(['logs' => $logs]);
        } catch (PDOException $e) {
            Response::error('Error fetching logs', 500);
        }
    }

    public function getNotifications() {
        try {
            $user = AuthMiddleware::getUser();
            if (!$user) Response::error('Unauthorized', 401);
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC LIMIT 20
            ");
            $stmt->execute([$user['id']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::json(['notifications' => $notifications]);
        } catch (PDOException $e) {
            Response::error('Error fetching notifications', 500);
        }
    }

    public function markAllRead() {
        try {
            $user = AuthMiddleware::getUser();
            if (!$user) Response::error('Unauthorized', 401);
            
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            Response::json(['message' => 'All marked read']);
        } catch (PDOException $e) {
            Response::error('Error updating notifications', 500);
        }
    }

    public function seedData() {
        try {
            $db = Database::getConnection();
            $user = AuthMiddleware::getUser();
            $userId = $user ? $user['id'] : 1;
            
            // Check if seeded already to avoid duplicates
            $stmt = $db->query("SELECT COUNT(*) FROM activity_logs");
            if ($stmt->fetchColumn() > 0) {
                Response::json(['message' => 'Already seeded']);
            }
            
            // 1. Seed Logs
            $logs = [
                [$userId, 'Asset Assigned: ThinkPad X1 Carbon (LPT-092)', 'Allocations'],
                [$userId, 'Maintenance Approved: Projector EPS-4K (AV-11)', 'Maintenance'],
                [$userId, 'Asset Returned: iPad Pro (TAB-02)', 'Allocations'],
                [$userId, 'Booking Confirmed: Conference Room A', 'Bookings'],
                [$userId, 'Audit Cycle Closed: Q3 Electronics Audit', 'Audit'],
                [$userId, 'Asset Transferred: Delivery Van (VH-04) to Logistics', 'Allocations']
            ];
            
            $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, module, created_at) VALUES (?, ?, ?, ?)");
            foreach ($logs as $i => $log) {
                $hours = $i * 2;
                $date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
                $logStmt->execute([$log[0], $log[1], $log[2], $date]);
            }
            
            // 2. Seed Notifications
            $notifications = [
                [$userId, 'warning', 'Overdue Maintenance: Forklift FL-04 is 3 days overdue for scheduled service.', 'maintenance', 0],
                [$userId, 'assignment_returned', 'Asset Returned: Sarah Jenkins returned iPad Pro (TAB-02).', 'allocation', 1],
                [$userId, 'fact_check', 'Audit Discrepancy Flagged: 3 items missing in Department B.', 'audit', 0],
                [$userId, 'event', 'Booking Reminder: Strategy Sync in Conf Room C starts in 15 mins.', 'booking', 0],
                [$userId, 'info', 'System Update: AssetFlow v2.4 deployed successfully.', 'system', 1],
                [$userId, 'assignment_turned_in', 'Transfer Approved: MacBook Pro transfer to Engineering is complete.', 'allocation', 0]
            ];
            
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, message, reference_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($notifications as $i => $notif) {
                $hours = $i * 3;
                $date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
                $notifStmt->execute([$notif[0], $notif[1], $notif[2], $notif[3], $notif[4], $date]);
            }
            
            Response::json(['message' => 'Seeded successfully']);
        } catch (PDOException $e) {
            Response::error('Error seeding data: ' . $e->getMessage(), 500);
        }
    }
}
