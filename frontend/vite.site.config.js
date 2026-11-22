import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Public website + /admin CMS (frontend/site) — builds into ../www (index.html is read by www/index.php,
// which adds per-page SEO / OpenGraph tags). The Q-TRACK ERP is vite.config.js (/erp/).
export default defineConfig({
  root: 'site',
  base: (process.env.VITE_BASE || '/').replace(/\/?$/, '/'),
  publicDir: 'public',
  plugins: [react()],
  build: { outDir: '../../www', emptyOutDir: false, assetsDir: 'assets', chunkSizeWarningLimit: 900 },
  server: { port: 5174, proxy: { '/api': 'http://127.0.0.1:8090' } },
});
