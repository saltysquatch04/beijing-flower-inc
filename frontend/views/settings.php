<?php
    session_start();
    include("db.php");

    // Will handle if a user isn't logged in 
    if(!isset($_SESSION['user'])){
        header("Location: /views/loginpage.php");
        exit;
    };

    // Will grab the information we need for the setting page 
    $userName = $_SESSION['user']['Name'];
    $userEmail = $_SESSION['user']['Email'];
    // Will grab the first letter of there name 
    $userInitial = strtoupper($userName[0]);

    $message = "";
    $db = new DB();

    // Handles Notification Update
    if (isset($_POST['updateNotification'])) {
        $notification = isset($_POST['notification']) ? 1 : 0;

        if ($db->updateNotification($userEmail, $notification)) {
            $_SESSION['user']['Notification'] = $notification;
            $message = "Notification preference updated!";
        } else {
            $message = "Could not update notification preference.";
        }
    }

    // Handles Username Update
    if (isset($_POST['updateUsername'])) {
        $newUsername = htmlspecialchars(trim($_POST['newUsername']));

        if ($db->updateUsername($userEmail, $newUsername)) {
            $_SESSION['user']['Username'] = $newUsername;
            $message = "Username updated successfully!";
        } else {
            $message = "Could not update username. Please try again.";
        }
    }

    // Handles Password Update
    if (isset($_POST['updatePassword'])) {
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];

        if ($db->updatePassword($userEmail, $currentPassword, $newPassword)) {
            $message = "Password updated successfully!";
        } else {
            $message = "Could not update password. Please try again.";
        }
    }

    // Handles Email Update
    if (isset($_POST['updateEmail'])) {
        $newEmail = htmlspecialchars(trim($_POST['newEmail']));

        if ($db->updateEmail($userEmail, $newEmail)) {
            $_SESSION['user']['Email'] = $newEmail;
            $userEmail = $newEmail;
            $message = "Email updated successfully!";
        } else {
            $message = "Could not update email. Please try again.";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com" rel="stylesheet">

    <link rel="stylesheet" href="../css/bootstrap-5.3.8-dist/css/bootstrap.css" /> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> 
    <link rel="stylesheet" href="../css/styles.css" />

    <title>Beijing Flowers Inc — Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com">

    <link rel="stylesheet" href="../css/bootstrap-5.3.8-dist/css/bootstrap.css" /> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> 
    <link rel="stylesheet" href="../css/styles.css" />
    <title>Beijing Flowers. Inc Settings</title>
</head>

<body>

<?php include '../includes/header.php'; ?>

<div class="d-flex px-3 py-2 mx-0 w-100 ">
    <div class="d-flex h-100 gap-2 mx-0 w-100">
        <div class="bg-secondary border border-3 primary-border rounded-3 w-100 p-3 pb-5 settings-outer" style="height: 700px;">

            <a href="../views/homepage.php" class="back-link d-flex align-items-center pb-2">
                ← Back to Dashboard
            </a>

            <div class="d-flex flex-column bg-border-color rounded mx-2 p-3 h-100">

                <div class="border-bottom border-dark">
                    <p class="mb-0 fw-bold text-dark">Settings</p>
                </div>

                <div class="d-flex row bg-secondary mx-2 p-3 mt-2 rounded h-100">

                    <!-- LEFT SIDE MENU -->
                    <div class="col-5">
                        <p class="fw-bold">Welcome to your profile</p>

                        <div class="d-flex align-items-center gap-3 mb-5">

                            <!-- Profile Circle ~ Only contains the user's first letter of their name -->
                            <div class="profile-avatar">
                                <?php echo $userInitial; ?>
                            </div>

                            <!-- Name + Email ~ Will grab and display them to the user -->
                            <div>
                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($userName); ?></p>
                                <p class="mb-0 fs-small text-secondary"><?php echo htmlspecialchars($userEmail); ?></p>
                            </div>

                        </div>

                        <div class="d-flex flex-column">
                            <div class="edit-item d-flex gap-2" data-target="#editNotificationPanel">
                                <i class="bi bi-bell"></i><p>Edit Notifications</p>
                            </div>

                            <div class="edit-item d-flex gap-2" data-target="#editUsernamePanel">
                                <i class="bi bi-pencil-square"></i><p>Edit Username</p>
                            </div>

                            <div class="edit-item d-flex gap-2 " data-target="#editPasswordPanel">
                                <i class="bi bi-pencil-square"></i><p>Edit Password</p>
                            </div>

                            <div class="edit-item d-flex gap-2 " data-target="#editEmailPanel">
                                <i class="bi bi-pencil-square"></i><p>Edit Email Address</p>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE PANELS -->
                    <div class="col-7">
                        <!-- Notification Panel -->
                        <div class="edit-panel bg-primary rounded p-3" id="editNotificationPanel">
                            <p class="fw-bold">Notification Preference</p>

                            <?php if ($message && isset($_POST['updateNotification'])): ?>
                                <div class="alert alert-success"><?php echo $message; ?></div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="activePanel" value="editNotificationPanel">

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="notification" id="notificationCheck"
                                        <?php echo ($_SESSION['user']['Notification'] == 1) ? 'checked' : ''; ?>>
                                    
                                    <label class="form-check-label" for="notificationCheck">
                                        Receive Notifications
                                    </label>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="updateNotification" class="btn btn-primary w-75">Save</button>
                                </div>
                            </form>
                        </div>

                        <!-- Username Panel -->
                        <div class="edit-panel bg-primary rounded p-3" id="editUsernamePanel">
                            <p class="fw-bold">Edit Username</p>
            
                            <?php if ($message && isset($_POST['updateUsername'])): ?>
                                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
                            <?php endif; ?>
            
                            <form method="POST">
                                <input type="hidden" name="activePanel" value="editUsernamePanel">
                                <input type="text" class="form-control mb-2 w-75" name="newUsername" placeholder="New Username" required>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="updateUsername" class="btn btn-primary w-75">Save</button>
                                    <button type="button" class="btn btn-secondary w-75">Close</button>
                                </div>
                            </form>
                        </div>

                        <!-- Password Panel -->
                        <div class="edit-panel bg-primary rounded p-3" id="editPasswordPanel">
                            <p class="fw-bold">Edit Password</p>

                            <?php if ($message && isset($_POST['updatePassword'])): ?>
                                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="activePanel" value="editPasswordPanel">
                                <input type="password" class="form-control mb-2 w-75" name="currentPassword" placeholder="Current Password" required>
                                <input type="password" class="form-control mb-3 w-75" name="newPassword" placeholder="New Password" required>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="updatePassword" class="btn btn-primary w-75">Save</button>
                                    <button type="button" class="btn btn-secondary w-75">Close</button>
                                </div>
                            </form>
                        </div>

                        <!-- Email Panel -->
                        <div class="edit-panel bg-primary rounded p-3" id="editEmailPanel">
                            <p class="fw-bold">Edit Email</p>

                            <?php if ($message && isset($_POST['updateEmail'])): ?>
                                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="activePanel" value="editEmailPanel">
                                <input type="email" class="form-control mb-2 w-75" name="newEmail" placeholder="New Email" required>
                               
                                <div class="d-flex gap-2">
                                    <button type="submit" name="updateEmail" class="btn btn-primary w-75">Save</button>
                                    <button type="button" class="btn btn-secondary w-75">Close</button>
                                </div>
                            </form>
                        </div>
                    </div> <!-- end col-8 -->

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../css/bootstrap-5.3.8-dist/js/bootstrap.bundle.js"></script>
<script src="../js/settings.js" defer></script>
<script>
    const activePanel = "<?php echo isset($_POST['activePanel']) ? $_POST['activePanel'] : 'editNotificationPanel'; ?>";
</script>

</body>
</html>
