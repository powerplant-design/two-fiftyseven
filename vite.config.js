import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  // Allow WordPress to serve the page while Vite handles assets.
  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5173',
    // Allow DevKinsta local domain to make requests to the Vite server.
    allowedHosts: ['two-fiftyseven.local', 'localhost'],
    hmr: {
      host: 'localhost',
      port: 5173,
    },
  },

  build: {
    // Output to assets/dist so WordPress can find the manifest.
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'assets/js/main.js'),
      },
    },
  },
});
