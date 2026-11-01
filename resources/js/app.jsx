import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/Components/theme-provider';
import { notify } from '@/lib/notify';

// Each page is its own chunk, loaded on first visit.
const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    resolve: (name) => {
        // 1. Direct match
        if (pages[`./Pages/${name}.jsx`]) {
            return pages[`./Pages/${name}.jsx`]();
        }

        // 2. Case-insensitive path match
        const lowerName = `./Pages/${name}.jsx`.toLowerCase();
        const found = Object.keys(pages).find((k) => k.toLowerCase() === lowerName);
        if (found) {
            return pages[found]();
        }

        // 3. Kebab-case to PascalCase conversion (e.g. auth/register-vendor -> Auth/RegisterVendor)
        const pascalName = name
            .split('/')
            .map((part) =>
                part
                    .split('-')
                    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                    .join('')
            )
            .join('/');

        const page = pages[`./Pages/${pascalName}.jsx`];

        if (!page) {
            throw new Error(`Halaman Inertia "${name}" tidak ditemukan.`);
        }

        return page();
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange>
                <App {...props} />
            </ThemeProvider>
        );
    },
});
// Network failure (offline, timeout, failed chunk load): show a toast instead of failing silently.
router.on('networkError', (event) => {
    if (import.meta.env.DEV) {
        console.error('[Inertia] networkError', event.detail.error);
    }

    notify.error('Koneksi ke server terputus. Periksa jaringan Anda lalu coba lagi.');

    return false;
});
