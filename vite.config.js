import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const root = path.dirname(fileURLToPath(import.meta.url));

/** Tulis public/sw.js dari template; tidak mengubah file sumber di repo. */
function pwaServiceWorkerVersion() {
    return {
        name: 'pwa-service-worker-version',
        closeBundle() {
            const buildManifest = path.join(root, 'public/build/manifest.json');
            const templatePath = path.join(root, 'resources/js/sw.template.js');
            const swPath = path.join(root, 'public/sw.js');

            if (!fs.existsSync(buildManifest) || !fs.existsSync(templatePath)) {
                return;
            }

            const vite = JSON.parse(fs.readFileSync(buildManifest, 'utf8'));
            const version = createHash('sha256')
                .update(fs.readFileSync(buildManifest))
                .digest('hex')
                .slice(0, 12);

            const precache = [];
            const seen = new Set();
            for (const entry of Object.values(vite)) {
                for (const key of ['file', 'css']) {
                    const value = entry[key];
                    if (!value) {
                        continue;
                    }
                    const files = Array.isArray(value) ? value : [value];
                    for (const file of files) {
                        const url = `/build/${file}`;
                        if (!seen.has(url)) {
                            seen.add(url);
                            precache.push(url);
                        }
                    }
                }
            }

            let sw = fs.readFileSync(templatePath, 'utf8');
            sw = sw.replaceAll('__CACHE_VERSION__', version);
            sw = sw.replaceAll('__PRECACHE_URLS__', JSON.stringify(precache));
            fs.writeFileSync(swPath, sw);
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        pwaServiceWorkerVersion(),
    ],
});