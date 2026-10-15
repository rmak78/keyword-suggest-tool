import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

// In development the TypeScript app is served by Vite and the PHP proxy by
// `npm run php` (php -S localhost:8080 -t public). Requests to /api/* are
// forwarded to PHP so the front end can be developed against the real proxy.
export default defineConfig({
  base: './',
  plugins: [tailwindcss()],
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    sourcemap: false,
  },
  test: {
    environment: 'node',
    include: ['tests/**/*.test.ts'],
  },
})
