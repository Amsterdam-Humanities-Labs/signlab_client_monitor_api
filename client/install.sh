#!/usr/bin/env bash
# Install signlab-client-monitor for the current user.
#
# Two facts about this estate shape this script. There is no build step
# anywhere in the deploy, and the hosts are Ubuntu with a PEP 668
# EXTERNALLY-MANAGED marker, so `pip install --user` refuses outright. So:
# try pip with the flag that makes it work, and if pip is unusable - or
# reports a success that does not import - fall back to copying the two
# importable trees into the user site directory, which is already on sys.path
# and needs neither a build nor root.
#
# Idempotent. Safe to run on a machine that already has it.
#
#   client/install.sh
#   PYTHON=python3.11 client/install.sh

set -eu

here="$(cd "$(dirname "$0")" && pwd)"
python="${PYTHON:-python3}"

# Everything runs from / so that it tests what is installed rather than the
# source tree this script was launched from.
run_at_root() { (cd / && "$python" "$@"); }

has_requests() { run_at_root -c 'import requests' >/dev/null 2>&1; }

imports() {
  run_at_root -c 'import signlab_client_monitor as m, python_client
print("installed", m.__version__, m.__file__)'
}

# A host without requests has a correct install of a package that cannot send
# anything. Say that, rather than reporting it as a packaging failure.
finish() {
  if has_requests; then
    imports
  else
    echo "installed, but this interpreter has no requests, so the client" >&2
    echo "cannot send anything yet: apt install python3-requests" >&2
  fi
  exit 0
}

if "$python" -m pip --version >/dev/null 2>&1; then
  echo "installing with pip"
  if "$python" -m pip install --user --break-system-packages "$here" 2>/dev/null ||
     "$python" -m pip install --user "$here" 2>/dev/null ||
     "$python" -m pip install "$here"; then
    # Some old pips build this as an empty UNKNOWN-0.0.0 wheel, because their
    # build isolation cannot fetch a setuptools new enough to read [project].
    # A pip that reports success is not evidence; importing is.
    if ! has_requests || imports >/dev/null 2>&1; then
      finish
    fi
    echo "pip reported success but the package does not import" >&2
  fi
  echo "falling back to a plain copy" >&2
fi

# Not `python -m site --user-site`: that exits non-zero when the directory does
# not exist yet, which under `set -e` would end this script in silence.
site="$(run_at_root -c 'import site; print(site.getusersitepackages())')"
echo "copying into $site"
mkdir -p "$site"
rm -rf "$site/signlab_client_monitor"
cp -R "$here/signlab_client_monitor" "$site/signlab_client_monitor"
cp "$here/python_client.py" "$site/python_client.py"

if has_requests && ! imports >/dev/null 2>&1; then
  echo "copied to $site, but this interpreter does not read it." >&2
  echo "Inside a virtualenv use pip instead: $python -m pip install $here" >&2
  exit 1
fi
finish
