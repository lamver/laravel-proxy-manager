import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 3000,
    // Настройка прокси для отправки запросов на бэкенд
    proxy: {
      '/api': {
        target: 'http://webserver:80', // Обращаемся к Nginx контейнеру
        changeOrigin: true,
      }
    }
  }
})