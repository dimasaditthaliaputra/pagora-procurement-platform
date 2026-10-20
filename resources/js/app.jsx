import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/Components/theme-provider';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

        // 1. Direct match
        if (pages[`./Pages/${name}.jsx`]) {
            return pages[`./Pages/${name}.jsx`];
        }

        // 2. Case-insensitive path match
        const lowerName = `./Pages/${name}.jsx`.toLowerCase();
        const found = Object.keys(pages).find((k) => k.toLowerCase() === lowerName);
        if (found) {
            return pages[found];
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

        return pages[`./Pages/${pascalName}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange>
                <App {...props} />
            </ThemeProvider>
        );
    },
});