#!/usr/bin/env bash
# Install signlab-client-monitor for the current user.
#
# Two facts about this estate shape this script. There is no build step
# anywhere in the deploy, and the hosts are Ubuntu with a PEP 668
# EXTERNALLY-MANAGED marker, so `pip install --user` refuses outright. So:
# try pip with the flag that makes it work, and if pip is unusable, fall back
# to copying the two importable trees into the user site directory - which is
# already on sys.path, and needs neither a build nor root.
#
# Idempotent. Safe to run on a machine that already has it.

set -eu

here="$(cd "$(dirname "$0")" && pwd)"
python="${PYTHON:-python3}"

if "$python" -m pip --version >/dev/null 2>&1; then
  echo "installing with pip"
  if "$python" -m pip install --user --break-system-packages "$here" 2>/dev/null ||
     "$python" -m pip install --user "$here"; then
    "$python" -c 'import signlab_client_monitor as m; print("installed", m.__version__, m.__file__)'
    exit 0
  fi
  echo "pip failed; falling back to a plain copy" >&2
fi

site="$("$python" -m site --user-site)"
echo "copying into $site"
mkdir -p "$site"
rm -rf "$site/signlab_client_monitor"
cp -R "$here/signlab_client_monitor" "$site/signlab_client_monitor"
cp "$here/python_client.py" "$site/python_client.py"
"$python" -c 'import signlab_client_monitor as m; print("installed", m.__version__, m.__file__)'
