"""Discord and Mailjet alerts, credentials from the environment.

The implementation lives in `client.py` so that the one vendored file carries
it; see `logs.py` for why.
"""

from .client import ALERT_LEVELS, send_alert  # noqa: F401

__all__ = ["send_alert", "ALERT_LEVELS"]
