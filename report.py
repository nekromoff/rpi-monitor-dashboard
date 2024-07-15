#!/usr/bin/env python3
###########################################################
## RPi Monitor Dashboard                                 ##
## https://github.com/nekromoff/rpi-monitor-dashboard    ##
## Copyright (c) 2024+ Daniel Duris, dusoft@staznosti.sk ##
## License: MIT                                          ##
## Version: 1.0                                          ##
###########################################################

import tomli as tomllib
import subprocess
import json
import requests

_version="1.0"

with open("config.toml", "rb") as f:
    config = tomllib.load(f)

content={}

# always extract hostname
output=subprocess.run("/usr/bin/hostname", universal_newlines = True, stdout = subprocess.PIPE)
hostname=output.stdout.strip()
content["hostname"]=hostname

for name, command in config["commands"].items():
    try:
        # simple command (TOML string)
        if isinstance(command, str):
            command=command.replace("{hostname}", hostname)
            parts=command.split(" ")
            output=subprocess.run(parts, universal_newlines = True, stdout = subprocess.PIPE)
            content[name]=output.stdout.strip()
        # command with text search and extraction (TOML array)
        elif isinstance(command, list):
            command[0]=command[0].replace("{hostname}", hostname)
            parts=command[0].split(" ")
            output=subprocess.run(parts, universal_newlines = True, stdout = subprocess.PIPE)
            content[name]=""
            for string in command[1]:
                for line in output.stdout.split("\n"):
                    if string in line:
                        content[name]=content[name]+line.strip()+"\n"
        content[name]=content[name].strip()
    except Exception:
        # ignore, skip
        pass

try:
    for name, command in config["commands_shell"].items():
        command=command.replace("{hostname}", hostname)
        subprocess.run([command], shell=True)
except Exception:
    # ignore, skip
    pass

headers = {"User-Agent": "RPi Monitor Dashboard/"+_version}

# if screenshot command exists, upload image
try:
    if config["commands_shell"]["screenshot"]:
        files = {'screenshot': open(hostname+".png", 'rb')}
        r = requests.post(config["receiver"], files=files, headers=headers)
except Exception:
    # ignore, skip
    pass

headers["Content-Type"]="application/json"
json_data = json.dumps(content)
response = requests.post(config["receiver"], data=json_data, headers=headers)
