#!/usr/bin/env bash
# Pinned official binary with release checksum verification; no action license needed.
set -euo pipefail
qpc_destination="${1:?Provide a tool directory outside the plugin runtime}"
qpc_version=8.30.1
case "$(uname -s)" in Linux) qpc_os=linux ;; Darwin) qpc_os=darwin ;; *) exit 1 ;; esac
case "$(uname -m)" in x86_64|amd64) qpc_arch=x64 ;; arm64|aarch64) qpc_arch=arm64 ;; *) exit 1 ;; esac
qpc_archive="gitleaks_${qpc_version}_${qpc_os}_${qpc_arch}.tar.gz"
qpc_base="https://github.com/gitleaks/gitleaks/releases/download/v${qpc_version}"
mkdir -p "$qpc_destination"
qpc_download="$(mktemp -d)"
trap 'rm -rf "$qpc_download"' EXIT
curl --proto '=https' --tlsv1.2 --retry 3 -fsSL "$qpc_base/$qpc_archive" -o "$qpc_download/$qpc_archive"
curl --proto '=https' --tlsv1.2 --retry 3 -fsSL "$qpc_base/gitleaks_${qpc_version}_checksums.txt" -o "$qpc_download/checksums.txt"
python3 - "$qpc_download" "$qpc_archive" <<'PY'
from pathlib import Path
import hashlib, sys
folder, name = Path(sys.argv[1]), sys.argv[2]
checks = {line.split()[-1].lstrip('*'): line.split()[0] for line in (folder / 'checksums.txt').read_text().splitlines() if line.strip()}
if checks.get(name) != hashlib.sha256((folder / name).read_bytes()).hexdigest():
    raise SystemExit('Official Gitleaks checksum verification failed.')
PY
tar -xzf "$qpc_download/$qpc_archive" -C "$qpc_destination" gitleaks
"$qpc_destination/gitleaks" version
