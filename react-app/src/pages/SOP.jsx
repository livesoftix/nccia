import { useState, useEffect, useCallback } from 'react';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ConfirmModal from '../components/ConfirmModal';
import ReferenceViewModal from '../components/ReferenceViewModal';
import { useAuth } from '../contexts/AuthContext';
import { canManageReference } from '../utils/permissions';

const EMPTY_FORM = { title: '', department: 'Investigation', version: '1.0', effective_date: '', content: '' };

const DEPARTMENTS = [
  'Investigation',
  'Operations',
  'Forensics',
  'Legal Branch',
  'Admin / HR',
  'Control Room / Helpdesk',
  'Evidence Custody (Malkhana)',
];

export default function SOP() {
  const { user } = useAuth();
  const canManage = canManageReference(user);
  const [sops, setSops] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modal, setModal] = useState(null); // 'create' | 'edit'
  const [editing, setEditing] = useState(null);
  const [viewTarget, setViewTarget] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const fetchSops = useCallback(() => {
    setLoading(true);
    api.get('/sops')
      .then(r => setSops(r.data.data || r.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetchSops();
  }, [fetchSops]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...EMPTY_FORM });
    setError('');
    setModal('create');
  };

  const openEdit = (s) => {
    setEditing(s);
    setForm({
      title: s.title || '',
      department: s.department || 'Investigation',
      version: s.version || '1.0',
      effective_date: s.effective_date ? s.effective_date.split('T')[0] : '',
      content: s.content || s.description || '',
    });
    setError('');
    setModal('edit');
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = { ...form, description: form.content || form.description || '' };
      delete payload.content;
      if (editing) {
        await api.put(`/sops/${editing.id}`, payload);
      } else {
        await api.post('/sops', payload);
      }
      setModal(null);
      fetchSops();
    } catch (err) {
      setError(err.response?.data?.message || 'Error saving SOP');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await api.delete(`/sops/${deleteTarget.id}`);
      setDeleteTarget(null);
      fetchSops();
    } catch {
      alert('Error deleting SOP');
    }
  };

  if (loading && sops.length === 0) return <div className="page-content"><LoadingSkeleton type="table" columns={4} rows={8} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Standard Operating Procedures (SOP)</h1>
          <p className="page-subtitle">Documented procedures for consistent operations</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          {canManage && (
          <button onClick={openCreate} className="btn btn-primary btn-sm">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> Add SOP
          </button>
          )}
        </div>
      </div>

      {!canManage && (
        <div style={{ marginBottom: 16, padding: '10px 14px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8, fontSize: 13, color: '#1e40af' }}>
          Official NCCIA SOPs — read only.
        </div>
      )}

      <div className="card">
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr><th>#</th><th>Title</th><th>Department</th><th>Version</th><th>Effective Date</th><th style={{textAlign:'center'}}>Actions</th></tr>
              </thead>
              <tbody>
                {sops.map((s) => (
                  <tr key={s.id}>
                    <td><span className="table-id">#{s.id}</span></td>
                    <td><span style={{fontSize:'13px',fontWeight:600,color:'#1e293b'}}>{s.title}</span></td>
                    <td>{s.department}</td>
                    <td><span className="badge badge-info">v{s.version}</span></td>
                    <td>{s.effective_date ? new Date(s.effective_date).toLocaleDateString('en-GB') : '-'}</td>
                    <td style={{textAlign:'center'}}>
                      <div style={{display:'flex',gap:'6px',justifyContent:'center'}}>
                        <button onClick={() => setViewTarget(s)} className="btn btn-outline btn-sm">View</button>
                        {canManage && (
                        <>
                        <button onClick={() => openEdit(s)} className="btn btn-outline btn-sm btn-icon" title="Edit">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button onClick={() => setDeleteTarget(s)} className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:8,width:32,height:32,display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}} title="Delete">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
                {sops.length === 0 && <tr><td colSpan={6} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No SOPs added yet</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal */}
      {modal && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.5)', padding: 20 }} onClick={() => setModal(null)}>
          <div style={{ background: '#fff', borderRadius: 12, width: '100%', maxWidth: 540, boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }} onClick={e => e.stopPropagation()}>
            <div style={{ padding: '18px 24px', borderBottom: '1px solid #f0f0f0' }}>
              <h3 style={{ margin: 0, fontSize: 15, fontWeight: 700, color: '#1e293b' }}>
                {modal === 'edit' ? `Edit SOP #${editing?.id}` : 'Add Standard Operating Procedure'}
              </h3>
            </div>
            <form onSubmit={handleSave} style={{ padding: '18px 24px 24px' }}>
              {error && <div style={{ background: 'rgba(229,62,62,0.1)', color: '#b42318', padding: '10px 14px', borderRadius: 8, fontSize: 13, marginBottom: 14 }}>{error}</div>}
              
              <div className="cf-field" style={{ marginBottom: 12 }}>
                <label className="cf-label required">SOP Title</label>
                <input type="text" className="cf-input" value={form.title} onChange={e => setForm({...form, title: e.target.value})} placeholder="e.g. Digital Forensic Acquisition Procedure" required />
              </div>

              <div className="cf-row-3" style={{ marginBottom: 12 }}>
                <div className="cf-field">
                  <label className="cf-label required">Department</label>
                  <select className="cf-input" value={form.department} onChange={e => setForm({...form, department: e.target.value})} required>
                    {DEPARTMENTS.map(d => <option key={d} value={d}>{d}</option>)}
                  </select>
                </div>
                <div className="cf-field">
                  <label className="cf-label required">Version</label>
                  <input type="text" className="cf-input" value={form.version} onChange={e => setForm({...form, version: e.target.value})} placeholder="e.g. 1.2" required />
                </div>
                <div className="cf-field">
                  <label className="cf-label">Effective Date</label>
                  <input type="date" className="cf-input" value={form.effective_date} onChange={e => setForm({...form, effective_date: e.target.value})} />
                </div>
              </div>

              <div className="cf-field" style={{ marginBottom: 16 }}>
                <label className="cf-label">Procedure Steps / Content</label>
                <textarea className="cf-input" rows={4} value={form.content} onChange={e => setForm({...form, content: e.target.value})} placeholder="Step-by-step procedures, protocols, and instructions..."></textarea>
              </div>

              <div style={{ display: 'flex', gap: 10 }}>
                <button type="submit" className="btn btn-primary" disabled={saving} style={{ flex: 1 }}>
                  {saving ? 'Saving...' : (modal === 'edit' ? 'Update SOP' : 'Create SOP')}
                </button>
                <button type="button" className="btn btn-outline" onClick={() => setModal(null)}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Confirmation */}
      <ConfirmModal
        open={!!deleteTarget}
        title="Delete SOP"
        message={`Are you sure you want to delete "${deleteTarget?.title}"? This cannot be undone.`}
        confirmText="Delete"
        danger={true}
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
      />

      <ReferenceViewModal
        open={!!viewTarget}
        title={viewTarget?.title}
        item={viewTarget}
        fields={[
          { key: 'department', label: 'Department' },
          { key: 'version', label: 'Version' },
          { key: 'effective_date', label: 'Effective Date' },
        ]}
        onClose={() => setViewTarget(null)}
      />
    </div>
  );
}