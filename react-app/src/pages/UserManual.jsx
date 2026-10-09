import { useState, useEffect, useCallback } from 'react';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ConfirmModal from '../components/ConfirmModal';
import ReferenceViewModal from '../components/ReferenceViewModal';
import { useAuth } from '../contexts/AuthContext';
import { canManageReference } from '../utils/permissions';

const EMPTY_FORM = { title: '', audience: 'All Users', version: '1.0', content: '', document_path: '' };

const AUDIENCES = [
  'All Users',
  'Enquiry Officers',
  'Investigation Officers',
  'Verification Officers',
  'Circle Incharge',
  'Moharrar / Front Desk',
  'Legal Branch',
  'Administrators',
];

export default function UserManual() {
  const { user } = useAuth();
  const canManage = canManageReference(user);
  const [manuals, setManuals] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modal, setModal] = useState(null); // 'create' | 'edit'
  const [editing, setEditing] = useState(null);
  const [viewTarget, setViewTarget] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const fetchManuals = useCallback(() => {
    setLoading(true);
    api.get('/user-manuals')
      .then(r => setManuals(r.data.data || r.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetchManuals();
  }, [fetchManuals]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...EMPTY_FORM });
    setError('');
    setModal('create');
  };

  const openEdit = (m) => {
    setEditing(m);
    setForm({
      title: m.title || '',
      audience: m.audience || 'All Users',
      version: m.version || '1.0',
      content: m.content || m.description || '',
      document_path: m.document_path || '',
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
        await api.put(`/user-manuals/${editing.id}`, payload);
      } else {
        await api.post('/user-manuals', payload);
      }
      setModal(null);
      fetchManuals();
    } catch (err) {
      setError(err.response?.data?.message || 'Error saving user manual');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await api.delete(`/user-manuals/${deleteTarget.id}`);
      setDeleteTarget(null);
      fetchManuals();
    } catch {
      alert('Error deleting user manual');
    }
  };

  if (loading && manuals.length === 0) return <div className="page-content"><LoadingSkeleton type="table" columns={4} rows={8} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">User Manuals</h1>
          <p className="page-subtitle">Guides & documentation for system users</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          {canManage && (
          <button onClick={openCreate} className="btn btn-primary btn-sm">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> Add Manual
          </button>
          )}
        </div>
      </div>

      {!canManage && (
        <div style={{ marginBottom: 16, padding: '10px 14px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8, fontSize: 13, color: '#1e40af' }}>
          Official NCCIA user manuals — read only.
        </div>
      )}

      <div className="card">
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr><th>#</th><th>Title</th><th>Audience</th><th>Version</th><th>Updated</th><th style={{textAlign:'center'}}>Actions</th></tr>
              </thead>
              <tbody>
                {manuals.map((m) => (
                  <tr key={m.id}>
                    <td><span className="table-id">#{m.id}</span></td>
                    <td><span style={{fontSize:'13px',fontWeight:600,color:'#1e293b'}}>{m.title}</span></td>
                    <td><span className="badge badge-secondary">{m.audience}</span></td>
                    <td><span className="badge badge-info">v{m.version}</span></td>
                    <td>{m.updated_at ? new Date(m.updated_at).toLocaleDateString('en-GB') : '-'}</td>
                    <td style={{textAlign:'center'}}>
                      <div style={{display:'flex',gap:'6px',justifyContent:'center'}}>
                        <button onClick={() => setViewTarget(m)} className="btn btn-outline btn-sm">View</button>
                        {canManage && (
                        <>
                        <button onClick={() => openEdit(m)} className="btn btn-outline btn-sm btn-icon" title="Edit">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button onClick={() => setDeleteTarget(m)} className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:8,width:32,height:32,display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}} title="Delete">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
                {manuals.length === 0 && <tr><td colSpan={6} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No manuals added yet</td></tr>}
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
                {modal === 'edit' ? `Edit User Manual #${editing?.id}` : 'Add New User Manual'}
              </h3>
            </div>
            <form onSubmit={handleSave} style={{ padding: '18px 24px 24px' }}>
              {error && <div style={{ background: 'rgba(229,62,62,0.1)', color: '#b42318', padding: '10px 14px', borderRadius: 8, fontSize: 13, marginBottom: 14 }}>{error}</div>}
              
              <div className="cf-field" style={{ marginBottom: 12 }}>
                <label className="cf-label required">Manual / Guide Title</label>
                <input type="text" className="cf-input" value={form.title} onChange={e => setForm({...form, title: e.target.value})} placeholder="e.g. Enquiry Officer Complete Flow Guide" required />
              </div>

              <div className="cf-row-2" style={{ marginBottom: 12 }}>
                <div className="cf-field">
                  <label className="cf-label required">Target Audience</label>
                  <select className="cf-input" value={form.audience} onChange={e => setForm({...form, audience: e.target.value})} required>
                    {AUDIENCES.map(a => <option key={a} value={a}>{a}</option>)}
                  </select>
                </div>
                <div className="cf-field">
                  <label className="cf-label required">Version</label>
                  <input type="text" className="cf-input" value={form.version} onChange={e => setForm({...form, version: e.target.value})} placeholder="e.g. 2.0" required />
                </div>
              </div>

              <div className="cf-field" style={{ marginBottom: 16 }}>
                <label className="cf-label">User Instructions / Content</label>
                <textarea className="cf-input" rows={4} value={form.content} onChange={e => setForm({...form, content: e.target.value})} placeholder="Guide content, instructions, workflow steps, FAQs..."></textarea>
              </div>

              <div style={{ display: 'flex', gap: 10 }}>
                <button type="submit" className="btn btn-primary" disabled={saving} style={{ flex: 1 }}>
                  {saving ? 'Saving...' : (modal === 'edit' ? 'Update Manual' : 'Create Manual')}
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
        title="Delete User Manual"
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
          { key: 'audience', label: 'Audience' },
          { key: 'version', label: 'Version' },
        ]}
        onClose={() => setViewTarget(null)}
      />
    </div>
  );
}