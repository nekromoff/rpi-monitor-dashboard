#!/usr/bin/env python3
###########################################################
## RPi Monitor Dashboard                                 ##
## https://github.com/nekromoff/rpi-monitor-dashboard    ##
## Copyright (c) 2024+ Daniel Duris, dusoft@staznosti.sk ##
## License: MIT                                          ##
## Version: 3.0                                          ##
###########################################################

__version__="3.0"

import sys
# tomli/tomllib compatibility layer as Python 3.11+ contains tomllib by default
if sys.version_info >= (3, 11):
    import tomllib
else:
    import tomli as tomllib
import subprocess
import json
import requests
import binascii

with open("config.toml", "rb") as f:
    config = tomllib.load(f)

content={}

# always extract hostname
output=subprocess.run("/usr/bin/hostname", universal_newlines = True, stdout = subprocess.PIPE)
hostname=output.stdout.strip()
content["hostname"]=hostname

headers = {"User-Agent": "RPi Monitor Dashboard/"+__version__, "X-Hostname": hostname, "X-Crc32": str(binascii.crc32(open("report.py", "rb").read())), "Content-Type": "application/json"}

# check for available updates
response=requests.head(config["receiver"], headers = headers)

# check for python script update and overwrite, if auto update enabled
if "X-Update" in response.headers and response.headers["X-Update"][2]=="1" and config['auto_update']==True:
    response=requests.get(config["receiver"]+'?update=3', headers = headers)
    try:
        subprocess.run('cp report.py report.py.bak', shell = True)
        f = open("report.py", "w")
        f.write(response.text)
        f.close()
    except Exception:
        # ignore, skip
        pass

# check for one-time commands and proceed with execution
if "X-Update" in response.headers and response.headers["X-Update"][1]=="1":
    response=requests.get(config["receiver"]+'?update=2', headers = headers)
    commands=response.text.split("\n")
    try:
        for line_no, command in enumerate(commands):
            command=command.replace("{hostname}", hostname)
            subprocess.run([command], shell = True)
    except Exception:
        # ignore, skip
        pass

# check for config update and proceed with update
if "X-Update" in response.headers and response.headers["X-Update"][0]=="1":
    # backup current config in case the new one is messed up
    subprocess.run('cp config.toml config.toml.bak', shell = True)
    response=requests.get(config["receiver"]+'?update=1', headers = headers)
    current_config=open("config.toml", "rt").read()
    current_config_lines=current_config.split("\n")
    new_config=''
    for line_no, line in enumerate(current_config_lines):
        new_config=new_config+line+"\n"
        # search for our special line
        pos=line.strip().find("#-#*+*#-#")
        # if found, assemble new config from existing receiver and config update received
        if pos!=-1:
            new_config=new_config+response.text;
            new_config=new_config.strip()
            f = open("config.toml", "w")
            f.write(new_config)
            f.close()
            # validate new config
            try:
                with open("config.toml", "rb") as f:
                    test_config = tomllib.load(f)
                # once validated, confirm it back to receiver
                # content["_config"]=open("config.toml", "rt").read()
                #json_data = json.dumps(content)
                #response = requests.post(config["receiver"], data=json_data, headers=headers)
            except tomllib.TOMLDecodeError:
                # new config parse fail, invalid TOML config, fallback to backup config
                subprocess.run('cp config.toml.bak config.toml', shell = True)
            # backup cleanup
            subprocess.run('rm config.toml.bak', shell = True)
            break;

with open("config.toml", "rb") as f:
    config = tomllib.load(f)

# include config contents in the payload
content["_config"]=open("config.toml", "rt").read()

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
        subprocess.run([command], shell = True)
except Exception:
    # ignore, skip
    pass

# post payload
json_data = json.dumps(content)
response = requests.post(config["receiver"], data = json_data, headers = headers)

headers.pop("Content-Type")

# if screenshot command exists, upload image
try:
    if config["commands_shell"]["screenshot"]:
        files = {'screenshot': open(hostname+".png", 'rb')}
        r = requests.post(config["receiver"], files = files, headers = headers)
except Exception:
    # ignore, skip
    pass
