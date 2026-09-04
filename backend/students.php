<?php
header('Content-Type: application/json');
require 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Fetch a single student with phones and memberships (read-only)
            $sql = "
                SELECT s.*, 
                       GROUP_CONCAT(DISTINCT sc.Phone_Number) AS Phone_Numbers,
                       GROUP_CONCAT(DISTINCT CONCAT(j.Club_ID, ':', j.Designation) SEPARATOR ';') AS Memberships
                FROM Students s
                LEFT JOIN Students_contact sc ON s.Student_ID = sc.Student_ID
                LEFT JOIN Joine j ON s.Student_ID = j.Student_ID
                WHERE s.Student_ID = ?
                GROUP BY s.Student_ID
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $student = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($student) {
                // Parse phone numbers
                $student['Phone_Numbers'] = $student['Phone_Numbers'] ? explode(',', $student['Phone_Numbers']) : [];
                // Parse memberships
                $memberships = [];
                if ($student['Memberships']) {
                    foreach (explode(';', $student['Memberships']) as $item) {
                        list($clubId, $designation) = explode(':', $item);
                        $memberships[] = ['Club_ID' => (int)$clubId, 'Designation' => $designation];
                    }
                }
                $student['Memberships'] = $memberships;
                unset($student['Password']); // Never expose password
                echo json_encode($student);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Student not found"]);
            }
        } else {
            // Fetch all students
            $sql = "
                SELECT s.*, 
                       GROUP_CONCAT(DISTINCT sc.Phone_Number) AS Phone_Numbers,
                       GROUP_CONCAT(DISTINCT CONCAT(j.Club_ID, ':', j.Designation) SEPARATOR ';') AS Memberships
                FROM Students s
                LEFT JOIN Students_contact sc ON s.Student_ID = sc.Student_ID
                LEFT JOIN Joine j ON s.Student_ID = j.Student_ID
                GROUP BY s.Student_ID
                ORDER BY s.Student_ID DESC
            ";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
                foreach ($students as &$student) {
                    $student['Phone_Numbers'] = $student['Phone_Numbers'] ? explode(',', $student['Phone_Numbers']) : [];
                    $memberships = [];
                    if ($student['Memberships']) {
                        foreach (explode(';', $student['Memberships']) as $item) {
                            list($clubId, $designation) = explode(':', $item);
                            $memberships[] = ['Club_ID' => (int)$clubId, 'Designation' => $designation];
                        }
                    }
                    $student['Memberships'] = $memberships;
                    unset($student['Password']);
                }
                echo json_encode($students);
            } else {
                http_response_code(500);
                echo json_encode(["error" => mysqli_error($conn)]);
            }
        }
        break;

    case 'POST':
        // Create a new student (with phone numbers only – no club membership)
        mysqli_begin_transaction($conn);
        try {
            // Insert into Students
            $stmt = mysqli_prepare($conn, "INSERT INTO Students (Name, Email, Password, Street, Sub_district, District) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssssss", $input['name'], $input['email'], $input['password'], $input['street'], $input['sub_district'], $input['district']);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_error($conn));
            }
            $studentId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Insert phone numbers (required – at least one)
            if (!isset($input['phones']) || !is_array($input['phones']) || count($input['phones']) === 0) {
                throw new Exception("At least one phone number is required.");
            }
            $phoneStmt = mysqli_prepare($conn, "INSERT INTO Students_contact (Student_ID, Phone_Number) VALUES (?, ?)");
            foreach ($input['phones'] as $phone) {
                $phone = trim($phone);
                if (!preg_match('/^[0-9]{11}$/', $phone)) {
                    throw new Exception("Invalid phone number: $phone (must be exactly 11 digits).");
                }
                mysqli_stmt_bind_param($phoneStmt, "is", $studentId, $phone);
                if (!mysqli_stmt_execute($phoneStmt)) {
                    throw new Exception(mysqli_error($conn));
                }
            }
            mysqli_stmt_close($phoneStmt);

            mysqli_commit($conn);
            echo json_encode(["success" => true, "id" => $studentId]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update an existing student (replace phone numbers)
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Student ID required"]);
            break;
        }
        $studentId = (int)$_GET['id'];

        mysqli_begin_transaction($conn);
        try {
            // Update Students table
            $stmt = mysqli_prepare($conn, "UPDATE Students SET Name=?, Email=?, Password=?, Street=?, Sub_district=?, District=? WHERE Student_ID=?");
            mysqli_stmt_bind_param($stmt, "ssssssi", $input['name'], $input['email'], $input['password'], $input['street'], $input['sub_district'], $input['district'], $studentId);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            // Replace phone numbers: delete all then re-insert
            $deleteStmt = mysqli_prepare($conn, "DELETE FROM Students_contact WHERE Student_ID = ?");
            mysqli_stmt_bind_param($deleteStmt, "i", $studentId);
            if (!mysqli_stmt_execute($deleteStmt)) {
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($deleteStmt);

            if (isset($input['phones']) && is_array($input['phones']) && count($input['phones']) > 0) {
                $phoneStmt = mysqli_prepare($conn, "INSERT INTO Students_contact (Student_ID, Phone_Number) VALUES (?, ?)");
                foreach ($input['phones'] as $phone) {
                    $phone = trim($phone);
                    if (!preg_match('/^[0-9]{11}$/', $phone)) {
                        throw new Exception("Invalid phone number: $phone (must be exactly 11 digits).");
                    }
                    mysqli_stmt_bind_param($phoneStmt, "is", $studentId, $phone);
                    if (!mysqli_stmt_execute($phoneStmt)) {
                        throw new Exception(mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($phoneStmt);
            } else {
                // If no phones provided, it's okay – we just delete all (student can have zero phones after update)
            }

            mysqli_commit($conn);
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete a student (ON DELETE CASCADE handles child tables)
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Student ID required"]);
            break;
        }
        $studentId = (int)$_GET['id'];

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "DELETE FROM Students WHERE Student_ID = ?");
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_error($conn));
            }
            if (mysqli_stmt_affected_rows($stmt) == 0) {
                throw new Exception("Student not found");
            }
            mysqli_stmt_close($stmt);
            mysqli_commit($conn);
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>