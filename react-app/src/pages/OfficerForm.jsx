import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../api';
import { countryCodes } from '../data/countries';

const SearchableSelect = ({ options, value, onChange, placeholder }) => {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const ref = useRef(null);
  useEffect(() => {
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);
  const filtered = options.filter(o => o.label.toLowerCase().includes(search.toLowerCase()));
  return (
    <div className="cf-input-wrap" ref={ref} style={{ position: 'relative' }}>
      <input
        className="cf-input"
        placeholder={placeholder}
        value={open ? search : (value || '')}
        onFocus={() => { setOpen(true); setSearch(''); }}
        onChange={e => setSearch(e.target.value)}
        autoComplete="off"
      />
      {open && (
        <div style={{ position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 99, background: '#fff', border: '1px solid #ccc', maxHeight: 220, overflowY: 'auto', borderRadius: '0 0 6px 6px' }}>
          <div
            style={{ padding: '8px 12px', cursor: 'pointer', background: !value ? '#e8f0fe' : 'transparent', fontWeight: value ? 400 : 500 }}
            onClick={() => { onChange(''); setOpen(false); setSearch(''); }}
          >— Select —</div>
          {filtered.map(c => (
            <div
              key={c.value}
              style={{ padding: '8px 12px', cursor: 'pointer', background: value === c.label ? '#e8f0fe' : 'transparent', fontWeight: value === c.label ? 600 : 400 }}
              onClick={() => { onChange(c.label); setOpen(false); setSearch(''); }}
            >{c.label}</div>
          ))}
          {filtered.length === 0 && <div style={{ padding: '8px 12px', color: '#999' }}>No results</div>}
        </div>
      )}
    </div>
  );
};

const initialForm = {
  name: '',
  badge_no: '',
  designation: '',
  circle: '',
  zone: '',
  email: '',
  contact_no: '',
  contact_country_code: '+92',
  address: '',
  date_of_joining: '',
  status: 'active',
  remarks: '',
};

export default function OfficerForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [form, setForm] = useState(initialForm);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [circles, setCircles] = useState([]);
  const [zones, setZones] = useState([]);

  const setF = (field) => (e) => setForm(f => ({ ...f, [field]: e.target.value }));

  const setPhone = (e) => {
    let val = e.target.value.replace(/\D/g, '').replace(/^0+/, '');
    if (val.length > 10) val = val.slice(0, 10);
    setForm(f => ({ ...f, contact_no: val }));
  };

  useEffect(() => {
    api.get('/lookup/circles').then(r => setCircles(Array.isArray(r.data) ? r.data : (r.data?.data || []))).catch(() => {});
    api.get('/lookup/zones').then(r => setZones(Array.isArray(r.data) ? r.data : (r.data?.data || []))).catch(() => {});
    if (id) {
      api.get(`/investigation-officers/${id}`).then(r => {
        const d = r.data.data || r.data;
        if (d.contact_no) d.contact_no = String(d.contact_no).replace(/\D/g, '').replace(/^0+/, '');
        if (!d.contact_country_code) d.contact_country_code = '+92';
        if (d.date_of_joining) d.date_of_joining = String(d.date_of_joining).slice(0, 10);
        setForm({ ...initialForm, ...d });
      }).catch(() => navigate('/investigation-officers'));
    }
  }, [id, navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setServerError('');
    try {
      const payload = {
        ...form,
        designation: form.designation || null,
        circle: form.circle || null,
        zone: form.zone || null,
        email: form.email || null,
        address: form.address || null,
        remarks: form.remarks || null,
        date_of_joining: form.date_of_joining || null,
      };
      if (id) {
        await api.put(`/investigation-officers/${id}`, payload);
      } else {
        await api.post('/investigation-officers', payload);
      }
      navigate('/investigation-officers');
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        setErrors(res.errors);
        setServerError('Please fix the highlighted fields below.');
      } else {
        setServerError(res?.message || 'Error saving officer. Please try again.');
      }
    } finally {
      setSaving(false);
    }
  };

  const renderField = (label, field, opts = {}) => {
    const { type = 'text', required = false, options = null } = opts;
    const fieldErr = errors[field];
    const errText = Array.isArray(fieldErr) ? fieldErr[0] : fieldErr;
    return (
      <div className="cf-field">
        <label className={`cf-label${required ? ' required' : ''}`}>{label}</label>
        {options ? (
          <select className="cf-input" value={form[field] || ''} onChange={setF(field)}>
            {options.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
          </select>
        ) : (
          <input type={type} className="cf-input" value={form[field] || ''} onChange={setF(field)} required={required} />
        )}
        {errText && <div className="cf-error">{errText}</div>}
      </div>
    );
  };

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">{id ? 'Edit Investigation Officer' : 'New Investigation Officer'}</h1>
          <p className="page-subtitle">{id ? 'Update officer details' : 'Add a new investigation officer'}</p>
          <div className="title-underline"></div>
        </div>
      </div>

      <div className="cf-section">
        <div className="cf-section-header">
          <div className="cf-section-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div><div className="cf-section-title">Personal Information</div><div className="cf-section-sub">Basic details of the officer</div></div>
        </div>
        <div className="cf-body">
          <form onSubmit={handleSubmit}>
            <div className="cf-row-2">
              {renderField('Badge No', 'badge_no', { required: true })}
              {renderField('Full Name', 'name', { required: true })}
            </div>
            <div className="cf-row-2">
              {renderField('Designation', 'designation')}
              {renderField('Date of Joining', 'date_of_joining', { type: 'date' })}
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">Circle</label>
                <SearchableSelect options={circles.map(c => ({ label: c.name, value: c.id }))} value={form.circle} onChange={v => setForm(f => ({ ...f, circle: v }))} placeholder="Select Circle" />
                {errors.circle && <div className="cf-error">{Array.isArray(errors.circle) ? errors.circle[0] : errors.circle}</div>}
              </div>
              <div className="cf-field">
                <label className="cf-label">Zone</label>
                <SearchableSelect options={zones.map(z => ({ label: z.name, value: z.id }))} value={form.zone} onChange={v => setForm(f => ({ ...f, zone: v }))} placeholder="Select Zone" />
                {errors.zone && <div className="cf-error">{Array.isArray(errors.zone) ? errors.zone[0] : errors.zone}</div>}
              </div>
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">Contact No</label>
                <div style={{ display: 'flex', gap: '8px' }}>
                  <select className="cf-input" value={form.contact_country_code || '+92'} onChange={setF('contact_country_code')} style={{ width: '190px' }}>
                    {countryCodes.map(c => <option key={c.code + c.name} value={c.code}>{c.code} — {c.name}</option>)}
                  </select>
                  <input type="text" className="cf-input" value={form.contact_no || ''} onChange={setPhone} placeholder="3XXXXXXXXX" maxLength={10} style={{ flex: 1 }} />
                </div>
                {errors.contact_no && <div className="cf-error">{Array.isArray(errors.contact_no) ? errors.contact_no[0] : errors.contact_no}</div>}
              </div>
              {renderField('Email', 'email', { type: 'email' })}
            </div>
            {renderField('Address', 'address')}
            <div className="cf-field">
              <label className="cf-label">Remarks</label>
              <textarea className="cf-input cf-textarea" name="remarks" rows="2" value={form.remarks || ''} onChange={setF('remarks')}></textarea>
            </div>
            <div className="cf-row-2">
              {renderField('Status', 'status', { options: [{ value: 'active', name: 'Active' }, { value: 'inactive', name: 'Inactive' }] })}
              <div className="cf-field"></div>
            </div>
            {serverError && (
              <div className="cf-alert cf-alert-error">{serverError}</div>
            )}

            <div style={{ display: 'flex', gap: 12, justifyContent: 'flex-end', marginTop: 20 }}>
              <button type="button" className="btn btn-outline" onClick={() => navigate('/investigation-officers')}>Cancel</button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : (id ? 'Update Officer' : 'Save Officer')}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
