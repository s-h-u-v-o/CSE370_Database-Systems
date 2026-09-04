<?php
header('Content-Type: application/json');
require 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // Return all memberships with student and club names
        if (isset($_GET['student_id'])) {
            $student_id = (int)$_GET['student_id'];
            $sql = "
                SELECT j.*, s.Name as StudentName, c.Name as ClubName
                FROM Joine j
                LEFT JOIN Students s ON j.Student_ID = s.Student_ID
                LEFT JOIN Club c ON j.Club_ID = c.Club_ID
                WHERE j.Student_ID = ?
                ORDER BY j.Join_Date DESC
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $student_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            mysqli_stmt_close($stmt);
        } else {
            $sql = "
                SELECT j.*, s.Name as StudentName, c.Name as ClubName
                FROM Joine j
                LEFT JOIN Students s ON j.Student_ID = s.Student_ID
                LEFT JOIN Club c ON j.Club_ID = c.Club_ID
                ORDER BY j.Join_Date DESC
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
        }
        break;

    case 'POST':
        $stmt = mysqli_prepare($conn, "INSERT INTO Joine (Student_ID, Club_ID, Designation, Join_Date) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiss", $input['student_id'], $input['club_id'], $input['designation'], $input['join_date']);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'PUT':
        if (!isset($_GET['student_id']) || !isset($_GET['club_id'])) {
            echo json_encode(["error" => "Student ID and Club ID required"]);
            break;
        }
        $student_id = $_GET['student_id'];
        $club_id = $_GET['club_id'];
        $designation = $input['designation'] ?? null;

        if (!$designation) {
            echo json_encode(["error" => "Designation is required"]);
            break;
        }

        // Validate the designation (optional)
        $valid_roles = ['General Member', 'Volunteer', 'Executive'];
        if (!in_array($designation, $valid_roles)) {
            echo json_encode(["error" => "Invalid designation"]);
            break;
        }

        // Update only the designation
        $stmt = mysqli_prepare($conn, "UPDATE Joine SET Designation = ? WHERE Student_ID = ? AND Club_ID = ?");
        mysqli_stmt_bind_param($stmt, "sii", $designation, $student_id, $club_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'DELETE':
        if (!isset($_GET['student_id']) || !isset($_GET['club_id'])) {
            echo json_encode(["error" => "Student ID and Club ID required"]);
            break;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM Joine WHERE Student_ID=? AND Club_ID=?");
        mysqli_stmt_bind_param($stmt, "ii", $_GET['student_id'], $_GET['club_id']);
        if (mysqli_stmt_execute($stmt)) {
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