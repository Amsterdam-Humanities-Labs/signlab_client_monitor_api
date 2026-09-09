"""Rotating log setup, so the scripts that heartbeat also stop filling the disk.

Every script that vendored a copy of the heartbeat client also hand-rolled its
own `logging.basicConfig(handlers=[FileHandler(...), StreamHandler(...)])`.
A plain `FileHandler` never rotates: `sync_vicon_rsync.log` and friends grow
until `checkDisk` - which reports through this same API - complains about the
partition they are on.

This is the one-line replacement, and it is here rather than in a repo of its
own because the same scripts already have to import this package.
"""

from __future__ import annotations

import logging
import os
import sys
from logging.handlers import RotatingFileHandler
from typing import Optional

#: 5 MB a file, 5 old files kept: at most 30 MB per log, which is small next to
#: anything on these machines and long enough to cover a week of cron runs.
DEFAULT_MAX_BYTES = 5 * 1024 * 1024
DEFAULT_BACKUP_COUNT = 5

DEFAULT_FORMAT = "[%(asctime)s] [%(levelname)s] %(message)s"


def setup_rotating_logger(
    path: str,
    name: Optional[str] = None,
    level: int = logging.INFO,
    max_bytes: int = DEFAULT_MAX_BYTES,
    backup_count: int = DEFAULT_BACKUP_COUNT,
    to_stream: bool = True,
    fmt: str = DEFAULT_FORMAT,
) -> logging.Logger:
    """Configure and return a logger that writes to a size-rotated file.

    Args:
        path: Log file. Its directory is created if missing.
        name: Logger name. `None` - the default - configures the root logger,
            which is what a script replacing `logging.basicConfig` wants, and
            what makes this package's own messages land in the same file.
        level: Threshold for both handlers.
        max_bytes: Rotate once the file passes this size.
        backup_count: How many rotated files to keep.
        to_stream: Also log to stdout, so cron mail and `journalctl` still show
            the run. Every copy this replaces did this; keep it.
        fmt: Format string.

    Calling it twice for the same logger replaces the handlers rather than
    adding a second set, so a script that is imported as well as run does not
    log everything twice.
    """
    directory = os.path.dirname(os.path.abspath(path))
    if directory:
        os.makedirs(directory, exist_ok=True)

    logger = logging.getLogger(name)
    logger.setLevel(level)

    for handler in list(logger.handlers):
        logger.removeHandler(handler)
        handler.close()

    formatter = logging.Formatter(fmt)

    file_handler = RotatingFileHandler(
        path, maxBytes=max_bytes, backupCount=backup_count, encoding="utf-8"
    )
    file_handler.setLevel(level)
    file_handler.setFormatter(formatter)
    logger.addHandler(file_handler)

    if to_stream:
        stream_handler = logging.StreamHandler(sys.stdout)
        stream_handler.setLevel(level)
        stream_handler.setFormatter(formatter)
        logger.addHandler(stream_handler)

    return logger
