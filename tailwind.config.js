/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './**/*.php',
    './assets/js/**/*.js',
  ],
  theme: {
    extend: {},
  },
  plugins: [
    // Uncomment if you want prose/typography support:
    // require('@tailwindcss/typography'),
  ],
};
