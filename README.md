# RPi Monitor Dashboard

Raspberry Pi Monitor Dashboard is a simple monitoring tool with a dashboard suitable for monitoring multiple RPi devices. The number of devices you can monitor is unlimited. Fully configurable as to what report from bash / cli / terminal run.

**Remote configuration updates for Linux devices can be managed in the dashboard.**

By default it reports these data:

* hostname
* CPU temperature
* network IP address
* ping results
* running browser (only Firefox and Chromium are checked at the moment)
* optionally, a screenshot of Pi's X screen (DISPLAY=:0)

**It can report anything as commands are defined as standard bash commands and are fully configurable in config file.**

![image](https://github.com/nekromoff/rpi-monitor-dashboard/assets/8550349/dd7d2664-dc8a-43d8-ba7c-b2a08751fc94)

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

## Dependencies
* Python v3
* Python modules:
    * subprocess, json, requests (should be available by default)
    * tomli / tomllib (`pip3 install tomli` for Python <3.10, available by default from 3.11)
* If screenshots are enabled, scrot is recommended:
    * scrot (`sudo apt install scrot`)

## Please ⭐ star 🌟 this repo, if you like it and use it.
