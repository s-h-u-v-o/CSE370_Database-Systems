<?php
header('Content-Type: application/json');
require 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['equip_id']) && isset($_GET['log_id'])) {
            $sql = "
                SELECT m.*, e.Name as EquipmentName
                FROM Maintenance_Log m
                LEFT JOIN Equipments e ON m.Equip_ID = e.Equip_ID
                WHERE m.Equip_ID = ? AND m.Log_ID = ?
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $_GET['equip_id'], $_GET['log_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_assoc($result) ?: ["error" => "Maintenance log not found"]);
            mysqli_stmt_close($stmt);
        } elseif (isset($_GET['equip_id'])) {
            $sql = "
                SELECT m.*, e.Name as EquipmentName
                FROM Maintenance_Log m
                LEFT JOIN Equipments e ON m.Equip_ID = e.Equip_ID
                WHERE m.Equip_ID = ?
                ORDER BY m.Date DESC
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['equip_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            mysqli_stmt_close($stmt);
        } else {
            $sql = "
                SELECT m.*, e.Name as EquipmentName
                FROM Maintenance_Log m
                LEFT JOIN Equipments e ON m.Equip_ID = e.Equip_ID
                ORDER BY m.Date DESC
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
        }
        break;

    case 'POST':
        $equip_id = $input['equip_id'];
        $date = $input['date'];
        $description = $input['description'];
        $cost = $input['cost'];

        // Get next Log_ID for this equipment
        $maxSql = "SELECT IFNULL(MAX(Log_ID), 0) + 1 AS NextLogID FROM Maintenance_Log WHERE Equip_ID = ?";
        $maxStmt = mysqli_prepare($conn, $maxSql);
        mysqli_stmt_bind_param($maxStmt, "i", $equip_id);
        mysqli_stmt_execute($maxStmt);
        $maxResult = mysqli_stmt_get_result($maxStmt);
        $row = mysqli_fetch_assoc($maxResult);
        $nextLogId = $row['NextLogID'];
        mysqli_stmt_close($maxStmt);

        // Insert maintenance log
        $stmt = mysqli_prepare($conn, "INSERT INTO Maintenance_Log (Equip_ID, Log_ID, Date, Description, Cost) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iissd", $equip_id, $nextLogId, $date, $description, $cost);
        if (mysqli_stmt_execute($stmt)) {
            // UPDATE equipment status to 'Maintenance'
            $updateStmt = mysqli_prepare($conn, "UPDATE Equipments SET Status = 'Maintenance' WHERE Equip_ID = ?");
            mysqli_stmt_bind_param($updateStmt, "i", $equip_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            echo json_encode(["success" => true, "log_id" => $nextLogId]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'PUT':
        if (!isset($_GET['equip_id']) || !isset($_GET['log_id'])) {
            echo json_encode(["error" => "Equipment ID and Log ID required"]);
            break;
        }
        $equip_id = $_GET['equip_id'];
        $log_id = $_GET['log_id'];
        $date = $input['date'];
        $description = $input['description'];
        $cost = $input['cost'];

        $stmt = mysqli_prepare($conn, "UPDATE Maintenance_Log SET Date=?, Description=?, Cost=? WHERE Equip_ID=? AND Log_ID=?");
        mysqli_stmt_bind_param($stmt, "ssdii", $date, $description, $cost, $equip_id, $log_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'DELETE':
        if (!isset($_GET['equip_id']) || !isset($_GET['log_id'])) {
            echo json_encode(["error" => "Equipment ID and Log ID required"]);
            break;
        }
        $equip_id = $_GET['equip_id'];
        $log_id = $_GET['log_id'];

        // 1. Delete the specific log
        $stmt = mysqli_prepare($conn, "DELETE FROM Maintenance_Log WHERE Equip_ID=? AND Log_ID=?");
        mysqli_stmt_bind_param($stmt, "ii", $equip_id, $log_id);
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode(["error" => mysqli_error($conn)]);
            break;
        }
        mysqli_stmt_close($stmt);

        // 2. Check if there are any remaining logs for this equipment
        $checkStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS remaining FROM Maintenance_Log WHERE Equip_ID=?");
        mysqli_stmt_bind_param($checkStmt, "i", $equip_id);
        mysqli_stmt_execute($checkStmt);
        $result = mysqli_stmt_get_result($checkStmt);
        $row = mysqli_fetch_assoc($result);
        $remaining = $row['remaining'];
        mysqli_stmt_close($checkStmt);

        // 3. If no logs remain, update equipment status to 'Available'
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