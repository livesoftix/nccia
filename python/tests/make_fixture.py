"""Create one synthetic PDF for Laravel tests.

    python -m tests.make_fixture OUT.pdf --kind text|scan --seq N --circle NAME [--drop "Mobile Number"] [--append "line"] [--pages N]
"""

import argparse
from pathlib import Path

from tests import synthetic

parser = argparse.ArgumentParser()
parser.add_argument("out")
parser.add_argument("--kind", default="text", choices=["text", "scan"])
parser.add_argument("--seq", type=int, default=1)
parser.add_argument("--circle", default="Lahore")
parser.add_argument("--drop", action="append", default=[])
parser.add_argument("--append", action="append", default=[])
parser.add_argument("--pages", type=int, default=1)
args = parser.parse_args()

body, _ = synthetic.sample(args.seq, args.circle)
lines = [line for line in body.splitlines() if not any(line.startswith(prefix) for prefix in args.drop)]
body = "\n".join(lines + args.append) + "\n"
out = Path(args.out)
if args.kind == "scan":
    synthetic.scanned_pdf(out, body)
else:
    synthetic.text_pdf(out, body, extra_pages=max(0, args.pages - 1))
print(out)
