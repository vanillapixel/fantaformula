/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./src/**/*.{js,jsx,ts,tsx}",
    ],
    theme: {
        extend: {
            colors: {
                primary: '#e62d2d',
                dark: {
                    100: '#1B2021',
                    200: '#000000',
                }
            },
            fontFamily: {
                'titillium': ['TitilliumWeb', 'sans-serif'],
            }
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
}
