<?php
header('Content-Type: application/json');
require 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Get specific event with aggregated data
            $sql = "
                SELECT e.*, 
                       c.Name as HostClub,
                       (SELECT COUNT(*) FROM Volunteer v WHERE v.Event_ID = e.Event_ID) as Volunteer_Count,
                       (SELECT COUNT(*) FROM Need n WHERE n.Event_ID = e.Event_ID) as Equipment_Bookings
                FROM Events e
                LEFT JOIN Club c ON e.Primary_Club_ID = c.Club_ID
                WHERE e.Event_ID = ?
            ";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $_GET['id']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $event = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                echo json_encode($event ?: ["error" => "Event not found"]);
            } else {
                echo json_encode(["error" => mysqli_error($conn)]);
            }
        } else {
            // Get all events with aggregated data
            $sql = "
                SELECT e.*, 
                       c.Name as HostClub,
                       (SELECT COUNT(*) FROM Volunteer v WHERE v.Event_ID = e.Event_ID) as Volunteer_Count,
                       (SELECT COUNT(*) FROM Need n WHERE n.Event_ID = e.Event_ID) as Equipment_Bookings
                FROM Events e
                LEFT JOIN Club c ON e.Primary_Club_ID = c.Club_ID
                ORDER BY e.Date ASC
            ";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            } else {
                echo json_encode(["error" => mysqli_error($conn)]);
            }
        }
        break;

    case 'POST':
        // Create new event
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "INSERT INTO Events (Title, Date, Venue, Primary_Club_ID, Description) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception(mysqli_error($conn));
            
            mysqli_stmt_bind_param($stmt, "sssis", $input['title'], $input['date'], $input['venue'], $input['primary_club_id'], $input['description']);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($conn));
            
            $eventId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Optional: Add volunteers if provided
            if (isset($input['volunteers']) && is_array($input['volunteers'])) {
                $volStmt = mysqli_prepare($conn, "INSERT INTO Volunteer (Student_ID, Event_ID, Role, Hours_Worked) VALUES (?, ?, ?, ?)");
                if (!$volStmt) throw new Exception(mysqli_error($conn));
                
                foreach ($input['volunteers'] as $volunteer) {
                    mysqli_stmt_bind_param($volStmt, "iisd", $volunteer['student_id'], $eventId, $volunteer['role'], $volunteer['hours_worked']);
                    if (!mysqli_stmt_execute($volStmt)) throw new Exception(mysqli_error($conn));
                }
                mysqli_stmt_close($volStmt);
            }

            // Optional: Add equipment bookings if provided
            if (isset($input['bookings']) && is_array($input['bookings'])) {
                $bookStmt = mysqli_prepare($conn, "INSERT INTO Need (Equip_ID, Event_ID, Borrow_Time, Return_Time, Status) VALUES (?, ?, ?, ?, ?)");
                if (!$bookStmt) throw new Exception(mysqli_error($conn));
                
                foreach ($input['bookings'] as $booking) {
                    $status = $booking['status'] ?? 'Confirmed';
                    mysqli_stmt_bind_param($bookStmt, "iisss", $booking['equip_id'], $eventId, $booking['borrow_time'], $booking['return_time'], $status);
                    if (!mysqli_stmt_execute($bookStmt)) throw new Exception(mysqli_error($conn));
                }
                mysqli_stmt_close($bookStmt);
            }

            mysqli_commit($conn);
            echo json_encode(["success" => true, "id" => $eventId]);
        } catch(Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(["error" => "Transaction failed: " . $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update event
        if (!isset($_GET['id'])) {
            echo json_encode(["error" => "Event ID required"]);
            break;
        }
        
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "UPDATE Events SET Title=?, Date=?, Venue=?, Primary_Club_ID=?, Description=? WHERE Event_ID=?");
            if (!$stmt) throw new Exception(mysqli_error($conn));
            
            mysqli_stmt_bind_param($stmt, "sssisi", $input['title'], $input['date'], $input['venue'], $input['primary_club_id'], $input['description'], $_GET['id']);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($conn));
            mysqli_stmt_close($stmt);

            // Update volunteers if provided (delete old, insert new)
            if (isset($input['volunteers'])) {
                $delStmt = mysqli_prepare($conn, "DELETE FROM Volunteer WHERE Event_ID=?");
                if (!$delStmt) throw new Exception(mysqli_error($conn));
                mysqli_stmt_bind_param($delStmt, "i", $_GET['id']);
                if (!mysqli_stmt_execute($delStmt)) throw new Exception(mysqli_error($conn));
                mysqli_stmt_close($delStmt);

                if (is_array($input['volunteers']) && count($input['volunteers']) > 0) {
                    $volStmt = mysqli_prepare($conn, "INSERT INTO Volunteer (Student_ID, Event_ID, Role, Hours_Worked) VALUES (?, ?, ?, ?)");
                    if (!$volStmt) throw new Exception(mysqli_error($conn));
                    
                    foreach ($input['volunteers'] as $volunteer) {
                        mysqli_stmt_bind_param($volStmt, "iisd", $volunteer['student_id'], $_GET['id'], $volunteer['role'], $volunteer['hours_worked']);
                        if (!mysqli_stmt_execute($volStmt)) throw new Exception(mysqli_error($conn));
                    }
                    mysqli_stmt_close($volStmt);
                }
            }

            mysqli_commit($conn);
            echo json_encode(["success" => true]);
        } catch(Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(["error" => "Transaction failed: " . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete event (ON DELETE CASCADE handles Volunteer and Need)
        if (!isset($_GET['id'])) {
            echo json_encode(["error" => "Event ID required"]);
            break;
        }
        
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "DELETE FROM Events WHERE Event_ID=?");
            if (!$stmt) throw new Exception(mysqli_error($conn));
            
            mysqli_stmt_bind_param($stmt, "i", $_GET['id']);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($conn));
            
            if (mysqli_stmt_affected_rows($stmt) == 0) {
                throw new Exception("Event not found");
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            echo json_encode(["success" => true]);
        } catch(Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(["error" => "Deletion failed: " . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>