import { useState, useEffect } from 'react';
import api from '../api';
import ConfirmModal from '../components/ConfirmModal';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function OffenceTypes() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editItem, setEditItem] = useState(null);
  const [form, setForm] = useState({ name: '', value: '', group: '' });
  const [deleteTarget, setDeleteTarget] = useState(null);

  const fetchData = () => {
    api.get('/offence-types').then(r => setList(r.data.data || r.data)).finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, []);

  const resetForm = () => { setForm({ name: '', value: '', group: '' }); setEditItem(null); setShowForm(false); };

  const handleEdit = (item) => {
    setForm({ name: item.name, value: item.value, group: item.group || '' });
    setEditItem(item);
    setShowForm(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      if (editItem) {
        await api.put(`/offence-types/${editItem.id}`, form);
      } else {
        await api.post('/offence-types', form);
      }
      resetForm();
      fetchData();
    } catch (err) {
      alert('Error saving offence type');
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    await api.delete(`/offence-types/${deleteTarget.id}`);
    setDeleteTarget(null);
    fetchData();
  };

  if (loading) return <div className="page-content"><LoadingSkeleton type="table" columns={5} rows={8} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Crime Categories</h1>
          <p className="page-subtitle">Manage offence types</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary btn-sm" onClick={() => { resetForm(); setShowForm(!showForm); }}>
            {showForm ? 'Cancel' : <><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> Add Category</>}
          </button>
        </div>
      </div>

      {showForm && (
        <div className="card" style={{marginBottom:20}}>
          <div className="card-header">
            <div className="card-title"><div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>{editItem ? 'Edit Category' : 'New Category'}</div>
          </div>
          <div className="card-body">
            <form onSubmit={handleSubmit}>
              <div className="cf-field">
                <label className="cf-label required">Name</label>
                <input className="cf-input" name="name" value={form.name} onChange={e => setForm({...form, name: e.target.value})} required />
              </div>
              <div className="cf-field">
                <label className="cf-label required">Value</label>
                <input className="cf-input" name="value" value={form.value} onChange={e => setForm({...form, value: e.target.value})} required />
              </div>
              <div className="cf-field">
                <label className="cf-label">Group</label>
                <input className="cf-input" name="group" value={form.group} onChange={e => setForm({...form, group: e.target.value})} placeholder="e.g. Financial Crimes" />
              </div>
              <div style={{display:'flex',gap:10}}>
                <button type="submit" className="btn btn-primary btn-sm">{editItem ? 'Update' : 'Save'}</button>
                <button type="button" className="btn btn-outline btn-sm" onClick={resetForm}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="card">
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead><tr><th>#</th><th>Group</th><th>Value</th><th>Name</th><th>Actions</th></tr></thead>
              <tbody>
                {list.map((o, i) => (
                  <tr key={o.id}>
                    <td><span className="table-id">#{o.id}</span></td>
                    <td>{o.group || '-'}</td>
                    <td><code>{o.value}</code></td>
                    <td>{o.name}</td>
                    <td>
                      <div style={{display:'flex',gap:'6px'}}>
                        <button onClick={() => handleEdit(o)} className="btn btn-outline btn-sm btn-icon" title="Edit">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button onClick={() => setDeleteTarget(o)} className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:'8px',width:'36px',height:'36px',display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}} title="Delete">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {list.length === 0 && <tr><td colSpan={5} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No categories found</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <ConfirmModal
        open={!!deleteTarget}
        title="Delete Category"
        message={`Delete "${deleteTarget?.name}"? This cannot be undone.`}
        confirmLabel="Delete"
        variant="warning"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}
