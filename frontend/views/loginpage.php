<?php
    session_start();
    include("db.php"); 

    if (isset($_POST["submit"])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $db = new DB(); 
        $user = $db->userLogin($username, $password);
        
        if ($user) {
            $_SESSION['email'] = $user['Email'];
            $_SESSION['username'] = $user['Username'];
            header("Location: /views/homepage.php");
            exit;
        } else {
            echo "<script>alert('Incorrect Login, Please Try Again!');</script>";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
            <img src="../logo/Beijing_Logo.png" alt="Our Tean Logo">

            <div class="inner-layer">
                <h3 class="greeting">Welcome!</h3>

                <form class="form" name="form" method="POST">
                    <div class="input">
                        <label for="Username">Username:</label>
                        <input type="text" id="username" name="username">
                    </div>

                    <div class="passwordIcon">
                        <label for="Password">Password:</label>
                        <input type="password" id="password" name="password">
                        <i class="bi bi-eye-slash toggle-password" data-target="password"></i>
                    </div>

                    <br>

                    <input type="submit"  class="submit" id="submit" value="Login" name="submit" >
                    <br>

                    <a href="/views/registerpage.php" class="Account">Create an account</a>
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