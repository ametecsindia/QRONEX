/** Tailwind v3 config for QRONEX (separate from Laravel's default Tailwind v4/Vite setup). */
module.exports = {
  content: ['./resources/views/**/*.blade.php', './public/js/*.js'],
  theme: { extend: {
    fontFamily: { sans: ['"Schibsted Grotesk"', 'ui-sans-serif', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'] },
    colors: {
      ink: '#081A33',
      navy: { DEFAULT: '#0E2747', 2: '#173A64', 3: '#21497A' },
      teal: { DEFAULT: '#0A8278', 2: '#14B8A6', soft: '#DCF3EF', glow: '#5EEAD4' },
      scan: '#F2B33D',
      paper: '#F5F7FA',
    },
    letterSpacing: { tightest: '-.035em' },
  } },
};
