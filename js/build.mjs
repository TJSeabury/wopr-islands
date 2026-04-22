import esbuild from 'esbuild';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(process.cwd());
const outdir = path.join(root, 'dist');

await mkdir(outdir, { recursive: true });

const result = await esbuild.build({
  entryPoints: [path.join(root, 'js', 'src', 'index.ts')],
  bundle: true,
  format: 'iife',
  platform: 'browser',
  target: ['es2020'],
  sourcemap: true,
  outfile: path.join(outdir, 'wopr-islands.js'),
  globalName: 'WoprIslands',
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV ?? 'production'),
  },
});

// Minimal manifest so PHP code can read a stable version/hash if desired later.
await writeFile(
  path.join(outdir, 'manifest.json'),
  JSON.stringify({ outputs: ['wopr-islands.js'], metafile: Boolean(result.metafile) }, null, 2) + '\n',
  'utf8'
);

