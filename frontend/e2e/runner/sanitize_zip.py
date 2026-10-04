#!/usr/bin/env python3
import zipfile
import re
import sys
import tempfile
import os
import shutil

if len(sys.argv) < 2:
    sys.exit(0)

zip_path = sys.argv[1]
if not os.path.exists(zip_path):
    sys.exit(0)

temp_dir = tempfile.mkdtemp()
temp_zip = os.path.join(temp_dir, "sanitized.zip")

patterns = [
    (re.compile(r'("password"\s*:\s*")[^"]+(")', re.IGNORECASE), r'\1[REDACTED]\2'),
    (re.compile(r"('password'\s*:\s*')[^']+(')", re.IGNORECASE), r"\1[REDACTED]\2"),
    (re.compile(r'password=[^&"\'\s\\]+', re.IGNORECASE), r'password=[REDACTED]'),
    (re.compile(r'Password123![^"\'\s\\]*'), r'[REDACTED_PASSWORD]'),
    (re.compile(r'("authorization"\s*:\s*")[^"]+(")', re.IGNORECASE), r'\1[REDACTED]\2'),
    (re.compile(r'Bearer\s+[A-Za-z0-9_\-\.]+', re.IGNORECASE), r'Bearer [REDACTED_TOKEN]'),
    (re.compile(r'Apikey\s+[A-Za-z0-9_\-\.]+', re.IGNORECASE), r'Apikey [REDACTED_KEY]'),
    (re.compile(r'("cookie"\s*:\s*")[^"]+(")', re.IGNORECASE), r'\1[REDACTED]\2'),
    (re.compile(r'("set-cookie"\s*:\s*")[^"]+(")', re.IGNORECASE), r'\1[REDACTED]\2'),
    (re.compile(r'aura_session=[A-Za-z0-9_%\-]+', re.IGNORECASE), r'aura_session=[REDACTED]'),
    (re.compile(r'XSRF-TOKEN=[A-Za-z0-9_%\-]+', re.IGNORECASE), r'XSRF-TOKEN=[REDACTED]'),
    (re.compile(r'whsec_[a-zA-Z0-9_\-]+'), r'[REDACTED_WEBHOOK_SECRET]'),
    (re.compile(r'test_sepay_[a-zA-Z0-9_\-]+'), r'[REDACTED_SECRET]'),
    (re.compile(r'test_payos_[a-zA-Z0-9_\-]+'), r'[REDACTED_SECRET]'),
]

try:
    with zipfile.ZipFile(zip_path, 'r') as src, zipfile.ZipFile(temp_zip, 'w', compression=zipfile.ZIP_DEFLATED) as dst:
        for item in src.infolist():
            data = src.read(item.filename)
            # Only sanitize text/json/trace entries
            if any(item.filename.endswith(ext) for ext in ['.json', '.network', '.trace', '.stacks', '.txt', '.log']):
                try:
                    text = data.decode('utf-8')
                    for p, repl in patterns:
                        text = p.sub(repl, text)
                    data = text.encode('utf-8')
                except Exception:
                    pass
            dst.writestr(item, data)
    shutil.move(temp_zip, zip_path)
    print("OK")
except Exception as e:
    sys.stderr.write(str(e) + "\n")
    sys.exit(1)
finally:
    shutil.rmtree(temp_dir, ignore_errors=True)
