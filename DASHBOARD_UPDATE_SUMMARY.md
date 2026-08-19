# Dashboard Update Summary

## Changes Made

### 1. **Process List Now Collapsible** ✅
- Process lists are now hidden by default (collapsed)
- **Auto-expand for errors**: Cards with warning or offline clients automatically show process details
- Toggle button shows "Show/Hide Process Details"
- Button text updates dynamically when toggled

### 2. **Metrics Button More Prominent** ✅
- Changed from outline-secondary to **primary blue button**
- Renamed from "Historical Metrics" to "Show System Metrics (7 Days)"
- Button text toggles to "Hide System Metrics" when expanded
- Added margin-top for better spacing when metrics are visible

### 3. **Test Data Added** ✅
All three DRS clients now have 7 days of test metrics:
- `drs-file-mover`: 172 metrics
- `drs-converter`: 168 metrics
- `drs-crop-processor`: 168 metrics

## How It Works Now

### Dashboard Behavior:
1. **On page load**:
   - Process lists are collapsed (hidden)
   - Cards with errors (warning/offline) auto-expand to show problematic processes
   - Metrics button is visible as a blue primary button

2. **When clicking "Show Process Details"**:
   - Process list expands
   - Button text changes to "Hide Process Details"

3. **When clicking "Show System Metrics"**:
   - Button makes API call to fetch 7 days of metrics
   - Three charts appear: CPU Usage, Disk Usage, I/O Wait
   - Charts show line graphs with summary stats below
   - Button text changes to "Hide System Metrics"

## Testing the Dashboard

1. **View Dashboard**:
   ```
   https://signcollect.nl/client_monitor_dashboard/
   ```

2. **Check Process Lists**:
   - All online clients: Process list should be collapsed
   - Warning/offline clients: Process list should be expanded

3. **View Metrics**:
   - Click the blue "Show System Metrics (7 Days)" button
   - Should see 3 charts with 168 data points each
   - Charts should show:
     - CPU Usage: Blue line chart with avg & max percentages
     - Disk Usage: Orange line chart with avg percentage
     - I/O Wait: Purple line chart with avg percentage

## API Endpoints Tested

All working correctly:
```bash
# Get metrics for any client
curl 'https://signcollect.nl/client_monitor_api/api.php?action=get_metrics&client_id=drs-file-mover&hours=168'

# Submit new metrics
curl -X POST 'https://signcollect.nl/client_monitor_api/api.php?action=submit_metrics' \
  -H 'Content-Type: application/json' \
  -d '{"client_id":"drs-file-mover","metrics":{"cpu_percent":45.2,"disk_usage_percent":68.5}}'
```

## Metrics Collector Service

**Status**: Running ✅
- Service: `client-monitor-metrics.service`
- User: `gomer`
- Collection interval: Every 1 hour
- Next collection: Top of the next hour

**To view logs**:
```bash
sudo journalctl -u client-monitor-metrics -f
tail -f /var/log/client_monitor_metrics.log
```

**To trigger immediate collection** (for testing):
You can temporarily reduce the interval in the Python script and restart:
```bash
nano /web/client_monitor_api/services/metrics_collector.py
# Change: COLLECTION_INTERVAL = 3600
# To:     COLLECTION_INTERVAL = 300  # 5 minutes
sudo systemctl restart client-monitor-metrics
```

## File Changes Summary

### Modified:
- `/web/client_monitor_dashboard/js/dashboard.js`
  - Lines 583-704: Updated `renderIPGroups()` function
  - Added collapsible process lists with auto-expand for errors
  - Added dynamic button text toggling
  - Changed metrics button to primary style

### Database:
- Added 504 metrics records across 3 clients (7 days each)
- Total metrics in system: 508 records

## Features Working:

✅ Process list collapsible by default
✅ Auto-expand for warning/offline clients
✅ Blue prominent metrics button
✅ Dynamic button text (Show/Hide)
✅ Three metrics charts (CPU, Disk, I/O Wait)
✅ 7-day historical data
✅ Summary statistics below charts
✅ Metrics collector service running
✅ API endpoints functional
✅ Test data populated

## Next Steps (Optional):

1. **Wait for live data**: The metrics collector will submit its first datapoint at the top of the next hour
2. **Reduce collection interval**: Edit the Python script to collect more frequently for testing
3. **Add more clients**: Any new client that submits metrics will automatically show charts
4. **Customize charts**: Modify chart colors, time ranges, or add more metrics in `dashboard.js`

---

**Status**: ✅ All features working as requested
**Date**: 2026-01-20 19:20
**Test URL**: https://signcollect.nl/client_monitor_dashboard/
