<?php
header('Content-Type: application/json');
require 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $sql = "
                SELECT e.*, c.Name as OwnerClub
                FROM Equipments e
                LEFT JOIN Club c ON e.Owner_Club_ID = c.Club_ID
                WHERE e.Equip_ID = ?
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_assoc($result) ?: ["error" => "Equipment not found"]);
            mysqli_stmt_close($stmt);
        } else {
            $sql = "
                SELECT e.*, c.Name as OwnerClub
                FROM Equipments e
                LEFT JOIN Club c ON e.Owner_Club_ID = c.Club_ID
                ORDER BY e.Equip_ID DESC
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
        }
        break;

    case 'POST':
        // Equip_ID is auto-increment, so we don't include it in the INSERT
        $stmt = mysqli_prepare($conn, "INSERT INTO Equipments (Name, Type, Status, Owner_Club_ID, Purchase_Date) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssis", $input['name'], $input['type'], $input['status'], $input['owner_club_id'], $input['purchase_date']);
        if (mysqli_stmt_execute($stmt)) {
            $newId = mysqli_insert_id($conn);
            echo json_encode(["success" => true, "id" => $newId]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            echo json_encode(["error" => "Equipment ID required"]);
            break;
        }
        $equipId = (int)$_GET['id'];
        $newStatus = $input['status'] ?? null;

        if (!$newStatus) {
            echo json_encode(["error" => "Status is required"]);
            break;
        }

        // Fetch current status
        $checkStmt = mysqli_prepare($conn, "SELECT Status FROM Equipments WHERE Equip_ID = ?");
        mysqli_stmt_bind_param($checkStmt, "i", $equipId);
        mysqli_stmt_execute($checkStmt);
        $result = mysqli_stmt_get_result($checkStmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($checkStmt);

        if (!$row) {
            echo json_encode(["error" => "Equipment not found"]);
            break;
        }
        $currentStatus = $row['Status'];

        // Enforce one-way: only Available -> Damaged
        if ($currentStatus !== 'Available' || $newStatus !== 'Damaged') {
            echo json_encode(["error" => "Only Available equipment can be marked as Damaged."]);
            break;
        }

        // Update status
        $stmt = mysqli_prepare($conn, "UPDATE Equipments SET Status = ? WHERE Equip_ID = ?");
        mysqli_stmt_bind_param($stmt, "si", $newStatus, $equipId);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            echo json_encode(["error" => "Equipment ID required"]);
            break;
        }
        $id = (int)$_GET['id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM Equipments WHERE Equip_ID = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["error" => "Equipment not found"]);
            }
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;
}
?>