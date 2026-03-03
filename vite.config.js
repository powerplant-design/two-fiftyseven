import { defineConfig } from 'vite';
import { resolve } from 'path';
import tailwindcss from '@tailwindcss/postcss';

export default defineConfig({
  css: {
    devSourcemap: true,
    postcss: {
      plugins: [tailwindcss()],
    },
    preprocessorOptions: {
      scss: {
        silenceDeprecations: ['legacy-js-api'],
        loadPaths: ['node_modules'],
      },
    },
  },

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
    target: 'esnext',
    minify: true,
    sourcemap: false,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'assets/js/main.js'),
      },
    },
  },
});
