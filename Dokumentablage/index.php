<?php
session_start();

// Basisverzeichnis und aktuelles Verzeichnis
$baseDir = __DIR__ . '/uploads';
$message = '';

// Verzeichnis für gespeicherte Events
$eventsFile = 'events/events.json';
if (!file_exists($eventsFile)) {
    file_put_contents($eventsFile, json_encode([]));
}

// Aktuelles Verzeichnis
$currentDir = isset($_GET['path']) ? $baseDir . '/' . $_GET['path'] : $baseDir;
if (!file_exists($baseDir)) {
    mkdir($baseDir, 0777, true);
}

// Datei-Upload-Handler
if (isset($_FILES['fileToUpload'])) {

    $relativePath = $_POST['currentPath'] ?? '';
    $relativePath = str_replace(['..', '\\'], '', $relativePath);

    $uploadDir = $baseDir . ($relativePath ? '/' . $relativePath : '');

    $target_file = $uploadDir . '/' . basename($_FILES["fileToUpload"]["name"]);

    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        $message = "Datei '" . htmlspecialchars(basename($_FILES["fileToUpload"]["name"])) . "' erfolgreich hochgeladen.";
    } else {
        $message = "Fehler beim Hochladen der Datei.";
    }
}

// Ordner-Erstellungs-Handler
if (isset($_POST['folderName'])) {
    $folderName = basename($_POST['folderName']);
    $newDir = $currentDir . '/' . $folderName;
    if (!file_exists($newDir)) {
        mkdir($newDir, 0777, true);
        $message = "Ordner '$folderName' erfolgreich erstellt.";
    } else {
        $message = "Ordner '$folderName' existiert bereits.";
    }
}

// Events laden
$events = json_decode(file_get_contents($eventsFile), true);

// Neues Event speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['date'], $_POST['time'], $_POST['description'])) {
    $newEvent = [
        "date" => $_POST['date'],
        "time" => $_POST['time'],
        "description" => $_POST['description']
    ];
    $events[] = $newEvent;
    file_put_contents($eventsFile, json_encode($events));
}

// Tage des Monats berechnen
function getDaysInMonth($year, $month) {
    $days = [];
    $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    for ($day = 1; $day <= $lastDay; $day++) {
        $days[] = $day;
    }
    return $days;
}

// Monat und Jahr
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$daysInCurrentMonth = getDaysInMonth($currentYear, $currentMonth);

// Vorheriger & nächster Monat
$prevMonth = ($currentMonth == 1) ? 12 : $currentMonth - 1;
$prevYear = ($currentMonth == 1) ? $currentYear - 1 : $currentYear;

$nextMonth = ($currentMonth == 12) ? 1 : $currentMonth + 1;
$nextYear = ($currentMonth == 12) ? $currentYear + 1 : $currentYear;

$backPath = isset($_GET['path']) ? dirname($_GET['path']) : '';

// Sidebar-Funktion
function displayDirectory($path, $relativePath = '', $indent = 0) {
    $items = scandir($path);
    echo "<div class='folder-level'>";
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $itemPath = $path . '/' . $item;
        $itemRelPath = $relativePath ? "$relativePath/$item" : $item;
        $padding = $indent * 15;

        if (is_dir($itemPath)) {
            echo "
            <div class='sidebar-entry' style='padding-left: {$padding}px'>
                <span class='folder-icon'>📁</span>
                <a href='index.php?path=" . urlencode($itemRelPath) . "' class='folder-link'>$item</a>
                <div class='folder-children'>
            ";
            displayDirectory($itemPath, $itemRelPath, $indent + 1);
            echo "</div></div>";
        } else {
            echo "
            <div class='sidebar-entry file' style='padding-left: {$padding}px'>
                <span class='file-icon'>📄</span>
                <a href='uploads/$itemRelPath' target='_blank' class='file-link'>$item</a>
            </div>";
        }
    }
    echo "</div>";
}

