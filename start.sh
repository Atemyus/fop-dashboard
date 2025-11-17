#!/bin/bash
# Fury of Sparta - Startup script for Railway

# Set default port if not provided
PORT=${PORT:-8080}

echo "Starting Fury of Sparta Dashboard on port $PORT..."

# Start PHP built-in server
php -S 0.0.0.0:$PORT -t .
