<?php
$env = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/.env');

class DB {
    private $conn;

    public function __construct() {
        global $env;

        $db_server = $env["DB_SERVER"];
        $db_user = $env["DB_USER"];
        $db_pass = $env["DB_PASS"];
        $db_name = $env["DB_NAME"];

        $this->conn = new mysqli($db_server, $db_user, $db_pass, $db_name);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    // Login function
    function userLogin ($username, $password) {
        $query = "SELECT * FROM Users WHERE Username = ? AND Passwd = SHA2(?, 256)";
        $stmt = $this->conn->prepare($query);

        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if($user){
            // Will store the user data in session 
            $_SESSION['user'] = $user;
            return $user;
        }
        return false;
    }

    public function getConnection() {
        return $this->conn;
    }

    // // Inserting Users
    function createAccount($name, $email, $userName, $password, $notification) {
        $query = "INSERT INTO Users (Name, Email, Username, Passwd, Notification) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        // siiss = String, String, String, String, & Integer
        $stmt->bind_param("ssssi", $name, $email, $userName, $password, $notification);
        
        // uncommented this
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            echo $stmt->error;
            $stmt->close();
            return false;
        }
    }

    // Updating Notification Preference
    function updateNotification($email, $notification) {
        $query = "UPDATE Users SET Notification = ? WHERE Email = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) return false;

        // is = Integer, String
        $stmt->bind_param("is", $notification, $email);
        $stmt->execute();

        if ($stmt->error) {
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    // Updating Username 
    function updateUsername($email, $newUsername){            
        $query = "UPDATE Users SET Username = ? WHERE Email = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) return false;

        // ss = String, String
        $stmt->bind_param("ss", $newUsername, $email);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return false;
        }
    }

    // Updating Password 
    function updatePassword($email, $currentPassword, $newPassword){
        // Lets make sure the current password is correct
        $query = "SELECT * FROM Users WHERE Email = ? AND Passwd = SHA2(?, 256)";
        $stmt = $this->conn->prepare($query);

        // ss = String, String
        $stmt->bind_param("ss", $email, $currentPassword);
        $stmt->execute();
        $result = $stmt->get_result();

        // Will only occur if $currentPassword is incorrect
        if ($result->num_rows !== 1) {
            return false; 
        }

        // If it's correct, $currentPassword will update to $newPassword
        $query = "UPDATE Users SET Passwd = ? WHERE Email = ?";
        $stmt = $this->conn->prepare($query);

        // ss = String, String
        $stmt->bind_param("ss", $newPassword, $email);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return false;
        }
    }

    // Updating Email 
    function updateEmail($currentEmail, $newEmail){
        $query = "UPDATE Users SET Email = ? WHERE Email = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) return false;

        // ss = String, String
        $stmt->bind_param("ss", $newEmail, $currentEmail);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return false;
        }
    }
}
?>