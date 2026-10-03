#!/usr/bin/env python3
"""Source-only release: never include runtime state, secrets, caches or dependencies."""
from pathlib import Path
import hashlib,zipfile
ROOT=Path(__file__).resolve().parents[1]
DEST=ROOT.parent/'lagospanel-1.1.0.zip'
exclude={'.git','.cache','node_modules','vendor','__pycache__','.phpunit.cache','.idea','.vscode'}
with zipfile.ZipFile(DEST,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9)as z:
 for p in sorted(ROOT.rglob('*')):
  if not p.is_file():continue
  rel=p.relative_to(ROOT)
  if any(part in exclude for part in rel.parts):continue
  if p.name.startswith('.env') and p.name!='.env.example':continue
  if p.name.endswith(('.log','.sqlite','.sqlite-wal','.sqlite-shm','.db','.sql','.dump')):continue
  if rel.parts[0]=='storage' and p.name!='.gitignore':continue
  if rel.parts[:2]==('bootstrap','cache') and p.name!='.gitignore':continue
  if p.name in ['.phpunit.result.cache','auth.json']:continue
  z.write(p,'lagospanel/'+str(rel))
with zipfile.ZipFile(DEST)as z:
 assert z.testzip() is None
 assert not any('/.env'==name[-5:] or '/.cache/' in name for name in z.namelist())
sha=hashlib.sha256(DEST.read_bytes()).hexdigest()
Path(str(DEST)+'.sha256').write_text(f'{sha}  {DEST.name}\n')
print(f'{DEST}\n{DEST.stat().st_size} bytes\nSHA256 {sha}')
