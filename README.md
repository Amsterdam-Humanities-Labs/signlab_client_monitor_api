# signlab_client_monitor_api
Registration and heartbeat API for SignCollect scripts and services, plus the `signlab-client-monitor` Python client.

## What it does
- A client registers once (`client_id`, name, `heartbeat_interval`), then POSTs heartbeats. Status is computed on read from `last_seen`: `<= 1.5 x interval` online, `<= 2.0 x` warning, else offline (per-client thresholds, these are the defaults).
- Clients can also submit metrics (CPU, I/O wait, disk, memory), kept 7 days and charted by the dashboard.
- `api.php?action=<name>` routes; POST bodies are JSON; `src/ClientMonitorService.php` holds the logic (prepared statements). Responses: `{success, data, errors}`.
- `client/` is the installable `signlab-client-monitor` package (`ClientMonitor`, `setup_rotating_logger`). See `client/README.md`.
- No authentication: anyone who can reach `api.php` can register, update or delete any client ([stack#31](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack/issues/31)).

| Action | Method | Params |
|---|---|---|
| `register` | POST | `client_id`, `client_name`, opt. `description`, `heartbeat_interval` (3600), `metadata`; duplicate id = error |
| `heartbeat` | POST | `client_id`, opt. `metadata` |
| `get_clients` / `get_client` / `get_stats` | GET | opt. `status=online\|warning\|offline` / `client_id` / none |
| `update_client` / `delete_client` | POST | `client_id` (+ fields); delete cascades to metrics |
| `submit_metrics` / `get_metrics` | POST / GET | `client_id`; `hours` (168) |

## Where it runs
- Production VPS, `/web/client_monitor_api`, `https://signcollect.nl/client_monitor_api/api.php` (hardcoded in every caller).
- Not on demo hosts (no `repos.tsv` row).

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
Not deployed by `interface_deploy`; TODO: document how production gets updated (probably by hand).

## Configuration
- `src/config.php`: not in git, no example. Defines the DB connection and `getDbConnection()`, `sendSuccess()`, `sendError()`.
- `services/metrics_collector.py`: `API_URL` hardcoded to production; needs `psutil`; reports as `server-<primary IP>` hourly. Falls back to `examples/python_client.py` (a byte copy of the package client) when the package is not installed.
- `examples/`: PHP and bash clients. A single shared PHP client is planned under `client/` ([stack#37](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack/issues/37)).

## Dependencies
- MySQL `admin_gebarenoverleg`: `client_monitors`, `client_metrics`.
- `signlab_client_monitor_dashboard`: the UI over this API.
- Callers: `signlab_pythonCron` (`checkDisk.py`, `rclone_monitor.py`, `sync_mocap_files.py`, vendored `python_client.py`), `signlab_viconSync`, `signlab_mocap`, `signlab_drs`, `signlab_zin`, `signlab_hh`, `signlab_annotation-editors` (Python package, vendored copies or inline PHP curl).
- Stack overview: https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack
