"""Client for the SignCollect client monitor API.

    from signlab_client_monitor import ClientMonitor

    monitor = ClientMonitor(client_id="my-job", client_name="My Job",
                            heartbeat_interval=3600)
    monitor.send_heartbeat_with_stats("success", "done", {"files": 42})

See README.md for what this replaces and how to migrate a call site.
"""

from .client import (
    DEFAULT_API_URL,
    DEFAULT_HEARTBEAT_INTERVAL,
    DEFAULT_TIMEOUT,
    ClientMonitor,
    Response,
)
from .logs import (
    DEFAULT_BACKUP_COUNT,
    DEFAULT_MAX_BYTES,
    setup_rotating_logger,
)

__all__ = [
    "ClientMonitor",
    "Response",
    "DEFAULT_API_URL",
    "DEFAULT_HEARTBEAT_INTERVAL",
    "DEFAULT_TIMEOUT",
    "setup_rotating_logger",
    "DEFAULT_MAX_BYTES",
    "DEFAULT_BACKUP_COUNT",
]

__version__ = "1.0.0"
