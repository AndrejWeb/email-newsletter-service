import { defineConfig } from 'vite';

export default defineConfig({
  root: '.',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: 'index.html'
    }
  },
  server: {
    port: 3000,
    proxy: {
      '/api': 'http://localhost:8011',
      '/track': 'http://localhost:8011',
      '/unsubscribe': 'http://localhost:8011'
    }
  }
});
