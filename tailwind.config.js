import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Instrument Sans Variable"', ...defaultTheme.fontFamily.sans],
                display: ['"Bricolage Grotesque Variable"', ...defaultTheme.fontFamily.sans],
            },
            // Palette lives in CSS variables (see resources/css/app.css) so light and dark share class names.
            colors: {
                paper: "rgb(var(--paper) / <alpha-value>)",
                panel: "rgb(var(--panel) / <alpha-value>)",
                ink: "rgb(var(--ink) / <alpha-value>)",
                deep: "rgb(var(--deep) / <alpha-value>)",
                "on-deep": "rgb(var(--on-deep) / <alpha-value>)",
                gold: "rgb(var(--gold) / <alpha-value>)",
                lilac: "rgb(var(--lilac) / <alpha-value>)",
            },
        },
    },

    plugins: [forms],
};
