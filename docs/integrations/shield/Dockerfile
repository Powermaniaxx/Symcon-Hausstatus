# Set NODE_RED_IMAGE to the EXACT existing image/tag (Alpine variant).
ARG NODE_RED_IMAGE
FROM ${NODE_RED_IMAGE}
USER root
RUN apk add --no-cache android-tools
USER node-red
# /data must retain the EXISTING volume/bind mount, including flows and credentials.
# The poll script sets HOME to /data/shield-adb so new ADB keys survive recreation.
