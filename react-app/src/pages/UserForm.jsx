import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

const roles = [
  'admin', 'operator', 'verification_officer', 'circle_incharge',
  'enquiry_officer', 'investigation_officer', 'moharrar', 'reader_branch',
  'ad_legal', 'dd_legal', 'additional_director', 'ad_administration', 'director_general',
  'admin_forensic', 'dd_forensic', 'ad_forensic', 'desk_forensic', 'forensic_team',
];

const CI_ALLOWED_ROLES = [
  { value: 'investigation_officer', label: 'Investigation Officer (IO)' },
  { value: 'enquiry_officer', label: 'Enquiry Officer (EO)' },
  { value: 'moharrar', label: 'Moharrar' },
  { value: 'operator', label: 'Front Desk Officer (FDO)' },
  { value: 'ad_administration', label: 'AD Administration (AAD)' },
  { value: 'reader_branch', label: 'Reader Branch' },
  { value: 'verification_officer', label: 'Verification Officer (VO)' },
];

export default function UserForm() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const { user: currentUser } = useAuth();
  const isCi = currentUser?.role === 'circle_incharge' || currentUser?.roles?.some(r => (r.name || r) === 'circle_incharge');
  const isSuper = currentUser?.role === 'admin' || currentUser?.role === 'director_general' || currentUser?.roles?.some(r => ['admin', 'director_general'].includes(r.name || r));

  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    role: '',
    designation: '',
    circle_id: '',
    zone_id: '',
  });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [circles, setCircles] = useState([]);
  const [zones, setZones] = useState([]);

  useEffect(() => {
    api.get('/lookup/zones').then(r => setZones(r.data || [])).catch(() => {});
    api.get('/lookup/circles').then(r => setCircles(r.data || [])).catch(() => {});
  }, []);

  useEffect(() => {
    if (!isEdit && isCi && !isSuper && currentUser?.circle_id) {
      setForm(f => ({
        ...f,
        circle_id: currentUser.circle_id,
        zone_id: currentUser.circle?.zone_id || currentUser.zone_id || '',
      }));
    }
  }, [isEdit, isCi, isSuper, currentUser]);

  useEffect(() => {
    if (isEdit) {
      api.get(`/users/${id}`).then(r => {
        const u = r.data.data || r.data;
        setForm({
          name: u.name || '',
          email: u.email || '',
          password: '',
          role: u.roles?.[0]?.name || '',
          designation: u.designation || '',
          circle_id: u.circle_id || '',
          zone_id: u.zone_id || '',
        });
      }).catch(() => navigate('/users'));
    }
  }, [id]);

  const setF = (field) => (e) => {
    setForm(f => ({ ...f, [field]: e.target.value }));
    setErrors(e => ({ ...e, [field]: null }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      if (isEdit) {
        await api.put(`/users/${id}`, form);
      } else {
        await api.post('/users', form);
      }
      navigate('/users');
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors || {});
      } else {
        alert(err.response?.data?.message || 'Failed to save user');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">{isEdit ? 'Edit User' : 'Add User'}</h1>
          <p className="page-subtitle">{isEdit ? 'Update user details & role' : 'Create a new system user with role'}</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <a href="/users" className="btn btn-outline btn-sm" onClick={(e) => { e.preventDefault(); navigate('/users'); }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 18 9 12 15 6"/></svg>
            Back
          </a>
        </div>
      </div>

      <form onSubmit={handleSubmit} style={{margin:'0 auto'}}>
        {Object.keys(errors).length > 0 && (
          <div style={{background:'rgba(229,62,62,0.1)',border:'1px solid #e53e3e',borderRadius:8,padding:'12px 16px',marginBottom:16}}>
            <ul style={{margin:0,paddingLeft:18,fontSize:13,color:'#e53e3e'}}>
              {Object.entries(errors).map(([key, msgs]) => (
                Array.isArray(msgs) ? msgs.map((msg, i) => <li key={key+i}>{msg}</li>) : <li key={key}>{msgs}</li>
              ))}
            </ul>
          </div>
        )}

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#2B2B2B'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Account Details</div>
              <div className="cf-section-sub">Login credentials & role assignment</div>
            </div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">Full Name</label>
                <input className="cf-input" type="text" value={form.name} onChange={setF('name')} required />
                {errors.name && <span className="cf-error">{errors.name[0]}</span>}
              </div>
              <div className="cf-field">
                <label className="cf-label required">Email (Login ID)</label>
                <input className="cf-input" type="email" value={form.email} onChange={setF('email')} required />
                {errors.email && <span className="cf-error">{errors.email[0]}</span>}
              </div>
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">Password {isEdit && <span style={{fontSize:11,color:'#6c757d',fontWeight:400}}>(leave empty to keep current)</span>}</label>
                <input className="cf-input" type="password" autoComplete="new-password" value={form.password} onChange={setF('password')} placeholder={isEdit ? 'Leave empty to keep' : '15–128 characters'} required={!isEdit} minLength={15} maxLength={128} />
                <span style={{fontSize:11,color:'#6c757d',display:'block',marginTop:2}}>Use a long, uncommon passphrase of 15–128 characters.</span>
                {errors.password && <span className="cf-error">{errors.password[0]}</span>}
              </div>
              <div className="cf-field">
                <label className="cf-label required">Role</label>
                <select className="cf-input" value={form.role} onChange={setF('role')} required>
                  <option value="">— Select Role —</option>
                  {(isCi && !isSuper ? CI_ALLOWED_ROLES : roles.map(r => ({ value: r, label: r === 'operator' ? 'Front Desk Officer' : r.replace(/_/g, ' ') }))).map(r => (
                    <option key={r.value} value={r.value}>{r.label}</option>
                  ))}
                </select>
                {errors.role && <span className="cf-error">{errors.role[0]}</span>}
              </div>
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">Designation</label>
                <input className="cf-input" type="text" value={form.designation} onChange={setF('designation')} placeholder={isCi ? "e.g. Sub Inspector / Reader / Moharrar" : "e.g. Director General"} />
              </div>
              <div className="cf-field"></div>
            </div>

            {['director_general', 'additional_director', 'dd_legal', 'ad_legal', 'admin'].includes(form.role) && (
              <div style={{
                background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 8,
                padding: '12px 16px', margin: '8px 0 16px', display: 'flex', alignItems: 'center',
                justifyContent: 'space-between', gap: 12, flexWrap: 'wrap'
              }}>
                <div style={{ fontSize: 13, color: '#0369a1', lineHeight: 1.45, maxWidth: 550 }}>
                  <strong>🏛️ Headquarters Role (National Jurisdiction):</strong> This executive officer operates across Pakistan (Islamabad HQ). Leave Zone &amp; Circle as National/HQ to oversee all circles, or click below.
                </div>
                <button
                  type="button"
                  onClick={() => setForm(f => ({ ...f, zone_id: '', circle_id: '' }))}
                  style={{
                    padding: '6px 14px', background: '#015C94', color: '#fff',
                    border: 'none', borderRadius: 6, fontSize: 12, fontWeight: 700, cursor: 'pointer'
                  }}
                >
                  📍 Set as Islamabad HQ (National)
                </button>
              </div>
            )}

            {isCi && !isSuper ? (
              <div style={{
                background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8,
                padding: '14px 18px', margin: '12px 0 16px', display: 'flex', alignItems: 'center',
                gap: 12
              }}>
                <span style={{ fontSize: 24 }}>🏢</span>
                <div style={{ fontSize: 13, color: '#1e40af', lineHeight: 1.45 }}>
                  <strong>Station Multi-Tenancy Protection:</strong> This officer will be permanently assigned to your circle (<strong>{circles.find(c => c.id == currentUser?.circle_id)?.name || currentUser?.circle?.name || 'Your Circle'}</strong>). Regional officers only access complaints, verifications and enquiries in their assigned station.
                </div>
              </div>
            ) : (
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label">Zone</label>
                  <select className="cf-input" value={form.zone_id} onChange={e => { setForm(f => ({ ...f, zone_id: e.target.value, circle_id: '' })); setErrors(e => ({ ...e, zone_id: null })); }}>
                    <option value="">{['director_general', 'additional_director', 'dd_legal', 'ad_legal', 'admin'].includes(form.role) ? '— National / All Zones (Islamabad HQ) —' : '— Select Zone —'}</option>
                    {zones.map(z => <option key={z.id} value={z.id}>{z.name} ({z.code})</option>)}
                  </select>
                </div>
                <div className="cf-field">
                  <label className="cf-label">Circle</label>
                  <select className="cf-input" value={form.circle_id} onChange={setF('circle_id')}>
                    <option value="">{['director_general', 'additional_director', 'dd_legal', 'ad_legal', 'admin'].includes(form.role) ? '— All Circles / National Jurisdiction (Islamabad HQ) —' : '— Select Circle —'}</option>
                    {circles.filter(c => !form.zone_id || c.zone_id == form.zone_id).map(c => <option key={c.id} value={c.id}>{c.name} ({c.code})</option>)}
                  </select>
                </div>
              </div>
            )}
          </div>
        </div>

        <div style={{display:'flex',gap:10,justifyContent:'flex-end',marginTop:16}}>
          <button type="button" className="btn btn-outline" onClick={() => navigate('/users')}>Cancel</button>
          <button type="submit" className="btn cf-submit-btn" disabled={saving} style={{background:'#015C94',color:'#fff',padding:'12px 24px',fontWeight:600,fontSize:'14px',borderRadius:'8px',border:'none',cursor:saving?'not-allowed':'pointer',opacity:saving?0.7:1}}>
            {saving ? 'Saving...' : (isEdit ? 'Update User' : 'Create User')}
          </button>
        </div>
      </form>
    </div>
  );
}
