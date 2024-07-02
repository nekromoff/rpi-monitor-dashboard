# RPi Monitor Dashboard

Raspberry Pi Monitor Dashboard is a simple monitoring tool with a dashboard suitable for monitoring multiple RPi devices. The number of devices you can monitor is unlimited.

It currently reports these data:

* hostname
* CPU temperature
* network IP address
* ping results
* running browser (only Firefox and Chromium are checked at the moment)
* optionally, a screenshot of Pi's X screen (DISPLAY=:0)

![image](https://github.com/nekromoff/rpi-monitor-dashboard/assets/8550349/dd7d2664-dc8a-43d8-ba7c-b2a08751fc94)

## Architecture

* **Server** receiver and dashboard (one file) written in PHP
* **Client** reporting script written in Python (+config in TOML)

## Installation

1. Upload `index.php` to your server (any desired path), _optionally edit configuration options_
2. Create `logs/` directory in the same path and make it writable (777)
3. Rename `config.toml.example` to `config.toml` and edit: change URL to server receiver path, network interface to check, disable screenshot functionality
4. Upload `report.py` and `config.toml` to your Raspberry Pi (e.g. put in a new directory `/home/pi/rpi-monitor/`)
5. Edit CRON (`crontab -e`) and add this line: `1 * * * * cd /home/pi/rpi-monitor/ && python3 report.py` (adjust reporting interval, if needed)
6. Open server dashboard URL in your browser and enjoy

## Dependencies
* Python v3
* Python modules:
    * subprocess, json, requests (should be available by default)
    * toml (`pip3 install toml`)
* If screenshots are enabled:
    * scrot (`sudo apt install scrot`)
