<?php
/*********************************************************/
/* RPi Monitor Dashboard                                 */
/* https://github.com/nekromoff/rpi-monitor-dashboard    */
/* Copyright (c) 2024+ Daniel Duris, dusoft@staznosti.sk */
/* License: MIT                                          */
/* Version: 1.0                                          */
/*********************************************************/
$config = [];
require 'config.php';
date_default_timezone_set($config['timezone']);
const HEALTH_COLORS = ['#8BF8C0', '#F4B490', '#F68DA4'];

// Handle uploads and receive data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // handle image upload
    if (isset($_FILES) and isset($_FILES['screenshot']['name'])) {
        $filename = pathinfo($_FILES['screenshot']['name'])['filename'];
        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], 'logs/' . md5($filename) . '.png')) {
            header('HTTP/1.1 200 OK');
        } else {
            header('HTTP/1.1 401 Unauthorized');
        }
    } else {
        // receive data
        header('HTTP/1.1 200 OK');
        $content = json_decode(file_get_contents('php://input'));
        if (isset($content->_config)) {
            file_put_contents('logs/' . md5($content->hostname) . '_config', json_encode($content->_config));
        }
        if (isset($content->hostname)) {
            file_put_contents('logs/' . md5($content->hostname), json_encode($content));
            echo '{"status":"OK"}';
        } else {
            echo '{"status":"Missing identifier"}';
        }
    }
    exit;
}

// Handle display and dashboard
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $authenticated = true;
    // Handle Digest authentication, if user/pass configured
    if ($config['username'] and $config['password']) {
        $realm = 'RPi Monitor Dashboard';
        if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5($realm) . '"');
            $error = '<h1>Not authorized. Enter username and password to login.</h1>';
            $authenticated = false;
        } elseif (!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) or $data['username'] != $config['username']) {
            $error = '<h1>Incorrect login details.</h1>';
            $authenticated = false;
        }
        if (isset($data['response'])) {
            $hash1 = md5($data['username'] . ':' . $realm . ':' . $config['password']);
            $hash2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
            $valid_response = md5($hash1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $hash2);
            if ($data['response'] != $valid_response) {
                $error = '<h1>Incorrect login details.</h1>';
                $authenticated = false;
            }
        }
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>RPi Monitor Dashboard</title></head><body>';
    if ($authenticated === true) {
        echo '<h1>RPi Monitor Dashboard</h1>';
        echo '<div style="display: flex; flex-wrap: wrap;">';
        foreach (glob('logs/*') as $log) {
            // skip screenshots
            if (strpos($log, '.png') !== false) {
                continue;
            }
            $health = 100;
            $modified_time = filemtime($log);
            $content = file_get_contents($log);
            $content = json_decode($content);
            /*if (strpos($content->ping, ', 0%') === false) {
            $health -= 30;
            }*/
            $color = '#8BF8C0';
            echo '<div style="width:calc(32vw - 1em - 1px); padding:1em; border:1px dashed #000; background:' . $color . '">';
            echo 'Last update: ' . date('F d Y H:i:s', $modified_time);
            echo '<h2>' . $content->hostname . '</h2>';
            if (file_exists($log . '.png')) {
                echo '<img src="' . $log . '.png" style="width:100%">';
            }
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
    } else {
        echo $error;
    }
    echo '</body></html>';
}

// see: https://www.php.net/manual/en/features.http-auth.php
function http_digest_parse($txt)
{
    // protect against missing data
    $needed_parts = ['nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1];
    $data = [];
    $keys = implode('|', array_keys($needed_parts));
    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $data[$m[1]] = $m[3] ? $m[3] : $m[4];
        unset($needed_parts[$m[1]]);
    }
    return $needed_parts ? false : $data;
}
