<?php
header('Content-Type: application/json');
require 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $sql = "
                SELECT c.*,
                       (SELECT COUNT(*) FROM Joine WHERE Club_ID = c.Club_ID) AS MemberCount,
                       (SELECT GROUP_CONCAT(Email SEPARATOR ', ') FROM Club_Emails WHERE Club_ID = c.Club_ID) AS ContactEmails
                FROM Club c
                WHERE c.Club_ID = ?
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_assoc($result) ?: ["error" => "Club not found"]);
            mysqli_stmt_close($stmt);
        } else {
            $sql = "
                SELECT c.*,
                       (SELECT COUNT(*) FROM Joine WHERE Club_ID = c.Club_ID) AS MemberCount,
                       (SELECT GROUP_CONCAT(Email SEPARATOR ', ') FROM Club_Emails WHERE Club_ID = c.Club_ID) AS ContactEmails
                FROM Club c
                ORDER BY c.Club_ID DESC
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
        }
        break;

    case 'POST':
        $name = $input['name'] ?? '';
        $department = $input['department'] ?? '';
        $office_room = $input['office_room'] ?? null;
        $emails = $input['emails'] ?? '';

        if (!$name || !$department) {
            echo json_encode(["error" => "Name and Department are required"]);
            break;
        }

        // Insert club – Club_ID is auto‑generated
        $stmt = mysqli_prepare($conn, "INSERT INTO Club (Name, Department, Office_Room) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $name, $department, $office_room);
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode(["error" => mysqli_error($conn)]);
            break;
        }
        $clubId = mysqli_insert_id($conn);  // Get the auto‑generated ID
        mysqli_stmt_close($stmt);

        // Insert emails
        if (!empty($emails)) {
            $emailList = array_map('trim', explode(',', $emails));
            $emailStmt = mysqli_prepare($conn, "INSERT INTO Club_Emails (Club_ID, Email) VALUES (?, ?)");
            foreach ($emailList as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    mysqli_stmt_bind_param($emailStmt, "is", $clubId, $email);
                    mysqli_stmt_execute($emailStmt);
                }
            }
            mysqli_stmt_close($emailStmt);
        }

        echo json_encode(["success" => true, "id" => $clubId]);
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            echo json_encode(["error" => "Club ID required"]);
            break;
        }
        $clubId = (int)$_GET['id'];
        $name = $input['name'] ?? '';
        $department = $input['department'] ?? '';
        $office_room = $input['office_room'] ?? null;
        $emails = $input['emails'] ?? '';

        if (!$name || !$department) {
            echo json_encode(["error" => "Name and Department are required"]);
            break;
        }

        // Update club
        $stmt = mysqli_prepare($conn, "UPDATE Club SET Name=?, Department=?, Office_Room=? WHERE Club_ID=?");
        mysqli_stmt_bind_param($stmt, "sssi", $name, $department, $office_room, $clubId);
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode(["error" => mysqli_error($conn)]);
            break;
        }
        mysqli_stmt_close($stmt);

        // Replace emails: delete old, insert new
        $delStmt = mysqli_prepare($conn, "DELETE FROM Club_Emails WHERE Club_ID=?");
        mysqli_stmt_bind_param($delStmt, "i", $clubId);
        mysqli_stmt_execute($delStmt);
        mysqli_stmt_close($delStmt);

        if (!empty($emails)) {
            $emailList = array_map('trim', explode(',', $emails));
            $emailStmt = mysqli_prepare($conn, "INSERT INTO Club_Emails (Club_ID, Email) VALUES (?, ?)");
            foreach ($emailList as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    mysqli_stmt_bind_param($emailStmt, "is", $clubId, $email);
                    mysqli_stmt_execute($emailStmt);
                }
            }
            mysqli_stmt_close($emailStmt);
        }

        echo json_encode(["success" => true]);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            echo json_encode(["error" => "Club ID required"]);
            break;
        }
        $clubId = (int)$_GET['id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM Club WHERE Club_ID=?");
        mysqli_stmt_bind_param($stmt, "i", $clubId);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["error" => "Club not found"]);
            }
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>