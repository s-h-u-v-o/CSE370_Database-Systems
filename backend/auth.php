<?php
header('Content-Type: application/json');
require 'db_connect.php'; 

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['email']) || !isset($input['password'])) {
    echo json_encode(["success" => false, "message" => "Email and password required"]);
    exit;
}

$email = $input['email'];
$password = $input['password'];

// Get student by email
$sql = "SELECT Student_ID, Name, Email, Password, Street, Sub_district, District FROM Students WHERE Email = ?";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Database error: " . mysqli_error($conn)]);
    exit;
}
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo json_encode(["success" => false, "message" => "Invalid email or password"]);
    exit;
}

// Verify password (use password_hash() in production)
if ($password !== $user['Password']) {
    echo json_encode(["success" => false, "message" => "Invalid email or password"]);
    exit;
}

// Fetch all designations for this student
$designationSql = "SELECT Designation FROM Joine WHERE Student_ID = ?";
$designationStmt = mysqli_prepare($conn, $designationSql);
mysqli_stmt_bind_param($designationStmt, "i", $user['Student_ID']);
mysqli_stmt_execute($designationStmt);
$designationResult = mysqli_stmt_get_result($designationStmt);
$designations = mysqli_fetch_all($designationResult, MYSQLI_ASSOC);
mysqli_stmt_close($designationStmt);

// Determine role: admin if any membership is Executive 
$role = 'student';
foreach ($designations as $row) {
    if ($row['Designation'] === 'Executive') {
        $role = 'admin';
        break;
    }
}

// Remove password before sending
unset($user['Password']);
$user['role'] = $role;

echo json_encode([
    "success" => true,
    "user" => $user,
    "message" => "Login successful"
]);
?>