<?php
header('Content-Type: application/json');
require 'db_connect.php';

// --------------------------------------------------
// Helper: recalculate badges for a student
// --------------------------------------------------
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
        // ----- LEADERBOARD (must come first) -----
        if (isset($_GET['action']) && $_GET['action'] === 'leaderboard') {
            // Dynamic leaderboard: compute badges directly from Volunteer hours
            $sql = "
                SELECT 
                    s.Student_ID,
                    s.Name,
                    (SELECT IFNULL(SUM(Hours_Worked), 0) FROM Volunteer WHERE Student_ID = s.Student_ID) AS Total_Hours,
                    (SELECT COUNT(DISTINCT Event_ID) FROM Volunteer WHERE Student_ID = s.Student_ID) AS Events_Count,
                    (SELECT COUNT(*) FROM Badge b 
                     WHERE b.Hours_Required <= (SELECT IFNULL(SUM(Hours_Worked), 0) FROM Volunteer WHERE Student_ID = s.Student_ID)
                    ) AS Badges_Earned,
                    (SELECT MAX(b.Tier) FROM Badge b 
                     WHERE b.Hours_Required <= (SELECT IFNULL(SUM(Hours_Worked), 0) FROM Volunteer WHERE Student_ID = s.Student_ID)
                    ) AS Highest_Tier
                FROM Students s
                HAVING Total_Hours > 0
                ORDER BY Total_Hours DESC
            ";
            $result = mysqli_query($conn, $sql);
            $leaderboard = mysqli_fetch_all($result, MYSQLI_ASSOC);
            echo json_encode(["success" => true, "leaderboard" => $leaderboard]);
            break;
        }

        // ----- Get badges for a specific student -----
        if (isset($_GET['student_id'])) {
            $student_id = (int)$_GET['student_id'];
            $sql = "
                SELECT b.*, 
                       e.Earned_Date,
                       e.Total_Hours,
                       CASE WHEN e.Student_ID IS NOT NULL THEN 1 ELSE 0 END AS Is_Earned
                FROM Badge b
                LEFT JOIN Earn e ON b.Badge_ID = e.Badge_ID AND e.Student_ID = ?
                ORDER BY FIELD(b.Tier, 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'), b.Hours_Required
            ";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $student_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(["success" => true, "data" => mysqli_fetch_all($result, MYSQLI_ASSOC)]);
            mysqli_stmt_close($stmt);
            break;
        }

        // ----- Get a single badge by ID -----
        if (isset($_GET['badge_id'])) {
            $sql = "SELECT * FROM Badge WHERE Badge_ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_GET['badge_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            echo json_encode(mysqli_fetch_assoc($result) ?: ["error" => "Badge not found"]);
            mysqli_stmt_close($stmt);
            break;
        }

        // ----- Get all badges -----
        $sql = "SELECT * FROM Badge ORDER BY FIELD(Tier, 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'), Hours_Required";
        $result = mysqli_query($conn, $sql);
        echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
        break;

    case 'POST':
        // 1. Recalculate for a single student
        if (isset($_GET['action']) && $_GET['action'] === 'recalculate') {
            $student_id = $input['student_id'] ?? 0;
            if (!$student_id) {
                echo json_encode(["error" => "Student ID required"]);
                break;
            }
            $earned = recalculateBadges($student_id);
            echo json_encode(["success" => true, "badges_earned" => $earned]);
            break;
        }

        // 2. Recalculate for all students
        if (isset($_GET['action']) && $_GET['action'] === 'recalculate_all') {
            $students = mysqli_query($conn, "SELECT Student_ID FROM Students");
            $total_earned = 0;
            while ($row = mysqli_fetch_assoc($students)) {
                $earned = recalculateBadges($row['Student_ID']);
                $total_earned += $earned;
            }
            echo json_encode(["success" => true, "total_earned" => $total_earned]);
            break;
        }

        // 3. Create a new badge (admin only)
        if (!isset($input['badge_id']) || !isset($input['name']) || !isset($input['tier']) || !isset($input['hours_required'])) {
            echo json_encode(["error" => "Missing required fields: badge_id, name, tier, hours_required"]);
            break;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO Badge (Badge_ID, Name, Tier, Description, Hours_Required) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssd", $input['badge_id'], $input['name'], $input['tier'], $input['description'], $input['hours_required']);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true, "message" => "Badge created"]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'PUT':
        if (!isset($_GET['badge_id'])) {
            echo json_encode(["error" => "Badge ID required"]);
            break;
        }
        $stmt = mysqli_prepare($conn, "UPDATE Badge SET Name=?, Tier=?, Description=?, Hours_Required=? WHERE Badge_ID=?");
        mysqli_stmt_bind_param($stmt, "sssdi", $input['name'], $input['tier'], $input['description'], $input['hours_required'], $_GET['badge_id']);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true, "message" => "Badge updated"]);
        } else {
            echo json_encode(["error" => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'DELETE':
        if (!isset($_GET['badge_id'])) {
            echo json_encode(["error" => "Badge ID required"]);
            break;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM Badge WHERE Badge_ID=?");
        mysqli_stmt_bind_param($stmt, "i", $_GET['badge_id']);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true, "message" => "Badge deleted"]);
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