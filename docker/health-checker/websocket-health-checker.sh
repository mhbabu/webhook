#!/bin/sh

# Attempt to connect to the WebSocket server on port 6001
echo "Attempting to connect to WebSocket server on port 6001..."
nc -z localhost 6001

# Check if the connection was successful
if [ $? -eq 0 ]; then
  echo "Connection successful: WebSocket is healthy."
  exit 0
else
  echo "Connection failed: WebSocket is unhealthy."
  exit 1
fi
