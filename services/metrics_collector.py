#!/usr/bin/env python3
"""
Client Monitor Metrics Collector Service

Collects system metrics (CPU, disk, I/O wait) hourly and submits to API.
Runs as a systemd service with automatic restart on failure.
"""

import sys
import os
import time
import logging
import psutil
from datetime import datetime

# The packaged client, from ../client. The fallback is the example module this
# used to import through a sys.path hack, and it is here because the package
# has to be installed on the host and a `git pull` alone does not install it -
# see client/README.md. Once client/install.sh has run on a host, the first
# branch is what runs.
try:
    from signlab_client_monitor import ClientMonitor, disk_usage
except ImportError:
    sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    from examples.python_client import ClientMonitor, disk_usage

# Configuration
API_URL = "https://signcollect.nl/client_monitor_api/api.php"

# Get server IP address to use as identifier
import socket
try:
    # Get the primary IP address of this server
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    s.connect(("8.8.8.8", 80))
    SERVER_IP = s.getsockname()[0]
    s.close()
except:
    SERVER_IP = socket.gethostname()

CLIENT_ID = f"server-{SERVER_IP}"
CLIENT_NAME = f"Server Metrics ({SERVER_IP})"
COLLECTION_INTERVAL = 3600  # 1 hour in seconds

# Logging setup
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s: %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger(__name__)


class MetricsCollector:
    """Collects and submits system metrics"""

    def __init__(self, api_url, client_id, client_name):
        self.client_monitor = ClientMonitor(api_url, client_id, client_name, heartbeat_interval=COLLECTION_INTERVAL)
        self.client_id = client_id
        logger.info(f"Initialized MetricsCollector for client: {client_id}")

    def collect_metrics(self):
        """Collect system metrics using psutil"""
        try:
            # CPU percentage over 1 second interval
            cpu_percent = psutil.cpu_percent(interval=1)

            # I/O wait time (Linux-specific)
            cpu_times = psutil.cpu_times_percent(interval=1)
            cpu_wait = getattr(cpu_times, 'iowait', None)

            # Disk usage for root partition
            disk = disk_usage('/')
            disk_percent = round(disk['used_percent'], 1)  # psutil's percent
            disk_total_gb = round(disk['total'] / (1024**3), 2)
            disk_used_gb = round(disk['used'] / (1024**3), 2)
            disk_free_gb = round(disk['free'] / (1024**3), 2)

            # Memory usage (optional)
            memory = psutil.virtual_memory()
            memory_percent = memory.percent

            metrics = {
                'cpu_percent': round(cpu_percent, 2),
                'cpu_wait_percent': round(cpu_wait, 2) if cpu_wait is not None else None,
                'disk_usage_percent': round(disk_percent, 2),
                'disk_total_gb': disk_total_gb,
                'disk_used_gb': disk_used_gb,
                'disk_free_gb': disk_free_gb,
                'memory_percent': round(memory_percent, 2)
            }

            logger.info(f"Collected metrics: CPU={metrics['cpu_percent']}%, "
                       f"Disk={metrics['disk_usage_percent']}%, "
                       f"Wait={metrics['cpu_wait_percent']}%")

            return metrics

        except Exception as e:
            logger.error(f"Error collecting metrics: {e}")
            return None

    def submit_metrics(self, metrics):
        """Submit metrics to API.

        This used to POST with raw `requests`, borrowing the client's api_url,
        because no copy of ClientMonitor had a submit_metrics method. The
        package has one, and it already sets a timeout and cannot raise.
        """
        result = self.client_monitor.submit_metrics(metrics)
        if result:
            metrics_id = (result.get('data') or {}).get('metrics_id')
            logger.info(f"Metrics submitted successfully (ID: {metrics_id})")
            return True

        logger.error(f"Metrics submission failed: {result.get('errors')}")
        return False

    def run(self):
        """Main loop: collect and submit metrics every hour"""
        logger.info("Starting metrics collector service")

        # Register client on startup (if not already registered)
        try:
            self.client_monitor.register()
            logger.info("Client registered successfully")
        except Exception as e:
            logger.warning(f"Registration failed (may already exist): {e}")

        while True:
            try:
                # Collect metrics
                metrics = self.collect_metrics()

                if metrics:
                    # Submit to API
                    self.submit_metrics(metrics)

                    # Also send heartbeat to update last_seen
                    try:
                        self.client_monitor.send_heartbeat()
                    except Exception as e:
                        logger.warning(f"Heartbeat failed: {e}")
                else:
                    logger.warning("Metrics collection returned None, skipping submission")

                # Sleep until next collection
                logger.info(f"Sleeping for {COLLECTION_INTERVAL} seconds...")
                time.sleep(COLLECTION_INTERVAL)

            except KeyboardInterrupt:
                logger.info("Received interrupt signal, shutting down gracefully")
                break
            except Exception as e:
                logger.error(f"Unexpected error in main loop: {e}")
                logger.info("Retrying in 60 seconds...")
                time.sleep(60)


if __name__ == '__main__':
    collector = MetricsCollector(API_URL, CLIENT_ID, CLIENT_NAME)
    collector.run()
