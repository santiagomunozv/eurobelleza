const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Roboto', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#1C4789',
                    50: '#EAF1FB',
                    100: '#D6E3F5',
                    600: '#1C4789',
                    700: '#163A71',
                },
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};
