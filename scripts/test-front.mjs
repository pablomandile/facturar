/*
 * Corre los tests del front (`vp test run`).
 *
 * Existe por Windows: la terminal de VS Code arranca en `c:\…` (minúscula) y
 * Vitest resuelve el proyecto como `C:/…`. Con las dos rutas carga dos veces su
 * propio módulo y todo test falla con "Vitest failed to find the current
 * suite". Arrancarlo con la letra de la unidad en mayúscula lo evita. En Linux
 * (el CI) no cambia nada.
 *
 *   npm run test:front
 */

import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// La unidad en mayúscula, en el directorio Y en la ruta del binario: si una
// sola de las dos queda en minúscula, vuelve a cargar dos copias.
const mayuscula = (ruta) =>
    /^[a-z]:/.test(ruta) ? ruta[0].toUpperCase() + ruta.slice(1) : ruta;

const cwd = mayuscula(resolve(dirname(fileURLToPath(import.meta.url)), '..'));
const vp = mayuscula(
    resolve(
        dirname(
            createRequire(import.meta.url).resolve('vite-plus/package.json'),
        ),
        'bin/vp',
    ),
);

const { status } = spawnSync(
    process.execPath,
    [vp, 'test', 'run', ...process.argv.slice(2)],
    { cwd, stdio: 'inherit' },
);

process.exit(status ?? 1);
