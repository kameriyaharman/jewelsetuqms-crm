// remove the previous website build from ../www without touching erp/, api/, server/ or index.php
import { rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';
const www = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'www');
for (const p of ['assets', 'index.html', 'img', 'sw.js', 'favicon.svg']) rmSync(join(www, p), { recursive: true, force: true });
