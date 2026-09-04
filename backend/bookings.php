<?php
header('Content-Type: application/json');
require 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['equip_id']) && isset($_GET['event_id'])) {
            $sql = "
                SELECT n.*, eq.Name as EquipmentName, e.Title as EventTitle
                FROM Need n
                LEFT JOIN Equipments eq ON n.Equip_ID = eq.Equip_ID
                LEFT JOIN Events e ON n.Event_ID = e.Event_ID
                WHERE n.Equip_ID = ? AND n.Event_ID = ?
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $_GET['equip_id'], $_GET['event_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_assoc($result) ?: ["error" => "Booking not found"]);
            mysqli_stmt_close($stmt);
        } elseif (isset($_GET['equip_id'])) {
            $sql = "
                SELECT n.*, e.Title as EventTitle
                FROM Need n
                LEFT JOIN Events e ON n.Event_ID = e.Event_ID
                WHERE n.Equip_ID = ?
                ORDER BY n.Borrow_Time DESC
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['equip_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            mysqli_stmt_close($stmt);
        } elseif (isset($_GET['event_id'])) {
            $sql = "
                SELECT n.*, eq.Name as EquipmentName
                FROM Need n
                LEFT JOIN Equipments eq ON n.Equip_ID = eq.Equip_ID
                WHERE n.Event_ID = ?
                ORDER BY n.Borrow_Time DESC
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['event_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            mysqli_stmt_close($stmt);
        } else {
            $sql = "
                SELECT n.*, eq.Name as EquipmentName, e.Title as EventTitle
                FROM Need n
                LEFT JOIN Equipments eq ON n.Equip_ID = eq.Equip_ID
                LEFT JOIN Events e ON n.Event_ID = e.Event_ID
                ORDER BY n.Borrow_Time DESC
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
        }
        break;

    case 'POST':
        $equip_id = $input['equip_id'];
        $event_id = $input['event_id'];
        $borrow_time = $input['borrow_time'];
        $return_time = $input['return_time'];

        // 1. Check if equipment is Available
        $checkStmt = mysqli_prepare($conn, "SELECT Status FROM Equipments WHERE Equip_ID = ?");
        mysqli_stmt_bind_param($checkStmt, "i", $equip_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $equip = mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);

        if (!$equip || $equip['Status'] !== 'Available') {
            echo json_encode(["error" => "Equipment is not available for booking (status: " . ($equip['Status'] ?? 'Unknown') . ")"]);
            break;
        }

        // 2. Insert booking (no Status column)
        $stmt = mysqli_prepare($conn, "INSERT INTO Need (Equip_ID, Event_ID, Borrow_Time, Return_Time) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiss", $equip_id, $event_id, $borrow_time, $return_time);
        if (mysqli_stmt_execute($stmt)) {
            // 3. Update equipment status to In-Use
            $updateStmt = mysqli_prepare($conn, "UPDATE Equipments SET Status = 'In-Use' WHERE Equip_ID = ?");
            mysqli_stmt_bind_param($updateStmt, "i", $equip_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'DELETE':
        if (!isset($_GET['equip_id']) || !isset($_GET['event_id'])) {
            echo json_encode(["error" => "Equipment ID and Event ID required"]);
            break;
        }
        $equip_id = $_GET['equip_id'];
        $event_id = $_GET['event_id'];

        // 1. Delete the booking
        $stmt = mysqli_prepare($conn, "DELETE FROM Need WHERE Equip_ID=? AND Event_ID=?");
        mysqli_stmt_bind_param($stmt, "ii", $equip_id, $event_id);
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode(["error" => mysqli_error($conn)]);
            break;
        }
        mysqli_stmt_close($stmt);

        // 2. Check if this equipment has any other bookings
        $checkStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS remaining FROM Need WHERE Equip_ID = ?");
        mysqli_stmt_bind_param($checkStmt, "i", $equip_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $row = mysqli_fetch_assoc($checkResult);
        $remaining = $row['remaining'];
        mysqli_stmt_close($checkStmt);

        // 3. If no bookings remain, set status back to Available
        if ($remaining == 0) {
            $updateStmt = mysqli_prepare($conn, "UPDATE Equipments SET Status = 'Available' WHERE Equip_ID = ?");
            mysqli_stmt_bind_param($updateStmt, "i", $equip_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }

        echo json_encode(["success" => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>