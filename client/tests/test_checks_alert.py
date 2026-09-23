"""Disk/mount checks and alerts. Each test pins what the script it replaced
did, so moving a script onto these cannot change what it reports."""

import shutil
import subprocess
import sys
from collections import namedtuple
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from signlab_client_monitor import (disk_usage, mount_read_write, mount_responds,
                                    send_alert)
from signlab_client_monitor import client as client_module

Usage = namedtuple("Usage", "total used free")


# -- disk_usage -------------------------------------------------------------

def test_disk_usage_matches_shutil(tmp_path):
    d = disk_usage(str(tmp_path))
    u = shutil.disk_usage(str(tmp_path))
    assert (d["total"], d["used"], d["free"]) == (u.total, u.used, u.free)


def test_disk_percentages(monkeypatch):
    """free_percent is checkDisk/server_monitor's free/total; used_percent is
    psutil's percent (watchdog_daemon, metrics_collector) before rounding."""
    monkeypatch.setattr(client_module.shutil, "disk_usage",
                        lambda p: Usage(1000, 700, 250))  # 50 reserved for root
    d = disk_usage("/")
    assert d["free_percent"] == 250 / 1000 * 100
    assert round(d["used_percent"], 1) == 73.7  # psutil: 700 / (700 + 250)


def test_disk_usage_raises_on_missing_path(tmp_path):
    with pytest.raises(OSError):
        disk_usage(str(tmp_path / "nope"))


# -- mount_responds (rclone_monitor.test_mount_accessible) -------------------

class Run:
    def __init__(self, returncode=0, stdout="", stderr=""):
        self.returncode, self.stdout, self.stderr = returncode, stdout, stderr


@pytest.mark.parametrize("run,expected", [
    (Run(0), (True, None)),
    (Run(2, stderr="ls: x: Transport endpoint is not connected"),
     (False, "Mount disconnected (FUSE endpoint not connected)")),
    (Run(124), (False, "Mount not responding (timeout)")),
    (Run(2, stderr=" boom \n"), (False, "Mount error: boom")),
])
def test_mount_responds(monkeypatch, run, expected):
    seen = {}

    def fake_run(cmd, **kw):
        seen["cmd"], seen["timeout"] = cmd, kw["timeout"]
        return run

    monkeypatch.setattr(client_module.subprocess, "run", fake_run)
    assert mount_responds("/mnt/x") == expected
    assert seen == {"cmd": ["timeout", "5", "ls", "/mnt/x"], "timeout": 6}


def test_mount_responds_hang(monkeypatch):
    def hang(cmd, **kw):
        raise subprocess.TimeoutExpired(cmd, kw["timeout"])
    monkeypatch.setattr(client_module.subprocess, "run", hang)
    assert mount_responds("/mnt/x") == (False, "Mount not responding (timeout)")


def test_mount_responds_on_a_real_directory(tmp_path):
    if not shutil.which("timeout"):
        pytest.skip("no coreutils timeout")
    assert mount_responds(str(tmp_path)) == (True, None)


# -- mount_read_write (server_monitor.check_rclone_mount) -------------------

def test_mount_read_write_requires_a_mount(tmp_path):
    with pytest.raises(RuntimeError, match="is not a mount point"):
        mount_read_write(str(tmp_path), str(tmp_path / "sc_test"))


def test_mount_read_write_round_trip(tmp_path, monkeypatch):
    monkeypatch.setattr(client_module.os.path, "ismount", lambda p: True)
    test_dir = tmp_path / "sc_test"
    assert mount_read_write(str(tmp_path), str(test_dir)) is None
    assert test_dir.is_dir()
    assert not (test_dir / "monitor_test.txt").exists()


def test_mount_read_write_hang_is_a_timeout(tmp_path, monkeypatch):
    monkeypatch.setattr(client_module.os.path, "ismount", lambda p: True)

    def hang(cmd, **kw):
        raise subprocess.TimeoutExpired(cmd, kw["timeout"])
    monkeypatch.setattr(client_module.subprocess, "run", hang)
    with pytest.raises(subprocess.TimeoutExpired):
        mount_read_write(str(tmp_path), str(tmp_path / "t"))


# -- send_alert --------------------------------------------------------------

class Resp:
    def __init__(self, status_code, text=""):
        self.status_code, self.text = status_code, text


class Calls(list):
    pass


@pytest.fixture
def posts(monkeypatch):
    calls = Calls()
    calls.status = None

    def fake_post(url, **kw):
        calls.append(dict(kw, url=url))
        if calls.status is not None:
            return Resp(calls.status, "nope")
        return Resp(204 if "webhooks" in url else 200)

    monkeypatch.setattr(client_module.requests, "post", fake_post)
    return calls


