#!/usr/bin/env python3
"""Envia arquivos do projeto para producao via FTP.

Le a configuracao de .vscode/sftp.json (host, usuario, senha, remotePath, ignore).
As credenciais nunca sao impressas.

Uso:
    python3 .agents/deploy.py app/modules/cursos/dashboard.php [outros...]
    python3 .agents/deploy.py --git      # envia tudo que o git aponta como modificado/novo
"""

import json
import os
import subprocess
import sys
from ftplib import FTP, error_perm

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CONFIG = os.path.join(ROOT, ".vscode", "sftp.json")


def load_config():
    with open(CONFIG) as fh:
        return json.load(fh)


def git_changed_files():
    out = subprocess.run(
        ["git", "-C", ROOT, "status", "--porcelain"],
        capture_output=True, text=True, check=True,
    ).stdout
    files = []
    for line in out.splitlines():
        status, path = line[:2], line[3:].strip()
        if status.strip() == "D" or path.endswith("/"):
            continue
        if path.startswith('"') and path.endswith('"'):
            path = path[1:-1]
        files.append(path)
    return files


def is_ignored(rel, patterns):
    parts = rel.split("/")
    return any(p in parts for p in patterns)


def ensure_dirs(ftp, remote_dir):
    path = ""
    for part in remote_dir.strip("/").split("/"):
        path += "/" + part
        try:
            ftp.mkd(path)
        except error_perm:
            pass  # ja existe


def main(argv):
    cfg = load_config()
    ignore = cfg.get("ignore", [])
    remote_root = cfg.get("remotePath", "/public_html").rstrip("/")

    if not argv or argv[0] == "--git":
        targets = git_changed_files()
    else:
        targets = argv

    uploads = []
    for target in targets:
        rel = os.path.relpath(os.path.abspath(target), ROOT).replace(os.sep, "/")
        if rel.startswith(".."):
            print(f"IGNORADO (fora do projeto): {rel}")
            continue
        if is_ignored(rel, ignore):
            print(f"IGNORADO (regra sftp.json): {rel}")
            continue
        if not os.path.isfile(os.path.join(ROOT, rel)):
            print(f"IGNORADO (nao e arquivo): {rel}")
            continue
        uploads.append(rel)

    if not uploads:
        print("Nada para enviar.")
        return 0

    ftp = FTP()
    ftp.connect(cfg["host"], int(cfg.get("port", 21)), timeout=60)
    ftp.login(cfg["username"], cfg["password"])
    try:
        ftp.set_pasv(True)
        failures = 0
        for rel in uploads:
            remote_path = f"{remote_root}/{rel}"
            remote_dir = os.path.dirname(remote_path)
            ensure_dirs(ftp, remote_dir)
            local = os.path.join(ROOT, rel)
            with open(local, "rb") as fh:
                ftp.storbinary(f"STOR {remote_path}", fh)
            size = ftp.size(remote_path)
            local_size = os.path.getsize(local)
            ok = size == local_size
            failures += 0 if ok else 1
            print(f"{'OK  ' if ok else 'FALHA'} {rel} -> {remote_path} ({local_size} bytes"
                  + ("" if ok else f", remoto reportou {size}") + ")")
    finally:
        ftp.quit()

    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
