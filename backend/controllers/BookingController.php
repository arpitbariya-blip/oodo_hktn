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
            Response::error('Database error: ' , 500);
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
            Response::error('Database error: ' , 500);
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
            Response::error('Error processing booking: ' , 500);
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
            Response::error('Error cancelling booking: ' , 500);
        }
    }

    public function reschedule() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['booking_id']) || empty($data['start_time']) || empty($data['end_time'])) {
            Response::error('Booking ID, Start Time, and End Time are required.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $db = Database::getConnection();
            
            // Check if authorized to edit this booking
            $authCheck = $db->prepare("SELECT asset_id FROM bookings WHERE id = ? AND booked_by = ? AND status != 'Cancelled'");
            $authCheck->execute([$data['booking_id'], $user_id]);
            $booking = $authCheck->fetch();
            
            if (!$booking) {
                Response::error('Booking not found or not authorized to reschedule.', 404);
                return;
            }
            
            // Check for conflict
            $conflictStmt = $db->prepare("
                SELECT id FROM bookings 
                WHERE asset_id = ? 
                AND id != ?
                AND status != 'Cancelled'
                AND (start_time < ? AND end_time > ?)
            ");
            $conflictStmt->execute([
                $booking['asset_id'],
                $data['booking_id'],
                $data['end_time'],
                $data['start_time']
            ]);
            
            if ($conflictStmt->fetch()) {
                Response::error('The requested time slot conflicts with an existing booking.', 409);
                return;
            }

            $stmt = $db->prepare("
                UPDATE bookings 
                SET start_time = ?, end_time = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['start_time'], $data['end_time'], $data['booking_id']]);

            Response::json(['message' => 'Booking rescheduled successfully.']);
        } catch(Exception $e) {
            Response::error('Error rescheduling booking: ' , 500);
        }
    }
}
