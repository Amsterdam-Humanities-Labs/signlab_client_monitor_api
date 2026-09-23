# Client Monitor API - Integration Examples

This directory contains ready-to-use example scripts showing how to integrate your scripts with the Client Monitor system.

## Available Examples

### 1. Bash Script (`bash_client.sh`)
Complete bash script with functions for registration and heartbeat monitoring.

**Quick Start:**
```bash
# Copy the configuration section to your script
source /web/client_monitor_api/examples/bash_client.sh

# In your script, call:
send_heartbeat
```

**Run the example:**
```bash
cd /web/client_monitor_api/examples
./bash_client.sh
```

### 2. Python Script (`python_client.py`)

**This file is no longer an example.** It is a verbatim vendored copy of
`client/signlab_client_monitor/client.py`, kept here so that a host which has
not installed the package can still do `from python_client import
ClientMonitor`. Do not edit it; edit the package and copy it back. The
packaged form is what new code should use:

```python
from signlab_client_monitor import ClientMonitor

monitor = ClientMonitor(
    client_id="my-script",
    client_name="My Script",
    heartbeat_interval=3600,
)

# Do your work...

monitor.send_heartbeat_with_stats("success", "done", {"files": 42})
```

Install it with `client/install.sh`. It registers itself before the first
heartbeat, sets a timeout, and cannot raise into the script it monitors - see
`client/README.md` for why those three properties are the point.

Running it directly no longer does anything: it is a module, not a demo.

### 3. PHP
The one PHP client in use is `php_client.php` in
[signlab_pythonCron](https://github.com/Amsterdam-Humanities-Labs/signlab_pythonCron),
required by its `mysql_backup.php`:
```php
require_once __DIR__ . '/php_client.php';
$monitor = new ClientMonitor('my-script', 'My Script', 'What it does', 3600);  // registers itself
$monitor->sendHeartbeatWithStats('success', 'done', ['records' => 42]);
```

## Common Usage Patterns

### Pattern 1: Simple Heartbeat
Just notify that your script is running:

**Bash:**
```bash
send_heartbeat
```

**Python:**
```python
monitor.send_heartbeat()
```

**PHP:**
```php
$monitor->sendHeartbeat();
```

### Pattern 2: Heartbeat with Statistics
Include execution statistics:

**Bash:**
```bash
send_heartbeat_with_stats "success" "Processed 150 records" 150
```

**Python:**
```python
monitor.send_heartbeat_with_stats(
    status="success",
    message="Processed 150 records",
    stats={"records_processed": 150}
)
```

**PHP:**
```php
$monitor->sendHeartbeatWithStats(
    'success',
    'Processed 150 records',
    ['records_processed' => 150]
);
```

### Pattern 3: Error Handling
Send heartbeat even when your script fails:

**Bash:**
```bash
if do_work; then
    send_heartbeat_with_stats "success" "Work completed"
else
    send_heartbeat_with_stats "error" "Work failed"
fi
```

**Python:**
```python
try:
    do_work()
    monitor.send_heartbeat_with_stats("success", "Work completed")
except Exception as e:
    monitor.send_heartbeat_with_stats("error", f"Work failed: {e}")
```

**PHP:**
```php
try {
    doWork();
    $monitor->sendHeartbeatWithStats('success', 'Work completed');
} catch (Exception $e) {
    $monitor->sendHeartbeatWithStats('error', "Work failed: {$e->getMessage()}");
}
```

## Cron Job Integration

### Every Hour
```cron
0 * * * * /path/to/your-script.sh
```

### Every 30 Minutes
```cron
*/30 * * * * /path/to/your-script.sh
```

### Daily at 2 AM
```cron
0 2 * * * /path/to/your-script.sh
```

### Every 5 Minutes
```cron
*/5 * * * * /path/to/your-script.sh
```

## Testing Examples

All examples are ready to run:

```bash
cd /web/client_monitor_api/examples

# Test bash
./bash_client.sh

# Test Python
python3 python_client.py
```

After running, check the dashboard to see your test clients!

## Configuration

Update these values in each example:

| Variable | Description | Example |
|----------|-------------|---------|
| `API_URL` | Client Monitor API endpoint | `http://localhost/client_monitor_api/api.php` |
| `CLIENT_ID` | Unique identifier | `my-backup-script` |
| `CLIENT_NAME` | Display name | `Daily Backup Script` |
| `DESCRIPTION` | What the script does | `Backs up database daily` |
| `HEARTBEAT_INTERVAL` | Expected interval (seconds) | `3600` (1 hour) |

## Registration

Each client needs to be registered once. You can either:

**Option 1: Call register function (recommended)**
```bash
# Bash
register_client

# Python
monitor.register()

# PHP
$monitor->register();
```

**Option 2: First heartbeat auto-registers**
The first time you send a heartbeat, if the client doesn't exist, you'll get an error. Register first.

## Troubleshooting

### "Client not found" error
- Run the registration function first
- Or use the API to register via curl

### Heartbeat not updating dashboard
- Check that the `CLIENT_ID` matches what's in the dashboard
- Verify the API URL is correct
- Check PHP error logs: `tail -f /web/client_monitor_api/php_errors.log`

### Script runs but no heartbeat
- Make sure you're calling `send_heartbeat()` after your work completes
- Check for network connectivity to the API server
- Verify the API endpoint is accessible

## Advanced Usage

### Custom Metadata
Include any data you want to track:

```python
monitor.send_heartbeat({
    "records_processed": 150,
    "files_created": 5,
    "execution_time": 45.2,
    "memory_used_mb": 128,
    "version": "2.1.0"
})
```

### Different Heartbeat Intervals

| Frequency | Seconds | Use Case |
|-----------|---------|----------|
| 5 minutes | 300 | High-frequency monitoring |
| 15 minutes | 900 | Frequent tasks |
| 30 minutes | 1800 | Regular monitoring |
| 1 hour | 3600 | Standard cron jobs |
| 6 hours | 21600 | Periodic tasks |
| 24 hours | 86400 | Daily jobs |

## Next Steps

1. Choose the language that matches your script
2. Copy the example to your project
3. Update the configuration values
4. Register your client (run once)
5. Add `send_heartbeat()` calls to your script
6. Check the dashboard to see your client!

## Support

For more information, see:
- Main documentation: `/web/client_monitor_dashboard/README.md`
- API documentation: Check the main README for endpoint details
