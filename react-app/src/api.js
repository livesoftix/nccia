import axios from 'axios';

export const getApiBaseUrl = () => {
  if (typeof window !== 'undefined') {
    const localHostnames = ['localhost', '127.0.0.1', '::1'];
    const isLocalHost = localHostnames.includes(window.location.hostname);

    if (isLocalHost) {
      localStorage.removeItem('nccia_server_url');
      return '/api';
    }

    const savedUrl = localStorage.getItem('nccia_server_url');
    if (savedUrl) return savedUrl.replace(/\/+$/, '') + '/api';
  }

  if (import.meta.env.VITE_API_URL) {
    return import.meta.env.VITE_API_URL.replace(/\/+$/, '') + '/api';
  }

  if (import.meta.env.DEV) {
    return '/api';
  }

  // Mobile APK when running in native webview (capacitor://localhost or https://localhost)
  if (typeof window !== 'undefined' && (
    window.Capacitor?.isNativePlatform?.() || 
    window.location.protocol === 'capacitor:' ||
    window.location.protocol === 'file:' ||
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1' ||
    window.location.pathname.endsWith('.html')
  )) {
    return 'https://nccia.real-erp.net/api';
  }

  return '/api';
};

const api = axios.create({
  baseURL: getApiBaseUrl(),
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  config.baseURL = getApiBaseUrl();

  // Attach Bearer token if present (for mobile app)
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('auth_token');
    if (token && !config.headers.Authorization) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }

  // Let the browser set multipart boundary for FormData (JSON content-type breaks uploads/updates)
  if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
    if (config.headers) {
      delete config.headers['Content-Type'];
      delete config.headers['content-type'];
    }
  }
  return config;
});

let csrfReady = false;
export const ensureCsrf = async () => {
  if (csrfReady) return;
  const base = getApiBaseUrl().replace(/\/api\/?$/, '');
  const csrfUrl = base ? `${base}/sanctum/csrf-cookie` : '/sanctum/csrf-cookie';
  try {
    await axios.get(csrfUrl, { withCredentials: true });
    csrfReady = true;
  } catch (e) {
    // If running native app without cookies, token auth will handle request authorization
  }
};

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { config, response } = error;
    
    // Replace generic Laravel 500 text so users see actionable messages when we provide them.
    if (response && response.data) {
      const msg = response.data.message;
      if (msg === 'Server Error' || msg === 'Internal Server Error') {
        response.data.message = 'An unexpected error occurred. Please try again or contact admin.';
      } else if (!msg && response.data.errors) {
        const first = Object.values(response.data.errors).flat()[0];
        if (first) {
          response.data.message = first;
        }
      }
    }

    if (response && response.status === 419 && !config._retried) {
      config._retried = true;
      csrfReady = false;
      try {
        await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
        csrfReady = true;
        return api(config);
      } catch (e) {
        return Promise.reject(e);
      }
    }
    return Promise.reject(error);
  }
);

export default api;
