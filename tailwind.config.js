/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './app/dashboard/resources/js/**/*.{js,jsx}',
    ],
    theme: {
        extend: {
            colors: {
                ink: '#172026',
                muted: '#64717d',
                line: '#d9e1e8',
                paper: '#fbfcfd',
                surface: '#ffffff',
                green: '#1f8a70',
                blue: '#2563eb',
                amber: '#b86f08',
            },
        },
    },
    plugins: [],
};
