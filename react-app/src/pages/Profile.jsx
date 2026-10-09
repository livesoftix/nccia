import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import MfaSettings from '../components/MfaSettings';

export default function Profile() {
  const { user, logout, refreshUser } = useAuth();
  const [ioData, setIoData] = useState(null);

  useEffect(() => {
    if (user?.roles?.some?.(r => r.name === 'investigation_officer')) {
      api.get('/investigation-officers').then(r => {
        const list = r.data.data || r.data;
        const mine = Array.isArray(list) ? list.find(o => o.user_id === user.id) : null;
        if (mine) setIoData(mine);
      }).catch(() => {});
    }
  }, [user]);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '', current_password: '' });
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');
  const [sigUploading, setSigUploading] = useState(false);
  const [sigFile, setSigFile] = useState(null);
  const fileRef = useRef(null);

  const handleSigUpload = async () => {
    if (!sigFile) return;
    setSigUploading(true);
    const fd = new FormData();
    fd.append('signature', sigFile);
    try {
      await api.post('/user/upload-signature', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      setMsg('Signature uploaded successfully');
      setSigFile(null);
      if (fileRef.current) fileRef.current.value = '';
      window.location.reload();
    } catch (e) {
      setErr(e.response?.data?.message || 'Failed to upload signature');
    } finally { setSigUploading(false); }
  };

  const startEdit = () => {
    setForm({ name: user?.name || '', email: user?.email || '', password: '', password_confirmation: '', current_password: '' });
    setEditing(true);
    setMsg('');
    setErr('');
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMsg('');
    setErr('');
    try {
      await api.put('/user/profile', form);
      await refreshUser();
      setForm(f => ({ ...f, password: '', password_confirmation: '', current_password: '' }));
      setMsg('Profile updated successfully');
      setEditing(false);
    } catch (e) {
      setErr(e.response?.data?.message || 'Failed to update');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label">Account</div>
          <h1 className="page-title">My Profile</h1>
          <p className="page-subtitle">Your account information</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          {!editing && <button className="btn btn-primary btn-sm" onClick={startEdit}>Edit Profile</button>}
          <button className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:'8px'}} onClick={logout}>Logout</button>
        </div>
      </div>

      {msg && <div className="cf-alert" style={{background:'#e6f7e6',border:'1px solid #b3e6b3',color:'#2e7d32',padding:'10px 16px',borderRadius:'8px',marginBottom:'16px',fontWeight:600,fontSize:'13px'}}>{msg}</div>}
      {err && <div className="cf-alert cf-alert-error">{err}</div>}

      <div className="card">
        <div className="card-body">
          {editing ? (
            <form onSubmit={handleSave}>
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label">Name</label>
                  <div className="cf-input-wrap">
                    <input type="text" className="cf-input" value={form.name} onChange={e => setForm(f => ({...f, name: e.target.value}))} required />
                  </div>
                </div>
                <div className="cf-field">
                  <label className="cf-label">Email</label>
                  <div className="cf-input-wrap">
                    <input type="email" className="cf-input" value={form.email} onChange={e => setForm(f => ({...f, email: e.target.value}))} required />
                  </div>
                </div>
              </div>
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label">New Password</label>
                  <div className="cf-input-wrap">
                    <input type="password" className="cf-input" autoComplete="new-password" minLength={15} maxLength={128} placeholder="Leave blank to keep current" value={form.password} onChange={e => setForm(f => ({...f, password: e.target.value}))} />
                    <span style={{fontSize:12,color:'#64748b'}}>Use 15–128 characters. A long, uncommon passphrase works well.</span>
                  </div>
                </div>
                <div className="cf-field">
                  <label className="cf-label">Confirm Password</label>
                  <div className="cf-input-wrap">
                    <input type="password" className="cf-input" autoComplete="new-password" minLength={15} maxLength={128} required={!!form.password} placeholder="Confirm new password" value={form.password_confirmation} onChange={e => setForm(f => ({...f, password_confirmation: e.target.value}))} />
                  </div>
                </div>
              </div>
              <div className="cf-actions">
                {(form.password || form.email !== user?.email) && <div className="cf-field">
                  <label className="cf-label" htmlFor="profile-current-password">Current password</label>
                  <input id="profile-current-password" className="cf-input" type="password" autoComplete="current-password" value={form.current_password}
                    onChange={e => setForm(f => ({...f, current_password: e.target.value}))} required />
                </div>}
                <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving...' : 'Save Changes'}</button>
                <button type="button" className="btn btn-outline" onClick={() => setEditing(false)}>Cancel</button>
              </div>
            </form>
          ) : (
            <table className="data-table" style={{width:'auto',minWidth:400}}>
              <tbody>
                <tr><td style={{fontWeight:600,padding:'10px 20px'}}>Name</td><td>{user?.name}</td></tr>
                <tr><td style={{fontWeight:600,padding:'10px 20px'}}>Email</td><td>{user?.email}</td></tr>
                <tr><td style={{fontWeight:600,padding:'10px 20px'}}>Designation</td><td>{user?.designation || ioData?.designation || '-'}</td></tr>
                <tr><td style={{fontWeight:600,padding:'10px 20px'}}>Role</td><td>{user?.roles?.map?.(r => r.name).join(', ') || user?.role || '-'}</td></tr>
                <tr><td style={{fontWeight:600,padding:'10px 20px'}}>Zone</td><td>{user?.zone?.name || ioData?.zone || '-'}</td></tr>
                <tr><td style={{fontWeight:600,padding:'10px 20px'}}>Circle</td><td>{user?.circle?.name || ioData?.circle || '-'}</td></tr>
              </tbody>
            </table>
          )}
        </div>
      </div>

      <div className="card" style={{marginTop:'20px'}}>
        <div className="card-header"><h3>Digital Signature</h3></div>
        <div className="card-body">
          <p style={{fontSize:'13px',color:'#666',marginBottom:'12px'}}>Upload your signature once — it will be automatically used in all verification reports.</p>
          <div style={{display:'flex',alignItems:'center',gap:'16px',flexWrap:'wrap'}}>
            {user?.signature_url ? (
              <img src={user.signature_url} alt="Signature" style={{maxHeight:'60px',border:'1px solid #e0e0e0',borderRadius:'6px',background:'#fff',padding:'4px'}} />
            ) : (
              <div style={{width:'120px',height:'50px',border:'1px dashed #ccc',borderRadius:'6px',display:'flex',alignItems:'center',justifyContent:'center',fontSize:'12px',color:'#999'}}>No signature</div>
            )}
            <input type="file" ref={fileRef} accept="image/png,image/jpeg,image/jpg" onChange={e => setSigFile(e.target.files[0])} style={{fontSize:'13px'}} />
            <button className="btn btn-primary btn-sm" onClick={handleSigUpload} disabled={!sigFile || sigUploading}>
              {sigUploading ? 'Uploading...' : 'Upload Signature'}
            </button>
          </div>
        </div>
      </div>
      <MfaSettings />
    </div>
  );
}
