<?php
    session_start();
    include("db.php");
    
    if (isset($_POST["submit"])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $notification = isset($_POST['notification']) ? 1 : 0;

        $db = new DB(); 
        $user = $db->createAccount($name, $email, $username, $password, $notification);

        if ($user) {
            header("Location: /views/loginpage.php");
            exit;
        } else {
            echo "<script>alert('Please Try Again! Could not process the request');</script>";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beijing Flower Inc.</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">   
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="../css/bootstrap-5.3.8-dist/css/bootstrap.css" /> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> 
    <link rel="stylesheet" href="../css/styles.css" />
</head>
<body>
    <h1 class="TeamName">Beijing - Flower Inc.</h1>

    <div class="doubleLayers">
        <div class="outer-layer">
            <div class="inner-layer">
                <a href="/views/loginpage.php" class="back-link d-flex align-items-center pb-2">
                    ← Back to login
                </a>

                <h3 class="createAccount">Create an Account</h3>
                <form class="form" name="form" method="POST">
                    <div class="accountInput">
                        <div class="nameInput">
                            <input type="text" id="name" name="name" placeholder="Full Name">
                        </div>
                    
                        <div class="userInput">
                            <input type="text" id="username" name="username" placeholder="Username">
                            <input type="text" id="email" name="email" placeholder="Email">
                        </div>
                    
                        <div class ="passwordInput">
                            <div class="passwordIcon">
                                <input type="password" class="password" id="password" name="password" placeholder="Password">
                                <i class="bi bi-eye-slash toggle-password" data-target="password"></i>
                            </div>

                            <div class="passwordIcon">
                                <input type="password" class="passwordInfo" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password">
                                <i class="bi bi-eye-slash toggle-password" data-target="confirmPassword"></i>
                            </div>
                        </div>

                        <div class = "notificationCheck">
                            <input type="checkbox" name="notification" value="1"> Receive Notifications
                        </div>
                    </div>                    
                    <br>
                    <input type="submit" class="submit" id="submit" value="Create Account" name="submit" >
                </form>
            </div> 
        </div>
    </div>
    
</body>

<!-- This script will only be executed if a user clicks to see their password -->
<script>
document.querySelectorAll(".toggle-password").forEach(icon => {
    icon.addEventListener("click", function(){
        const input = document.getElementById(this.dataset.target);

        if(input.type === "password"){
            input.type = "text";
            this.classList.remove("bi-eye-slash");
            this.classList.add("bi-eye");
        }else{
            input.type = "password";
            this.classList.remove("bi-eye");
            this.classList.add("bi-eye-slash");
        }

    });
});
</script>


</html>