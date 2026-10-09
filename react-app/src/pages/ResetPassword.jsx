import { useState, useEffect } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import axios from 'axios';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

const csrf = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true });

export default function ResetPassword() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [email, setEmail] = useState(params.get('email') || '');
  const [token, setToken] = useState(params.get('token') || '');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPwd, setShowPwd] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    if (user) {
      navigate('/', { replace: true });
    }
  }, [user, navigate]);

  useEffect(() => {
    setEmail(params.get('email') || '');
    setToken(params.get('token') || '');
  }, [params]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!token || !email) {
      setError('Invalid reset link. Request a new one from Forgot Password.');
      return;
    }
    setSaving(true);
    setMessage('');
    setError('');
    try {
      await csrf();
      const r = await api.post('/reset-password', {
        email: email.trim(),
        token,
        password,
        password_confirmation: passwordConfirmation,
      });
      setMessage(r.data?.message || 'Password updated successfully.');
      setTimeout(() => navigate('/login', { replace: true }), 2000);
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        setError(Object.values(res.errors).flat().join(' '));
      } else {
        setError(res?.message || 'Could not reset password. Try again.');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ minHeight: '100vh', background: '#f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20, fontFamily: "'DM Sans', sans-serif" }}>
      <div style={{ width: 'min(440px, 100%)', background: '#fff', borderRadius: 16, padding: '36px 32px', boxShadow: '0 12px 32px rgba(0,0,0,0.08)', border: '1px solid #e5e7eb' }}>
        <div style={{ textAlign: 'center', marginBottom: 24 }}>
          <img src="/images/NCCIA.webp" alt="NCCIA" style={{ width: 72, height: 72, borderRadius: 16, marginBottom: 12 }} />
          <h1 style={{ margin: 0, fontSize: 22, color: '#2B2B2B' }}>Reset Password</h1>
          <p style={{ margin: '8px 0 0', fontSize: 14, color: '#64748b' }}>Naya password set karein.</p>
        </div>

        {message && (
          <div style={{ background: '#ecfdf5', border: '1px solid #6ee7b7', color: '#065f46', borderRadius: 10, padding: '12px 14px', marginBottom: 16, fontSize: 13 }}>
            {message}
          </div>
        )}
        {error && (
          <div style={{ background: '#fff5f5', border: '1px solid #feb2b2', color: '#c53030', borderRadius: 10, padding: '12px 14px', marginBottom: 16, fontSize: 13 }}>
            {error}
          </div>
        )}

        {!token && (
          <div style={{ background: '#fffbeb', border: '1px solid #fcd34d', color: '#92400e', borderRadius: 10, padding: '12px 14px', marginBottom: 16, fontSize: 13 }}>
            Reset link invalid hai. <Link to="/forgot-password" style={{ color: '#015C94', fontWeight: 600 }}>Naya link mangen</Link>.
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#2B2B2B', marginBottom: 6 }}>Email</label>
          <input type="email" value={email} onChange={e => setEmail(e.target.value)} required readOnly={!!params.get('email')} style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #cbd5e1', borderRadius: 10, fontSize: 14, marginBottom: 16, boxSizing: 'border-box', background: params.get('email') ? '#f8fafc' : '#fff' }} />

          <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#2B2B2B', marginBottom: 6 }}>New Password</label>
          <div style={{ position: 'relative', marginBottom: 16 }}>
            <input type={showPwd ? 'text' : 'password'} value={password} onChange={e => setPassword(e.target.value)} required minLength={15} maxLength={128} autoComplete="new-password" placeholder="15–128 characters; choose an uncommon passphrase" style={{ width: '100%', padding: '12px 44px 12px 14px', border: '1.5px solid #264078', borderRadius: 10, fontSize: 14, boxSizing: 'border-box' }} />
            <button type="button" onClick={() => setShowPwd(v => !v)} style={{ position: 'absolute', right: 10, top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', cursor: 'pointer', color: '#64748b', fontSize: 12 }}>{showPwd ? 'Hide' : 'Show'}</button>
          </div>

          <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#2B2B2B', marginBottom: 6 }}>Confirm Password</label>
          <input type={showPwd ? 'text' : 'password'} value={passwordConfirmation} onChange={e => setPasswordConfirmation(e.target.value)} required minLength={15} maxLength={128} autoComplete="new-password" style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #264078', borderRadius: 10, fontSize: 14, marginBottom: 20, boxSizing: 'border-box' }} />

          <button type="submit" disabled={saving || !token} style={{ width: '100%', padding: 12, background: '#015C94', color: '#fff', border: 'none', borderRadius: 10, fontWeight: 600, fontSize: 14, cursor: saving || !token ? 'not-allowed' : 'pointer', opacity: saving || !token ? 0.7 : 1 }}>
            {saving ? 'Saving...' : 'Update Password'}
          </button>
        </form>

        <p style={{ textAlign: 'center', marginTop: 20, fontSize: 13 }}>
          <Link to="/login" style={{ color: '#264078', fontWeight: 600, textDecoration: 'none' }}>← Back to Sign In</Link>
        </p>
      </div>
    </div>
  );
}
