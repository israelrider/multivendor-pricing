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
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
})
