from ftplib import FTP
from pathlib import Path
import os

HOST = os.environ["FTP_HOST"]
USERNAME = os.environ["FTP_USERNAME"]
PASSWORD = os.environ["FTP_PASSWORD"]
REMOTE_DIR = os.environ.get("FTP_REMOTE_DIR", "/htdocs")
LOCAL_DIR = Path(os.environ.get("FTP_LOCAL_DIR", "docs")).resolve()


def remove_tree(ftp, path):
    try:
        items = ftp.nlst(path)
    except Exception:
        return

    for item in items:
        name = item.rsplit("/", 1)[-1]
        if name in (".", ".."):
            continue
        try:
            ftp.delete(item)
        except Exception:
            remove_tree(ftp, item)
            try:
                ftp.rmd(item)
            except Exception:
                pass


def ensure_dir(ftp, path):
    current = ""
    for part in path.split("/")[:-1]:
        if not part:
            continue
        current = f"{current}/{part}" if current else part
        try:
            ftp.mkd(current)
        except Exception:
            pass


with FTP(HOST, timeout=60) as ftp:
    ftp.login(USERNAME, PASSWORD)
    ftp.cwd(REMOTE_DIR)

    for item in ftp.nlst():
        name = item.rsplit("/", 1)[-1]
        if name in (".", ".."):
            continue
        try:
            ftp.delete(item)
        except Exception:
            remove_tree(ftp, item)
            try:
                ftp.rmd(item)
            except Exception:
                pass

    for local in LOCAL_DIR.rglob("*"):
        if not local.is_file():
            continue
        remote = local.relative_to(LOCAL_DIR).as_posix()
        ensure_dir(ftp, remote)
        with local.open("rb") as handle:
            ftp.storbinary(f"STOR {remote}", handle)

print("FTP deploy complete")
