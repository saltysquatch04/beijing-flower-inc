<!-- 
    This page is where we'll handle deleting the session data, global varriables, 
    and session cookies only if a user hits the logout button 
-->

<?php
    session_start();

    // will frees all session variables currently registered
    session_unset();

    // destroys all of the data associated with the current session 
    session_destroy();

    // will redirect the user back to the login page
    header("Location: /views/loginpage.php");
    exit();
?>

<!--
    At some point we'll need to unset the session cookie and any global variables 
    assoicated with the session name 
-->