// Events für Tag
function getEventsForDay($events, $date) {
    $eventsForDay = [];
    foreach ($events as $event) {
        if ($event['date'] == $date) {
            $eventsForDay[] = $event;
        }
    }
    return $eventsForDay;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dokumentablage</title>
<link rel="stylesheet" href="static/css/style.css">
<style>
body { display: flex; flex-direction: column; color: #00ff00; font-family: 'Orbitron', sans-serif; background-image: url('images/background.jpg'); background-repeat: no-repeat; background-size: cover; background-attachment: fixed; margin: 0; padding: 0; }

h1 { text-align: center; margin-top: 10px; color: #00ff00; }

/* Container für Schrift + Energiekreis */
.jarvis-container {
    position: relative;
    width: 100%;
    text-align: center;
    margin-top: 50px;
}

/* Energiekreis wie Iron-Man Reaktor */
.reactor-circle {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(0,255,0,0.3) 0%, rgba(0,255,0,0) 70%);
    box-shadow: 0 0 30px #00ff00, 0 0 60px #00ff00 inset;
    animation: rotateCircle 4s linear infinite;
    z-index: 1;
}

@keyframes rotateCircle {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Schrift + Animation */
.jarvis-holo {
    position: relative;
    z-index: 2;
    font-size: 60px;
    font-weight: bold;
    color: #00ff00;
    letter-spacing: 5px;
    text-shadow:
        0 0 8px #00ff00,
        0 0 20px #33ff33,
        0 0 40px #66ff66,
        0 0 60px #00ff00,
        0 0 80px #33ff33;
    animation: holoPulse 2s ease-in-out infinite,
               holoVibrate 0.1s infinite,
               holoGlitch 2.5s infinite;
}

/* Pulsieren */
@keyframes holoPulse {
    0% { text-shadow: 0 0 8px #00ff00, 0 0 20px #33ff33; }
    50% { text-shadow: 0 0 25px #66ff66, 0 0 45px #00ff00; }
    100% { text-shadow: 0 0 8px #00ff00, 0 0 20px #33ff33; }
}

/* Vibration */
@keyframes holoVibrate {
    0%,100% { transform: translateX(0); }
    25% { transform: translateX(1px); }
    50% { transform: translateX(-1px); }
    75% { transform: translateX(0.5px); }
}

/* Glitch */
@keyframes holoGlitch {
    0% { clip-path: inset(0 0 0 0); }
    10% { clip-path: inset(10% 0 80% 0); transform: skew(1deg); }
    22% { clip-path: inset(5% 0 65% 0); transform: skew(-1deg); }
    45% { clip-path: inset(0 0 0 0); transform: skew(0deg); }
    60% { clip-path: inset(15% 0 30% 0); transform: skew(2deg); }
    75% { clip-path: inset(0 0 0 0); }
    90% { clip-path: inset(8% 0 55% 0); }
    100% { clip-path: inset(0 0 0 0); }
}

/* Scanlinien */
.jarvis-holo::after {
    content: "";
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: linear-gradient(rgba(0,255,0,0) 0%, rgba(0,255,0,0.15) 50%, rgba(0,255,0,0) 100%);
    mix-blend-mode: screen;
    animation: scanline 2.5s linear infinite;
}

@keyframes scanline {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
}

.back-button {
    display: inline-block;
    padding: 10px 20px;
    background: rgba(0, 255, 0, 0.2);
    color: #00ff00;
    font-weight: bold;
    text-decoration: none;
    border: 1px solid #00ff00;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 0 5px #00ff00;
    text-align: center;
}

.back-button:hover {
    background: #00ff00;
    color: #000;
    box-shadow: 0 0 15px #00ff00, 0 0 20px #00ff00 inset;
    transform: translateY(-2px);
}

.clock { position: absolute; top: 10px; right: 20px; font-size: 36px; color: #00ff00; background: rgba(0,0,0,0.5); padding: 10px; border-radius: 8px; border: 1px solid #00ff00; text-shadow: 0 0 5px #00ff00; }

.sidebar { width: 30%; padding: 20px; background-color: rgba(0,0,0,0.55); height: calc(100vh - 40px); overflow-y: auto; border-right: 1px solid rgba(0,255,0,0.4); }

.sidebar-entry { margin: 4px 0; user-select: none; }
.sidebar-folder, .file { display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 6px; cursor: pointer; }
.sidebar-folder:hover, .file:hover { background: rgba(0,255,0,0.1); }
.folder-icon, .file-icon { font-size: 18px; }
.folder-link, .file-link { color: #b4ffb4; text-decoration: none; cursor: pointer; }
.folder-arrow { font-size: 10px; transition: transform .2s ease; opacity: .7; }
.folder-children { display: none; margin-left: 10px; }
.folder-open .folder-children { display: block; }
.folder-open .folder-arrow { transform: rotate(90deg); }

/* Kalender */
.calendar { position: absolute; top: 120px; right: 20px; background: rgba(0,0,0,0.6); border: 1px solid rgba(0,255,0,0.5); border-radius: 8px; color: #00ff00; padding: 10px; width: 260px; }
.calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
.day { padding: 5px; text-align: center; border: 1px solid rgba(0,255,0,0.5); border-radius: 4px; background: rgba(255,255,255,0.1); cursor: pointer; font-size: 0.9em; position: relative; }
.day.has-event { 
    background: rgba(0,255,0,0.15); /* optional leicht grün */
    border: 2px solid red;           /* rote Umrandung */
    box-shadow: 0 0 8px red, 0 0 12px red inset; /* optional: roter Glow */
}

.day:hover { background: rgba(0,255,0,0.2); }
.day-events { display: none; position: absolute; top: 100%; left: 0; background: rgba(0,0,0,0.9); border: 1px solid #00ff00; border-radius: 4px; padding: 5px; font-size: 0.8em; max-width: 220px; z-index: 10; }

.form-container { margin-top: 10px; }
.form-container input, .form-container textarea { width: 100%; padding: 10px; margin: 5px 0; background-color: rgba(255,255,255,0.1); border: 1px solid #00ff00; color: #00ff00; border-radius: 4px; }
.button, .back-button { border-radius: 8px; }

</style>
</head>
<body>

<div class="clock" id="clock"></div>
<div class="jarvis-container">
  <div class="reactor-circle"></div>
  <h1 id="jarvis-title" class="jarvis-holo">Dokumentablage</h1>
</div>

<div class="container">
<div class="sidebar">
<a href="index.php" class="back-button">Zurück zur Hauptseite</a>
<h3>Ordnerstruktur</h3>
<div class="folder-structure">
<?php displayDirectory($baseDir, '', 0); ?>
</div>

<h3>Datei hochladen</h3>
<form action="" method="post" enctype="multipart/form-data">
<input type="file" name="fileToUpload" required>
<input type="hidden" name="currentPath" value="<?= htmlspecialchars($_GET['path'] ?? '') ?>">
<button type="submit">Hochladen</button>
</form>

<h3>Neuen Ordner erstellen</h3>
<form action="" method="post">
<input type="text" name="folderName" placeholder="Ordnername" required>
<button type="submit">Ordner erstellen</button>
</form>
</div>

<div class="calendar">
<div class="calendar-header">
<a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>&path=<?= urlencode($backPath) ?>">&laquo;</a>
<div class="calendar-month"><?= date('F', strtotime("$currentYear-$currentMonth-01")) ?> <?= $currentYear ?></div>
<a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>&path=<?= urlencode($backPath) ?>">&raquo;</a>
</div>

<div class="calendar-days">
<?php
$weekDays = ['So','Mo','Di','Mi','Do','Fr','Sa'];
foreach($weekDays as $day) echo "<div>$day</div>";

// Wochentag des 1. Tages des Monats (0=So,6=Sa)
$firstDayOfMonth = date('w', strtotime("$currentYear-$currentMonth-01"));
for($i = 0; $i < $firstDayOfMonth; $i++) echo "<div class='day'></div>";

foreach($daysInCurrentMonth as $day) {
    // Monat und Tag immer zweistellig formatieren
    $dateStr = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    
    $eventsForDay = getEventsForDay($events, $dateStr);
    $eventClass = !empty($eventsForDay) ? 'has-event' : '';

    echo "<div class='day $eventClass' onclick='toggleEvents(this)'>$day";

    if(!empty($eventsForDay)) {
        echo "<div class='day-events'>";
        foreach($eventsForDay as $event) {
            echo htmlspecialchars($event['time']) . ": " . htmlspecialchars($event['description']) . "<br>";
        }
        echo "</div>";
    }
    echo "</div>";
}
?>
</div>

<div class="form-container">
<h3>Ereignisse hinzufügen</h3>
<form action="" method="post">
<input type="date" name="date" required>
<input type="time" name="time" required>
<textarea name="description" placeholder="Beschreibung" required></textarea>
<button type="submit">Ereignis speichern</button>
</form>
</div>
</div>
</div>

<script>
// Sidebar: Klick auf Ordnernamen klappt auf/zu
document.querySelectorAll('.folder-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();

        const entry = this.closest('.sidebar-entry');

        // Ordner auf / zu
        entry.classList.toggle('folder-open');

        // Aktuellen Pfad merken
        const url = new URL(this.href);
        const path = url.searchParams.get('path') || '';
        sessionStorage.setItem('currentPath', path);

        // Hidden-Feld aktualisieren
        const hiddenInput = document.querySelector('input[name="currentPath"]');
        if (hiddenInput) hiddenInput.value = path;
    });
});

// Uhrzeit
function updateClock() {
    let c = document.getElementById('clock');
    c.textContent = new Date().toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

// Kalender Events anzeigen
function toggleEvents(el) {
    let eventsDiv = el.querySelector('.day-events');
    if(eventsDiv) {
        eventsDiv.style.display = eventsDiv.style.display === 'block' ? 'none' : 'block';
    }
}
</script>

</body>
</html>
