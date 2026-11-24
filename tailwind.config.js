import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            screens: {
                sidebar: '861px',
            },
            fontFamily: {
                display: ['"Inter Variable"', 'Inter', 'system-ui', 'sans-serif'],
                sans: ['"Inter Variable"', 'Inter', 'system-ui', 'sans-serif'],
                hand: ['Caveat', 'cursive'],
            },
            // The views use numeric weights (font-600, font-700); without these keys
            // Tailwind generated nothing for them.
            fontWeight: {
                400: '400',
                500: '500',
                600: '600',
                700: '700',
                800: '800',
            },
            borderRadius: {
                card: '1rem',
            },
            boxShadow: {
                card: '0 1px 2px rgba(20, 41, 77, 0.04), 0 4px 16px rgba(20, 41, 77, 0.05)',
            },
            colors: {
                // App palette, aligned with brand.* below (home page).
                navy: '#14294D',
                'navy-deep': '#0E1F3B',
                'navy-soft': '#24416E',
                cream: '#FFFFFF',
                card: '#FFFFFF',
                'card-alt': '#FFFFFF',
                ink: '#2B3648',
                'ink-soft': '#4F5968',
                secondary: '#4F5968',
                'ink-faint': '#5A6472',
                line: '#ECE7DD',
                'line-card': '#ECE7DD',
                orange: '#F9B934',
                mandarin: '#35619E',
                'mandarin-soft': '#E3EDF9',
                english: '#3D6B52',
                'english-soft': '#E4F1E8',
                good: '#3D6B52',
                // Public home page (welcome.blade.php, components/landing).
                brand: {
                    navy: '#14294D',
                    yellow: '#F9B934',
                    'yellow-soft': '#FDEBC0',
                    paper: '#FBF8F3',
                    sky: '#EEF4FB',
                    'sky-icon': '#E3ECF8',
                    mint: '#F2F9F4',
                    line: '#ECE7DD',
                },
            },
        },
    },

    plugins: [forms],
};
