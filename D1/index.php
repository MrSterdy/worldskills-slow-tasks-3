<?php

$date = $_GET['date'] ?? date('Y-m-d', time());

$dateTime = strtotime($date);

$nextDate = date('Y-m-d', strtotime("+ 1 month", $dateTime));
$previousDate = date('Y-m-d', strtotime("- 1 month", $dateTime));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <link rel="stylesheet" href="calendar.css">
</head>
<body>
    <div class="custom-calendar-wrap">
        <div class="custom-inner">
            <div class="custom-header clearfix">
                <nav>
                    <a href="?date=<?php echo $previousDate ?>" class="custom-btn custom-prev"></a>
                    <a href="?date=<?php echo $nextDate ?>" class="custom-btn custom-next"></a>
                </nav>
                <h2 id="custom-month" class="custom-month"><?php echo date('F', $dateTime) ?></h2>
                <h3 id="custom-year" class="custom-year"><?php echo date('Y', $dateTime) ?></h3>
            </div>
            <div id="calendar" class="fc-calendar-container">
                <div class="fc-calendar fc-five-rows">
                    <div class="fc-head">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>
                    <div class="fc-body">
                        <?php
                            $totalDays = intval(date('t', $dateTime));
                            $targetDate = intval(date('j', $dateTime));
                            $targetDay = intval(date('w', strtotime(date('Y', $dateTime) . '-' . date('m', $dateTime) . '-01')));
                            $startDate = 1;

                            for ($i = 0; $i < 5; $i++) {
                                echo "<div class='fc-row'>";

                                for ($j = 0; $j < 7; $j++) {
                                    $class = $startDate === $targetDate ? 'fc-today' : '';

                                    echo "<div class='$class'><span class='fc-date'>";

                                    if ($totalDays >= $startDate && ($i !== 0 || $j >= $targetDay)) {
                                        echo $startDate;
                                        $startDate++;
                                    }

                                    echo "</span></div>";
                                }

                                echo "</div>";
                            }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>