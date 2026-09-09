"""What these tests are for: the six copies had four different signatures and
three different return conventions, and the call sites were written against
whichever copy they had. The interesting assertions here are therefore not
"does a heartbeat work" but "does every existing call site still work" - each
of the compatibility tests names the file it is standing in for.
"""

import json
import logging
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from signlab_client_monitor import ClientMonitor, Response, setup_rotating_logger
from signlab_client_monitor import client as client_module


class FakeResponse:
    def __init__(self, status_code=200, body=None, text=None):
        self.status_code = status_code
        self._body = body
        self.text = text if text is not None else json.dumps(body)

    def json(self):
        if self._body is None:
            raise ValueError("no json")
        return self._body


@pytest.fixture
def posts(monkeypatch):
    """Capture every POST the client makes and reply with success."""
    calls = []

    def fake_post(url, json=None, headers=None, timeout=None):
        calls.append({"url": url, "json": json, "timeout": timeout})
        return FakeResponse(200, {"success": True, "data": {"status": "online"},
                                  "errors": []})

    monkeypatch.setattr(client_module.requests, "post", fake_post)
    return calls


def actions(calls):
    return [c["url"].rsplit("action=", 1)[1] for c in calls]


# -- signature compatibility with the copies this replaces -----------------

def test_keyword_call_site_still_works(posts):
    """checkDisk.py, rclone_monitor.py, sync_mocap_files.py, matchVicon.py,
    convert.py, matchRecords.py, video_converter.py, zin_backup.py,
    move_studiofiles.py - all pass every argument by keyword."""
    monitor = ClientMonitor(
        api_url="https://signcollect.nl/client_monitor_api/api.php",
        client_id="check-disk",
        client_name="Disk Space Monitor",
        description="Monitors disk space",
        heartbeat_interval=3600,
    )
    assert monitor.send_heartbeat_with_stats("success", "ok", {"free_gb": 1})
    assert actions(posts) == ["register", "heartbeat"]


def test_positional_call_site_still_binds_correctly(posts):
    """metrics_collector.py:51 is the one positional call:
    ClientMonitor(api_url, client_id, client_name, heartbeat_interval=...).
    Under pythonCron's reordered signature api_url would silently become the
    client_id, and every metric would be filed against a client named after a
    URL."""
    monitor = ClientMonitor(
        "https://signcollect.nl/client_monitor_api/api.php",
        "server-10.0.0.1",
        "Server Metrics (10.0.0.1)",
        heartbeat_interval=3600,
    )
    assert monitor.api_url.endswith("api.php")
    assert monitor.client_id == "server-10.0.0.1"
    assert monitor.client_name == "Server Metrics (10.0.0.1)"


def test_api_url_may_be_omitted(posts):
    """pythonCron's copy defaulted api_url to production and dropped it from
    the front of the signature; anything written against that copy omits it."""
    monitor = ClientMonitor(client_id="some-job", client_name="Some Job")
    monitor.send_heartbeat()
    assert posts[0]["url"].startswith(client_module.DEFAULT_API_URL)


def test_client_id_is_required():
    with pytest.raises(ValueError):
        ClientMonitor(api_url="https://example.invalid/api.php")


def test_shim_module_exports_the_same_class():
    """`from python_client import ClientMonitor` after the vendored file is
    gone must find the same class, not a lookalike."""
    import python_client

    assert python_client.ClientMonitor is ClientMonitor


# -- return convention ------------------------------------------------------

def test_response_is_a_dict_and_a_bool(posts):
    monitor = ClientMonitor(client_id="job", auto_register=False)
    result = monitor.send_heartbeat()
    assert isinstance(result, dict)
    assert result["data"]["status"] == "online"
    assert result is not True and bool(result) is True


def test_failed_response_is_falsy(monkeypatch):
    """The two copies that returned bool returned False on failure. A plain
    dict would be truthy here, which is the bug this avoids."""
    monkeypatch.setattr(
        client_module.requests, "post",
        lambda *a, **k: FakeResponse(400, {"success": False, "data": None,
                                           "errors": ["nope"]}))
    monitor = ClientMonitor(client_id="job", auto_register=False)
    result = monitor.send_heartbeat()
    assert not result
    assert result.errors == ["nope"]


# -- monitoring must not be able to kill the monitored job ------------------

def test_network_failure_does_not_raise(monkeypatch):
    def boom(*args, **kwargs):
        raise client_module.requests.exceptions.ConnectionError("no route")

    monkeypatch.setattr(client_module.requests, "post", boom)
    monitor = ClientMonitor(client_id="job")
    assert not monitor.send_heartbeat_with_stats("success", "done")