HOOK = {"DISCORD_WEBHOOK_URL": "https://discord.com/api/webhooks/1/x"}
MAILJET = {"MAILJET_API_KEY": "k", "MAILJET_SECRET_KEY": "s"}


def test_discord_webhook_embed_is_what_discord_bot_sent(posts):
    """server_monitor.send_alert -> DiscordBot.send_notification."""
    assert send_alert("Low Disk Space", "**Host:** h\nfree 5%", "error",
                      channels=("discord",), footer="Server Monitor | t", env=HOOK)
    (call,) = posts
    assert call["url"] == HOOK["DISCORD_WEBHOOK_URL"]
    assert call["json"] == {"embeds": [{
        "title": "❌ Low Disk Space", "color": 0xE74C3C,
        "description": "**Host:** h\nfree 5%",
        "footer": {"text": "Server Monitor | t"}}]}
    assert call["timeout"] == 10


def test_discord_bot_token(posts):
    env = {"DISCORD_BOT_TOKEN": "t", "DISCORD_CHANNEL_ID": "42"}
    assert send_alert("Recovered: X", "ok", "info", channels=("discord",), env=env)
    (call,) = posts
    assert call["url"] == "https://discord.com/api/v10/channels/42/messages"
    assert call["headers"]["Authorization"] == "Bot t"
    assert call["json"]["embeds"][0]["color"] == 0x3498DB


def test_webhook_wins_over_bot_token(posts):
    env = dict(HOOK, DISCORD_BOT_TOKEN="t", DISCORD_CHANNEL_ID="42")
    send_alert("x", "y", channels=("discord",), env=env)
    assert posts[0]["url"] == HOOK["DISCORD_WEBHOOK_URL"]


def test_mailjet_message_is_what_checkdisk_sent(posts):
    assert send_alert("Disk Space Alert", "Warning: low", channels=("mailjet",),
                      email_from="a@x", from_name="Disk Monitor",
                      email_to="b@x", to_name="Admin", env=MAILJET)
    (call,) = posts
    assert call["url"] == "https://api.mailjet.com/v3.1/send"
    assert call["auth"] == ("k", "s")
    assert call["json"] == {"Messages": [{
        "From": {"Email": "a@x", "Name": "Disk Monitor"},
        "To": [{"Email": "b@x", "Name": "Admin"}],
        "Subject": "Disk Space Alert", "TextPart": "Warning: low"}]}


def test_mailjet_addresses_from_env(posts):
    env = dict(MAILJET, ALERT_EMAIL_FROM="f@x", ALERT_EMAIL_TO="t@x")
    assert send_alert("s", "m", channels=("mailjet",), env=env)
    msg = posts[0]["json"]["Messages"][0]
    assert (msg["From"]["Email"], msg["To"][0]["Email"]) == ("f@x", "t@x")


def test_unconfigured_channel_is_skipped_not_sent(posts, caplog):
    assert send_alert("x", "y", env={}) is False
    assert posts == []
    assert "discord alert not sent" in caplog.text
    assert "mailjet alert not sent" in caplog.text


def test_only_the_requested_channels(posts):
    send_alert("x", "y", channels=("discord",), env=dict(HOOK, **MAILJET),
               email_from="a", email_to="b")
    assert [c["url"] for c in posts] == [HOOK["DISCORD_WEBHOOK_URL"]]


def test_rejected_alert_is_false(posts):
    posts.status = 500
    assert send_alert("x", "y", channels=("discord",), env=HOOK) is False


def test_network_failure_does_not_raise(monkeypatch):
    def boom(url, **kw):
        raise client_module.requests.ConnectionError("down")
    monkeypatch.setattr(client_module.requests, "post", boom)
    assert send_alert("x", "y", env=dict(HOOK, **MAILJET),
                      email_from="a", email_to="b") is False


def test_credentials_default_to_the_environment(posts, monkeypatch):
    monkeypatch.setenv("DISCORD_WEBHOOK_URL", HOOK["DISCORD_WEBHOOK_URL"])
    assert send_alert("x", "y", channels=("discord",))


def test_rotating_logger_datefmt(tmp_path):
    """service_wrapper.py and watchdog_daemon.py log with a seconds-only date."""
    import re
    from signlab_client_monitor import setup_rotating_logger
    path = tmp_path / "w.log"
    log = setup_rotating_logger(str(path), name="datefmt-test", to_stream=False,
                                fmt="%(asctime)s - %(message)s",
                                datefmt="%Y-%m-%d %H:%M:%S")
    log.info("hi")
    for h in log.handlers:
        h.flush()
    assert re.fullmatch(r"\d{4}-\d\d-\d\d \d\d:\d\d:\d\d - hi\n", path.read_text())
