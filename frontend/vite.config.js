import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Q-TRACK ERP — served at /erp/ (builds into ../www/erp). The public website is vite.site.config.js.
// For a sub-folder install set VITE_BASE=/qtrack/ before building (the ERP then lives at /qtrack/erp/).
const root = (process.env.VITE_BASE || '/').replace(/\/?$/, '/');
export default defineConfig({
  base: root + 'erp/',
  plugins: [react()],
  build: { outDir: '../www/erp', emptyOutDir: true, assetsDir: 'assets', chunkSizeWarningLimit: 900 },
  server: { proxy: { '/api': 'http://127.0.0.1:8090' } },
});
