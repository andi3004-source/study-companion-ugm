/* eslint-disable no-restricted-globals */
/** Template service worker — digenerate ke public/sw.js saat `npm run build`. */
const CACHE_VERSION = '__CACHE_VERSION__';
const PRECACHE_URLS = __PRECACHE_URLS__;

const SHELL_CACHE = `tuturnusa-shell-${CACHE_VERSION}`;
const BUKU_CACHE = `tuturnusa-buku-${CACHE_VERSION}`;
const AUDIO_CACHE = `tuturnusa-audio-${CACHE_VERSION}`;

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

async function bukuPageStrategy(request, waitUntil) {
    const cache = await caches.open(BUKU_CACHE);
    const cached = await cache.match(request);

    const refresh = fetch(request)
        .then((response) => {
            if (response.ok) {
                return cache.put(request, response.clone());
            }
            return null;
        })
        .catch(() => null);

    if (cached) {
        waitUntil(refresh);
        return cached;
    }

    try {
        const network = await fetch(request);
        if (network.ok) {
            await cache.put(request, network.clone());
        }
        return network;
    } catch {
        const offline = await caches.match('/offline.html');
        return offline || Response.error();
    }
}

async function navigationFallback(request) {
    try {
        return await fetch(request);
    } catch {
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        return (await caches.match('/offline.html')) || Response.error();
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
                .filter((name) => !name.endsWith(`-${CACHE_VERSION}`))
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

    if (isBukuSakuPage(url)) {
        event.respondWith(bukuPageStrategy(request, (p) => event.waitUntil(p)));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(navigationFallback(request));
    }
});