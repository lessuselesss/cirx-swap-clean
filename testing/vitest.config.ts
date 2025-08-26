import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'happy-dom',
    globals: true,
    setupFiles: ['../ui/test/setup.ts']
  },
  resolve: {
    alias: {
      '~': resolve(__dirname, '../ui'),
      '@': resolve(__dirname, '../ui'),
    },
  },
})