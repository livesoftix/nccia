import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

export default function MfaSettings({ enrollment = false }) {
  const { user, refreshUser, logout } = useAuth();
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [setup, setSetup] = useState(null);
  const [qr, setQr] = useState('');
  const [recovery, setRecovery] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let active = true;
    if (setup?.uri) QRCode.toDataURL(setup.uri).then(url => { if (active) setQr(url); });
    return () => { active = false; };
  }, [setup]);

  const act = async (event, action) => {
    event.preventDefault();
    if (busy) return;
    setBusy(true); setError(''); setMessage('');
    try {
      if (action === 'setup') {
        const response = await api.post('/auth/mfa/setup', { password });
        setSetup(response.data); setPassword('');
      } else if (action === 'confirm') {
        const response = await api.post('/auth/mfa/confirm', { code });
        setRecovery(response.data.recovery_codes); setSetup(null); setQr(''); setCode('');
        setMessage('Two-factor authentication is enabled. Save your recovery codes before continuing.');
        // In required enrollment keep this screen until the user saves the one-time codes.
        if (!enrollment) await refreshUser();
      } else if (action === 'regenerate') {
        const response = await api.post('/auth/mfa/recovery-codes', { password, code });
        setRecovery(response.data.recovery_codes); setPassword(''); setCode('');
        setMessage('Your previous recovery codes have been replaced. Save these new codes offline before continuing.');
      } else {
        await api.delete('/auth/mfa', { data: { password, code } });
        setPassword(''); setCode(''); await refreshUser();
        setMessage('Two-factor authentication disabled.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'The security setting could not be updated.');
    } finally { setBusy(false); }
  };

  const restartSetup = () => {
    if (busy) return;
    setSetup(null); setQr(''); setCode(''); setPassword('');
    setError(''); setMessage('');
  };

  const acknowledgeRecovery = async () => {
    if (busy) return;
    setBusy(true); setError('');
    try {
      await refreshUser();
      setRecovery([]);
    } catch (err) {
      setError(err.response?.data?.message || 'Could not refresh your account. Your recovery codes are still shown; save them and try again.');
    } finally { setBusy(false); }
  };

  return <div className="card" style={{marginTop:20,maxWidth:680}}>
    <div className="card-header"><h3>Two-factor authentication</h3></div>
    <div className="card-body">
      {enrollment && <p>Your account requires an authenticator app before you can access the portal.</p>}
      {error && <p role="alert" style={{color:'#b91c1c'}}>{error}</p>}
      {message && <p role="status">{message}</p>}
      {recovery.length > 0 ? <div>
        <p>Keep these codes in a safe place. Each code works once. They will not be displayed again.</p>
        <pre style={{padding:16,background:'#f1f5f9',userSelect:'all'}}>{recovery.join('\n')}</pre>
        <button type="button" className="btn btn-primary" disabled={busy} onClick={acknowledgeRecovery}>I saved my recovery codes</button>
      </div> : setup ? <form onSubmit={e => act(e, 'confirm')}>
        <p>Scan this QR code in your authenticator app, or enter the setup key manually.</p>
        {qr && <img src={qr} alt="Authenticator setup QR code" width={200} height={200} />}
        <p><strong>Setup key:</strong> <code style={{userSelect:'all'}}>{setup.secret}</code></p>
        <label className="cf-label" htmlFor="setup-code">Six-digit authenticator code</label>
        <input className="cf-input" id="setup-code" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6}
          value={code} onChange={e => setCode(e.target.value)} required />
        <button className="btn btn-primary" disabled={busy} style={{marginTop:12}}>Confirm and enable</button>
        <button type="button" className="btn btn-outline" disabled={busy} style={{marginTop:12,marginLeft:8}} onClick={restartSetup}>Start setup again</button>
      </form> : !user?.mfa_enabled ? <form onSubmit={e => act(e, 'setup')}>
        <p>Add an authenticator app to protect your account.</p>
        <label className="cf-label" htmlFor="mfa-password">Current password</label>
        <input className="cf-input" id="mfa-password" type="password" autoComplete="current-password" value={password}
          onChange={e => setPassword(e.target.value)} required />
        <button className="btn btn-primary" disabled={busy} style={{marginTop:12}}>Set up authenticator</button>
      </form> : <div>
        <p>Authenticator protection is enabled.</p>
        <details><summary>Replace recovery codes</summary>
          <p>Replacing codes invalidates all previous recovery codes and signs out your other sessions.</p>
          <form onSubmit={e => act(e, 'regenerate')}>
            <label className="cf-label" htmlFor="recovery-password">Current password</label>
            <input className="cf-input" id="recovery-password" type="password" autoComplete="current-password" value={password}
              onChange={e => setPassword(e.target.value)} required />
            <label className="cf-label" htmlFor="recovery-code">Authenticator or unused recovery code</label>
            <input className="cf-input" id="recovery-code" autoComplete="one-time-code" maxLength={20} value={code}
              onChange={e => setCode(e.target.value)} required />
            <button className="btn btn-primary" disabled={busy} style={{marginTop:12}}>Replace recovery codes</button>
          </form>
        </details>
        {!user?.mfa_required && <details><summary>Disable authenticator protection</summary>
          <form onSubmit={e => act(e, 'disable')}>
            <label className="cf-label" htmlFor="disable-password">Current password</label>
            <input className="cf-input" id="disable-password" type="password" autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} required />
            <label className="cf-label" htmlFor="disable-code">Authenticator or recovery code</label>
            <input className="cf-input" id="disable-code" autoComplete="one-time-code" maxLength={20} value={code} onChange={e => setCode(e.target.value)} required />
            <button className="btn btn-outline" disabled={busy} style={{marginTop:12}}>Disable</button>
          </form>
        </details>}
      </div>}
      {enrollment && <button className="btn btn-outline" style={{marginTop:16}} onClick={logout}>Sign out</button>}
    </div>
  </div>;
}
