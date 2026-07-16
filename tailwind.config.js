/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.{blade.php,js}",
    "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
    "./vendor/livewire/flux/stubs/**/*.blade.php",
    "./vendor/livewire/flux-pro/stubs/**/*.blade.php",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
