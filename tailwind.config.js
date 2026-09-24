/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,ts,js}',
    ],
    theme: {
        extend: {
            colors: {
                ink: {
                    950: '#07090D',
                    900: '#0B0F14',
                    800: '#121821',
                    700: '#1A2332',
                    600: '#243044',
                },
                ember: {
                    400: '#FBBF24',
                    500: '#F59E0B',
                    600: '#EA580C',
                },
                cream: '#F8F4EC',
            },
            fontFamily: {
                sans: ['"Instrument Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['"Oswald"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                panel: '0 20px 50px rgba(0, 0, 0, 0.35)',
            },
        },
    },
    plugins: [require('@tailwindcss/forms')],
};
