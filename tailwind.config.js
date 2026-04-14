/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './includes/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f3f8f8',
          100: '#dbeceb',
          300: '#93c5c3',
          500: '#438d89',
          700: '#2f6461',
          900: '#1f4442',
        },
      },
    },
  },
  plugins: [],
};
