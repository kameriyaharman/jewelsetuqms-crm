import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Build goes straight into ../www (the folder you upload to shared hosting).
// For a sub-folder install set VITE_BASE=/qtrack/ before building.
export default defineConfig({
  base: process.env.VITE_BASE || '/',
  plugins: [react()],
  build: { outDir: '../www', emptyOutDir: false, assetsDir: 'assets', chunkSizeWarningLimit: 900 },
  server: { proxy: { '/api': 'http://127.0.0.1:8090' } },
});
