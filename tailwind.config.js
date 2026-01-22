module.exports = {
    content: ["./**/*.php", "./**/*.html", "./src/**/*.js", "./assets/**/*.js"],
    safelist: [
        // Regex to safelist dynamic color utilities used from PHP/JS (bg-, text-, border- with optional opacity e.g. /10)
        {
            pattern:
                /^(bg|text|border)-(blue|green|purple|gray|red|amber|neutral|gray|blue)-(?:[0-9]{3})(?:\/\d{1,3})?$/,
        },
        {
            pattern:
                /^(bg|text|border)-(green|red)-(?:[0-9]{3})(?:\/\d{1,3})?$/,
        },
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: [
                    '"TikTok Sans"',
                    '"Noto Color Emoji"',
                    "system-ui",
                    "sans-serif",
                ],
                mono: [
                    '"Fira Code"',
                    '"JetBrains Mono"',
                    "Consolas",
                    "monospace",
                ],
            },
            colors: {
                // keep named colors for convenience
                brand: {
                    DEFAULT: "var(--color-bg-dark)",
                },
            },
        },
    },
    plugins: [
        require("@tailwindcss/forms"),
        require("@tailwindcss/typography"),
    ],
};
