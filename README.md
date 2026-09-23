# signlab_client_monitor_api
Registration and heartbeat API for SignCollect scripts and services, plus the `signlab-client-monitor` Python client.

## What it does
- A client registers once (`client_id`, name, `heartbeat_interval`), then POSTs heartbeats.
- Status is computed on read from `last_seen`: online up to 1.5 x the interval, warning up to 2.0 x, else offline. These are the defaults; each client can have its own thresholds.
- Clients can also submit metrics (CPU, I/O wait, disk, memory). They are kept 7 days and charted by the dashboard.
- `api.php?action=<name>` routes requests; POST bodies are JSON. `src/ClientMonitorService.php` holds the logic (prepared statements). Responses are `{success, data, errors}`.
- `client/` is the installable `signlab-client-monitor` package: `ClientMonitor`, `setup_rotating_logger`, disk and mount checks, and `send_alert` (Discord, Mailjet). It imports without `requests`; sends then only log a warning. See `client/README.md`.
- Auth ([stack#31](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack/issues/31)): `register`, `heartbeat` and `submit_metrics` are open for machine clients. All other actions need the dashboard login (PHP session) and return 401 without it.

| Action | Method | Parameters |
|---|---|---|
| `register` | POST | `client_id`, `client_name`, optional `description`, `heartbeat_interval` (3600), `metadata`; a duplicate id is an error |
| `heartbeat` | POST | `client_id`, optional `metadata` |
| `get_clients` / `get_client` / `get_stats` | GET | optional `status=online\|warning\|offline` / `client_id` / none |
| `update_client` / `delete_client` | POST | `client_id` (plus fields); delete also removes the client's metrics |
| `submit_metrics` / `get_metrics` | POST / GET | `client_id`; `hours` (168) |

## Where it runs
- Core server: `/web/client_monitor_api`, `https://signcollect.nl/client_monitor_api/api.php`. Every caller hardcodes this URL.
- Not on the demo hosts (no `repos.tsv` row).

## Status
Production.

## How to run / deploy
```bash
mysql admin_gebarenoverleg < migrations/001_create_client_monitors_table.sql
mysql admin_gebarenoverleg < migrations/002_create_metrics_table.sql   # needs the MySQL event scheduler
client/install.sh                                                      # optional: the Python package
sudo install -m 644 services/client-monitor-metrics.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now client-monitor-metrics
cd client && python3 -m pytest tests -q
```
`interface_deploy` does not deploy it. TODO: document how the core server gets updated (probably by hand).

## Configuration
- `src/config.php` (not in git, no example) defines the database connection and `getDbConnection()`, `sendSuccess()`, `sendError()`.
- `services/metrics_collector.py` hardcodes `API_URL` to the core server and needs `psutil`. It reports hourly as `server-<primary IP>`. Without the package it falls back to `examples/python_client.py`, a byte-identical copy of the package client.
- `examples/` has a bash client. The PHP client in use is `php_client.php` in signlab_pythonCron (used by `mysql_backup.php`).

## Dependencies
- MySQL `admin_gebarenoverleg`: `client_monitors`, `client_metrics`.
- [signlab_client_monitor_dashboard](https://github.com/Amsterdam-Humanities-Labs/signlab_client_monitor_dashboard): the UI for this API.
- Callers use the Python package, a vendored copy or inline PHP curl: [signlab_pythonCron](https://github.com/Amsterdam-Humanities-Labs/signlab_pythonCron) (`check_disk.py`, `rclone_monitor.py`, `sync_mocap_files.py`, vendored `python_client.py`), [signlab_viconSync](https://github.com/Amsterdam-Humanities-Labs/signlab_viconSync), [signlab_mocap](https://github.com/Amsterdam-Humanities-Labs/signlab_mocap), [signlab_drs](https://github.com/Amsterdam-Humanities-Labs/signlab_drs), [signlab_zin](https://github.com/Amsterdam-Humanities-Labs/signlab_zin), [signlab_hh](https://github.com/Amsterdam-Humanities-Labs/signlab_hh), [signlab_annotation-editors](https://github.com/Amsterdam-Humanities-Labs/signlab_annotation-editors).
- Stack overview: [signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack).
