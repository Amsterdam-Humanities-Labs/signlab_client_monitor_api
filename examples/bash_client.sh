#!/bin/bash
###############################################################################
# Client Monitor API - Bash Integration Example
#
# This script demonstrates how to integrate your bash scripts/cron jobs
# with the Client Monitor system.
#
# Usage:
#   1. Copy this file to your project
#   2. Update the configuration section below
#   3. Call send_heartbeat() in your script after successful execution
#
# Example cron job (runs every hour):
#   0 * * * * /path/to/your-script.sh
###############################################################################

#------------------------------------------------------------------------------
# CONFIGURATION - Update these values for your script
#------------------------------------------------------------------------------
API_URL="http://localhost/client_monitor_api/api.php"
CLIENT_ID="my-bash-script"                    # Unique ID for this script
CLIENT_NAME="My Bash Script"                  # Display name in dashboard
DESCRIPTION="Description of what this script does"
HEARTBEAT_INTERVAL=3600                       # Expected interval in seconds (3600 = 1 hour)

#------------------------------------------------------------------------------
# FUNCTIONS
#------------------------------------------------------------------------------

# Register client (only needs to be run once)
register_client() {
    echo "Registering client with monitoring system..."

    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}?action=register" \
        -H "Content-Type: application/json" \
        -d "{
            \"client_id\": \"${CLIENT_ID}\",
            \"client_name\": \"${CLIENT_NAME}\",
            \"description\": \"${DESCRIPTION}\",
            \"heartbeat_interval\": ${HEARTBEAT_INTERVAL},
            \"metadata\": {
                \"hostname\": \"$(hostname)\",
                \"script_path\": \"$0\",
                \"version\": \"1.0.0\"
            }
        }")

    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)

    if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 201 ]; then
        echo "✓ Client registered successfully"
        echo "$BODY" | python3 -m json.tool 2>/dev/null || echo "$BODY"
        return 0
    else
        echo "✗ Failed to register client (HTTP $HTTP_CODE)"
        echo "$BODY"
        return 1
    fi
}

# Send heartbeat to update last_seen timestamp
send_heartbeat() {
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}?action=heartbeat" \
        -H "Content-Type: application/json" \
        -d "{
            \"client_id\": \"${CLIENT_ID}\",
            \"metadata\": {
                \"last_run\": \"$(date -Iseconds)\",
                \"hostname\": \"$(hostname)\"
            }
        }")

    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)

    if [ "$HTTP_CODE" -eq 200 ]; then
        echo "✓ Heartbeat sent successfully at $(date '+%Y-%m-%d %H:%M:%S')"
        return 0
    else
        echo "✗ Failed to send heartbeat (HTTP $HTTP_CODE)"
        echo "$BODY"
        return 1
    fi
}

# Send heartbeat with custom metadata
send_heartbeat_with_stats() {
    local status="$1"
    local message="$2"
    local records_processed="${3:-0}"

    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}?action=heartbeat" \
        -H "Content-Type: application/json" \
        -d "{
            \"client_id\": \"${CLIENT_ID}\",
            \"metadata\": {
                \"last_run\": \"$(date -Iseconds)\",
                \"hostname\": \"$(hostname)\",
                \"status\": \"${status}\",
                \"message\": \"${message}\",
                \"records_processed\": ${records_processed}
            }
        }")

    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)

    if [ "$HTTP_CODE" -eq 200 ]; then
        echo "✓ Heartbeat sent: $status - $message"
        return 0
    else
        echo "✗ Failed to send heartbeat (HTTP $HTTP_CODE)"
        return 1
    fi
}

#------------------------------------------------------------------------------
# MAIN SCRIPT LOGIC
#------------------------------------------------------------------------------

echo "=================================================="
echo "Starting: ${CLIENT_NAME}"
echo "Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=================================================="

# YOUR SCRIPT LOGIC GOES HERE
# Example:

# Step 1: Do some work
echo "Processing data..."
sleep 2

RECORDS_PROCESSED=150

# Step 2: More work
echo "Performing cleanup..."
sleep 1

# Step 3: Send heartbeat on success
if [ $? -eq 0 ]; then
    send_heartbeat_with_stats "success" "Processed ${RECORDS_PROCESSED} records" "$RECORDS_PROCESSED"
    exit 0
else
    send_heartbeat_with_stats "error" "Script failed" 0
    exit 1
fi

#------------------------------------------------------------------------------
# USAGE EXAMPLES
#------------------------------------------------------------------------------

# Example 1: Simple heartbeat (minimal)
# send_heartbeat

# Example 2: Heartbeat with status
# send_heartbeat_with_stats "success" "Backup completed" 0

# Example 3: Register client (run once manually)
# register_client
