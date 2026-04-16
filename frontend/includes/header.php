<?php  
    // --- NOTIFICATION INTEGRATION ---

    // 1. Fetch notifications if the database connection exists
    $notifications = [];
    if (isset($conn)) {
        // Fetching last 8 rows
        $notifQuery = "SELECT Timestamp, Type FROM Notification ORDER BY Timestamp DESC LIMIT 8";
        $notifResult = $conn->query($notifQuery);

        if ($notifResult) {
            while ($row = $notifResult->fetch_assoc()) {
                // Map types to user-friendly messages
                switch ($row['Type']) {
                    case 'System-Camera':
                        $msg = "The AI Model is unable to analyze the image (darkness/blur).";
                        break;
                    case 'Hydration':
                        $msg = "Your flowers need immediate action!";
                        break;
                    case 'System-Agent':
                        $msg = "AI Agent internal error. Please contact support.";
                        break;
                    default:
                        $msg = "System status update available.";
                        break;
                }

                // Parsing the custom timestamp: 2026-03-21_22-27-53
                $raw = $row['Timestamp']; // The full string

                // Separate Date and Time
                $datePart = substr($raw, 0, 10); // 2026-03-21
                $timePart = str_replace('-', ':', substr($raw, 11)); // 22:27:53

                // Calculate days ago
                $today = new DateTime(date("Y-m-d")); // Today's date (midnight)
                $notifDate = new DateTime($datePart);  // Notification date (midnight)
                $interval = $today->diff($notifDate);
                $daysDiff = (int)$interval->format('%r%a'); // %r includes the minus sign if in past

                // Create the "Days Ago" label
                if ($daysDiff === 0) {
                    $daysLabel = "Today";
                } elseif ($daysDiff === -1) {
                    $daysLabel = "Yesterday";
                } else {
                    $daysLabel = abs($daysDiff) . " days ago";
                }

                $displayTime = date("g:i A", strtotime($timePart));

                $notifications[] = [
                    'message' => $msg,
                    'time'    => $displayTime,
                    'days'    => $daysLabel
                ];
            }
        }
    }
?>

<script>
    const savedTheme = localStorage.getItem("theme") || "dark";
    document.body.classList.add(savedTheme + "-mode");
</script>

