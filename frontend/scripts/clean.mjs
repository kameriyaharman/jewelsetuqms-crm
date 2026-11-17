// remove previous build output from ../www without touching api/ and server/
import { rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';
const www = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'www');
for (const p of ['assets', 'index.html', 'img', 'sw.js', 'manifest.webmanifest']) rmSync(join(www, p), { recursive: true, force: true });
