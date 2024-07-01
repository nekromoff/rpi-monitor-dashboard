<?php
// set for correct time reporting in dashboard
$timezone = 'Europe/Bratislava';

/**********************************************/
date_default_timezone_set($timezone);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('HTTP/1.1 200 OK');
    $content = json_decode(file_get_contents('php://input'));
    if (isset($content->hostname)) {
        file_put_contents('logs/' . md5($content->hostname), json_encode($content));
        echo '{"status":"OK"}';
    } else {
        echo '{"status":"Missing identifier"}';
    }
    exit;
}

echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>RPi Monitor Dashboard</title></head><body>';
echo '<h1>RPi Monitor Dashboard</h1>';
echo '<div style="display: flex; flex-wrap: wrap;">';
foreach (glob('logs/*') as $log) {
    $health = 100;
    $modified_time = filemtime($log);
    $content = file_get_contents($log);
    $content = json_decode($content);
    if (strpos($content->ping, ', 0%') === false) {
        $health -= 30;
    }
    if (!trim($content->browser)) {
        $health -= 50;
    }
    if ($health >= 90) {
        $color = '#8BF8C0';
    } elseif ($health >= 40) {
        $color = '#F4B490';
    } else {
        $color = '#F68DA4';
    }
    echo '<div style="padding:1em; border:1px dashed #000; background:' . $color . '">';
    echo 'Last update: ' . date('F d Y H:i:s', $modified_time);
    echo '<h2>' . $content->hostname . ' (' . $health . '%)</h2>';
    unset($content->hostname);
    echo '<table>';
    foreach ($content as $key => $item) {
        echo '<tr><td>';
        echo '<h3 style="margin-top:0.5em; margin-bottom:0.2em;">' . $key . '</h3>';
        echo nl2br($item, false);
        echo '</td></tr>';
    }
    echo '</table>';
    echo '</div>';
}
echo '</div>';
echo '</body></html>';
