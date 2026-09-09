"""Rotating log setup.

The implementation lives in `client.py`, because that file is vendored on its
own onto hosts that cannot install the package and a script that needs a
heartbeat generally needs a log file too. This module is the tidier import
path for code that does not care about that.
"""

from .client import (  # noqa: F401
    DEFAULT_BACKUP_COUNT,
    DEFAULT_FORMAT,
    DEFAULT_MAX_BYTES,
    setup_rotating_logger,
)

__all__ = ["setup_rotating_logger", "DEFAULT_MAX_BYTES", "DEFAULT_BACKUP_COUNT",
           "DEFAULT_FORMAT"]
