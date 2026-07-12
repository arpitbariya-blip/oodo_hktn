<?php
class BookingController {
    
    public function getCalendar() {
        $date = $_GET['date'] ?? date('Y-m-d');
        
        try {
            $db = Database::getConnection();
            $query = "
                SELECT b.*, a.name as asset_name, u.name as booked_by_name
                FROM bookings b
                JOIN assets a ON b.asset_id = a.id
                JOIN users u ON b.booked_by = u.id
                WHERE DATE(b.start_time) = ? AND b.status != 'Cancelled'
                ORDER BY b.start_time ASC
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$date]);
            
            Response::json($stmt->fetchAll());
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function getMyUpcoming() {
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $db = Database::getConnection();
            $query = "
                SELECT b.*, a.name as asset_name 
                FROM bookings b
                JOIN assets a ON b.asset_id = a.id
                WHERE b.booked_by = ? AND b.status IN ('Upcoming', 'Ongoing')
                ORDER BY b.start_time ASC
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            
            Response::json($stmt->fetchAll());
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['asset_id']) || empty($data['start_time']) || empty($data['end_time'])) {
            Response::error('Asset, Start Time, and End Time are required.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $db = Database::getConnection();
            
            // Overlap Validation
            $checkStmt = $db->prepare("
                SELECT b.id, u.name as conflicting_user
                FROM bookings b
                JOIN users u ON b.booked_by = u.id
                WHERE b.asset_id = ? 
                  AND b.status IN ('Upcoming', 'Ongoing')
                  AND b.start_time < ? 
                  AND b.end_time > ?
                LIMIT 1
            ");
            $checkStmt->execute([
                $data['asset_id'],
                $data['end_time'],
                $data['start_time']
            ]);
            $conflict = $checkStmt->fetch();

            if ($conflict) {
                Response::error('Scheduling Conflict', 409, [
                    'conflict_data' => [
                        'user' => $conflict['conflicting_user']
                    ]
                ]);
                return;
            }

            // Create Booking
            $ins = $db->prepare("
                INSERT INTO bookings (asset_id, booked_by, title, purpose, start_time, end_time, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Upcoming')
            ");
            $ins->execute([
                $data['asset_id'],
                $user_id,
                $data['title'] ?? 'Asset Booking',
                $data['purpose'] ?? '',
                $data['start_time'],
                $data['end_time']
            ]);

            Response::json(['message' => 'Resource booked successfully.']);
        } catch(Exception $e) {
            Response::error('Error processing booking: ' . $e->getMessage(), 500);
        }
    }

    public function cancel() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['booking_id'])) {
            Response::error('Booking ID required.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE bookings 
                SET status = 'Cancelled', cancelled_by = ?, updated_at = NOW()
                WHERE id = ? AND booked_by = ?
            ");
            $stmt->execute([$user_id, $data['booking_id'], $user_id]);

            if ($stmt->rowCount() > 0) {
                Response::json(['message' => 'Booking cancelled.']);
            } else {
                Response::error('Booking not found or not authorized to cancel.', 404);
            }
        } catch(Exception $e) {
            Response::error('Error cancelling booking: ' . $e->getMessage(), 500);
        }
    }
}
