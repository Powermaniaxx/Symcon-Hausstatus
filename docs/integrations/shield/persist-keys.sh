#!/bin/sh
# Run INSIDE nodered-node-red1 as the normal Node-RED user, before recreation.
set -eu
destination=/data/shield-adb/.android
mkdir -p "$destination"
chmod 700 "$destination"
if [ ! -f "$destination/adbkey" ]; then
    if [ -f "$HOME/.android/adbkey" ]; then
        cp "$HOME/.android/adbkey" "$destination/adbkey"
        if [ -f "$HOME/.android/adbkey.pub" ]; then cp "$HOME/.android/adbkey.pub" "$destination/adbkey.pub"; fi
        chmod 600 "$destination/adbkey"
        echo 'Existing key preserved.'
    else
        echo 'No key found for this user. Authorize the new key on the Shield after first poll.'
    fi
else
    echo 'Persistent key already exists; retained.'
fi
# Do not restart the shared ADB server or alter existing connections.
