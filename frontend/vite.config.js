import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

// Increase max listeners to suppress MaxListenersExceededWarning
// from Vite's internal stream handling (resize events on WriteStream)
process.stdout.setMaxListeners(0)

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'https://coaching-management-system.test',
        changeOrigin: true,
        secure: false,
      },
    },
  },
})
