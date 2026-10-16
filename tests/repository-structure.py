"""Check Symcon's top-level module discovery rules, without a running server."""
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
ignored = {"libs", "docs", "imgs", "tests", "actions"}
json.loads((root / "library.json").read_text(encoding="utf-8"))
for directory in root.iterdir():
    if not directory.is_dir() or directory.name.startswith(".") or directory.name in ignored:
        continue
    module = directory / "module.json"
    assert module.is_file(), f"Invalid Symcon module folder: {directory.name} (module.json missing)"
    json.loads(module.read_text(encoding="utf-8"))
print("Symcon repository structure: passed")
