#!/bin/bash

# Start the driver
upsdrvctl start dummy

# Start NUT server
upsd

# Start NUT client
upsmon

# Keep container alive
 tail -f /dev/null