/**
 * PWA: registrasi service worker dan UI "Pasang aplikasi" di beranda.
 */
function statusRegion() {
    return document.getElementById('mode-suara-status');
}

function hidePasangSection(section) {
    if (section) {
        section.hidden = true;
    }
}

export function initPwaInstall() {
    const section = document.getElementById('pasang-aplikasi');
    if (!section) {
        return;
    }

    if (window.matchMedia('(display-mode: standalone)').matches) {
        hidePasangSection(section);
        return;
    }

    const tombol = document.getElementById('tombol-pasang-aplikasi');
    /** @type {BeforeInstallPromptEvent | null} */
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        if (tombol) {
            tombol.hidden = false;
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        hidePasangSection(section);
    });

    tombol?.addEventListener('click', async () => {
        if (!deferredPrompt) {
            return;
        }
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        const status = statusRegion();
        if (status) {
            status.textContent = outcome === 'accepted'
                ? 'Tuturnusa sedang dipasang'
                : 'Pemasangan dibatalkan';
        }
        deferredPrompt = null;
        if (outcome === 'accepted') {
            tombol.hidden = true;
        }
    });
}

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

document.addEventListener('DOMContentLoaded', () => {
    registerServiceWorker();
    initPwaInstall();
});