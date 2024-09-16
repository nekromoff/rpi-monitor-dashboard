# RPi Monitor Dashboard

Raspberry Pi Monitor Dashboard is a simple monitoring tool with a dashboard suitable for monitoring multiple RPi devices (or any Linux devices). The number of devices you can monitor is unlimited. Fully configurable as to what report from bash / cli / terminal run.

**Remote configuration updates as well as one-time commands to run on Linux devices (RPis) can be managed in the dashboard.**

By default it reports these data:

* hostname
* CPU temperature
* network IP address
* ping results
* running browser (only Firefox and Chromium are checked at the moment)
* optionally, a screenshot of Pi's X screen (DISPLAY=:0)

**It can report anything as commands are defined as standard bash commands and are fully configurable in config file.**

![Dashboard screenshot](https://github.com/user-attachments/assets/4ed59bdf-6876-4ceb-b7e1-67ae04e4534d)

## Architecture

* **Server** receiver and dashboard (one file) written in PHP (+config) - hosted anywhere
* **Client** reporting script (one file) written in Python (+config) - used on RPi / Linux device

## Installation

### Server
1. Upload `index.php` and `config.php` to your server (any desired path), _optionally edit `config.php` options_
2. Create `logs/` directory in the same path and make it writable (777)

### Client
1. Rename `config.toml.example` to `config.toml` and edit: URL to server receiver path and _optionally monitoring commands to execute_ (see Configuration in detail below)
2. Upload `report.py` and `config.toml` to your Raspberry Pi (e.g. put in a new directory `/home/pi/rpi-monitor/`)
3. Edit CRON (`crontab -e`) and add this line: `1 * * * * cd /home/pi/rpi-monitor/ && python3 report.py` (adjust reporting interval, if needed)

**Open server dashboard URL in your browser and enjoy.**

## Configuration explained

### Server (config.php)
1. You can change`$config['timezone']` to your timezone for dashboard to report correct time.
2. You can set `$config['username']` and `$config['password']` to secure dashboard URL with login (digest in-browser authentication is used).

Dashboard URL works without login by default.

### Client (config.toml)
Standard commands **with output** are put under `[commands]` section:

This will run `uptime` command and fetch full output:

```uptime = "/usr/bin/uptime"```

This will run `ifconfig` command and fetch **only** output of line containing "inet ":

```network = ["/usr/sbin/ifconfig wlan0", ["inet "] ]```

This will run `ps` command and fetch **only** output of lines containing "firefox" or "chromium":

```browser = ["/usr/bin/ps -A", ["firefox", "chromium"] ]```

You can add as many strings to search for as you need. Any found output lines will be joined into one string and reported back under the name of command (e.g. browser).

Shell commands **without output** are put under `[commands_shell]` section (executed with `shell=True` in Python subprocess) - e.g. screenshot functionality.

You can use any names for the commands, the names are then shown in the dashboard.

### How does config update work? (explanation for geeks)

1. Each remote device (RPi or Linux) connects to the receiver URL (defined in config.toml) on the server.
2. Before reporting metrics based on current config.toml, it asks server whether there was a config update (HTTP HEAD method is used to save bandwidth). If it exists, it creates a config backup (as a safety measure) and retrieves the new config (GET method).
3. The script replaces `[commands]` and `[commands_shell]` sections with the updated commands and saves it as a new config.toml. Receiver URL is never replaced (and is unavailable in the dashboard) as an incorrect URL could stop the remote device from further reporting. This would be similar to crashing your monitoring, so the tool prevents it by default.
4. The updated local config.toml is tested for validity. If invalid TOML syntax is detected, backup version of the former config is used again. If valid syntax is detected, the new config will be used.

## Upgrade (v1 to v2)
1. Please, upload new config.toml.example and change any reporting metrics, if required (bash commands).
2. Copy new report.py to your client(s), index.php to your server.
3. Optionally delete any files in logs/ directory, so you don't have reported data in old format hanging around.

## Upgrade (v2.x to v3)
1. Use your dashboard to update configs of your devices with new config option:
```
# Allow automatic updates of report.py from Github (experimental)
auto_update = false
```
2. Set auto_update to true, if you wish. This is still experimental, but hopefully updates won't break your monitoring.
3. Update config.php with `// REPORTING INTERVAL` section to have unresponsive devices displayed with red background in your dashboard.
4. Copy new report.py to your client(s), index.php to your server.

## Dependencies
* Python v3
* Python modules:
    * sys, subprocess, json, requests, binascii (should be available by default)
    * tomli / tomllib (`pip3 install tomli` for Python <3.10, available by default from 3.11)
* If screenshots are enabled, scrot is recommended:
    * scrot (`sudo apt install scrot`)

## Please ⭐ star 🌟 this repo, if you like it and use it.
