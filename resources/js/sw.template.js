/* eslint-disable no-restricted-globals */
/** Template service worker — digenerate ke public/sw.js saat `npm run build`. */
const CACHE_VERSION = '__CACHE_VERSION__';
const PRECACHE_URLS = __PRECACHE_URLS__;

const SHELL_CACHE = `tuturnusa-shell-${CACHE_VERSION}`;
const BUKU_CACHE = `tuturnusa-buku-${CACHE_VERSION}`;
const AUDIO_CACHE = `tuturnusa-audio-${CACHE_VERSION}`;

const NAV_TIMEOUT_MS = 3000;
const AUDIO_CACHE_PREFIX = 'tuturnusa-audio-';

const PRECACHE = [
    '/',
    '/offline.html',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-512.png',
    ...PRECACHE_URLS,
];

function isBukuSakuPage(url) {
    return url.pathname === '/buku-saku' || url.pathname.startsWith('/buku-saku/');
}

function isBukuSakuAudio(url) {
    return url.pathname.startsWith('/audio/buku-saku/');
}

function isBuildAsset(url) {
    return url.pathname.startsWith('/build/');
}

function acceptsHtml(request) {
    const accept = request.headers.get('Accept') || '';

    return accept.includes('text/html');
}

/** Halaman dokumen yang di-cache network-first (hindari HTML lama setelah deploy). */
function isCachedNavigation(request, url) {
    const p = url.pathname;

    if (request.mode === 'navigate') {
        return (
            p === '/'
            || p === '/bantuan'
            || isBukuSakuPage(url)
        );
    }

    if (!acceptsHtml(request)) {
        return false;
    }

    return p === '/' || p === '/bantuan' || isBukuSakuPage(url);
}

function mustBypassCache(url, request) {
    if (request.method !== 'GET') {
        return true;
    }
    const p = url.pathname;
    return (
        p.startsWith('/conversation')
        || p.startsWith('/pronunciation-check')
        || p.startsWith('/livewire')
    );
}

function isLegacyAudioCacheName(name) {
    return name.startsWith(AUDIO_CACHE_PREFIX);
}

/**
 * @returns {{ start: number, end: number } | { unsatisfiable: true } | null}
 */
function parseByteRange(rangeHeader, size) {
    if (!rangeHeader || size <= 0) {
        return null;
    }

    const match = /^bytes=(.*)$/i.exec(rangeHeader.trim());
    if (!match) {
        return null;
    }

    const spec = match[1];

    if (spec.startsWith('-')) {
        const suffixLength = Number.parseInt(spec.slice(1), 10);
        if (!Number.isFinite(suffixLength) || suffixLength <= 0) {
            return { unsatisfiable: true };
        }
        if (suffixLength > size) {
            return { unsatisfiable: true };
        }
        const start = size - suffixLength;
        return { start, end: size - 1 };
    }

    const dash = spec.indexOf('-');
    if (dash === -1) {
        return { unsatisfiable: true };
    }

    const rawStart = spec.slice(0, dash);
    const rawEnd = spec.slice(dash + 1);
    const start = Number.parseInt(rawStart, 10);
    const end = rawEnd === '' ? size - 1 : Number.parseInt(rawEnd, 10);

    if (!Number.isFinite(start) || start < 0 || start >= size) {
        return { unsatisfiable: true };
    }
    if (!Number.isFinite(end) || end < start || end >= size) {
        return { unsatisfiable: true };
    }

    return { start, end };
}

function responseRangeNotSatisfiable(size) {
    return new Response('Requested Range Not Satisfiable', {
        status: 416,
        statusText: 'Range Not Satisfiable',
        headers: {
            'Content-Range': `bytes */${size}`,
        },
    });
}

/** Layani Range dari respons cache penuh (206/416) — dibutuhkan <audio> di Safari. */
async function respondWithRange(cachedResponse, request) {
    const rangeHeader = request.headers.get('Range');
    if (!rangeHeader) {
        return cachedResponse;
    }

    const blob = await cachedResponse.blob();
    const size = blob.size;
    const parsed = parseByteRange(rangeHeader, size);

    if (parsed === null) {
        return cachedResponse;
    }
    if ('unsatisfiable' in parsed) {
        return responseRangeNotSatisfiable(size);
    }

    const { start, end } = parsed;
    const slice = blob.slice(start, end + 1);

    const headers = new Headers();
    headers.set('Content-Type', cachedResponse.headers.get('Content-Type') || 'audio/mpeg');
    headers.set('Content-Length', String(slice.size));
    headers.set('Content-Range', `bytes ${start}-${end}/${size}`);
    headers.set('Accept-Ranges', 'bytes');

    return new Response(slice, { status: 206, statusText: 'Partial Content', headers });
}

async function fetchFullAudio(request) {
    return fetch(request.url, { credentials: request.credentials });
}

/** Cache-first untuk MP3 Buku Saku (dukungan Range/206). */
async function audioStrategy(request) {
    const cache = await caches.open(AUDIO_CACHE);
    const key = request.url;
    const cached = await cache.match(key);

    if (cached) {
        return respondWithRange(cached, request);
    }

    try {
        const full = await fetchFullAudio(request);
        if (full.ok) {
            await cache.put(key, full.clone());
            const stored = await cache.match(key);
            if (stored) {
                return respondWithRange(stored, request);
            }
        }
        return full;
    } catch {
        return cached ? respondWithRange(cached, request) : Response.error();
    }
}

/** Cache-first untuk aset Vite ber-hash di /build/ (nama file berubah tiap build). */
async function buildAssetStrategy(request) {
    const cache = await caches.open(SHELL_CACHE);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            await cache.put(request, response.clone());
        }
        return response;
    } catch {
        return Response.error();
    }
}

/**
 * Network-first untuk navigasi/HTML: hindari layanan HTML lama yang merujuk CSS/JS usang.
 * Jatuh ke cache hanya saat jaringan gagal/timeout, lalu offline.html.
 */
async function networkFirstNavigation(request) {
    const cache = await caches.open(BUKU_CACHE);
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), NAV_TIMEOUT_MS);

    try {
        const response = await fetch(request, { signal: controller.signal });
        clearTimeout(timeoutId);
        if (response.ok) {
            await cache.put(request, response.clone());
        }
        return response;
    } catch {
        clearTimeout(timeoutId);
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }
        const offline = await caches.match('/offline.html');
        return offline || Response.error();
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((name) => !name.endsWith(`-${CACHE_VERSION}`) && !isLegacyAudioCacheName(name))
                .map((name) => caches.delete(name))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (mustBypassCache(url, request)) {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html') || Response.error())
        );
        return;
    }

    if (isBukuSakuAudio(url)) {
        event.respondWith(audioStrategy(request));
        return;
    }

    if (isBuildAsset(url)) {
        event.respondWith(buildAssetStrategy(request));
        return;
    }

    if (isCachedNavigation(request, url)) {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
    }
});