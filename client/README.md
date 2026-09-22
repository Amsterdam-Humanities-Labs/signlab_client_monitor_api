# signlab-client-monitor

The client half of the client monitor: what a script imports to heartbeat to `api.php` one directory up.

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

## Behaviour (pinned by `tests/`)
- Signature `(api_url, client_id, client_name, description, heartbeat_interval)`; `api_url` may be omitted and defaults to production.
- `register()` runs once by itself before the first heartbeat.
- Nothing raises; every call has a timeout. Methods return a `Response` dict that is falsy on failure.
- `from python_client import ClientMonitor` also resolves to this package, so the old `sys.path` call sites keep working.
- `logs.py` only re-exports `setup_rotating_logger` from `client.py`, so `client.py` can be vendored as one file.

## Install
```bash
client/install.sh    # pip --user --break-system-packages, or a plain copy into the user site dir
pip install --user --break-system-packages \
  "git+https://github.com/Amsterdam-Humanities-Labs/signlab_client_monitor_api@main#subdirectory=client"
```
Nothing breaks if it is never installed: vendored `python_client.py` copies (byte-identical to `signlab_client_monitor/client.py`, refresh command in their header) sit next to the scripts and win on `sys.path`.

## Test
```bash
cd client && python3 -m pytest tests -q    # no network; requests.post is replaced
```
