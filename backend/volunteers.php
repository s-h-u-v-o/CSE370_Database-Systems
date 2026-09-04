<?php
header('Content-Type: application/json');
require 'db_connect.php';

function recalculateBadges($student_id) {
    global $conn;

    // 1. Get total volunteer hours for this student
    $sql = "SELECT IFNULL(SUM(Hours_Worked), 0) AS total_hours FROM Volunteer WHERE Student_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $total_hours = $row['total_hours'];
    mysqli_stmt_close($stmt);

    // 2. Get all badges ordered by Hours_Required (ascending)
    $badgeStmt = mysqli_prepare($conn, "SELECT Badge_ID, Hours_Required FROM Badge ORDER BY Hours_Required ASC");
    mysqli_stmt_execute($badgeStmt);
    $badges = mysqli_stmt_get_result($badgeStmt);
    $badgesEarned = 0;

    while ($badge = mysqli_fetch_assoc($badges)) {
        if ($total_hours >= $badge['Hours_Required']) {
            // Check if already earned
            $checkStmt = mysqli_prepare($conn, "SELECT 1 FROM Earn WHERE Student_ID = ? AND Badge_ID = ?");
            mysqli_stmt_bind_param($checkStmt, "ii", $student_id, $badge['Badge_ID']);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            if (mysqli_num_rows($checkResult) == 0) {
                // Earn this badge
                $earnStmt = mysqli_prepare($conn, "INSERT INTO Earn (Student_ID, Badge_ID, Earned_Date, Total_Hours) VALUES (?, ?, CURDATE(), ?)");
                mysqli_stmt_bind_param($earnStmt, "iid", $student_id, $badge['Badge_ID'], $total_hours);
                mysqli_stmt_execute($earnStmt);
                mysqli_stmt_close($earnStmt);
                $badgesEarned++;
            }
            mysqli_stmt_close($checkStmt);
        }
    }
    mysqli_stmt_close($badgeStmt);

    return $badgesEarned;
}

// --------------------------------------------------
// Main API switch
// --------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['student_id']) && isset($_GET['event_id'])) {
            $sql = "
                SELECT v.*, s.Name as StudentName, e.Title as EventTitle
                FROM Volunteer v
                LEFT JOIN Students s ON v.Student_ID = s.Student_ID
                LEFT JOIN Events e ON v.Event_ID = e.Event_ID
                WHERE v.Student_ID = ? AND v.Event_ID = ?
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $_GET['student_id'], $_GET['event_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_assoc($result) ?: ["error" => "Volunteer record not found"]);
            mysqli_stmt_close($stmt);
        } elseif (isset($_GET['student_id'])) {
            $sql = "
                SELECT v.*, e.Title as EventTitle
                FROM Volunteer v
                LEFT JOIN Events e ON v.Event_ID = e.Event_ID
                WHERE v.Student_ID = ?
                ORDER BY v.Hours_Worked DESC
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['student_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            mysqli_stmt_close($stmt);
        } elseif (isset($_GET['event_id'])) {
            $sql = "
                SELECT v.*, s.Name as StudentName
                FROM Volunteer v
                LEFT JOIN Students s ON v.Student_ID = s.Student_ID
                WHERE v.Event_ID = ?
                ORDER BY v.Hours_Worked DESC
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['event_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            mysqli_stmt_close($stmt);
        } else {
            $sql = "
                SELECT v.*, s.Name as StudentName, e.Title as EventTitle
                FROM Volunteer v
                LEFT JOIN Students s ON v.Student_ID = s.Student_ID
                LEFT JOIN Events e ON v.Event_ID = e.Event_ID
                ORDER BY v.Hours_Worked DESC
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
        }
        break;

    case 'POST':
        $student_id = $input['student_id'];
        $event_id = $input['event_id'];
        $role = $input['role'];
        $hours_worked = $input['hours_worked'];

        // Check that student exists (optional, but good)
        $checkStmt = mysqli_prepare($conn, "SELECT 1 FROM Students WHERE Student_ID = ?");
        mysqli_stmt_bind_param($checkStmt, "i", $student_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        if (mysqli_num_rows($checkResult) == 0) {
            echo json_encode(["error" => "Student not found"]);
            break;
        }
        mysqli_stmt_close($checkStmt);

        $stmt = mysqli_prepare($conn, "INSERT INTO Volunteer (Student_ID, Event_ID, Role, Hours_Worked) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iisd", $student_id, $event_id, $role, $hours_worked);
        if (mysqli_stmt_execute($stmt)) {
            recalculateBadges($student_id);   // Recalculate badges
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'PUT':
        if (!isset($_GET['student_id']) || !isset($_GET['event_id'])) {
            echo json_encode(["error" => "Student ID and Event ID required"]);
            break;
        }
        $student_id = $_GET['student_id'];
        $event_id = $_GET['event_id'];
        $role = $input['role'] ?? '';
        $hours_worked = $input['hours_worked'] ?? 0;

        if (empty($role) || $hours_worked <= 0 || $hours_worked > 24) {
            echo json_encode(["error" => "Valid hours (0.5 to 24) and role are required"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE Volunteer SET Role=?, Hours_Worked=? WHERE Student_ID=? AND Event_ID=?");
        mysqli_stmt_bind_param($stmt, "sdii", $role, $hours_worked, $student_id, $event_id);
        if (mysqli_stmt_execute($stmt)) {
            recalculateBadges($student_id);   // Recalculate badges
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'DELETE':
        if (!isset($_GET['student_id']) || !isset($_GET['event_id'])) {
            echo json_encode(["error" => "Student ID and Event ID required"]);
            break;
        }
        $student_id = $_GET['student_id'];
        $event_id = $_GET['event_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM Volunteer WHERE Student_ID=? AND Event_ID=?");
        mysqli_stmt_bind_param($stmt, "ii", $student_id, $event_id);
        if (mysqli_stmt_execute($stmt)) {
            recalculateBadges($student_id);   // Recalculate badges
            echo json_encode(["success" => true]);
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