<!-- /includes/header.php -->
<div class="container-fluid px-0">
    <div class="masthead d-flex align-items-center justify-content-between w-100 px-3">

        <!-- LEFT: Title -->
        <a href="homepage.php"
           class="masthead-title fs-5 fs-md-4 m-0 text-decoration-none text-reset text-start fw-bold">
            Beijing - Flower Inc
        </a>

        <!-- RIGHT -->
        <div class="d-flex align-items-center gap-2">

            <!-- ================= DESKTOP ================= -->
            <div class="d-none d-lg-flex align-items-center gap-3">

                <!-- MAIL DROPDOWN -->
                <div class="dropdown">
                    <span role="button"
                          data-bs-toggle="dropdown"
                          class="d-flex align-items-center gap-1">

                        <i class="bi bi-envelope fs-5 position-relative">
                            <?php if (!empty($notifications)): ?>
                                <span id="notification-dot"
                                      class="position-absolute translate-middle p-1 bg-danger rounded-circle"
                                      style="font-size: 0.5rem; top: 25%; left: 80%; display: none;">
                                </span>
                            <?php endif; ?>
                        </i>

                        <i class="bi bi-caret-down-fill fs-6"></i>
                    </span>

                    <!-- DROPDOWN CONTENT (UNCHANGED) -->
                    <div class="dropdown-menu dropdown-menu-end mail-dropdown shadow-lg"
                         style="min-width: 260px; max-width: 90vw; border: 1px solid #444;">

                        <div class="dropdown-header border-bottom border-secondary text-uppercase fw-bold"
                             style="font-size: 0.75rem;">
                            Recent Alerts
                        </div>

                        <?php if (empty($notifications)): ?>
                            <div class="dropdown-item text-muted text-center py-3">
                                No new notifications
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <div class="dropdown-item border-bottom border-secondary py-3 px-3"
                                     style="white-space: normal;">
                                    <div class="d-flex flex-column">
                                        <span class="text-white mb-1"
                                              style="font-size: 0.9rem; line-height: 1.3;">
                                            <?php echo $n['message']; ?>
                                        </span>
                                        <small class="text-info fw-bold"
                                               style="font-size: 0.75rem;">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo $n['days']; ?> • <?php echo $n['time']; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SETTINGS -->
                <a href="settings.php" class="text-decoration-none text-reset">
                    <i class="bi bi-gear fs-5"></i>
                </a>

                <!-- LOGOUT -->
                <form action="logout.php" method="POST">
                    <button class="btn-nav">
                        <i class="bi bi-box-arrow-right fs-5"></i>
                    </button>
                </form>

            </div>

            <!-- ================= MOBILE ================= -->
            <div class="dropdown d-lg-none">
                <button class="btn btn-outline-light p-2 hamburger-btn"
                        type="button"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        aria-expanded="false">
                        <i class="bi bi-list fs-4"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end shadow-lg mt-2"
                     style="min-width: 280px; background-color: #21292C; border: 1px solid #335867;">

                    <!-- MAIL -->
                    <div class="px-0">
                        <button class="dropdown-item d-flex justify-content-between align-items-center py-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#mobileNotifCollapse"
                                aria-expanded="false">
                            <span>
                                <i class="bi bi-envelope me-2"></i> Notifications
                                <?php if (!empty($notifications)): ?>
                                    <span id="mobile-notification-badge" class="badge bg-danger ms-1" style="font-size: 0.6rem;">NEW</span>
                                <?php endif; ?>
                            </span>
                            <i class="bi bi-chevron-down sub-menu-arrow"></i>
                        </button>

                        <div class="collapse" id="mobileNotifCollapse">
                            <div class="mobile-notif-inner border-bottom border-top border-secondary">
                                <?php if (empty($notifications)): ?>
                                    <div class="px-4 py-3 text-muted small">No new alerts</div>
                                <?php else: ?>
                                    <div class="notif-scroll-container" style="max-height: 300px; overflow-y: auto;">
                                        <?php foreach ($notifications as $n): ?>
                                            <div class="px-4 py-2 border-bottom border-secondary-subtle">
                                                <div class="text-white small mb-1"><?php echo $n['message']; ?></div>
                                                <div class="text-info" style="font-size: 0.7rem;">
                                                    <?php echo $n['days']; ?> • <?php echo $n['time']; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- SETTINGS -->
                    <a href="settings.php"
                       class="dropdown-item d-flex align-items-center py-2">
                        <i class="bi bi-gear me-2"></i> Settings
                    </a>

                    <div class="dropdown-divider border-secondary"></div>

                    <!-- LOGOUT -->
                    <form action="logout.php" method="POST" class="m-0">
                        <button class="dropdown-item d-flex align-items-center">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const dot = document.getElementById('notification-dot');
        const mobileBadge = document.getElementById('mobile-notification-badge');
        
        // Select containers by ID for stability
        const desktopMenu = document.getElementById('desktopMailDropdown');
        const mobileCollapse = document.getElementById('mobileNotifCollapse');

        const markAsSeen = () => {
            if (dot) dot.style.setProperty('display', 'none', 'important');
            if (mobileBadge) mobileBadge.style.setProperty('display', 'none', 'important');
            sessionStorage.setItem('notificationsSeen', 'true');
        };

        // 1. Check Session Storage immediately
        if (sessionStorage.getItem('notificationsSeen')) {
            if (dot) dot.style.display = 'none';
            if (mobileBadge) mobileBadge.style.display = 'none';
        }

        // 2. Desktop: Mark seen when the envelope dropdown opens
        if (desktopMenu) {
            desktopMenu.addEventListener('shown.bs.dropdown', markAsSeen);
        }

        // 3. Mobile: Mark seen ONLY when they actually expand the Notifications section
        if (mobileCollapse) {
            mobileCollapse.addEventListener('shown.bs.collapse', markAsSeen);
        }
    });
</script>