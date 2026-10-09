import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api, { ensureCsrf } from '../api';

import { getClientWebRtcIps } from '../utils/detectClientIp';

export const csrf = () => ensureCsrf();

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [remaining, setRemaining] = useState(null);
  const [retryAfter, setRetryAfter] = useState(null);
  const [mfaRequired, setMfaRequired] = useState(false);

  const refreshUser = async () => {
    const response = await api.get('/user');
    setUser(response.data);
    return response.data;
  };

  const checkAuth = useCallback(() => {
    const forced = sessionStorage.getItem('force_logout');
    if (forced) {
      sessionStorage.removeItem('force_logout');
      localStorage.removeItem('auth_token');
      setLoading(false);
      return;
    }
    csrf().finally(() => {
      api.get('/user').then(r => setUser(r.data)).catch(() => setUser(null)).finally(() => setLoading(false));
    });
  }, []);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  const login = async (email, password, mfaCode = '', captchaToken = '') => {
    setError(null);
    setRemaining(null);
    setRetryAfter(null);
    try {
      await csrf();
      const client_webrtc_ips = await getClientWebRtcIps();
      const r = await api.post('/login', { email, password, mfa_code: mfaCode, captcha_token: captchaToken, client_webrtc_ips });
      setMfaRequired(false);
      setUser(r.data.user);
      if (r.data?.token) {
        localStorage.setItem('auth_token', r.data.token);
      }
      return r.data;
    } catch (err) {
      const data = err.response?.data || {};
      setMfaRequired(!!data.mfa_required);
      const msg = data.message || 'Login failed';
      if (err.response?.status === 429) {
        setRetryAfter(data.retry_after || 60);
        setError(msg);
      } else {
        setRemaining(data.remaining);
        setError(data.remaining !== undefined ? `${msg} (${data.remaining} attempt${data.remaining === 1 ? '' : 's'} left)` : msg);
      }
      throw err;
    }
  };

  const forensicLogin = async (email, password, mfaCode = '', captchaToken = '') => {
    setError(null);
    setRemaining(null);
    setRetryAfter(null);
    try {
      await csrf();
      const client_webrtc_ips = await getClientWebRtcIps();
      const r = await api.post('/forensic/login', { email, password, mfa_code: mfaCode, captcha_token: captchaToken, client_webrtc_ips });
      setMfaRequired(false);
      setUser(r.data.user);
      return r.data;
    } catch (err) {
      const data = err.response?.data || {};
      setMfaRequired(!!data.mfa_required);
      const msg = data.message || 'Login failed';
      if (err.response?.status === 429) {
        setRetryAfter(data.retry_after || 60);
        setError(msg);
      } else {
        setRemaining(data.remaining);
        setError(data.remaining !== undefined ? `${msg} (${data.remaining} attempt${data.remaining === 1 ? '' : 's'} left)` : msg);
      }
      throw err;
    }
  };

  const clearError = useCallback(() => { setError(null); setRemaining(null); setRetryAfter(null); }, []);

  const logout = async () => {
    try { await api.post('/logout'); } catch { /* Clear the local login even if the session expired. */ }
    localStorage.removeItem('auth_token');
    setUser(null);
    setMfaRequired(false);
    if (typeof window !== 'undefined' && (window.Capacitor?.isNativePlatform?.() || window.location.protocol === 'capacitor:' || window.location.hostname === 'localhost')) {
      window.location.hash = '/login';
    } else {
      window.location.href = '/force-logout';
    }
  };

  return (
    <AuthContext.Provider value={{ user, loading, error, remaining, retryAfter, mfaRequired, refreshUser, login, forensicLogin, logout, clearError }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
