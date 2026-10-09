import { useState, useEffect } from 'react';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import { useAuth } from '../contexts/AuthContext';
import { hasAnyRole } from '../utils/permissions';

const ACTION_HELP = {
  arrest_warrant: 'Issuing / printing an arrest warrant',
  search_warrant: 'Issuing / printing a search warrant',
  raid_permission: 'Permission to conduct a raid',
  proclamation: 'Proclamation of an absconder (Section 87 CrPC)',
  attachment: 'Attachment of property (Section 88 CrPC)',
};

export default function ApprovalSettings() {
  const { user } = useAuth();
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(null);
  const [notice, setNotice] = useState('');

  const canEdit = hasAnyRole(user, ['admin', 'director_general']);

  const fetchData = () => {
    setLoading(true);
    api.get('/approval-settings')
      .then(r => setList(r.data.data || r.data))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, []);

  const setRequirement = async (setting, requirement) => {
    if (!canEdit || setting.requirement === requirement) return;
    setSaving(setting.action_key);
    setNotice('');
    try {
      const r = await api.put(`/approval-settings/${setting.action_key}`, { requirement });
      setList(prev => prev.map(s => s.action_key === setting.action_key ? { ...s, requirement } : s));
      setNotice(r.data.message || 'Updated.');
    } catch (err) {
      setNotice(err?.response?.data?.message || 'Could not update this setting.');
    } finally {
      setSaving(null);
    }
  };

  if (loading) return <div className="page-content"><LoadingSkeleton type="table" columns={3} rows={5} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Approval Settings</h1>
          <p className="page-subtitle">Control which actions need Circle Incharge approval before they can be issued</p>
          <div className="title-underline"></div>
        </div>
      </div>

      {notice && <div className="card" style={{marginBottom:16}}><div className="card-body" style={{color:'#2563eb'}}>{notice}</div></div>}

      {!canEdit && (
        <div className="card" style={{marginBottom:16}}>
          <div className="card-body" style={{color:'#6c757d'}}>
            You can view these settings. Only an Administrator or Director General can change them.
          </div>
        </div>
      )}

      <div className="card">
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead><tr><th>Action</th><th>What it covers</th><th>Requirement</th></tr></thead>
              <tbody>
                {list.map((s) => (
                  <tr key={s.action_key}>
                    <td><strong>{s.label}</strong></td>
                    <td style={{color:'#6c757d'}}>{ACTION_HELP[s.action_key] || '-'}</td>
                    <td>
                      <div style={{display:'inline-flex',gap:6,border:'1px solid #e2e8f0',borderRadius:10,padding:3}}>
                        <button
                          className={`btn btn-sm ${s.requirement === 'open' ? 'btn-primary' : 'btn-outline'}`}
                          disabled={!canEdit || saving === s.action_key}
                          onClick={() => setRequirement(s, 'open')}
                        >Open</button>
                        <button
                          className={`btn btn-sm ${s.requirement === 'mandatory' ? 'btn-primary' : 'btn-outline'}`}
                          disabled={!canEdit || saving === s.action_key}
                          onClick={() => setRequirement(s, 'mandatory')}
                        >CI approval required</button>
                      </div>
                    </td>
                  </tr>
                ))}
                {list.length === 0 && <tr><td colSpan={3} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No approval actions configured.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <p style={{marginTop:14,color:'#6c757d',fontSize:13}}>
        <strong>Open</strong>: the officer can issue the document directly.{' '}
        <strong>CI approval required</strong>: the officer must request approval and a Circle Incharge must approve it before the document can be printed.
      </p>
    </div>
  );
}
