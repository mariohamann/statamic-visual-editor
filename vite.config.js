import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/js/cp.js',
        'resources/js/bridge.js',
      ],
      publicDirectory: 'resources/dist',
    }),
    statamic(),
  ],
});
