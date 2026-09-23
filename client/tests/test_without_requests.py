"""Demo hosts have no `requests`. The client must still import there, because
scheduler_v2.py and friends import it for setup_rotating_logger; sends must
degrade to a logged warning. (stijn: python-scheduler crash-looped on this.)"""

import importlib.util
import sys
from pathlib import Path

CLIENT = Path(__file__).resolve().parent.parent / "signlab_client_monitor" / "client.py"


def load_without_requests(monkeypatch):
    monkeypatch.setitem(sys.modules, "requests", None)  # makes `import requests` fail
    spec = importlib.util.spec_from_file_location("client_no_requests", CLIENT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def test_imports_and_logs_without_requests(monkeypatch, tmp_path):
    client = load_without_requests(monkeypatch)
    assert client.requests is None
    log = client.setup_rotating_logger(str(tmp_path / "x.log"), name="no-req",
                                       to_stream=False)
    log.info("still logging")
    for h in log.handlers:
        h.flush()
    assert "still logging" in (tmp_path / "x.log").read_text()
    assert client.disk_usage(str(tmp_path))["total"] > 0


def test_sends_fail_softly_without_requests(monkeypatch, caplog):
    client = load_without_requests(monkeypatch)
    monitor = client.ClientMonitor(client_id="c", client_name="C")
    result = monitor.send_heartbeat()
    assert not result and "not installed" in result.errors[0]
    assert client.send_alert("t", "m", channels=("discord",),
                             env={"DISCORD_WEBHOOK_URL": "https://x"}) is False
    assert "not installed" in caplog.text
