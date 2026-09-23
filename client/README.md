# signlab-client-monitor
The client side of the client monitor. A script imports it to send heartbeats to `api.php` in the parent folder.

```python
from signlab_client_monitor import ClientMonitor, setup_rotating_logger

setup_rotating_logger("/home/gomer/viconSync/logs/sync.log")   # 5 MB x 5 files, also to console
monitor = ClientMonitor(client_id="check-disk", client_name="Disk Space Monitor",
                        description="Monitors disk space", heartbeat_interval=3600)
try:
    monitor.send_heartbeat_with_stats("success", "all mounts healthy", {"free_gb": do_the_work()})
except Exception as exc:
    monitor.send_heartbeat_with_stats("error", f"failed: {exc}")
```

## Behaviour (fixed by `tests/`)
- Signature: `(api_url, client_id, client_name, description, heartbeat_interval)`. Without `api_url` it uses the core server.
- `register()` runs once, by itself, before the first heartbeat.
- No call raises, and every call has a timeout. Without `requests` installed it still imports; sends then only log a warning. Methods return a `Response` dict that is falsy on failure.
- `from python_client import ClientMonitor` also resolves to this package, so old `sys.path` call sites keep working.
- `logs.py`, `checks.py` and `alert.py` only re-export from `client.py`. That way `client.py` can be vendored as one file.
- `disk_usage(path)` returns bytes as `df -B1` does, plus `free_percent` and `used_percent` (psutil's `percent`, not rounded). It raises if the path is unreadable.
- `mount_responds(path)` runs `ls` with a 5 s timeout. `mount_read_write(mount, test_dir)` writes, reads back and deletes a file; it raises on failure.
- `send_alert(title, msg, level, channels=("discord", "mailjet"))` reads credentials only from the environment: `DISCORD_WEBHOOK_URL`, or `DISCORD_BOT_TOKEN` + `DISCORD_CHANNEL_ID`; `MAILJET_API_KEY` + `MAILJET_SECRET_KEY`. Addresses come as arguments or from `ALERT_EMAIL_FROM`/`ALERT_EMAIL_TO`. It skips unconfigured channels and never raises.

## Install
```bash
client/install.sh    # pip --user --break-system-packages, or a plain copy into the user site dir
pip install --user --break-system-packages \
  "git+https://github.com/Amsterdam-Humanities-Labs/signlab_client_monitor_api@main#subdirectory=client"
```
Installing is optional. Vendored `python_client.py` copies sit next to the scripts and win on `sys.path`. They are byte-identical to `signlab_client_monitor/client.py`; the refresh command is in their header.

## Test
```bash
cd client && python3 -m pytest tests -q    # no network; requests.post is replaced
```
