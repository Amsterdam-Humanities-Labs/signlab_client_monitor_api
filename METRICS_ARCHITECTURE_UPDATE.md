# Metrics Architecture Update - Server-Level Design

## What Changed

### Previous Design ❌
- Metrics tied to individual workers (client processes)
- Had to click a button to show metrics
- Metrics were hidden/collapsed by default
- Confusing which worker the metrics represented

### New Design ✅
- **Metrics represent the entire server (by IP address)**
- **Metrics always visible** - no button needed
- **One metrics collector per server**, not per worker
- Clear separation: Server metrics vs Worker details

---

## New Architecture

### Dashboard View Structure

```
┌─────────────────────────────────────────┐
│  IP Address: 136.144.170.87            │
│  [14 processes badge]                   │
├─────────────────────────────────────────┤
│  📊 Donut Chart (Status Distribution)   │
├─────────────────────────────────────────┤
│  🖥️ Server Metrics (7 Days)             │
│  ┌───────────────────────────────────┐  │
│  │  CPU Chart  │ Disk Chart │ I/O    │  │
│  │  (always visible)                  │  │
│  └───────────────────────────────────┘  │
├─────────────────────────────────────────┤
│  [Show/Hide Worker Details] ← Button   │
├─────────────────────────────────────────┤
│  📋 Worker List (Collapsible)           │
│  • vicon-sync-rsync      [online]      │
│  • rclone-mount-monitor  [online]      │
│  • check-disk            [warning] ⚠️  │
│  (auto-expanded if errors)             │
└─────────────────────────────────────────┘
```

### Data Model

**Server-Level Clients:**
- `client_id`: `server-{IP}` (e.g., `server-136.144.170.87`)
- `client_name`: `Server Metrics ({IP})`
- `description`: System-level metrics for the entire server
- `ip_address`: The server's IP

**Worker Clients:**
- `client_id`: Worker-specific (e.g., `vicon-sync-rsync`)
- `client_name`: Worker name
- `ip_address`: Same as server IP (multiple workers per server)

**Metrics Storage:**
- Only server-level clients submit to `client_metrics` table
- One row per hour per server
- Represents entire system: CPU, disk, memory, I/O wait

---

## Metrics Collector Configuration

### Updated Python Script

The metrics collector now:
1. **Auto-detects its server IP** using socket connection
2. **Registers as**: `server-{IP}` instead of a worker name
3. **Submits system-level metrics** for the entire server

**Key Changes:**
```python
# Old approach
CLIENT_ID = "metrics-collector-service"

# New approach
import socket
s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
s.connect(("8.8.8.8", 80))
SERVER_IP = s.getsockname()[0]
CLIENT_ID = f"server-{SERVER_IP}"
```

### Deployment on Each Server

**Install on each server:**
```bash
# Copy files to server
scp /web/client_monitor_api/services/metrics_collector.py user@{SERVER_IP}:/opt/metrics/
scp /web/client_monitor_api/services/client-monitor-metrics.service user@{SERVER_IP}:/tmp/

# On the server, install service
sudo cp /tmp/client-monitor-metrics.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable client-monitor-metrics
sudo systemctl start client-monitor-metrics
```

**The service will:**
- Detect its own IP (e.g., 136.144.170.87)
- Register as `server-136.144.170.87`
- Submit hourly metrics for the entire server

---

## JavaScript Changes

### Auto-Load Metrics

Metrics now load automatically when the dashboard loads:

```javascript
// Old: Triggered by button click
onclick="loadMetricsForIP('${ip}')"

// New: Auto-load when card is rendered
loadMetricsForIP(ip);  // Called automatically
```

### Server-Level Client ID

```javascript
// Use server-level client ID
const serverClientId = `server-${ip}`;

// Fetch metrics from API
fetch(`${API_BASE}?action=get_metrics&client_id=${serverClientId}&hours=168`)
```

---

## Test Data

### Server-Level Clients Created ✅

| Client ID | IP Address | Metrics Count |
|-----------|------------|---------------|
| `server-136.144.170.87` | 136.144.170.87 | 168 (7 days) |
| `server-145.100.134.164` | 145.100.134.164 | 168 (7 days) |
| `server-145.100.134.185` | 145.100.134.185 | 168 (7 days) |
| `server-146.50.10.128` | 146.50.10.128 | 168 (7 days) |
| `server-146.50.49.31` | 146.50.49.31 | 168 (7 days) |

**Total:** 5 servers × 168 metrics = 840 server-level metrics

### API Endpoints

**Get server metrics:**
```bash
curl 'https://signcollect.nl/client_monitor_api/api.php?action=get_metrics&client_id=server-136.144.170.87&hours=168'
```

**Submit server metrics:**
```bash
curl -X POST 'https://signcollect.nl/client_monitor_api/api.php?action=submit_metrics' \
  -H 'Content-Type: application/json' \
  -d '{
    "client_id": "server-136.144.170.87",
    "metrics": {
      "cpu_percent": 55.2,
      "disk_usage_percent": 72.5,
      "cpu_wait_percent": 1.8,
      "memory_percent": 68.3
    }
  }'
```

---

## Dashboard Behavior

### On Page Load:
1. ✅ **Server metrics load automatically** (no button click needed)
2. ✅ **Charts appear immediately** if data exists
3. ✅ **Worker list collapsed** by default
4. ✅ **Auto-expand workers** if any are warning/offline

### User Interactions:
1. **Click "Show Worker Details"** → Expands list of individual workers
2. **Click "Hide Worker Details"** → Collapses worker list
3. **Metrics always visible** → No interaction needed

### Error States:
- **No metrics available:** Shows warning message
  > ⚠️ No system metrics available yet. The metrics collector will start reporting after installation.
- **No IP address:** Shows info message
  > ℹ️ No IP address available for this server
- **API error:** Shows error message
  > ❌ Failed to load metrics

---

## Migration Steps

### Step 1: Update Dashboard ✅ DONE
- Modified `dashboard.js` to auto-load server metrics
- Changed UI to always show metrics section
- Updated button text: "Process Details" → "Worker Details"

### Step 2: Update Metrics Collector ✅ DONE
- Modified `metrics_collector.py` to use server IP
- Uses `server-{IP}` format for client_id

### Step 3: Create Server-Level Clients ✅ DONE
- Added 5 server-level clients (one per IP)
- Added 7 days of test metrics for each

### Step 4: Deploy to Servers ⏳ MANUAL
Restart the metrics collector on this server:
```bash
/tmp/restart_metrics.sh
```

For other servers, deploy the service:
```bash
# On each remote server:
sudo systemctl restart client-monitor-metrics
```

---

## Benefits of New Architecture

### ✅ Clearer Separation
- **Server metrics** = System-wide (CPU, disk, I/O)
- **Worker details** = Individual process status

### ✅ Better UX
- Metrics always visible (no hunting for button)
- Cleaner card layout
- Faster information access

### ✅ Scalable
- One collector per server (not per worker)
- Reduces database records (1 server vs 14 workers = 14× fewer records)
- Simpler maintenance

### ✅ Logical Grouping
- All workers on same IP share same server metrics
- Makes sense: they run on the same physical/virtual machine

---

## Testing

### View Dashboard:
```
https://signcollect.nl/client_monitor_dashboard/
```

**Expected Results:**
1. Each IP card shows server metrics automatically
2. Three charts visible: CPU, Disk, I/O Wait
3. Worker list collapsed (unless errors)
4. Click "Show Worker Details" to see individual workers

### Verify Metrics:
```bash
# Check server clients
mysql -u user -p$DB_PASSWORD admin_gebarenoverleg \
  -e "SELECT client_id, ip_address FROM client_monitors WHERE client_id LIKE 'server-%'"

# Check metrics count
mysql -u user -p$DB_PASSWORD admin_gebarenoverleg \
  -e "SELECT client_id, COUNT(*) FROM client_metrics WHERE client_id LIKE 'server-%' GROUP BY client_id"
```

---

## File Changes

### Modified:
- `/web/client_monitor_api/services/metrics_collector.py`
  - Lines 20-36: Added server IP detection
- `/web/client_monitor_dashboard/js/dashboard.js`
  - Lines 620-628: Moved metrics section above worker list
  - Lines 630-638: Renamed to "Worker Details"
  - Lines 677: Auto-load metrics on render
  - Lines 739-777: Updated loadMetricsForIP() function

### Database:
- Added 5 server-level client records
- Added 840 server-level metrics records

---

**Status**: ✅ Architecture Updated
**Date**: 2026-01-20 19:30
**Next Action**: Restart metrics collector service to use new server ID
**Test URL**: https://signcollect.nl/client_monitor_dashboard/
