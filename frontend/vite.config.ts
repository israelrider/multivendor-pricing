import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    // Laravel runs on :8000 (`php artisan serve`). Proxying /api keeps the
    // browser on one origin, so no CORS setup is needed in development.
    // 127.0.0.1, not localhost: on Windows Node tries ::1 first, but
    // `artisan serve` listens on IPv4 only, which adds ~2s to every request.
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
