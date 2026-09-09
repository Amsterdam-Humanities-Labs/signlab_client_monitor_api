# signlab-client-monitor

The client half of the client monitor: the thing a script imports to say
"I am still alive". It talks to `api.php` one directory up.

```python
from signlab_client_monitor import ClientMonitor

monitor = ClientMonitor(
    client_id="check-disk",
    client_name="Disk Space Monitor",
    description="Monitors disk space and alerts when it runs low",
    heartbeat_interval=3600,
)

try:
    free_gb = do_the_work()
    monitor.send_heartbeat_with_stats("success", "all mounts healthy",
                                      {"free_gb": free_gb})
except Exception as exc:
    monitor.send_heartbeat_with_stats("error", f"failed: {exc}")
```

Registration happens by itself before the first heartbeat, so there is no
one-off setup step to forget. `send_heartbeat_with_stats(status, message,
stats)` is what nearly every caller actually uses; `register()`,
`send_heartbeat()` and `submit_metrics()` are there when you need them.

## Why this exists

This class existed in **six different versions across nine files**, reached by
`sys.path.insert(0, '/home/gomer/pythonCron')` — a hardcoded path on one
particular server — from eight call sites in three repositories. The drift was
not cosmetic:

| Copy | How it differed |
|---|---|
| `examples/python_client.py` (canonical) | no timeout; **raises** on any API failure |
| `signlab_viconSync/python_client.py` | byte-identical to the canonical one |
| `signlab_pythonCron/python_client.py` | moved `api_url` from first argument to last, defaulted it, auto-registered in `__init__`, returned `bool` |
| `ftp_monitor.py:30` (inline) | added a timeout and caught everything; logged instead of printing |
| `glb_matcher.py:28` (inline) | the same two fixes again, at different log levels |
| `sync_vicon_rsync.py:128` (inline) | the same fixes a third time, and **deleted `register()` entirely** |

The same two repairs — a request timeout, and not letting a monitoring failure
kill the job being monitored — were reinvented four times, with three different
return conventions. This package is those repairs, once.

## Compatibility with the copies it replaces

Every existing call site keeps working. The tests in `tests/` assert this
file by file; the short version:

- **Argument order is the canonical one**, `(api_url, client_id, client_name,
  description, heartbeat_interval)`, so the one positional call
  (`services/metrics_collector.py`) still binds correctly. Under pythonCron's
  reordered signature it would have filed every metric against a client whose
  id was a URL.
- **`api_url` may be omitted** and defaults to production, as in pythonCron's
  copy, so callers written against that one keep working too.
- **`register()` is called automatically once**, immediately before the first
  heartbeat rather than in `__init__`. Callers that relied on pythonCron's
  auto-registration get it; the four that call `register()` themselves do not
  register twice.
- **The return value is a `dict` that is falsy on failure.** Three copies
  returned the response dict, two returned `bool`; `Response` is both, so
  `result["data"]` and `if result:` are each right.
- **Nothing raises.** Every method catches everything and returns a falsy
  `Response`. A timeout is always set.
- **A `python_client` module ships with the package**, so
  `from python_client import ClientMonitor` — the line under all eight
  `sys.path` hacks — resolves to this code once the vendored file beside it is
  gone, and to the vendored file until then. That module name is the seam that
  lets a machine be migrated whenever it is convenient rather than all at once.

## Rotating logs

Every script that vendored the client also hand-rolled a
`logging.basicConfig(...)` with a plain `FileHandler`, which never rotates.
`setup_rotating_logger` replaces that with one line:

```python
from signlab_client_monitor import setup_rotating_logger

setup_rotating_logger("/home/gomer/viconSync/logs/sync.log")
```

5 MB per file, 5 files kept, still echoed to stdout so cron mail and
`journalctl` are unchanged. Configuring the root logger this way also captures
the heartbeat client's own messages, so a failed heartbeat is in the same file
as the run that failed.

## Installing it

This estate has no build step anywhere in its deploy, and the machines run
Ubuntu with a PEP 668 `EXTERNALLY-MANAGED` marker, so a plain `pip install`
refuses. `install.sh` handles both facts:

```bash
client/install.sh
```

It tries `pip install --user --break-system-packages .` and, if pip is not
usable at all, copies the two importable trees into the user site directory —
which is on `sys.path` already, needs no build, no network and no root. Either
way the result is the same files in the same place, and it is idempotent.

To install onto a machine that does not have this repository:

```bash
pip install --user --break-system-packages \
  "git+https://github.com/Amsterdam-Humanities-Labs/signlab_client_monitor_api@main#subdirectory=client"
```

Pin `@main` to a tag when there is a reason to.

**Nothing breaks if this is never run.** The vendored `python_client.py` files
are still in place next to the scripts that import them, and a vendored copy
earlier on `sys.path` wins over an installed package. That is deliberate: the
failure mode of an install nobody runs is "everything carries on as before",
not a 500 page. It is also why the vendored copies are now byte-mirrors of
`signlab_client_monitor/client.py` rather than six divergent files — see the
header of any of them for the one-line refresh command.

## Testing

```bash
cd client && python3 -m pytest tests -q
```

No network: the tests replace `requests.post`. They are worth reading before
changing the signature, because most of them exist to pin one real call site.
