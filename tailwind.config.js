/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],

  theme: {
    extend: {
      colors: {
        'green' : '#F4FEF1',
        'green-sec' : '#C1FDBB',
      }
    },
  },
  plugins: [],
}

