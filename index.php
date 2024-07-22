<?php
/*********************************************************/
/* RPi Monitor Dashboard                                 */
/* https://github.com/nekromoff/rpi-monitor-dashboard    */
/* Copyright (c) 2024+ Daniel Duris, dusoft@staznosti.sk */
/* License: MIT                                          */
/* Version: 2.0                                          */
/*********************************************************/
$config = [];
require 'config.php';
date_default_timezone_set($config['timezone']);
const HEALTH_COLORS = ['#8BF8C0', '#F4B490', '#F68DA4']; // @TODO unused, maybe ratio of health from no. of reported metrics

if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
    if (isset(getallheaders()['X-Hostname'])) {
        $filename = 'logs/' . md5(getallheaders()['X-Hostname']) . '_new.config';
    }
    // Handle config update check
    if ((isset($filename) and file_exists($filename)) or file_exists('logs/_new.config')) {
        header('HTTP/1.1 200 OK');
        header('X-Update: 1');
    }
    exit;
}

$display_dashboard = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle uploads and receive data
    if (isset($_POST) and isset($_POST['new_config'])) {
        // save new config
        if (isset($_POST['all']) and $_POST['all'] == 1) {
            file_put_contents('logs/_new.config', $_POST['new_config']);
        } else {
            file_put_contents('logs/' . md5($_POST['hostname']) . '_new.config', $_POST['new_config']);
        }
        $display_dashboard = true;
    } elseif (isset($_FILES) and isset($_FILES['screenshot']['name'])) {
        // Handle image upload
        $filename = pathinfo($_FILES['screenshot']['name'])['filename'];
        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], 'logs/' . md5($filename) . '.png')) {
            header('HTTP/1.1 200 OK');
        } else {
            header('HTTP/1.1 401 Unauthorized');
        }
        exit;
    } else {
        // receive data
        header('HTTP/1.1 200 OK');
        $content = json_decode(file_get_contents('php://input'));
        if (isset($content->_config)) {
            file_put_contents('logs/' . md5($content->hostname) . '.config', $content->_config);
        }
        unset($content->_config);
        if (isset($content->hostname)) {
            file_put_contents('logs/' . md5($content->hostname) . '.log', json_encode($content));
            echo '{"status":"OK"}';
        } else {
            echo '{"status":"Missing identifier"}';
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' or $display_dashboard === true) {
    // Handle authentication, dashboard and configuration update for remotes
    if (isset($_GET['update']) and $_GET['update'] == 1) {
        // Send update to remote devices
        if (file_exists('logs/_new.config')) {
            // counte, if all remotes retrieved updated config
            $new_config = file_get_contents('logs/_new.config');
            $counter_file = 'logs/_new_config.count';
            $count = 1;
            if (file_exists($counter_file)) {
                $counter = fopen($counter_file, 'r+');
                flock($counter, LOCK_EX);
                $count = trim(fread($counter, filesize($counter_file)));
                $count++;
                rewind($counter);
                fwrite($counter, $count);
                flock($counter, LOCK_UN);
                fclose($counter);
            } else {
                $counter = fopen($counter_file, 'w');
                flock($counter, LOCK_EX);
                fwrite($counter, $count);
                flock($counter, LOCK_UN);
                fclose($counter);
            }
            // delete new config once retrieved by all remotes
            if (count(glob('logs/*.log')) == $count) {
                unlink('logs/_new.config');
                unlink($counter_file);
            }
        }
        if (isset(getallheaders()['X-Hostname']) and file_exists('logs/' . md5(getallheaders()['X-Hostname']) . '_new.config')) {
            $new_config = file_get_contents('logs/' . md5(getallheaders()['X-Hostname']) . '_new.config');
            unlink('logs/' . md5(getallheaders()['X-Hostname']) . '_new.config');
        }
        echo $new_config;
        exit;
    }
    if (isset($_GET['cancel']) and $_GET['cancel'] == 1) {
        // cancel config update
        if (isset($_GET['hostname']) and file_exists('logs/' . md5($_GET['hostname']) . '_new.config')) {
            unlink('logs/' . md5($_GET['hostname']) . '_new.config');
            $flash = 'ℹ️ Config update canceled.';
        } elseif (file_exists('logs/_new.config')) {
            unlink('logs/_new.config');
            $flash = 'ℹ️ Config update canceled for all devices.';
        }
    }
    // Assume user is authenticated, unless auth is configured and digest tells you otherwise
    $authenticated = true;
    if ($config['username'] and $config['password']) {
        // Handle Digest authentication, if user/pass configured
        $realm = 'RPi Monitor Dashboard';
        if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",algorithm=SHA-256,nonce="' . uniqid() . '",opaque="' . hash('sha256', $realm) . '"');
            $error = '<h1>Not authorized. Enter username and password to login.</h1>';
            $authenticated = false;
        } elseif (!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) or $data['username'] != $config['username']) {
            $error = '<h1>Incorrect login details.</h1>';
            $authenticated = false;
        }
        if (isset($data['response'])) {
            $hash1 = hash('sha256', $data['username'] . ':' . $realm . ':' . $config['password']);
            $hash2 = hash('sha256', $_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
            $valid_response = hash('sha256', $hash1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $hash2);
            if ($data['response'] != $valid_response) {
                $error = '<h1>Incorrect login details.</h1>';
                $authenticated = false;
            }
        }
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>RPi Monitor Dashboard</title><style>
        * { margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; padding: 1em; }
        figure { padding: 1em; font-weight: bold; background: #FFF; }
        main { display: flex; flex-direction: row; flex-wrap: wrap; }
        section { width: calc(33vw - 3em - 2px); padding:1em; border:1px dashed #000; background: #8BF8C0; }
        h3 { margin-top:0.5em; margin-bottom:0.2em; }
        img { width: 100%; }
        details { border: 1px solid rgba(255,255,255,0);}
        summary { cursor:pointer; padding: 0.5em; }
        details[open] { border: 1px dotted #000; }
        textarea { width:calc(100% - 2em); padding: 0.5em; display: block; margin: 0 auto; resize: vertical; }
        input[type=submit] { margin-top: 1em; width:100%; height:2em; font-size: 120%; background: #000; color: #fff; border-width: 1px; cursor: pointer; }
        input[type=submit]:hover { background: #fff; color: #000; font-weight: bold; }
        label { cursor:pointer; }
        button { padding: 0 0.5em; font-size: 120%; background: #fff; color: #000; border-width: 1px; cursor: pointer; }
        button:hover { background: #000; color: #fff; font-weight: bold; }
        @media (max-width: 800px) { main {flex-direction: column;} section { width: calc(100vw - 4em - 2px); } }
        .warning { background: #FFD6DA; }
    </style></head><body>';
    if ($authenticated === true) {
        // Display dashboard, if auth check passed
        echo '<h1>RPi Monitor Dashboard</h1>';
        if (file_exists('logs/_new.config')) {
            echo '<figure class="warning">⚠️ New configuration will be applied on next contact to all devices. <a href="./?cancel=1"><button>Cancel</button></a><details><summary>Config</summary><textarea name="new_config" rows="10">' . file_get_contents('logs/_new.config') . '</textarea></details></figure>';
        }
        if (isset($flash) and !isset($_GET['hostname'])) {
            echo '<figure>' . $flash . '</figure>';
        }
        echo '<main>';
        foreach (glob('logs/*.log') as $log) {
            $modified_time = filemtime($log);
            $content = file_get_contents($log);
            $content = json_decode($content);
            echo '<section>';
            if (isset($flash) and isset($_GET['hostname']) and $_GET['hostname'] == $content->hostname) {
                echo '<figure>' . $flash . '</figure>';
            }
            if (file_exists('logs/' . md5($content->hostname) . '_new.config')) {
                echo '<figure class="warning">⚠️ New configuration will be applied on next contact. <a href="./?cancel=1&hostname=' . urlencode($content->hostname) . '"><button>Cancel</button></a><details><summary>Config</summary><textarea name="new_config" rows="10">' . file_get_contents('logs/' . md5($content->hostname) . '_new.config') . '</textarea></details></figure>';
            }
            echo 'Last update: ' . date('F d Y H:i:s', $modified_time);
            $config_filename = str_ireplace('.log', '.config', $log);
            if (file_exists($config_filename)) {
                $cleaned_config = stripReceiverFromTOMLConfig(file_get_contents($config_filename));
                echo '<details><summary>Config</summary><form method="post" action="./"><textarea name="new_config" rows="10">' . $cleaned_config . '</textarea><input type="checkbox" name="all" id="all' . md5($content->hostname) . '" value="1"><label for="all' . md5($content->hostname) . '"> Apply to all</label><input type="hidden" name="hostname" value="' . $content->hostname . '"><input type="submit" value="Save"></form></details>';
            }
            echo '<h2>' . $content->hostname . '</h2>';
            $screenshot = str_ireplace('.log', '.png', $log);
            if (file_exists($screenshot)) {
                echo '<img src="' . $screenshot . '">';
            }
            unset($content->hostname);
            echo '<table>';
            foreach ($content as $key => $item) {
                echo '<tr><td>';
                echo '<h3>' . $key . '</h3>';
                echo nl2br($item, false);
                echo '</td></tr>';
            }
            echo '</table>';
            echo '</section>';
        }
        echo '</main>';
    } else {
        echo $error;
    }
    echo '</body></html>';
}

// HTTP Digest authentication parser
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

function stripReceiverFromTOMLConfig($config)
{
    $cleaned_config = '';
    $config_lines = explode("\n", $config);
    foreach ($config_lines as $key => $line) {
        if (stripos($line, '#-#*+*#-#') !== false) {
            $offset_key = $key + 1;
        }
        if (isset($offset_key) and $key >= $offset_key) {
            $cleaned_config .= $line . "\n";
        }
    }
    return $cleaned_config;
}
