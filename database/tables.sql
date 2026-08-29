CREATE DATABASE Club_Collab;
USE Club_Collab;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE Club (
    Club_ID INT PRIMARY KEY,
    Name TEXT NOT NULL UNIQUE,
    Department TEXT NOT NULL,
    Office_Room TEXT,
    CONSTRAINT chk_club_name CHECK (LENGTH(Name) > 0)
);

CREATE TABLE Students (
    Student_ID INT PRIMARY KEY,
    Name TEXT NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Street TEXT NOT NULL,
    Sub_district TEXT NOT NULL,
    District TEXT NOT NULL,
    CONSTRAINT chk_email CHECK (Email LIKE '%@%'),
    CONSTRAINT chk_student_name CHECK (LENGTH(Name) > 0)
);

CREATE TABLE Club_Emails (
    Club_ID INT NOT NULL,
    Email VARCHAR(100) NOT NULL,
    PRIMARY KEY (Club_ID, Email),
    FOREIGN KEY (Club_ID) REFERENCES Club(Club_ID) ON DELETE CASCADE,
    CONSTRAINT chk_contact_email CHECK (Email LIKE '%@%.com' OR Email LIKE '%@%.edu')
);

CREATE TABLE Students_contact (
    Student_ID INT NOT NULL,
    Phone_Number VARCHAR(11) NOT NULL,
    PRIMARY KEY (Student_ID, Phone_Number),
    FOREIGN KEY (Student_ID) REFERENCES Students(Student_ID) ON DELETE CASCADE,
    CONSTRAINT chk_phone CHECK (LENGTH(Phone_Number) = 11)
);

CREATE TABLE Joine (
    Student_ID INT NOT NULL,
    Club_ID INT NOT NULL,
    Designation TEXT NOT NULL,
    Join_Date DATE NOT NULL,
    PRIMARY KEY (Student_ID, Club_ID),
    FOREIGN KEY (Student_ID) REFERENCES Students(Student_ID) ON DELETE CASCADE,
    FOREIGN KEY (Club_ID) REFERENCES Club(Club_ID) ON DELETE CASCADE,
    CONSTRAINT chk_Designation CHECK (Designation IN ('General Member', 'Volunteer', 'Executive', 'Advisor'))
);

CREATE TABLE Equipments (
    Equip_ID INT AUTO_INCREMENT PRIMARY KEY,   -- ← AUTO_INCREMENT added
    Name TEXT NOT NULL,
    Type TEXT NOT NULL,
    Status TEXT NOT NULL DEFAULT 'Available',
    Owner_Club_ID INT NOT NULL,
    Purchase_Date DATE,
    FOREIGN KEY (Owner_Club_ID) REFERENCES Club(Club_ID) ON DELETE RESTRICT,
    CONSTRAINT chk_status CHECK (Status IN ('Available', 'In-Use', 'Damaged', 'Maintenance')),
    CONSTRAINT chk_equip_type CHECK (Type IN ('Camera', 'Projector', 'Microphone', 'Laptop', 'Speaker', 'Other'))
);

CREATE TABLE Maintenance_Log (
    Equip_ID INT NOT NULL,
    Log_ID INT NOT NULL,
    Date DATE NOT NULL,
    Description TEXT NOT NULL,
    Cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (Equip_ID, Log_ID),
    FOREIGN KEY (Equip_ID) REFERENCES Equipments(Equip_ID) ON DELETE CASCADE,
    CONSTRAINT chk_cost CHECK (Cost >= 0)
);

CREATE TABLE Events (
    Event_ID INT PRIMARY KEY,
    Title VARCHAR(200) NOT NULL,
    Date DATE NOT NULL,
    Venue VARCHAR(200) NOT NULL,
    Primary_Club_ID INT NOT NULL,
    Description TEXT,
    FOREIGN KEY (Primary_Club_ID) REFERENCES Club(Club_ID) ON DELETE RESTRICT,
    CONSTRAINT chk_title CHECK (LENGTH(Title) > 0)
);

CREATE TABLE Need (
    Equip_ID INT NOT NULL,
    Event_ID INT NOT NULL,
    Borrow_Time DATETIME NOT NULL,
    Return_Time DATETIME NOT NULL,
    Status VARCHAR(20) NOT NULL DEFAULT 'Confirmed',
    PRIMARY KEY (Equip_ID, Event_ID),
    FOREIGN KEY (Equip_ID) REFERENCES Equipments(Equip_ID) ON DELETE CASCADE,
    FOREIGN KEY (Event_ID) REFERENCES Events(Event_ID) ON DELETE CASCADE,
    CONSTRAINT chk_booking_time CHECK (Return_Time > Borrow_Time),
    CONSTRAINT chk_booking_status CHECK (Status IN ('Confirmed', 'Completed', 'Cancelled'))
);

