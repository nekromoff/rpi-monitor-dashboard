# RPi Monitor Dashboard

Raspberry Pi Monitor Dashboard is a simple monitoring tool with a dashboard suitable for monitoring multiple RPi devices. The number of devices you can monitor is unlimited.

It currently reports these data:

* hostname
* network IP address
* ping results
* running browser (only Firefox and Chromium are checked at the moment)

## Architecture

* **Server** receiver and dashboard (one file) written in PHP
* **Client** reporting script written in Python (+config in TOML)

## Installation

1. Upload `index.php` to your server (any desired path), _optionally edit `$timezone`_
2. Create `logs/` directory in the same path and make it writable (777)
3. Rename `config.toml.example` to `config.toml` and edit: change URL to server receiver path and network interface to check
4. Upload `report.py` and `config.toml` to your Raspberry Pi (e.g. put in a new directory `/home/pi/rpi-monitor/`)
5. Edit CRON (`crontab -e`) and add this line: `1 * * * * cd /home/pi/rpi-monitor/ && python3 report.py` (adjust reporting interval, if needed)
6. Open server dashboard URL in your browser and enjoy