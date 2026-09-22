"""Compatibility shim for the vendored `python_client.py` this package replaces.

Eight call sites reach the heartbeat client with

    sys.path.insert(0, '/home/gomer/pythonCron')
    from python_client import ClientMonitor

which is a hardcoded path on one particular server. Installing this package
puts a `python_client` module on the normal import path, so those two lines
keep working once the vendored file next to them is deleted - and keep working
in the meantime, because a vendored copy earlier on `sys.path` still wins.

Nothing new should import this. New code imports `signlab_client_monitor`.
"""

from signlab_client_monitor.client import (  # noqa: F401
    DEFAULT_API_URL,
    DEFAULT_HEARTBEAT_INTERVAL,
    DEFAULT_TIMEOUT,
    ClientMonitor,
    Response,
    disk_usage,
    mount_read_write,
    mount_responds,
    send_alert,
    setup_rotating_logger,
)

__all__ = ["ClientMonitor", "Response", "DEFAULT_API_URL",
           "DEFAULT_HEARTBEAT_INTERVAL", "DEFAULT_TIMEOUT", "disk_usage",
           "mount_responds", "mount_read_write", "send_alert",
           "setup_rotating_logger"]
