<?php 
    session_start(); 
    include("db.php");

    //will handle if a user isn't logged in 
    if(!isset($_SESSION['user'])){
        header("Location: /views/loginpage.php");
        exit;
    };

    $db = new DB();
    $conn = $db->getConnection(); 

    date_default_timezone_set('America/New_York');

    // 1. DEFINE "TODAY" VARIABLES 
    $todayTimestamp = time();
    $currentTimestamp = time();
    $todayDay   = (int)date('j'); 
    $todayMonth = (int)date('n'); 
    $todayYear  = (int)date('Y');

    // 2. GET CALENDAR INPUTS
    $month = isset($_GET['month']) ? (int)$_GET['month'] : $todayMonth; 
    $year = isset($_GET['year']) ? (int)$_GET['year'] : $todayYear;
    if (isset($_GET['day'])) {
        // User explicitly clicked a day
        $selectedDay = (int)$_GET['day'];
    } elseif (isset($_GET['month']) || isset($_GET['year'])) {
        // User clicked 'Next' or 'Prev' month, but no day is in URL
        $selectedDay = 1;
    } else {
        // Fresh page load (First time visiting)
        $selectedDay = $todayDay;
    }
    $selectedTime = $_GET['time'] ?? null;
    $rawTimeStart = isset($_GET['timeStart']) ? strtotime($_GET['timeStart']) : time();

    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }

    $nextMonth = $month + 1;
    $nextYear = $year;
    if ($nextMonth > 12) {
        $nextMonth = 1;
        $nextYear++;
    }

    // 1. Determine which week to show in the chart
    // If a day is selected, use that. Otherwise, use today.
    $referenceDate = ($selectedDay) 
        ? "$year-$month-$selectedDay" 
        : date("Y-m-d");

    // 2. Calculate Sunday of that week (Always available for the chart)
    $refTimestamp = strtotime($referenceDate);
    $dayOfWeek = (int)date('w', $refTimestamp);
    $startOfWeekTimestamp = strtotime("-$dayOfWeek days", $refTimestamp);
    $sundayDate = date("Y-m-d", $startOfWeekTimestamp);

    // 3. Define the lookup key for summaries
    $weeklyLookupKey = $sundayDate . "T00:00:00.000-04:00";

    // 1. Month Status for Calendar Highlighting
    $isCurrentMonth = ($month == $todayMonth && $year == $todayYear);
    $isFutureMonth  = ($year > $todayYear) || ($year == $todayYear && $month > $todayMonth);

    // 2. Format the Display Header (e.g., "April 7, 2026 12:00 PM")
    $selectedDateTimeFormatted = "No selection";

    if ($selectedDay && $selectedTime) {
        // Create a timestamp from the selection parts
        $headerTimestamp = strtotime("$year-$month-$selectedDay $selectedTime");
        if ($headerTimestamp) {
            $selectedDateTimeFormatted = date("F j, Y g:i A", $headerTimestamp);
        }
    }

    // 3. Create the Database Lookup Format (YYYY-MM-DD_HH-ii-ss)
    $selectedTimestamp = null;
    if ($selectedDay && $selectedTime) {
        $selectedTimestamp = date("Y-m-d_H-i-s", strtotime("$year-$month-$selectedDay $selectedTime"));
    }

    // 3. GENERATE THE TIME SLOTS
    // Snap to the top of the hour
    $timeStart = mktime(
        date('H', $rawTimeStart), 0, 0, 
        date('n', $rawTimeStart), date('j', $rawTimeStart), date('Y', $rawTimeStart)
    );

    $times = [];
    $startTime = $timeStart; 
    $endTime = strtotime('+1 hour', $startTime);

    for ($t = $startTime; $t <= $endTime; $t = strtotime('+10 minutes', $t)) {
        $times[] = $t;
    }

    // 4. AUTO-SELECT FIRST SLOT
    // Now $selectedTime is guaranteed for the query
    if ($selectedDay && !$selectedTime && !empty($times)) {
        $selectedTime = date('g:i A', $times[0]);
    }

    // 5. SET UP NAVIGATION & TODAY DETECTION
    $isTodaySelected = ($selectedDay == $todayDay && $month == $todayMonth && $year == $todayYear);

    $prevTimeStart = strtotime("-1 hour", $timeStart);
    $nextTimeStart = strtotime("+1 hour", $timeStart);
    $canGoNext = !($isTodaySelected && $nextTimeStart > time());
    $canGoPrev = true;

    // 6. CALENDAR MATH (for rendering the grid)
    if ($month < 1) { $month = 12; $year--; } 
    if ($month > 12) { $month = 1; $year++; }
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $monthName = date('F', $firstDay); 
    $totalDays = date('t', $firstDay); 
    $startDay = date('w', $firstDay);

    // --- DB INTEGRATION DEFAULTS ---
    $hydration = "N/A";
    $confidence = "N/A";
    $currentFilePath = "";
    $currentSpecies = "N/A";
    $currentAnalysis = "No analysis available for this selection.";
    $currentRec = "No recommendation available.";
    $dailySummaryText = "No summary available.";
    $weeklySummaryText = "No weekly summary available.";
    $hydrationLevel = "N/A";
    $dimmed = '#2f3b40';
    $c1 = $c2 = $c3 = $c4 = $dimmed;

    if ($selectedDay && $selectedTime && isset($_SESSION['email'])) {

        $email = $_SESSION['email'];

        $rawTime = strtotime("$year-$month-$selectedDay $selectedTime");

        $minutes = date('i', $rawTime);
        $roundedMinutes = floor($minutes / 10) * 10;

        $roundedTimestamp = date(
            "Y-m-d_H-i-s",
            mktime(
                date('H', $rawTime),
                $roundedMinutes,
                0,
                date('n', $rawTime),
                date('j', $rawTime),
                date('Y', $rawTime)
            )
        );

        $query = "
            SELECT 
            FileName, FilePath, HydrationStatus, ConfidenceScore, FlowerSpecies FROM Picture;
        ";     
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // 1. Remove .jpg extension
            $fileTimestampRaw = str_replace('.jpg', '', $row['FileName']);
            
            // 2. Explicitly parse the specific format: YYYY-MM-DD_HH-II-SS
            $dateObj = date_create_from_format("Y-m-d_H-i-s", $fileTimestampRaw);

            if ($dateObj) {
                $H = $dateObj->format('H');
                $i = (int)$dateObj->format('i');
                $roundedI = floor($i / 10) * 10;
                
                // 3. Rebuild the normalized string to match your $roundedTimestamp
                // Format: Y-m-d_H-i-00 (Notice we force 00 at the end)
                $normalizedFileTime = $dateObj->format('Y-m-d') . "_" . $H . "-" . str_pad($roundedI, 2, "0", STR_PAD_LEFT) . "-00";

                // 4. Comparison
                if ($normalizedFileTime === $roundedTimestamp) {
                    $analysisQuery = "SELECT a.Analysis, a.Recommendation 
                      FROM ImageAnalysis a 
                      WHERE a.FileName = ?";
    
                    $stmtAnalysis = $conn->prepare($analysisQuery);
                    $stmtAnalysis->bind_param("s", $row['FileName']);
                    $stmtAnalysis->execute();
                    $analysisResult = $stmtAnalysis->get_result();
                    $analysisRow = $analysisResult->fetch_assoc();

                    // Store the data in variables for the HTML below
                    $currentAnalysis = $analysisRow['Analysis'] ?? "No analysis available.";
                    $currentRec = $analysisRow['Recommendation'] ?? "No recommendation available.";
                    
                    $hydration = $row['HydrationStatus'];
                    $confidence = $row['ConfidenceScore'];
                    $currentFilePath = ".." . $row['FilePath'];
                    $currentSpecies = $row['FlowerSpecies'];

                    // Map the string status to a numerical level
                    $levels = [
                        'healthy'  => 4,
                        'mild'     => 3,
                        'moderate' => 2,
                        'severe'   => 1
                    ];

                    $status = $row['HydrationStatus'];

                    // Assign the level, defaulting to "N/A" if the status isn't in our list
                    $hydrationLevel = $levels[strtolower($hydration)] ?? "N/A";

                    break; 
                }
            }
        }
        
        if ($hydration === "N/A") {
            echo "No exact timestamp match: " . $selectedTimestamp;
        }

        // ---  Daily Summary Logic ---
            // 1. Get the date (YYYY-MM-DD) from the raw selected time
            $currentDateOnly = date("Y-m-d", $rawTime);
            
            // 2. Build the ISO string to match your Timestamp column format
            $isoTimestamp = $currentDateOnly . "T00:00:00.000-04:00";

            // 3. Query the dailysummary table
            $summaryQuery = "SELECT Summary FROM DailySummary WHERE Timestamp = ?";
            $stmtSum = $conn->prepare($summaryQuery);
            $stmtSum->bind_param("s", $isoTimestamp);
            $stmtSum->execute();
            $sumResult = $stmtSum->get_result();
            $sumRow = $sumResult->fetch_assoc();

            $dailySummaryText = $sumRow['Summary'] ?? "No summary available for this date.";

        // --- Weekly Summary Logic ---
            // 1. Ensure $rawTime is definitely using the user's selected date
            $currentDate = date("Y-m-d", $rawTime); 

            // 2 & 3. Explicitly find "this week's Sunday"
            $dateTime = new DateTime($currentDate);
            // If today is Sunday, 'this week' stays Sunday. 
            // Otherwise, it finds the Sunday immediately preceding this date.
            if ($dateTime->format('w') != 0) {
                $dateTime->modify('last Sunday');
            }
            $startOfWeekTimestamp = $dateTime->getTimestamp();
            $sundayDate = $dateTime->format('Y-m-d');

            // 4. Build the ISO string for the database
            $weeklyLookupKey = $sundayDate . "T00:00:00.000-04:00";

            $weeklyQuery = "SELECT Summary FROM WeeklySummary WHERE Timestamp = ?";
            $stmtWeek = $conn->prepare($weeklyQuery);
            $stmtWeek->bind_param("s", $weeklyLookupKey);
            $stmtWeek->execute();
            $weekResult = $stmtWeek->get_result();
            $weekRow = $weekResult->fetch_assoc();

            $weeklySummaryText = $weekRow['Summary'] ?? "No weekly summary available for the week starting " . date("M d", $startOfWeekTimestamp);
        
    // --- Dynamic Flower Level Graphic Logic ---
        if (is_numeric($hydrationLevel)) {
            switch ($hydrationLevel) {
                case 4: // All green (healthy)
                    $c1 = $c2 = $c3 = $c4 = '#4caf50';
                    break;

                case 3: // Top left dimmed, 3 yellow (mild)
                    $c2 = $dimmed;  // Visual Top-Left dimmed
                    $c1 = $c3 = $c4 = '#A88A13'; // Yellow
                    break;

                case 2: // Only right two orange, left dimmed (moderate)
                    $c3 = $c2 = $dimmed; // Left sides dimmed
                    $c4 = $c1 = '#C46200'; // Right sides orange
                    break;

                case 1: // Top left red, rest dimmed (severe)
                    $c1 = '#BA3134'; // Visual Top-Left red
                    $c4 = $c3 = $c2 = $dimmed; // Rest dimmed
                    break;
            }
        }
    }

    $ringGradient = "
        conic-gradient(
            from -173deg,
            $c4 0deg 75deg,     transparent 75deg 90deg,   /* Segment 1 */
            $c1 90deg 165deg,   transparent 165deg 180deg, /* Segment 2 */
            $c2 180deg 255deg,  transparent 255deg 270deg, /* Segment 3 */
            $c3 270deg 345deg,  transparent 345deg 360deg  /* Segment 4 */
        );
    ";

    // --- weekly chart ---
    $weeklyData = [];
    $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    // Mapping for the database strings to numbers
    $statusMap = ['healthy' => 4, 'mild' => 3, 'moderate' => 2, 'severe' => 1];

    foreach ($daysOfWeek as $index => $dayName) {
        // Calculate the specific date for this day of the week
        $dateForDay = date('Y-m-d', strtotime("$sundayDate +$index days"));
        
        // Query average hydration for that specific day
        // We use LIKE to match the date part of the FileName or we can use the Timestamp if available
        $query = "SELECT HydrationStatus FROM Picture WHERE FileName LIKE ?";
        $dateParam = $dateForDay . "%";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $dateParam);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $totalLevel = 0;
        $count = 0;
        
        while ($row = $res->fetch_assoc()) {
            $status = strtolower($row['HydrationStatus']);
            if (isset($statusMap[$status])) {
                $totalLevel += $statusMap[$status];
                $count++;
            }
        }
        
        // Average for the day (default to 0 if no data)
        $weeklyData[] = ($count > 0) ? round($totalLevel / $count, 1) : 0;
    }

    // Convert PHP array to JSON for JavaScript use
    $jsWeeklyData = json_encode($weeklyData);

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">   

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com" rel="stylesheet">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com">

        <link rel="stylesheet" href="../css/bootstrap-5.3.8-dist/css/bootstrap.css" /> 
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> 
        <link rel="stylesheet" href="../css/styles.css" />
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <title>Beijing Flower Inc.</title>

        <style>
            /* This dynamically overrides the static CSS based on your PHP variable */
            .ring {
                background: <?php echo $ringGradient; ?> !important;
            }
        </style>

    </head>
    <body style="margin:0; padding:0; overflow-x:hidden;">

        <?php include '../includes/header.php'; ?>

        <div class="container-fluid p-0 m-0 overflow-hidden">
            <div class="dashboard-grid">
            
                <!-- calendar -->
                <div class="grid-calendar">
            
                    <div class="bg-secondary border border-3 primary-border rounded-3 px-0">
                        <div class="d-flex justify-content-between align-items-center mb-3 bg-primary rounded-top">
                        <!-- previous month -->
                        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>"
                            class="btn border-0 btn-months-left p-2 pt-1 fs-5 rounded-0">
                            &laquo;
                        </a>
                            <p class="text-center mb-0 fs-6 fw-bold"><?php echo "$monthName $year"; ?></p>
                        <!-- next month -->
                        <?php if ($isCurrentMonth): ?>
                            <span class="btn btn-months-right disabled-month p-2 pt-1 fs-5 rounded-0">&raquo;</span>
                        <?php else: ?>
                            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>"
                            class="btn border-0 btn-months-right p-2 pt-1 fs-5 rounded-0">
                            &raquo;
                            </a>
                        <?php endif; ?>                           </div>
                            <div class="row fw-bold text-center border-bottom pb-2 mx-1">
                                <div class="col">Sun</div>
                                <div class="col">Mon</div>
                                <div class="col">Tue</div>
                                <div class="col">Wed</div>
                                <div class="col">Thu</div>
                                <div class="col">Fri</div>
                                <div class="col">Sat</div>
                            </div>
                            <div class="row text-center g-0">
                                <?php
                                    $todayTimestamp = strtotime(date('Y-m-d'));
                                    for ($i = 0; $i < $startDay; $i++) {
                                        echo '<div class="col py-4 bg-none"></div>';
                                    }
                                    for ($day = 1; $day <= $totalDays; $day++) {
                                        $currentDate = strtotime("$year-$month-$day");
                                        $isFuture = ($currentDate > $todayTimestamp);
                                        $isToday = ($day == $todayDay && $month == $todayMonth && $year == $todayYear);
                                        $isSelected = ($selectedDay == $day);
                                        echo '<div class="col py-2">';
                                        if ($isFuture) {
                                            // disabled day
                                            echo '<div class="calendar-day text-center disabled-day">';
                                            echo $day;
                                            echo '</div>';
                                        } else {
                                            // clickable day
                                            echo '<a href="?month='.$month.'&year='.$year.'&day='.$day.'" class="text-decoration-none">';
                                            echo '<div class="calendar-day text-center '
                                                .($isToday ? 'today has-tooltip' : '').' '
                                                .($isSelected ? 'selected' : '').'">';
                                            echo $day;
                                            echo '</div>';
                                            echo '</a>';
                                        }
                                        echo '</div>';
                                        if (($day + $startDay) % 7 == 0) {
                                            echo '</div><div class="row text-center mx-1">';
                                        }
                                    }
                                    $remaining = (7 - (($totalDays + $startDay) % 7)) % 7;
                                    for ($i = 0; $i < $remaining; $i++) {
                                        echo '<div class="col py-4 bg-none"></div>';
                                    }
                                ?>
                            </div>
                            <div class="d-flex justify-content-between bg-primary w-100 px-3 py-2 mt-2 rounded-bottom flex-wrap">
            
            
                            <!-- Time Buttons -->
            
                                <!-- PREV -->
            
                                <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&day=<?php echo $selectedDay; ?>&timeStart=<?php echo date('Y-m-d H:i:s', $prevTimeStart); ?>"
                                    class="btn btn-sm btn-time-nav">&laquo;</a>
            
                                <?php
                                    foreach ($times as $timeStamp) {
                                        $timeFormatted = date('g:i A', $timeStamp);
                                        $isSelectedTime = ($selectedTime === $timeFormatted);
                                        // Disable if the specific slot is in the future
                                        $isFutureTime = $isTodaySelected && ($timeStamp > $currentTimestamp);
                                        if ($isFutureTime) {
                                            // Render as a greyed-out button with no link
                                            echo '<button class="btn btn-time-select disabled-time" disabled>'.$timeFormatted.'</button>';
                                        } else {
                                            // Render as a clickable link
                                            echo '<a href="?month='.$month.'&year='.$year.'&day='.$selectedDay.'&time='.$timeFormatted.'&timeStart='.date('Y-m-d H:i:s', $timeStart).'" class="text-decoration-none">';
                                            echo '<button class="btn btn-time-select '.($isSelectedTime ? 'active-time' : '').'">'.$timeFormatted.'</button>';
                                            echo '</a>';
                                        }
                                    }
                                ?>
                                <!-- NEXT -->
                                <?php if ($canGoNext): ?>
                                    <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&day=<?php echo $selectedDay; ?>&timeStart=<?php echo date('Y-m-d H:i:s', $nextTimeStart); ?>"
                                    class="btn btn-sm btn-time-nav">&raquo;</a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-time-nav disabled-time">&raquo;</span>
                                <?php endif; ?>
            
                            </div>
                    </div>
            
                </div>
                <!-- selected flower  -->
                <div class="grid-flower">
                    <div class="container-lg bg-secondary border border-3 primary-border rounded-3 text-center h-100 d-flex flex-column px-0">
                        <div class="d-flex flex-column justify-content-between flex-grow-1 h-100">
                            <p class="text-center fw-bold fs-6 bg-tertiary p-2 rounded-top">Selected Flower</p>
            
                            <div class="w-100 d-flex flex-column flex-md-row gap-3 px-3 pb-3">
                                <div class="border border-3 tertiary-border rounded-3 p-3 w-100 bg-primary">
                                    <p class="fs-small text-start">Flower Status</p>
                                    <div class="d-flex justify-content-end align-items-end">
                                        <p class="fs-5"><?php echo ucfirst($hydration ?? "N/A")?></p>
                                    </div>
                                </div>
                                <div class="border border-3 tertiary-border rounded-3 p-3 w-100 bg-primary">
                                    <p class="fs-small text-start">Hydration Level</p>
                                    <div class="d-flex justify-content-end align-items-end">
                                        <p class="fs-5"><?php echo htmlspecialchars($hydrationLevel ?? "N/A")?></p>
                                    </div>
                                </div>
                                <div class="border border-3 tertiary-border rounded-3 p-3 w-100 bg-primary">
                                    <p class="fs-small text-start">AI Certainty</p>
                                    <div class="d-flex justify-content-end align-items-end">
                                        <p class="fs-5"><?php echo htmlspecialchars($confidence ?? "N/A")?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-grow-1 px-3">
                                <div class="d-flex flex-column bg-primary border border-3 tertiary-border rounded-3 p-3 w-100 h-100 justify-content-between gap-5 align-items-center">
                                    <div class="flex-grow-1 d-flex justify-content-center align-items-center">
                                        <div class="ring-wrapper">
                                            <div class="ring"></div>
                                            <div class="ring-center">
                                                <svg width="124" height="100" viewBox="0 0 124 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M61.6667 97.1667C61.6667 64.625 35.0417 38 2.5 38C2.5 70.5417 29.125 97.1667 61.6667 97.1667Z" stroke="#AB5492" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M48.0583 27.9418C37.4083 16.1084 25.575 9.6001 25.575 9.6001C25.575 9.6001 20.8417 24.3918 22.6167 41.5501" stroke="#AB5492" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M79.4166 55.1583V49.8333C79.4166 23.8 61.6666 2.5 61.6666 2.5C61.6666 2.5 43.9166 23.8 43.9166 49.8333V55.1583" stroke="#AB5492" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M100.717 41.5501C102.492 24.3918 97.7584 9.6001 97.7584 9.6001C97.7584 9.6001 85.925 15.5168 75.275 27.9418" stroke="#AB5492" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M61.6666 97.1667C94.2083 97.1667 120.833 70.5417 120.833 38C88.2916 38 61.6666 64.625 61.6666 97.1667Z" stroke="#AB5492" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-secondary rounded p-2 w-100 d-none d-md-block">
                                        <p class="fw-bold">Recommended Action:</p>
                                        <p class="line-clamp-6"><?php echo $currentRec; ?></p>
                                    </div>
                                </div>
                                <div class="d-none d-md-flex flex-column gap-1 bg-primary border border-3 tertiary-border rounded-3 w-100">
                                    <p class="text-center fw-bold fs-6 bg-tertiary p-2 rounded-top"><?php echo $selectedDateTimeFormatted; ?></p>
                                    <div class="d-flex flex-column justify-content-around p-3 h-100 gap-4">
                                        <div class="border border-2 h-100">
                                            <img src="<?php echo $currentFilePath; ?>" alt="Analyzed Flower" class="img-fluid">
                                        </div>
                                        <div class="bg-secondary rounded p-2">
                                            <p class="fw-bold">Current Image AI Analysis:</p>
                                            <p class="line-clamp-6"><?php echo $currentAnalysis; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-3">
                                <button
                                    class="btn btn-tertiary w-100 mt-3 mb-3"
                                    data-bs-toggle="modal"
                                    data-bs-target="#flowerModal">
                                    See more
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- weekly chart  -->
                <div class="grid-chart">
            
                        <div class="bg-secondary border border-3 primary-border rounded-3 h-100 w-100 d-flex flex-column h-100">
                            <p class="text-center mb-0 fs-6 fw-bold bg-primary py-2 rounded-top">Weekly Data Chart</p>
                            <div class="chart-container flex-grow-1 p-3">
                                <canvas id="weeklyChart" class="p-3"></canvas>
                            </div>
                            <script>
                                const ctx = document.getElementById('weeklyChart').getContext('2d');
            
                                // Real data from PHP
                                const apiData = <?php echo $jsWeeklyData; ?>;
                                const weeklyChart = new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: ['Su', 'Mon', 'Tu', 'Wed', 'Th', 'Fri', 'Sa'],
                                        datasets: [{
                                            label: 'Hydration Level',
                                            data: apiData,
                                            fill: true,
                                            borderColor: '#ff4fae',
                                            backgroundColor: 'rgba(255, 79, 174, 0.15)',
                                            tension: 0.4,
                                            borderWidth: 3,
                                            pointRadius: 4, // Added small points to see data clearly
                                            pointBackgroundColor: '#ff4fae'
                                        }]
                                    },
                                    options: {
                                        devicePixelRatio: 2,
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: { display: false },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        const labels = ['', 'Severe', 'Moderate', 'Mild', 'Healthy'];
                                                        return ' Status: ' + labels[Math.round(context.raw)];
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            x: {
                                                ticks: { color: '#fff', font: { size: 15 } },
                                                grid: { display: false }
                                            },
                                            y: {
                                                min: 1, // Locked at 1
                                                max: 4, // Locked at 4
                                                ticks: {
                                                    color: '#fff',
                                                    stepSize: 1,
                                                    font: { size: 15 },
                                                    callback: function(value) {
                                                        // Map the numbers back to words on the axis
                                                        const labels = {4: 'Healthy', 3: 'Mild', 2: 'Mod', 1: 'Sev'};
                                                        return labels[value];
                                                    }
                                                },
                                                grid: { color: 'rgba(255,255,255,0.1)' }
                                            }
                                        }
                                    }
                                });
                            </script>
                        </div>
            
                </div>
                <!-- weekly summary  -->
                <div class="grid-weeksum">
            
                    <div class="bg-secondary border border-3 primary-border rounded-3">
                        <p class="text-center fw-bold fs-6 bg-primary p-2 rounded-top">Week of <?php echo date("F jS", $startOfWeekTimestamp); ?> Summary: </p>
                        <p class="p-3" style="font-size: 1.1rem; line-height: 1.7;"><?php echo $weeklySummaryText; ?></p>
                    </div>
            
                </div>
                <!-- daily summary and weather  -->
                <div class="grid-dailyweather">
            
                    <div class="row g-2 h-100 flex-grow-1">
                        <!-- daily summary  -->
                        <div class="col-12 col-md-6 d-flex">
                            <div class="bg-secondary border border-3 primary-border rounded-3 w-100 d-flex flex-column">
                                <p class="text-center fw-bold fs-6 bg-primary p-2 rounded-top mb-0">Overall Daily Summary</p>
            
                                <div class="p-3 d-flex flex-column h-100">
                                    <p class="line-clamp-3 mb-2" style="font-size: 1.0rem; line-height: 1.7;">
                                        <?php echo $dailySummaryText; ?>
                                    </p>
                                    <div class="mt-auto">
                                        <button type="button"
                                                class="btn btn-tertiary w-100 "
                                                data-bs-toggle="modal"
                                                data-bs-target="#summaryModal">
                                            See More
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- weather  -->
                        <div class="col-12 col-md-6 d-flex">
                            <div class="bg-secondary border border-3 primary-border weather-widget justify-content-between p-3 rounded-3 w-100 h-100">
                                <div class="d-flex justify-content-between align-items-center h-100">
                                    <div>
                                        <p class="fw-bold fs-5 m-0" id="weather-location">Loading...</p>
                                        <p class="fs-2 fw-bold m-0" id="weather-temp">--°</p>
                                        <p class="m-0" id="weather-status">Fetching weather...</p>
                                    </div>
                                    <i class="bi bi-geo-alt-fill fs-3 align-self-start pe-2 pt-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
            
                </div>
            </div>
        </div>

        <!-- SELECTED FLOWER MODAL -->
         <!-- Flower Details Modal -->
        <div class="modal fade" id="flowerModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered custom-mobile-modal">
                <div class="modal-content bg-secondary text-white border border-3 primary-border rounded-4">
                    
                    <div class="d-md-none w-100 d-flex justify-content-center pt-2">
                        <div style="width: 40px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px;"></div>
                    </div>

                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fs-6 fs-md-5 pe-4">
                            <span class="fw-bold text-white"><?php echo ucfirst($currentSpecies); ?></span>
                            <span class="d-block d-md-inline text-light small mt-1 mt-md-0">
                                <?= ($selectedDateTimeFormatted !== "No selection") ? $selectedDateTimeFormatted : "Select a date" ?>
                            </span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
                    </div>

                    <div class="modal-body p-3 p-md-4">
                        <div class="mb-3">
                            <div class="border border-2 rounded-3 overflow-hidden bg-dark shadow-sm">
                                <img src="<?php echo $currentFilePath; ?>" 
                                    alt="Analyzed Flower" 
                                    class="img-fluid w-100" 
                                    style="max-height: 35vh; object-fit: cover;">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="bg-primary p-2 p-md-3 rounded border tertiary-border border-opacity-50">
                                    <small class="text-tertiary d-block text-uppercase fw-bold" style="font-size: 0.6rem;">Hydration Status</small>
                                    <span class="fw-bold" style="font-size: 0.9rem;"><?php echo ucfirst($hydration)?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-primary p-2 p-md-3 rounded border tertiary-border border-opacity-50">
                                    <small class="text-tertiary d-block text-uppercase fw-bold" style="font-size: 0.6rem;">Hydration Level</small>
                                    <span class="fw-bold" style="font-size: 0.9rem;"><?php echo $hydrationLevel?></span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-2">
                            <div class="bg-primary p-3 rounded border tertiary-border border-opacity-25">
                                <strong class="text-tertiary d-block mb-1" style="font-size: 0.7rem; text-transform: uppercase;">AI Analysis</strong>
                                <p class="mb-0 text-light" style="font-size: 0.9rem; line-height: 1.5;"><?php echo $currentAnalysis; ?></p>
                            </div>

                            <div class="bg-primary p-3 rounded border tertiary-border border-opacity-25">
                                <strong class="text-tertiary d-block mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Recommended Action</strong>
                                <p class="mb-0 text-light" style="font-size: 0.9rem; line-height: 1.5;"><?php echo $currentRec; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="summaryModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered custom-mobile-modal">
                <div class="modal-content bg-secondary text-white border border-3 primary-border rounded-4">
                    
                    <div class="d-md-none w-100 d-flex justify-content-center pt-2">
                        <div style="width: 40px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px;"></div>
                    </div>

                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold text-white">Overall Daily Summary</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
                    </div>

                    <div class="modal-body p-3 p-md-4">
                        <div class="bg-primary p-3 p-md-4 rounded shadow-sm border tertiary-border border-opacity-25">
                            <p class="mb-0 text-light summary-text-mobile" style="line-height: 1.6; font-size: 1rem;">
                                <?php echo $dailySummaryText; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="../js/homepage.js" defer></script>
    </body>
</html>