def test_html_error_page_does_not_raise(monkeypatch):
    """Apache returning its 500 page instead of JSON used to be a traceback
    inside the script being monitored."""
    monkeypatch.setattr(
        client_module.requests, "post",
        lambda *a, **k: FakeResponse(500, None, "<html>Internal Error</html>"))
    monitor = ClientMonitor(client_id="job", auto_register=False)
    result = monitor.send_heartbeat()
    assert not result
    assert "not JSON" in result.errors[0]


def test_every_request_has_a_timeout(posts):
    monitor = ClientMonitor(client_id="job")
    monitor.send_heartbeat()
    assert all(call["timeout"] == 10 for call in posts)


# -- registration -----------------------------------------------------------

def test_registers_once_before_the_first_heartbeat(posts):
    """pythonCron's copy registered in __init__ so its callers never had to;
    keep that, but only once."""
    monitor = ClientMonitor(client_id="job")
    monitor.send_heartbeat()
    monitor.send_heartbeat()
    assert actions(posts) == ["register", "heartbeat", "heartbeat"]


def test_explicit_register_does_not_double_register(posts):
    """compress_blackmagic.py:237, ftp_monitor.py:809, glb_matcher.py:157 and
    metrics_collector.py:128 call register() themselves."""
    monitor = ClientMonitor(client_id="job")
    monitor.register({"version": "2.0"})
    monitor.send_heartbeat()
    assert actions(posts) == ["register", "heartbeat"]
    assert posts[0]["json"]["metadata"] == {"version": "2.0"}


def test_duplicate_registration_is_success(monkeypatch):
    """The API rejects a known client_id as an error. That is what an
    already-registered client looks like on every run after the first."""
    monkeypatch.setattr(
        client_module.requests, "post",
        lambda *a, **k: FakeResponse(
            400, {"success": False, "data": None,
                  "errors": ["Client with this client_id already exists"]}))
    monitor = ClientMonitor(client_id="job", auto_register=False)
    assert monitor.register()


def test_auto_register_can_be_turned_off(posts):
    monitor = ClientMonitor(client_id="job", auto_register=False)
    monitor.send_heartbeat()
    assert actions(posts) == ["heartbeat"]


# -- wire format ------------------------------------------------------------

def test_action_goes_in_the_query_string_not_the_body(posts):
    monitor = ClientMonitor(client_id="job", auto_register=False)
    monitor.send_heartbeat()
    assert posts[0]["url"].endswith("?action=heartbeat")
    assert "action" not in posts[0]["json"]


def test_stats_are_merged_into_the_metadata_blob(posts):
    monitor = ClientMonitor(client_id="job", auto_register=False)
    monitor.send_heartbeat_with_stats("warning", "2 failed", {"ok": 8, "err": 2})
    metadata = posts[0]["json"]["metadata"]
    assert metadata["status"] == "warning"
    assert metadata["message"] == "2 failed"
    assert metadata["ok"] == 8 and metadata["err"] == 2
    assert "last_run" in metadata and "hostname" in metadata


def test_submit_metrics_posts_the_sample(posts):
    monitor = ClientMonitor(client_id="server-1", auto_register=False)
    assert monitor.submit_metrics({"cpu_percent": 12.5, "disk_usage_percent": 71})
    assert actions(posts) == ["submit_metrics"]
    assert posts[0]["json"]["metrics"]["cpu_percent"] == 12.5


def test_register_sends_the_interval_the_api_derives_status_from(posts):
    monitor = ClientMonitor(client_id="job", heartbeat_interval=86400)
    monitor.register()
    assert posts[0]["json"]["heartbeat_interval"] == 86400


# -- rotating logs (#21) ----------------------------------------------------

def test_rotating_logger_rotates(tmp_path):
    path = tmp_path / "logs" / "job.log"
    logger = setup_rotating_logger(str(path), name="rotate-test",
                                   max_bytes=200, backup_count=2,
                                   to_stream=False)
    for i in range(200):
        logger.info("a line that is long enough to force rotation %d", i)

    assert path.exists()
    assert (tmp_path / "logs" / "job.log.1").exists()
    assert not (tmp_path / "logs" / "job.log.3").exists(), "backup_count ignored"
    assert path.stat().st_size < 2000


def test_rotating_logger_is_idempotent(tmp_path):
    path = tmp_path / "job.log"
    setup_rotating_logger(str(path), name="idem-test", to_stream=False)
    logger = setup_rotating_logger(str(path), name="idem-test", to_stream=False)
    assert len(logger.handlers) == 1


def test_client_logs_into_the_rotating_file(tmp_path, posts):
    """The point of shipping both halves together: configure the root logger
    once and the heartbeat client's own messages land in the same file."""
    path = tmp_path / "job.log"
    setup_rotating_logger(str(path), to_stream=False)
    try:
        ClientMonitor(client_id="job").send_heartbeat()
        assert "registered: job" in path.read_text()
    finally:
        logging.getLogger().handlers.clear()
