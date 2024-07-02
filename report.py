#!/usr/bin/env python3

import toml
import subprocess
import json
import requests

config=toml.load("config.toml")

content={}
command=subprocess.run(["/usr/bin/hostname"], universal_newlines = True, stdout = subprocess.PIPE)
content["hostname"]=command.stdout.strip()
if config["screenshot"] == True:
    screenshot_filename=content["hostname"]+".png";
    command=subprocess.run(["DISPLAY=:0 /usr/bin/scrot -o "+screenshot_filename], shell=True)
command=subprocess.run(["/usr/bin/vcgencmd","measure_temp"], universal_newlines = True, stdout = subprocess.PIPE)
content["temperature"]=command.stdout.strip()
command=subprocess.run(["/usr/sbin/ifconfig",config["network"]], universal_newlines = True, stdout = subprocess.PIPE)
for item in command.stdout.split("\n"):
    if "inet" in item:
        content["network"]=item.strip()
        break
command=subprocess.run(["/usr/bin/ping","-c4","8.8.8.8"], universal_newlines = True, stdout = subprocess.PIPE)
for item in command.stdout.split("\n"):
    if "packets" in item:
        content["ping"]=item.strip()
    if "rtt" in item:
        content["ping"]=content["ping"]+"\n"+item.strip()
command=subprocess.run(["/usr/bin/ps","-A"], universal_newlines = True, stdout=subprocess.PIPE)
for item in command.stdout.split("\n"):
    if "firefox" in item:
        content["browser"]=item.strip()
        break
    if "chromium" in item:
        content["browser"]=item.strip()
        break

if config["screenshot"] == True:
    files = {'screenshot': open(screenshot_filename, 'rb')}
    r = requests.post(config["receiver"], files=files)

headers = {"Content-Type": "application/json"}
json_data = json.dumps(content)
response = requests.post(config["receiver"], data=json_data, headers=headers)
#print(response.text)
