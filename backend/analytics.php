<?php
header('Content-Type: application/json');
require 'db_connect.php';

$type = $_GET['type'] ?? $_GET['action'] ?? '';

try {
    switch ($type) {
        case 'equipment_utilization':
            // Equipment utilization from Equipments + Need + Club
            $sql = "
                SELECT 
                    e.Name,
                    e.Type,
                    c.Name as OwnerClub,
                    COUNT(n.Event_ID) as Total_Bookings,
                    IFNULL(AVG(TIMESTAMPDIFF(HOUR, n.Borrow_Time, n.Return_Time)), 0) as Avg_Hours_Booked,
                    IFNULL(SUM(m.Cost), 0) as Total_Maintenance_Cost,
                    CASE 
                        WHEN COUNT(n.Event_ID) > 10 THEN 'High'
                        WHEN COUNT(n.Event_ID) > 3 THEN 'Medium'
                        ELSE 'Low'
                    END as Utilization_Level
                FROM Equipments e
                LEFT JOIN Need n ON e.Equip_ID = n.Equip_ID
                LEFT JOIN Club c ON e.Owner_Club_ID = c.Club_ID
                LEFT JOIN Maintenance_Log m ON e.Equip_ID = m.Equip_ID
                GROUP BY e.Equip_ID
                ORDER BY Total_Bookings DESC
                LIMIT 20
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            break;

        case 'event_success':
            // Event metrics from Events + Volunteer + Need + Club
            $sql = "
                SELECT 
                    e.Title,
                    e.Date,
                    c.Name as Primary_Club,
                    COUNT(DISTINCT v.Student_ID) as Volunteers_Count,
                    COUNT(DISTINCT n.Equip_ID) as Equipment_Used,
                    CASE 
                        WHEN COUNT(DISTINCT v.Student_ID) > 20 THEN 'Large'
                        WHEN COUNT(DISTINCT v.Student_ID) > 8 THEN 'Medium'
                        ELSE 'Small'
                    END as Event_Scale,
                    (COUNT(DISTINCT v.Student_ID) + COUNT(DISTINCT n.Equip_ID)) as Success_Score
                FROM Events e
                LEFT JOIN Club c ON e.Primary_Club_ID = c.Club_ID
                LEFT JOIN Volunteer v ON e.Event_ID = v.Event_ID
                LEFT JOIN Need n ON e.Event_ID = n.Event_ID
                GROUP BY e.Event_ID
                ORDER BY e.Date DESC
                LIMIT 20
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            break;

        case 'student_engagement':
            // Student engagement from Students + Volunteer + Joine
            $sql = "
                SELECT 
                    s.Name,
                    IFNULL(SUM(v.Hours_Worked), 0) as Total_Hours,
                    COUNT(DISTINCT v.Event_ID) as Events_Volunteered,
                    COUNT(DISTINCT j.Club_ID) as Clubs_Joined,
                    MAX(CASE WHEN j.Designation = 'Executive' THEN 1 ELSE 0 END) as Is_Executive,
                    COUNT(DISTINCT j.Designation) as Different_Roles,
                    (IFNULL(SUM(v.Hours_Worked), 0) + COUNT(DISTINCT v.Event_ID) * 5 + COUNT(DISTINCT j.Club_ID) * 10) as Engagement_Score,
                    CASE 
                        WHEN IFNULL(SUM(v.Hours_Worked), 0) > 50 THEN 'Highly Engaged'
                        WHEN IFNULL(SUM(v.Hours_Worked), 0) > 15 THEN 'Moderately Engaged'
                        WHEN IFNULL(SUM(v.Hours_Worked), 0) > 0 THEN 'Occasionally Active'
                        ELSE 'Inactive'
                    END as Engagement_Category
                FROM Students s
                LEFT JOIN Volunteer v ON s.Student_ID = v.Student_ID
                LEFT JOIN Joine j ON s.Student_ID = j.Student_ID
                GROUP BY s.Student_ID
                ORDER BY Engagement_Score DESC
                LIMIT 50
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            break;

        case 'booking_patterns':
            // Booking patterns from Need + Equipments
            $sql = "
                SELECT 
                    DAYNAME(Borrow_Time) as Day_Of_Week,
                    HOUR(Borrow_Time) as Hour_Of_Day,
                    eq.Type as Equipment_Type,
                    COUNT(*) as Booking_Count,
                    IFNULL(AVG(TIMESTAMPDIFF(HOUR, Borrow_Time, Return_Time)), 0) as Avg_Duration_Hours,
                    CASE 
                        WHEN COUNT(*) > 10 THEN 'High'
                        WHEN COUNT(*) > 3 THEN 'Medium'
                        ELSE 'Low'
                    END as Demand_Level
                FROM Need n
                JOIN Equipments eq ON n.Equip_ID = eq.Equip_ID
                GROUP BY Day_Of_Week, Hour_Of_Day, Equipment_Type
                ORDER BY Booking_Count DESC
                LIMIT 50
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            break;

        case 'volunteer_leaderboard':
            // Leaderboard from Volunteer + Students
            $sql = "
                SELECT 
                    s.Student_ID,
                    s.Name,
                    IFNULL(SUM(v.Hours_Worked), 0) as Total_Hours,
                    COUNT(DISTINCT v.Event_ID) as Events_Count,
                    (SELECT COUNT(*) FROM Earn e WHERE e.Student_ID = s.Student_ID) as Badges_Earned,
                    (SELECT MAX(b.Tier) FROM Earn e JOIN Badge b ON e.Badge_ID = b.Badge_ID WHERE e.Student_ID = s.Student_ID) as Highest_Tier
                FROM Students s
                LEFT JOIN Volunteer v ON s.Student_ID = v.Student_ID
                GROUP BY s.Student_ID
                HAVING Total_Hours > 0
                ORDER BY Total_Hours DESC
                LIMIT 25
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            break;

        case 'club_membership_stats':
            // Club membership stats from Club + Joine
            $sql = "
                SELECT 
                    c.Name as Club_Name,
                    c.Department,
                    COUNT(j.Student_ID) as Total_Members,
                    SUM(CASE WHEN j.Designation = 'Executive' THEN 1 ELSE 0 END) as Executives_Count,
                    MIN(j.Join_Date) as Oldest_Member_Join,
                    MAX(j.Join_Date) as Newest_Member_Join
                FROM Club c
                LEFT JOIN Joine j ON c.Club_ID = j.Club_ID
                GROUP BY c.Club_ID
                ORDER BY Total_Members DESC
            ";
            $result = mysqli_query($conn, $sql);
            echo json_encode(mysqli_fetch_all($result, MYSQLI_ASSOC));
            break;

        case 'overview_stats':
            $stats = [];
            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Students");
            $stats['total_students'] = (int)mysqli_fetch_assoc($result)['total'];
            
            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Club");
            $stats['total_clubs'] = (int)mysqli_fetch_assoc($result)['total'];
            
            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Equipments");
            $stats['total_equipment'] = (int)mysqli_fetch_assoc($result)['total'];
            
            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Events");
            $stats['total_events'] = (int)mysqli_fetch_assoc($result)['total'];
            
            $result = mysqli_query($conn, "SELECT IFNULL(SUM(Hours_Worked), 0) as total FROM Volunteer");
            $stats['total_volunteer_hours'] = (float)mysqli_fetch_assoc($result)['total'];
            
            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Need");
            $stats['total_bookings'] = (int)mysqli_fetch_assoc($result)['total'];
            
            echo json_encode($stats);
            break;

        default:
            http_response_code(400);
            echo json_encode(["error" => "Invalid analytics type. Available: equipment_utilization, event_success, student_engagement, booking_patterns, volunteer_leaderboard, club_membership_stats, overview_stats"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>