import { defineConfig } from 'vite';
import { resolve } from 'node:path';

// One entry per screen. Output names are stable (no hashes) so PHP can find them;
// cache busting comes from filemtime() in Assets.php.
export default defineConfig({
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    target: 'es2020',
    cssCodeSplit: true,
    rollupOptions: {
      input: {
        'admin-rules': resolve(__dirname, 'assets/src/admin/rules.ts'),
        'admin-vendors': resolve(__dirname, 'assets/src/admin/vendors.ts'),
        panel: resolve(__dirname, 'assets/src/panel/panel.ts'),
        calculator: resolve(__dirname, 'assets/src/front/calculator.ts'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