CREATE TABLE Volunteer (
    Student_ID INT NOT NULL,
    Event_ID INT NOT NULL,
    Role TEXT NOT NULL,
    Hours_Worked DECIMAL(5, 2) NOT NULL,
    PRIMARY KEY (Student_ID, Event_ID),
    FOREIGN KEY (Student_ID) REFERENCES Students(Student_ID) ON DELETE CASCADE,
    FOREIGN KEY (Event_ID) REFERENCES Events(Event_ID) ON DELETE CASCADE,
    CONSTRAINT chk_hours CHECK (Hours_Worked > 0 AND Hours_Worked <= 24),
    CONSTRAINT chk_Role_length CHECK (LENGTH(Role) > 0)
);

CREATE TABLE Badge (
    Badge_ID INT PRIMARY KEY,
    Name TEXT NOT NULL UNIQUE,
    Tier TEXT NOT NULL,
    Description TEXT NOT NULL,
    Hours_Required DECIMAL(5, 2) NOT NULL,
    CONSTRAINT chk_tier CHECK (Tier IN ('Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond')),
    CONSTRAINT chk_hours_required CHECK (Hours_Required >= 0)
);

CREATE TABLE Earn (
    Student_ID INT NOT NULL,
    Badge_ID INT NOT NULL,
    Earned_Date DATE NOT NULL,
    Total_Hours DECIMAL(6, 2) NOT NULL,
    PRIMARY KEY (Student_ID, Badge_ID),
    FOREIGN KEY (Student_ID) REFERENCES Students(Student_ID) ON DELETE CASCADE,
    FOREIGN KEY (Badge_ID) REFERENCES Badge(Badge_ID) ON DELETE CASCADE,
    CONSTRAINT chk_total_hours CHECK (Total_Hours >= 0)
);

-- ============================================
-- DEMO DATA
-- ============================================

-- Student Demo Account
INSERT INTO Students (Student_ID, Name, Email, Password, Street, Sub_district, District)
VALUES (
    1,
    'Demo Student',
    'student@example.edu',
    'student123',
    '123 University Ave',
    'Dhanmondi',
    'Dhaka'
);

INSERT INTO Students_contact (Student_ID, Phone_Number)
VALUES (1, '01234567890');

-- Admin Demo Account (Executive/Advisor)
INSERT INTO Students (Student_ID, Name, Email, Password, Street, Sub_district, District)
VALUES (
    2,
    'Demo Admin',
    'admin@example.edu',
    'admin123',
    '456 Admin Road',
    'Banani',
    'Dhaka'
);

INSERT INTO Students_contact (Student_ID, Phone_Number)
VALUES (2, '09876543210');

-- Create a Demo Club
INSERT INTO Club (Club_ID, Name, Department, Office_Room)
VALUES (1, 'Demo Club', 'Computer Science', 'Room 101');

-- Admin as Advisor of Demo Club
INSERT INTO Joine (Student_ID, Club_ID, Designation, Join_Date)
VALUES (2, 1, 'Advisor', CURDATE());

-- Student as General Member of Demo Club
INSERT INTO Joine (Student_ID, Club_ID, Designation, Join_Date)
VALUES (1, 1, 'General Member', CURDATE());

-- Club Email for Demo Club
INSERT INTO Club_Emails (Club_ID, Email)
VALUES (1, 'democlub@example.edu');

-- Sample Badges
INSERT INTO Badge (Badge_ID, Name, Tier, Description, Hours_Required)
VALUES 
(1, 'Bronze Helper', 'Bronze', 'Completed 10 volunteer hours', 10.00),
(2, 'Silver Helper', 'Silver', 'Completed 25 volunteer hours', 25.00),
(3, 'Gold Helper', 'Gold', 'Completed 50 volunteer hours', 50.00),
(4, 'Platinum Helper', 'Platinum', 'Completed 100 volunteer hours', 100.00),
(5, 'Diamond Helper', 'Diamond', 'Completed 200 volunteer hours', 200.00);

-- Sample Events
INSERT INTO Events (Event_ID, Title, Date, Venue, Primary_Club_ID, Description)
VALUES 
(1, 'Demo Club Meeting', CURDATE() + INTERVAL 14 DAY, 'Room 101', 1, 'First meeting of the semester'),
(2, 'Tech Workshop', CURDATE() + INTERVAL 28 DAY, 'Auditorium', 1, 'Hands-on workshop on web development');