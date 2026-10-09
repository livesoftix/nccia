import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { writeFileSync, readFileSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const rootDir = dirname(fileURLToPath(import.meta.url))

function emitAppVersion() {
  return {
    name: 'emit-app-version',
    closeBundle() {
      const outDir = resolve(rootDir, '../public/react')
      const htmlPath = resolve(outDir, 'index.html')
      let version = Date.now().toString(36)
      if (existsSync(htmlPath)) {
        const html = readFileSync(htmlPath, 'utf8')
        const match = html.match(/index-([A-Za-z0-9_-]+)\.js/)
        if (match?.[1]) version = match[1]
      }
      writeFileSync(
        resolve(outDir, 'version.json'),
        JSON.stringify({ v: version, t: Date.now() }),
      )
    },
  }
}

export default defineConfig({
  plugins: [react(), emitAppVersion()],
  base: '/react/',
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
      '/login': 'http://localhost:8000',
      '/logout': 'http://localhost:8000',
    },
  },
  build: {
    outDir: '../public/react',
    emptyOutDir: true,
  },
})
