import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

const csrf = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true });

export default function ForgotPassword() {
  const { user } = useAuth();
  const [email, setEmail] = useState('');
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    if (user) {
      window.location.href = '/';
    }
  }, [user]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage('');
    setError('');
    try {
      await csrf();
      const r = await api.post('/forgot-password', { email: email.trim() });
      setMessage(r.data?.message || 'If that email is registered, a reset link has been sent.');
      setEmail('');
    } catch (err) {
      setError(err.response?.data?.message || 'Could not send reset link. Try again.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ minHeight: '100vh', background: '#f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20, fontFamily: "'DM Sans', sans-serif" }}>
      <div style={{ width: 'min(440px, 100%)', background: '#fff', borderRadius: 16, padding: '36px 32px', boxShadow: '0 12px 32px rgba(0,0,0,0.08)', border: '1px solid #e5e7eb' }}>
        <div style={{ textAlign: 'center', marginBottom: 24 }}>
          <img src="/images/NCCIA.webp" alt="NCCIA" style={{ width: 72, height: 72, borderRadius: 16, marginBottom: 12 }} />
          <h1 style={{ margin: 0, fontSize: 22, color: '#2B2B2B' }}>Forgot Password</h1>
          <p style={{ margin: '8px 0 0', fontSize: 14, color: '#64748b' }}>Enter your officer email — reset link bhej denge.</p>
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

        <form onSubmit={handleSubmit}>
          <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#2B2B2B', marginBottom: 6, textTransform: 'uppercase', letterSpacing: '.04em' }}>Email Address</label>
          <input
            type="email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            placeholder="officer@NCCIA.gov.pk"
            required
            autoComplete="email"
            style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #264078', borderRadius: 10, fontSize: 14, marginBottom: 20, boxSizing: 'border-box' }}
          />
          <button type="submit" disabled={saving} style={{ width: '100%', padding: 12, background: '#015C94', color: '#fff', border: 'none', borderRadius: 10, fontWeight: 600, fontSize: 14, cursor: saving ? 'wait' : 'pointer', opacity: saving ? 0.7 : 1 }}>
            {saving ? 'Sending...' : 'Send Reset Link'}
          </button>
        </form>

        <p style={{ textAlign: 'center', marginTop: 20, fontSize: 13 }}>
          <Link to="/login" style={{ color: '#264078', fontWeight: 600, textDecoration: 'none' }}>← Back to Sign In</Link>
        </p>
      </div>
    </div>
  );
}
