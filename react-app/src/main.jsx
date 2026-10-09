import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'

const VERSION_KEY = 'nccia_app_v'
const PENDING_UPDATE_KEY = 'nccia_pending_update'

/** Never auto-reload — only notify. Auto-reload was wiping in-progress form work. */
async function checkForAppUpdate() {
  try {
    const res = await fetch(`/react/version.json?t=${Date.now()}`, { cache: 'no-store' })
    if (!res.ok) return
    const data = await res.json()
    const next = String(data?.v || '')
    if (!next) return

    const prev = localStorage.getItem(VERSION_KEY)
    if (!prev) {
      localStorage.setItem(VERSION_KEY, next)
      return
    }
    if (prev !== next) {
      sessionStorage.setItem(PENDING_UPDATE_KEY, next)
      window.dispatchEvent(new CustomEvent('nccia:app-update', { detail: { v: next } }))
    }
  } catch {
    // ignore offline / missing version file
  }
}

const isNative = typeof window !== 'undefined' && (
  window.Capacitor?.isNativePlatform?.() || 
  window.location.protocol === 'capacitor:' ||
  window.location.protocol === 'file:' ||
  (window.location.hostname === 'localhost' && !import.meta.env.DEV)
);

if (!isNative) {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') checkForAppUpdate()
  })
  setTimeout(checkForAppUpdate, 5000)
  setInterval(checkForAppUpdate, 120_000)
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
