# client_monitor_api

Registration and heartbeat API for the long-running scripts, cron jobs and
services that make up the SignCollect infrastructure — the thing they call to
say "I am still alive", and the thing that notices when they stop.

## What it does

A client — any script, anywhere — registers itself once with a `client_id`, a
display name and the interval at which it intends to check in. It then POSTs a
heartbeat at that interval. The API stores the `last_seen` timestamp and, on
every read, derives the client's current status from how long ago that was:

    age ≤ interval × warning_threshold (1.5)  → online
    age ≤ interval × offline_threshold (2.0)  → warning
    otherwise, or never seen                  → offline

Status is therefore always *computed*, never merely stored; the `status` column
is a cache that `formatClient()` writes back when it disagrees with the
computed value. Nothing has to run on a timer for a dead client to show as
dead.

On top of that, clients may submit **system metrics** — CPU, I/O wait, disk and
memory — which are kept for seven days in a second table and charted by the
dashboard.

`api.php` is a single router; `src/ClientMonitorService.php` holds all the
logic and every query is a prepared statement.

### Endpoints

All responses are JSON of the shape `{success, data, errors}`.

| Action | Method | Purpose |
|---|---|---|
| `register` | POST | Create a client. `client_id` and `client_name` required; `description`, `heartbeat_interval` (default 3600s) and a free-form `metadata` object optional. A duplicate `client_id` is an error, not an update. |
| `heartbeat` | POST | Refresh `last_seen` for a `client_id`. Optional `metadata` replaces the stored blob. |
| `get_clients` | GET | All clients, optionally `&status=online\|warning\|offline`. |
| `get_client` | GET | One client, by `client_id`. |
| `update_client` | POST | Change name, description or `heartbeat_interval`. |
| `delete_client` | POST | Remove a client. Its metrics go with it (`ON DELETE CASCADE`). |
| `get_stats` | GET | Counts per status, for the dashboard's summary cards. |
| `submit_metrics` | POST | Store one metrics sample for a `client_id`. |
| `get_metrics` | GET | Metrics history for a `client_id`, `&hours=` (default 168, i.e. one week). |

The long-form request/response reference, with worked `curl` examples and
client snippets in three languages, lives in the README of
[`signlab_client_monitor_dashboard`](https://github.com/Amsterdam-Humanities-Labs/signlab_client_monitor_dashboard),
which documents the two halves as one system. `examples/` here holds a PHP and
a bash client; the Python one has moved (see below).

## The Python client

`client/` is an installable package, `signlab-client-monitor`, holding the
`ClientMonitor` class that the monitored scripts import. It lives here rather
than in a repository of its own because it is not a deployable - it is the
other end of this API's wire protocol, and versioning the two together is the
only way they cannot drift apart. That drift is not hypothetical: the class
existed in six different versions across nine files, reached from eight call
sites by `sys.path.insert(0, '/home/gomer/pythonCron')`, and the same two fixes
- a request timeout, and not letting a monitoring failure kill the monitored
job - had been reinvented four times. `client/README.md` has the table.

```bash
client/install.sh          # pip where pip works, a plain copy where it does not
```

`examples/python_client.py` is now a verbatim vendored copy of the package's
`client.py`, kept so that a host which has never run the installer can still
import it. Nothing here needs a build step, and nothing breaks if the installer
is never run.

**There is no authentication.** Any caller that can reach `api.php` can
register, heartbeat, update or delete any client. That is a known property, not
an oversight to discover in production.

## Where it runs

The **signcollect core server** (the production VPS), at
`/web/client_monitor_api`, reachable as
`https://signcollect.nl/client_monitor_api/api.php`. That URL is hardcoded in
every caller — the examples here, `services/client-monitor-metrics.service`,
and `checkDisk.py` / `rclone_monitor.py` / `sync_mocap_files.py` in
`signlab_pythonCron`.

It is **not deployed to the demo hosts** (`dev2`, `dev-1`): there is no row for
it in `interface_deploy/scripts/repos.tsv`, so a demo has no client monitor and
the dashboard's API calls would 404 there.

TODO: confirm how the production copy is updated — no script in
`interface_deploy/` touches it, so presumably by hand on the host.

## Status

**Production**, and modest. It has run since early 2026 and the schema has had
one addition (`client_metrics`).

`create_sample_data.php`, `test.php` and `test_api.php` in the root are
development aids — `create_sample_data.php` **writes rows** and should not be
run against the live database. The four `*_SUMMARY.md` / `*_UPDATE.md` files
are point-in-time development notes kept for history; where they disagree with
the code, the code is right.

## How to deploy it

Not through `repos.tsv`. Place the tree at `/web/client_monitor_api`, apply the
migrations in order, write `src/config.php`, and — if you want host metrics —
install the collector unit:

```bash
mysql admin_gebarenoverleg < migrations/001_create_client_monitors_table.sql
mysql admin_gebarenoverleg < migrations/002_create_metrics_table.sql

client/install.sh          # the ClientMonitor package the collector imports
sudo install -m 644 services/client-monitor-metrics.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now client-monitor-metrics
```

`client/install.sh` is optional for the collector - it falls back to the
vendored `examples/python_client.py` if the package is absent - but it is what
lets every other script on the host drop its `sys.path` hack.

`002` also creates a MySQL `EVENT` that deletes metrics older than seven days,
so it needs the event scheduler enabled. `services/metrics_collector.py`
requires `psutil`, identifies itself as `server-<primary IP>`, and submits one
sample an hour.

## Configuration

| File | Where it comes from |
|---|---|
| `src/config.php` | **Not in git** (`.gitignore` excludes `config.php`) and there is no example committed. It must define the database connection and the `getDbConnection()`, `sendSuccess()` and `sendError()` helpers that `api.php` calls. TODO: add a `config.example.php` — right now a fresh checkout cannot be brought up without copying this file off a running host. |
| database | `admin_gebarenoverleg`, on the same host. |
| `API_URL` in `services/metrics_collector.py` | Hardcoded to production. Edit it, or do not install the collector, on any other host. |

Git history was rewritten once to purge a committed database credential; treat
that as a reminder rather than as a solved problem.

## Dependencies

- **MySQL `admin_gebarenoverleg`** — tables `client_monitors` and
  `client_metrics`. Shared with the rest of the estate.
- **`signlab_client_monitor_dashboard`** — the UI over this API. It calls
  `/client_monitor_api/api.php` from the browser and holds the combined
  end-user documentation.
- **`signlab_pythonCron`** — three of its job scripts register and heartbeat
  here, which is what most of the client list is.
- **`psutil`** — only for `services/metrics_collector.py`.
