/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#2b8cee',
                    50: '#eff8ff',
                    100: '#dbeefe',
                    200: '#bfe3fe',
                    300: '#93d2fd',
                    400: '#60b8fa',
                    500: '#2b8cee',
                    600: '#1e77e4',
                    700: '#1661d1',
                    800: '#1850aa',
                    900: '#194686',
                },
                dark: {
                    bg: '#101922',
                    surface: '#1a2632',
                    border: '#324d67',
                },
                light: {
                    bg: '#f6f7f8',
                }
            },
            fontFamily: {
                display: ['Manrope', 'sans-serif'],
            },
        },
    },
    plugins: [],
}
