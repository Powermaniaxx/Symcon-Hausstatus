#!/bin/sh
# Run on the Synology via SSH. Reads container setup; does not change flows.
set -eu
container=nodered-node-red1
docker inspect --format 'Image: {{.Config.Image}}{{println}}{{range .Mounts}}Mount: {{.Source}} -> {{.Destination}}{{println}}{{end}}' "$container"
docker exec "$container" sh -c 'printf "Node: "; node --version; printf "HOME: %s\n" "$HOME"; command -v adb || true; command -v apk || true; command -v apt-get || true; test -f "$HOME/.android/adbkey" && echo "ADB key in default HOME exists" || true; test -f /data/shield-adb/.android/adbkey && echo "Persistent Shield key exists" || true'
