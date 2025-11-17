#!/bin/bash
# Fury of Sparta - Startup script for Railway

# Set default port if not provided
PORT=${PORT:-8080}

echo "🔱 Fury of Sparta - Initializing..."

# Initialize persistent data directory
if [ -d "/data" ]; then
    echo "✅ Persistent volume detected at /data"

    # Ensure write permissions
    chmod -R 755 /data 2>/dev/null || true

    # Create licenses.json if it doesn't exist
    if [ ! -f "/data/licenses.json" ]; then
        echo "📝 Creating initial licenses.json..."
        echo "{}" > /data/licenses.json
        chmod 666 /data/licenses.json
    fi

    # Create log file if it doesn't exist
    if [ ! -f "/data/license_log.txt" ]; then
        echo "📝 Creating initial license_log.txt..."
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] System initialized with persistent storage" > /data/license_log.txt
        chmod 666 /data/license_log.txt
    fi

    echo "✅ Data directory initialized successfully"
    echo "   - Licenses: /data/licenses.json"
    echo "   - Logs: /data/license_log.txt"
else
    echo "⚠️  No persistent volume found - using local directory (data will not persist)"
fi

echo "🚀 Starting Fury of Sparta Dashboard on port $PORT..."

# Start PHP built-in server
php -S 0.0.0.0:$PORT -t .
