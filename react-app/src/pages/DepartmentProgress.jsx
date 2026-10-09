import { useState, useEffect, useMemo, useRef } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ProgressBar from '../components/ProgressBar';
import OfficerHandoverModal from '../components/OfficerHandoverModal';

export default function DepartmentProgress() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);
  const [selectedOfficerForHandover, setSelectedOfficerForHandover] = useState(null);

  // Filters
  const currentYear = new Date().getFullYear();
  const [year, setYear] = useState(currentYear);
  const [circleId, setCircleId] = useState(user?.circle_id ? String(user.circle_id) : '');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [tableSearch, setTableSearch] = useState('');
  const [pieMode, setPieMode] = useState('stages'); // 'stages' | 'categories'
  const [hoveredSlice, setHoveredSlice] = useState(null);

  // Section Refs for smooth jumping
  const officersSectionRef = useRef(null);
  const hqFeedsSectionRef = useRef(null);
  const circlesSectionRef = useRef(null);
  const chartsSectionRef = useRef(null);
  const legalSectionRef = useRef(null);

  // Officers Directory Filters
  const [officerCircleFilter, setOfficerCircleFilter] = useState(user?.circle_id ? String(user.circle_id) : '');
  const [officerRoleFilter, setOfficerRoleFilter] = useState('');
  const [officerSearch, setOfficerSearch] = useState('');

  const isStationLocked = Boolean(
    data?.is_station_locked ||
    user?.role === 'circle_incharge' ||
    user?.designation?.toLowerCase().includes('incharge') ||
    (user?.circle_id && !user?.is_zonal_head && user?.role !== 'admin' && user?.role !== 'director_general')
  );

  const fetchMonitoringData = (silent = false) => {
    if (!silent) {
      setLoading(true);
      setError(null);
    } else {
      setRefreshing(true);
    }

    const params = new URLSearchParams();
    if (year) params.set('year', year);
    if (circleId) params.set('circle_id', circleId);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    api.get(`/department-progress?${params.toString()}`)
      .then(res => {
        setData(res.data);
        if (res.data?.is_station_locked && res.data?.selected_circle?.id) {
          const lockedId = String(res.data.selected_circle.id);
          setCircleId(lockedId);
          setOfficerCircleFilter(lockedId);
        }
      })
      .catch(err => {
        if (!silent) {
          setError(err.response?.data?.message || err.message || 'Failed to load monitoring dashboard');
        }
      })
      .finally(() => {
        setLoading(false);
        setRefreshing(false);
      });
  };

  useEffect(() => {
    fetchMonitoringData();
  }, [year, circleId]);

  const handleApplyCustomDates = (e) => {
    e.preventDefault();
    fetchMonitoringData();
  };

  const handleResetFilters = () => {
    setYear(currentYear);
    if (!isStationLocked) {
      setCircleId('');
      setOfficerCircleFilter('');
    }
    setDateFrom('');
    setDateTo('');
  };

  const handleCircleOfficersClick = (cId) => {
    setOfficerCircleFilter(String(cId));
    if (officersSectionRef.current) {
      officersSectionRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  };

  const handlePrintBriefing = () => {
    window.print();
  };

  // Pie Chart calculations for Donut
  const activePieData = useMemo(() => {
    if (!data) return [];
    if (pieMode === 'categories') {
      return (data.category_breakdown || []).map(c => ({
        name: c.name,
        count: Number(c.count) || 0,
        color: c.color || '#2563eb',
      }));
    }
    return (data.workflow_pie || []).map(s => ({
      name: s.name,
      count: Number(s.count) || 0,
      color: s.color || '#0284c7',
    }));
  }, [data, pieMode]);

  const pieTotal = useMemo(() => {
    return activePieData.reduce((acc, curr) => acc + curr.count, 0);
  }, [activePieData]);

  // Generate SVG Donut paths
  const donutSlices = useMemo(() => {
    if (!pieTotal || activePieData.length === 0) return [];
    let cumulativeAngle = 0;
    const cx = 150;
    const cy = 150;
    const outerR = 120;
    const innerR = 75;

    return activePieData.map((slice, index) => {
      const sliceAngle = (slice.count / pieTotal) * 360;
      const startAngle = cumulativeAngle;
      const endAngle = cumulativeAngle + sliceAngle;
      cumulativeAngle = endAngle;

      const startRad = (startAngle - 90) * (Math.PI / 180);
      const endRad = (endAngle - 90) * (Math.PI / 180);

      const x1 = cx + outerR * Math.cos(startRad);
      const y1 = cy + outerR * Math.sin(startRad);
      const x2 = cx + outerR * Math.cos(endRad);
      const y2 = cy + outerR * Math.sin(endRad);

      const x3 = cx + innerR * Math.cos(endRad);
      const y3 = cy + innerR * Math.sin(endRad);
      const x4 = cx + innerR * Math.cos(startRad);
      const y4 = cy + innerR * Math.sin(startRad);

      const largeArc = sliceAngle > 180 ? 1 : 0;

      const pathData = [
        `M ${x1} ${y1}`,
        `A ${outerR} ${outerR} 0 ${largeArc} 1 ${x2} ${y2}`,
        `L ${x3} ${y3}`,
        `A ${innerR} ${innerR} 0 ${largeArc} 0 ${x4} ${y4}`,
        'Z',
      ].join(' ');

      const percentage = Math.round((slice.count / pieTotal) * 100);

      return {
        ...slice,
        index,
        percentage,
        pathData,
      };
    });
  }, [activePieData, pieTotal]);

  // Bar Chart calculations
  const barChartData = useMemo(() => {
    if (!data?.circle_bar_chart) return [];
    return data.circle_bar_chart;
  }, [data]);

  const maxBarValue = useMemo(() => {
    if (!barChartData.length) return 10;
    return Math.max(...barChartData.map(b => b.total), 1);
  }, [barChartData]);

  // Monthly trends calculations
  const monthlyTrends = data?.monthly_trends || [];
  const maxMonthlyVal = useMemo(() => {
    if (!monthlyTrends.length) return 10;
    const maxRec = Math.max(...monthlyTrends.map(m => m.received), 0);
    const maxRes = Math.max(...monthlyTrends.map(m => m.resolved), 0);
    return Math.max(maxRec, maxRes, 5);
  }, [monthlyTrends]);

  // Filtered Circle Table
  const filteredCircles = useMemo(() => {
    if (!data?.circle_breakdown) return [];
    if (!tableSearch.trim()) return data.circle_breakdown;
    const q = tableSearch.toLowerCase();
    return data.circle_breakdown.filter(c =>
      (c.name && c.name.toLowerCase().includes(q)) ||
      (c.code && c.code.toLowerCase().includes(q))
    );
  }, [data, tableSearch]);

  // Filtered Officers List (Gujranwala, Lahore, etc.)
  const filteredOfficers = useMemo(() => {
    const list = data?.circle_officers || [];
    return list.filter(off => {
      if (officerCircleFilter && String(off.circle_id) !== String(officerCircleFilter)) {
        return false;
      }
      if (officerRoleFilter && !String(off.role).toLowerCase().includes(officerRoleFilter.toLowerCase())) {
        return false;
      }
      if (officerSearch.trim()) {
        const q = officerSearch.toLowerCase();
        const matchName = off.name && off.name.toLowerCase().includes(q);
        const matchEmail = off.email && off.email.toLowerCase().includes(q);
        const matchDesig = off.designation && off.designation.toLowerCase().includes(q);
        const matchCircle = off.circle_name && off.circle_name.toLowerCase().includes(q);
        if (!matchName && !matchEmail && !matchDesig && !matchCircle) {
          return false;
        }
      }
      return true;
    });
  }, [data, officerCircleFilter, officerRoleFilter, officerSearch]);

  if (loading) {
    return (
      <div className="page-content">
        <LoadingSkeleton type="stats" rows={6} />
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20, marginTop: 24 }}>
          <LoadingSkeleton type="chart" />
          <LoadingSkeleton type="chart" />
        </div>
        <div style={{ marginTop: 24 }}>
          <LoadingSkeleton type="table" rows={6} />
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="page-content">
        <div style={{
          padding: 40, textAlign: 'center', background: '#fff',
          borderRadius: 12, border: '1px solid #fee2e2', boxShadow: '0 2px 8px rgba(0,0,0,0.05)'
        }}>
          <div style={{ fontSize: 36, marginBottom: 12 }}>⚠️</div>
          <h2 style={{ color: '#b91c1c', marginBottom: 8, fontSize: 18, fontWeight: 700 }}>
            Access Restricted or Dashboard Unavailable
          </h2>
          <p style={{ color: '#6b7280', fontSize: 14, maxWidth: 500, margin: '0 auto 20px' }}>
            {error}
          </p>
          <button
            type="button"
            onClick={() => fetchMonitoringData()}
            style={{
              padding: '8px 18px', background: '#015C94', color: '#fff',
              border: 'none', borderRadius: 6, fontWeight: 600, cursor: 'pointer'
            }}
          >
            Retry Loading
          </button>
        </div>
      </div>
    );
  }

  const {
    metrics = {},
    circles = [],
    legal_backlog = [],
    circle_officers = [],
    hq_command = {},
    selected_circle = null,
    hq_dsr_feed = [],
    hq_do_feed = [],
    transfers_feed = []
  } = data || {};

  return (
    <div className="page-content" style={{ paddingBottom: 60 }}>
      {/* Dynamic Print Stylesheet for Official Briefing Output */}
      <style>{`
        .print-only-letterhead { display: none; }
        .print-only-footer { display: none; }

        @media print {
          @page {
            size: A4 portrait;
            margin: 10mm 10mm 12mm 10mm;
          }

          .print-only-letterhead { display: block !important; margin-bottom: 14px !important; }
          .print-only-footer { display: block !important; margin-top: 24px !important; }

          /* Hide application chrome and interactive UI elements */
          .no-print,
          .main-sidebar,
          aside,
          nav,
          .main-header,
          .topbar,
          .header,
          .user-dropdown,
          button,
          input,
          select,
          .modal-backdrop,
          .modal-overlay,
          .officer-handover-modal {
            display: none !important;
          }

          body, html {
            background: #fff !important;
            color: #0f172a !important;
            font-size: 11px !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }

          .page-content {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
          }

          /* Ink-efficient high-contrast command banner */
          .command-hero-card {
            background: #f8fafc !important;
            color: #0f172a !important;
            border: 2px solid #1a3d6b !important;
            box-shadow: none !important;
            padding: 14px 18px !important;
            margin-bottom: 14px !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
          }
          .command-hero-card h1 {
            color: #1a3d6b !important;
            font-size: 19px !important;
          }
          .command-hero-card p {
            color: #334155 !important;
            font-size: 11.5px !important;
          }
          .command-hero-badge {
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border: 1px solid #cbd5e1 !important;
          }

          .card {
            box-shadow: none !important;
            border: 1px solid #94a3b8 !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
            margin-bottom: 14px !important;
            background: #fff !important;
          }

          .card-header {
            background: #f1f5f9 !important;
            border-bottom: 1px solid #94a3b8 !important;
            padding: 8px 12px !important;
          }

          .card-title {
            color: #0f172a !important;
            font-weight: 800 !important;
            font-size: 13px !important;
          }

          .dashboard-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
          }

          table.data-table {
            width: 100% !important;
            border-collapse: collapse !important;
          }

          table.data-table th {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            border: 1px solid #94a3b8 !important;
            padding: 5px 6px !important;
            font-size: 9.5px !important;
            font-weight: 700 !important;
          }

          table.data-table td {
            border: 1px solid #cbd5e1 !important;
            padding: 4px 6px !important;
            font-size: 10px !important;
          }

          table.data-table tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
          }
        }
      `}</style>

      {/* Official Print-Only Letterhead */}
      <div className="print-only-letterhead">
        <table style={{ width: '100%', borderCollapse: 'collapse', borderBottom: '2.5px solid #1a3d6b', paddingBottom: 8, marginBottom: 12 }}>
          <tbody>
            <tr>
              <td style={{ width: 68, verticalAlign: 'middle', textAlign: 'left', border: 'none', padding: 0 }}>
                <img src="/images/images.jpg" alt="NCCIA" style={{ width: 56, height: 56, objectFit: 'contain' }} />
              </td>
              <td style={{ verticalAlign: 'middle', textAlign: 'center', border: 'none', padding: '0 12px' }}>
                <div style={{ fontSize: 9.5, fontWeight: 800, letterSpacing: '2px', color: '#334155', textTransform: 'uppercase' }}>
                  Government of Pakistan &bull; Ministry of Interior
                </div>
                <div style={{ fontSize: 20, fontWeight: 800, letterSpacing: '3.5px', color: '#1a3d6b', lineHeight: 1.1, marginTop: 2 }}>
                  NCCIA
                </div>
                <div style={{ fontSize: 11, fontWeight: 700, color: '#1a3d6b', textTransform: 'uppercase', letterSpacing: '0.8px', marginTop: 1 }}>
                  National Cyber Crime Investigation Agency
                </div>
                <div style={{ fontSize: 13.5, fontWeight: 800, color: '#0f172a', textTransform: 'uppercase', marginTop: 4, letterSpacing: '0.5px' }}>
                  {selected_circle
                    ? `${selected_circle.name.toUpperCase()} — REGIONAL COMMAND PERFORMANCE BRIEFING`
                    : 'NATIONWIDE EXECUTIVE COMMAND & PERFORMANCE BRIEFING'}
                </div>
                <div style={{ fontSize: 10, color: '#475569', fontWeight: 600, marginTop: 2 }}>
                  {selected_circle
                    ? `Territorial Command: ${selected_circle.zone_name || 'Regional Zone'} | Circle Incharge: ${selected_circle.incharge_name || 'Assigned Officer'}`
                    : 'Federal Headquarters Command & Control &bull; Director General & Ministry of Interior Casework Review'}
                </div>
              </td>
              <td style={{ width: 68, verticalAlign: 'middle', textAlign: 'right', border: 'none', padding: 0 }}>
                <img src="/images/pak-govt-logo.png" alt="Govt of Pakistan" style={{ width: 54, height: 54, objectFit: 'contain' }} />
              </td>
            </tr>
          </tbody>
        </table>

        {/* Official Meta Bar */}
        <div style={{
          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
          background: '#f1f5f9', border: '1px solid #cbd5e1', borderRadius: 4,
          padding: '5px 10px', fontSize: 10, fontWeight: 600, color: '#334155', marginBottom: 14
        }}>
          <div>
            <span style={{ color: '#64748b' }}>Reference: </span>
            <strong>NCCIA/HQ/BRIEF/{year || new Date().getFullYear()}/{selected_circle?.code || 'EXEC'}</strong>
          </div>
          <div>
            <span style={{ color: '#64748b' }}>Generated: </span>
            <strong>{new Date().toLocaleDateString('en-GB')} {new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</strong>
          </div>
          <div>
            <span style={{ color: '#64748b' }}>Classification: </span>
            <strong style={{ color: '#b91c1c' }}>OFFICIAL &bull; CONFIDENTIAL</strong>
          </div>
        </div>
      </div>

      {/* Circle Command Switcher Tabs Bar (Hidden for station locked officers) */}
      {!isStationLocked && circles.length > 1 && (
        <div className="circle-tabs-bar no-print" style={{
          display: 'flex', gap: 8, alignItems: 'center', overflowX: 'auto', padding: '10px 14px',
          background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, marginBottom: 16,
          boxShadow: '0 1px 3px rgba(0,0,0,0.04)'
        }}>
          <span style={{ fontSize: 12, fontWeight: 700, color: '#64748b', whiteSpace: 'nowrap' }}>
            Select Command Portal:
          </span>
          <button
            type="button"
            onClick={() => {
              setCircleId('');
              setOfficerCircleFilter('');
            }}
            style={{
              padding: '6px 14px', borderRadius: 20, fontSize: 12, fontWeight: 700, cursor: 'pointer',
              border: 'none', whiteSpace: 'nowrap',
              background: !circleId ? '#013658' : '#e2e8f0',
              color: !circleId ? '#fff' : '#334155'
            }}
          >
            🏛️ Islamabad HQ (Nationwide Command)
          </button>
          {circles.map(c => {
            const isSelected = String(circleId) === String(c.id);
            return (
              <button
                key={c.id}
                type="button"
                onClick={() => {
                  setCircleId(isSelected ? '' : String(c.id));
                  setOfficerCircleFilter(isSelected ? '' : String(c.id));
                }}
                style={{
                  padding: '6px 14px', borderRadius: 20, fontSize: 12, fontWeight: 700, cursor: 'pointer',
                  border: 'none', whiteSpace: 'nowrap',
                  background: isSelected ? '#0284c7' : '#f1f5f9',
                  color: isSelected ? '#fff' : '#1e293b'
                }}
              >
                🏛️ {c.name} {c.code ? `(${c.code})` : ''}
              </button>
            );
          })}
        </div>
      )}

      {/* Main Command Banner: Dedicated Circle Portal OR Islamabad HQ Portal */}
      {selected_circle ? (
        /* DEDICATED SEPARATE CIRCLE COMMAND BANNER */
        <div className="command-hero-card" style={{
          background: 'linear-gradient(135deg, #0c4a6e 0%, #0284c7 65%, #0369a1 100%)',
          color: '#fff', borderRadius: 14, padding: '22px 26px', marginBottom: 20,
          boxShadow: '0 8px 24px rgba(2,132,199,0.25)', position: 'relative', overflow: 'hidden'
        }}>
          {/* Subtle Background Code Pattern */}
          <div style={{
            position: 'absolute', right: -15, top: -25, opacity: 0.08, fontSize: 160,
            pointerEvents: 'none', userSelect: 'none', fontWeight: 900
          }}>
            {selected_circle.code || 'CIR'}
          </div>

          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16 }}>
            <div style={{ maxWidth: 700 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
                <span className="command-hero-badge" style={{
                  background: '#10b981', color: '#fff', fontSize: 11, fontWeight: 800,
                  padding: '3px 10px', borderRadius: 12, letterSpacing: '0.06em', textTransform: 'uppercase'
                }}>
                  Regional Circle Command
                </span>
                <span style={{ fontSize: 12, opacity: 0.95 }}>
                  • {selected_circle.zone_name} ({selected_circle.zone_code}) Jurisdiction
                </span>
              </div>
              <h1 style={{ fontSize: 26, fontWeight: 800, margin: '0 0 6px 0', letterSpacing: '-0.01em' }}>
                {selected_circle.name} — Regional Command Portal
              </h1>
              <p style={{ margin: 0, fontSize: 13.5, opacity: 0.9, lineHeight: 1.5 }}>
                Dedicated monitoring &amp; casework portal for {selected_circle.name}. Managing all verification, enquiry, and case files under this circle's territorial jurisdiction.
              </p>

              {/* Circle Badges & Incharge Card */}
              <div style={{ display: 'flex', gap: 10, marginTop: 14, flexWrap: 'wrap', alignItems: 'center' }}>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.2)', padding: '5px 12px', borderRadius: 20, fontSize: 12.5, fontWeight: 700 }}>
                  👤 Incharge: {selected_circle.incharge_name}
                </span>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.15)', padding: '4px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600 }}>
                  👮‍♂️ {selected_circle.total_officers || circle_officers.length} Officers Deployed
                </span>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.15)', padding: '4px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600 }}>
                  📑 {hq_dsr_feed.length} Recent DSRs
                </span>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.15)', padding: '4px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600 }}>
                  ✉️ {hq_do_feed.length} Monthly D.O. Letters
                </span>
              </div>
            </div>

            <div className="no-print" style={{ display: 'flex', flexDirection: 'column', gap: 8, alignItems: 'flex-end' }}>
              <button
                type="button"
                onClick={handlePrintBriefing}
                style={{
                  background: '#fff', color: '#0369a1', border: 'none', borderRadius: 8,
                  padding: '9px 16px', fontWeight: 800, fontSize: 13, cursor: 'pointer',
                  display: 'flex', alignItems: 'center', gap: 6, boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
                }}
              >
                <span>🖨️ Print {selected_circle.name} Briefing</span>
              </button>
              {!isStationLocked && (
                <button
                  type="button"
                  onClick={() => {
                    setCircleId('');
                    setOfficerCircleFilter('');
                  }}
                  style={{
                    background: 'rgba(255,255,255,0.18)', color: '#fff', border: '1px solid rgba(255,255,255,0.4)',
                    borderRadius: 6, padding: '5px 12px', fontSize: 11.5, fontWeight: 700, cursor: 'pointer'
                  }}
                >
                  ← Back to Islamabad HQ (All Circles)
                </button>
              )}
            </div>
          </div>

          {/* Quick Jump Bar */}
          <div className="jump-nav-bar no-print" style={{
            display: 'flex', gap: 8, marginTop: 18, paddingTop: 14, borderTop: '1px solid rgba(255,255,255,0.2)',
            flexWrap: 'wrap'
          }}>
            <button
              type="button"
              onClick={() => chartsSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.15)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              📊 {selected_circle.name} Analytics
            </button>
            <button
              type="button"
              onClick={() => officersSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.15)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              👮‍♂️ {selected_circle.name} Officers ({selected_circle.total_officers || circle_officers.length})
            </button>
            <button
              type="button"
              onClick={() => hqFeedsSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.15)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              📑 Circle DSR &amp; D.O. Reports ({hq_dsr_feed.length + hq_do_feed.length})
            </button>
            <button
              type="button"
              onClick={() => legalSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.15)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              ⚖️ Pending Legal Reviews ({legal_backlog.length})
            </button>
          </div>
        </div>
      ) : (
        /* ISLAMABAD HQ CENTRAL EXECUTIVE COMMAND BANNER */
        <div className="command-hero-card" style={{
          background: 'linear-gradient(135deg, #013658 0%, #015C94 65%, #0284c7 100%)',
          color: '#fff', borderRadius: 14, padding: '22px 26px', marginBottom: 20,
          boxShadow: '0 8px 24px rgba(1,92,148,0.25)', position: 'relative', overflow: 'hidden'
        }}>
          <div style={{
            position: 'absolute', right: -20, top: -20, opacity: 0.08, fontSize: 180,
            pointerEvents: 'none', userSelect: 'none', fontWeight: 900
          }}>
            HQ
          </div>

          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16 }}>
            <div style={{ maxWidth: 700 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
                <span className="command-hero-badge" style={{
                  background: '#f59e0b', color: '#000', fontSize: 11, fontWeight: 800,
                  padding: '3px 10px', borderRadius: 12, letterSpacing: '0.06em', textTransform: 'uppercase'
                }}>
                  Federal Executive Command
                </span>
                <span style={{ fontSize: 12, opacity: 0.9 }}>
                  • NCCIA Headquarters, Islamabad
                </span>
              </div>
              <h1 style={{ fontSize: 25, fontWeight: 800, margin: '0 0 6px 0', letterSpacing: '-0.01em' }}>
                Islamabad HQ — Central Executive Monitoring Portal
              </h1>
              <p style={{ margin: 0, fontSize: 13.5, opacity: 0.9, lineHeight: 1.5 }}>
                Central Command &amp; Oversight Directorate for DG, Additional Director, DD Legal &amp; AD Legal. Monitoring all provincial circles across Pakistan.
              </p>

              <div style={{ display: 'flex', gap: 10, marginTop: 14, flexWrap: 'wrap' }}>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.15)', padding: '4px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600 }}>
                  🏛️ {hq_command.total_circles || circles.length} Circles Monitored
                </span>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.15)', padding: '4px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600 }}>
                  👮‍♂️ {hq_command.total_officers || circle_officers.length} Deployed Officers
                </span>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.15)', padding: '4px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600 }}>
                  📑 {hq_command.pending_dsr || 0} DSRs Pending HQ
                </span>
                <span className="command-hero-badge" style={{ background: 'rgba(255,255,255,0.15)', padding: '4px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600 }}>
                  ✉️ {hq_command.pending_do || 0} D.O. Letters for DG
                </span>
              </div>
            </div>

            <div className="no-print" style={{ display: 'flex', flexDirection: 'column', gap: 8, alignItems: 'flex-end' }}>
              <button
                type="button"
                onClick={handlePrintBriefing}
                style={{
                  background: '#fff', color: '#013658', border: 'none', borderRadius: 8,
                  padding: '9px 16px', fontWeight: 800, fontSize: 13, cursor: 'pointer',
                  display: 'flex', alignItems: 'center', gap: 6, boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
                }}
              >
                <span>🖨️ Print Executive Briefing</span>
              </button>
              <div style={{ fontSize: 11, opacity: 0.8, textAlign: 'right' }}>
                DG Secretariat &amp; Ministry of Interior Briefing
              </div>
            </div>
          </div>

          <div className="jump-nav-bar no-print" style={{
            display: 'flex', gap: 8, marginTop: 18, paddingTop: 14, borderTop: '1px solid rgba(255,255,255,0.2)',
            flexWrap: 'wrap'
          }}>
            <button
              type="button"
              onClick={() => chartsSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.12)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              📊 Analytics &amp; Charts
            </button>
            <button
              type="button"
              onClick={() => circlesSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.12)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              🏛️ Circle Progress (Lahore, Gujranwala...)
            </button>
            <button
              type="button"
              onClick={() => officersSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.12)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              👮‍♂️ Circle Officers Directory
            </button>
            <button
              type="button"
              onClick={() => hqFeedsSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.12)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              📑 HQ DSR &amp; D.O. Feeds ({hq_command.pending_dsr || 0})
            </button>
            <button
              type="button"
              onClick={() => legalSectionRef.current?.scrollIntoView({ behavior: 'smooth' })}
              style={{ background: 'rgba(255,255,255,0.12)', color: '#fff', border: 'none', borderRadius: 6, padding: '5px 12px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}
            >
              ⚖️ Legal Scrutiny ({legal_backlog.length})
            </button>
          </div>
        </div>
      )}

      {/* Global Filter Bar */}
      <div className="filter-bar no-print" style={{
        display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap',
        background: '#fff', padding: '10px 16px', borderRadius: 10,
        border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,0.05)', marginBottom: 20
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
          {/* Year Filter */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: '#64748b' }}>Saal (Year):</span>
            <select
              className="filter-select"
              value={year}
              onChange={e => setYear(Number(e.target.value))}
              style={{ padding: '5px 10px', fontSize: 13, minWidth: 90 }}
            >
              {[...Array(5)].map((_, i) => currentYear - i).map(y => (
                <option key={y} value={y}>{y}</option>
              ))}
            </select>
          </div>

          {/* Circle Filter */}
          {isStationLocked ? (
            <div style={{
              display: 'flex', alignItems: 'center', gap: 6,
              background: '#f1f5f9', padding: '5px 12px', borderRadius: 6,
              border: '1px solid #cbd5e1'
            }}>
              <span style={{ fontSize: 12, fontWeight: 600, color: '#64748b' }}>Assigned Station:</span>
              <span style={{ fontSize: 12.5, fontWeight: 700, color: '#0369a1', display: 'flex', alignItems: 'center', gap: 5 }}>
                🏢 {selected_circle?.name || data?.user_station_name || user?.circle?.name || 'Station Command'} {selected_circle?.code ? `(${selected_circle.code})` : ''}
              </span>
            </div>
          ) : (
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span style={{ fontSize: 12, fontWeight: 600, color: '#64748b' }}>Selected Circle:</span>
              <select
                className="filter-select"
                value={circleId}
                onChange={e => {
                  setCircleId(e.target.value);
                  setOfficerCircleFilter(e.target.value);
                }}
                style={{ padding: '5px 10px', fontSize: 13, minWidth: 180 }}
              >
                {!data?.is_zonal_head && <option value="">All Circles (Islamabad HQ)</option>}
                {data?.is_zonal_head && <option value="">All Punjab Circles (Zonal Directorate)</option>}
                {circles.map(c => (
                  <option key={c.id} value={c.id}>
                    {c.name} {c.code ? `(${c.code})` : ''}
                  </option>
                ))}
              </select>
            </div>
          )}

          {/* Date Range Form */}
          <form onSubmit={handleApplyCustomDates} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <input
              type="date"
              className="filter-input"
              value={dateFrom}
              onChange={e => setDateFrom(e.target.value)}
              placeholder="From"
              style={{ padding: '4px 8px', fontSize: 12 }}
            />
            <span style={{ color: '#94a3b8', fontSize: 12 }}>–</span>
            <input
              type="date"
              className="filter-input"
              value={dateTo}
              onChange={e => setDateTo(e.target.value)}
              placeholder="To"
              style={{ padding: '4px 8px', fontSize: 12 }}
            />
            {(dateFrom || dateTo) && (
              <button
                type="submit"
                style={{
                  padding: '5px 10px', background: '#015C94', color: '#fff',
                  border: 'none', borderRadius: 4, fontSize: 12, fontWeight: 600, cursor: 'pointer'
                }}
              >
                Apply Date
              </button>
            )}
          </form>

          {(circleId || dateFrom || dateTo || officerCircleFilter) && (
            <button
              type="button"
              onClick={handleResetFilters}
              style={{
                background: 'transparent', border: 'none', color: '#ef4444',
                fontSize: 12, fontWeight: 600, cursor: 'pointer', padding: '4px 6px'
              }}
            >
              Reset Filters
            </button>
          )}
        </div>

        {/* Refresh button */}
        <button
          type="button"
          onClick={() => fetchMonitoringData(true)}
          disabled={refreshing}
          style={{
            display: 'flex', alignItems: 'center', gap: 5, padding: '6px 12px',
            background: '#f1f5f9', border: '1px solid #cbd5e1', borderRadius: 6,
            fontSize: 12.5, fontWeight: 600, color: '#334155', cursor: 'pointer'
          }}
          title="Refresh Data"
        >
          <svg
            width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" strokeWidth="2.5"
            style={{ transform: refreshing ? 'rotate(180deg)' : 'none', transition: 'transform 0.5s' }}
          >
            <polyline points="23 4 23 10 17 10" />
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
          </svg>
          <span>{refreshing ? 'Syncing...' : 'Live Refresh'}</span>
        </button>
      </div>

      {/* Top Metric Cards */}
      <div className="stats-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' }}>
        {/* Total Workload */}
        <div className="stat-card blue">
          <div className="stat-value" style={{ fontSize: 26, fontWeight: 800 }}>
            {metrics.total_workload?.toLocaleString() || 0}
          </div>
          <div className="stat-label">
            {selected_circle ? `${selected_circle.name} Workload` : 'Total Nationwide Workload'}
          </div>
          <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
            {metrics.total_complaints || 0} CMU + {metrics.total_enquiries || 0} Enq + {metrics.registered_cases || 0} Cases
          </div>
        </div>

        {/* Overall Disposal Rate */}
        <div className="stat-card green">
          <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
            <div className="stat-value" style={{ fontSize: 26, fontWeight: 800, color: '#16a34a' }}>
              {metrics.overall_disposal_rate || 0}%
            </div>
            <span style={{
              fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 10,
              background: metrics.overall_disposal_rate >= 70 ? '#dcfce7' : '#fef3c7',
              color: metrics.overall_disposal_rate >= 70 ? '#15803d' : '#b45309'
            }}>
              {metrics.overall_disposal_rate >= 70 ? 'Optimal' : 'In Progress'}
            </span>
          </div>
          <div className="stat-label">Disposal Rate</div>
          <div style={{ marginTop: 6 }}>
            <ProgressBar value={metrics.overall_disposal_rate || 0} color="#16a34a" />
          </div>
        </div>

        {/* Active Enquiries */}
        <div className="stat-card orange">
          <div className="stat-value" style={{ fontSize: 26, fontWeight: 800, color: '#ea580c' }}>
            {metrics.active_enquiries?.toLocaleString() || 0}
          </div>
          <div className="stat-label">Active Enquiries</div>
          <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
            Out of {metrics.total_enquiries || 0} registered
          </div>
        </div>

        {/* Finalized Cases / Convictions */}
        <div className="stat-card teal">
          <div className="stat-value" style={{ fontSize: 26, fontWeight: 800, color: '#0d9488' }}>
            {metrics.finalized_cases?.toLocaleString() || 0}
          </div>
          <div className="stat-label">Finalized Cases</div>
          <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
            Active FIR Cases: {Math.max(0, (metrics.registered_cases || 0) - (metrics.finalized_cases || 0))}
          </div>
        </div>

        {/* Pending Legal Review Backlog */}
        <div className="stat-card gold" style={{ borderLeft: '4px solid #f59e0b' }}>
          <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
            <div className="stat-value" style={{ fontSize: 26, fontWeight: 800, color: '#d97706' }}>
              {metrics.pending_legal_reviews || 0}
            </div>
            <span style={{
              background: '#fef3c7', color: '#b45309', fontSize: 10, fontWeight: 700,
              padding: '2px 6px', borderRadius: 8
            }}>
              Action Req.
            </span>
          </div>
          <div className="stat-label">Pending Legal Scrutiny</div>
          <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
            CFRs awaiting review
          </div>
        </div>

        {/* Court Hearings / Active Trials */}
        <div className="stat-card purple">
          <div className="stat-value" style={{ fontSize: 26, fontWeight: 800, color: '#7c3aed' }}>
            {metrics.total_court_cases || 0}
          </div>
          <div className="stat-label">Court Cases</div>
          <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
            Under active trial in courts
          </div>
        </div>
      </div>

      {/* Section: Feeds (DSR Reports & D.O. Letters) */}
      <div ref={hqFeedsSectionRef} style={{ marginTop: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: 20 }}>
          {/* Card 1: DSR Reports */}
          <div className="card" style={{ borderTop: '3px solid #0284c7' }}>
            <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span>📑 {selected_circle ? `${selected_circle.name} Daily Situation Reports (DSR)` : 'Daily Situation Reports (DSR Feed)'}</span>
                  {hq_command.pending_dsr > 0 && !selected_circle && (
                    <span style={{ background: '#ef4444', color: '#fff', fontSize: 10.5, fontWeight: 800, padding: '2px 7px', borderRadius: 10 }}>
                      {hq_command.pending_dsr} Pending HQ
                    </span>
                  )}
                </div>
                <div style={{ fontSize: 11.5, color: '#64748b', marginTop: 2 }}>
                  {selected_circle ? `Daily reports compiled by ${selected_circle.name}` : 'Daily situation updates forwarded by Circle Incharges to HQ.'}
                </div>
              </div>
              <Link to="/dsr-reports" style={{ fontSize: 12, fontWeight: 700, color: '#015C94', textDecoration: 'none' }}>
                All DSRs →
              </Link>
            </div>

            <div className="card-body" style={{ padding: 0 }}>
              <div className="table-responsive">
                <table className="data-table" style={{ fontSize: 12.5 }}>
                  <thead>
                    <tr>
                      <th>Circle</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th style={{ textAlign: 'right' }}>Submitted At</th>
                    </tr>
                  </thead>
                  <tbody>
                    {hq_dsr_feed.map(dsr => (
                      <tr key={dsr.id}>
                        <td>
                          <div style={{ fontWeight: 700, color: '#0f172a' }}>{dsr.circle_name}</div>
                          <div style={{ fontSize: 10.5, color: '#64748b' }}>{dsr.unit_name}</div>
                        </td>
                        <td>{dsr.report_date}</td>
                        <td>
                          <span style={{
                            padding: '2px 7px', borderRadius: 4, fontSize: 10.5, fontWeight: 700,
                            background: dsr.is_pending_ack ? '#fef3c7' : '#dcfce7',
                            color: dsr.is_pending_ack ? '#b45309' : '#15803d'
                          }}>
                            {dsr.status_label}
                          </span>
                        </td>
                        <td style={{ textAlign: 'right', fontSize: 11, color: '#64748b' }}>
                          {dsr.forwarded_at}
                        </td>
                      </tr>
                    ))}
                    {!hq_dsr_feed.length && (
                      <tr>
                        <td colSpan={4} style={{ textAlign: 'center', padding: 20, color: '#94a3b8' }}>
                          No DSR reports found for this view.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          {/* Card 2: Monthly D.O. Letters */}
          <div className="card" style={{ borderTop: '3px solid #7c3aed' }}>
            <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span>✉️ {selected_circle ? `${selected_circle.name} Monthly D.O. Letters` : 'Monthly D.O. Letters (DG Inflow)'}</span>
                  {hq_command.pending_do > 0 && !selected_circle && (
                    <span style={{ background: '#f59e0b', color: '#000', fontSize: 10.5, fontWeight: 800, padding: '2px 7px', borderRadius: 10 }}>
                      {hq_command.pending_do} Awaiting DG
                    </span>
                  )}
                </div>
                <div style={{ fontSize: 11.5, color: '#64748b', marginTop: 2 }}>
                  {selected_circle ? `Confidential monthly reports of ${selected_circle.name}` : 'Confidential monthly reports submitted to Director General.'}
                </div>
              </div>
              <Link to="/do-letters" style={{ fontSize: 12, fontWeight: 700, color: '#015C94', textDecoration: 'none' }}>
                All D.O. →
              </Link>
            </div>

            <div className="card-body" style={{ padding: 0 }}>
              <div className="table-responsive">
                <table className="data-table" style={{ fontSize: 12.5 }}>
                  <thead>
                    <tr>
                      <th>Circle</th>
                      <th>Month</th>
                      <th>DG Status</th>
                      <th style={{ textAlign: 'right' }}>Submitted</th>
                    </tr>
                  </thead>
                  <tbody>
                    {hq_do_feed.map(doLetter => (
                      <tr key={doLetter.id}>
                        <td style={{ fontWeight: 700, color: '#0f172a' }}>
                          {doLetter.circle_name}
                        </td>
                        <td>{doLetter.month_label}</td>
                        <td>
                          <span style={{
                            padding: '2px 7px', borderRadius: 4, fontSize: 10.5, fontWeight: 700,
                            background: doLetter.is_pending_ack ? '#fef3c7' : '#dcfce7',
                            color: doLetter.is_pending_ack ? '#b45309' : '#15803d'
                          }}>
                            {doLetter.status_label}
                          </span>
                        </td>
                        <td style={{ textAlign: 'right', fontSize: 11, color: '#64748b' }}>
                          {doLetter.forwarded_at}
                        </td>
                      </tr>
                    ))}
                    {!hq_do_feed.length && (
                      <tr>
                        <td colSpan={4} style={{ textAlign: 'center', padding: 20, color: '#94a3b8' }}>
                          No D.O. letters found for this view.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        {/* Inter-Circle Transfers Overview */}
        {transfers_feed.length > 0 && (
          <div className="card" style={{ marginTop: 16, borderLeft: '4px solid #0891b2' }}>
            <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14 }}>
                <span>🔄 {selected_circle ? `${selected_circle.name} Transfers Movement` : 'Recent Inter-Circle Case Transfers'}</span>
                <span style={{ fontSize: 11, color: '#64748b' }}>
                  ({transfers_feed.length} Active Records)
                </span>
              </div>
            </div>
            <div className="card-body" style={{ padding: '8px 16px' }}>
              <div style={{ display: 'flex', gap: 14, overflowX: 'auto', paddingBottom: 6 }}>
                {transfers_feed.map(tr => (
                  <div
                    key={tr.id}
                    style={{
                      background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8,
                      padding: '8px 14px', minWidth: 240, flexShrink: 0
                    }}
                  >
                    <div style={{ fontSize: 12, fontWeight: 700, color: '#0f172a' }}>{tr.complaint_no}</div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 11.5, marginTop: 4 }}>
                      <span style={{ color: '#64748b' }}>{tr.from_circle}</span>
                      <span style={{ color: '#0891b2', fontWeight: 800 }}>➔</span>
                      <span style={{ color: '#0369a1', fontWeight: 700 }}>{tr.to_circle}</span>
                    </div>
                    <div style={{ fontSize: 10.5, color: '#94a3b8', marginTop: 3 }}>Date: {tr.transferred_at}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Charts Section Row 1: Donut/Pie Chart & Monthly Inflow/Disposal Chart */}
      <div ref={chartsSectionRef} className="dashboard-grid" style={{ marginTop: 24, gridTemplateColumns: '1.1fr 1.4fr', gap: 20 }}>
        {/* Chart 1: Interactive SVG Pie / Donut Chart */}
        <div className="card" style={{ display: 'flex', flexDirection: 'column' }}>
          <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span>Distribution Breakdown</span>
            </div>
            {/* Toggle Pie Mode */}
            <div className="no-print" style={{ display: 'flex', background: '#f1f5f9', borderRadius: 6, padding: 2, gap: 2 }}>
              <button
                type="button"
                onClick={() => setPieMode('stages')}
                style={{
                  padding: '4px 10px', fontSize: 11, fontWeight: 700, border: 'none', borderRadius: 4,
                  cursor: 'pointer',
                  background: pieMode === 'stages' ? '#fff' : 'transparent',
                  color: pieMode === 'stages' ? '#015C94' : '#64748b',
                  boxShadow: pieMode === 'stages' ? '0 1px 2px rgba(0,0,0,0.1)' : 'none'
                }}
              >
                By Stage
              </button>
              <button
                type="button"
                onClick={() => setPieMode('categories')}
                style={{
                  padding: '4px 10px', fontSize: 11, fontWeight: 700, border: 'none', borderRadius: 4,
                  cursor: 'pointer',
                  background: pieMode === 'categories' ? '#fff' : 'transparent',
                  color: pieMode === 'categories' ? '#015C94' : '#64748b',
                  boxShadow: pieMode === 'categories' ? '0 1px 2px rgba(0,0,0,0.1)' : 'none'
                }}
              >
                By Crime Type
              </button>
            </div>
          </div>

          <div className="card-body" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '16px' }}>
            {pieTotal > 0 ? (
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 24, flexWrap: 'wrap', width: '100%' }}>
                {/* SVG Donut */}
                <div style={{ position: 'relative', width: 220, height: 220, flexShrink: 0 }}>
                  <svg viewBox="0 0 300 300" style={{ width: '100%', height: '100%', overflow: 'visible' }}>
                    {donutSlices.map(slice => {
                      const isHovered = hoveredSlice?.name === slice.name;
                      return (
                        <path
                          key={slice.name}
                          d={slice.pathData}
                          fill={slice.color}
                          style={{
                            cursor: 'pointer',
                            transition: 'transform 0.2s, opacity 0.2s',
                            transformOrigin: '150px 150px',
                            transform: isHovered ? 'scale(1.04)' : 'scale(1)',
                            opacity: hoveredSlice && !isHovered ? 0.6 : 1,
                            filter: isHovered ? 'drop-shadow(0 4px 8px rgba(0,0,0,0.2))' : 'none',
                          }}
                          onMouseEnter={() => setHoveredSlice(slice)}
                          onMouseLeave={() => setHoveredSlice(null)}
                        />
                      );
                    })}
                  </svg>
                  <div style={{
                    position: 'absolute', top: 0, left: 0, right: 0, bottom: 0,
                    display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                    pointerEvents: 'none', textAlign: 'center'
                  }}>
                    <div style={{ fontSize: 22, fontWeight: 800, color: '#0f172a' }}>
                      {hoveredSlice ? hoveredSlice.count.toLocaleString() : pieTotal.toLocaleString()}
                    </div>
                    <div style={{ fontSize: 11, fontWeight: 600, color: '#64748b', maxWidth: 100, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {hoveredSlice ? hoveredSlice.name : 'Total Volume'}
                    </div>
                    {hoveredSlice && (
                      <div style={{ fontSize: 11, fontWeight: 700, color: hoveredSlice.color, marginTop: 2 }}>
                        {hoveredSlice.percentage}%
                      </div>
                    )}
                  </div>
                </div>

                {/* Legend */}
                <div style={{ flex: '1 1 200px', display: 'flex', flexDirection: 'column', gap: 8 }}>
                  {donutSlices.map(slice => {
                    const isHovered = hoveredSlice?.name === slice.name;
                    return (
                      <div
                        key={slice.name}
                        onMouseEnter={() => setHoveredSlice(slice)}
                        onMouseLeave={() => setHoveredSlice(null)}
                        style={{
                          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                          padding: '5px 8px', borderRadius: 6, cursor: 'pointer',
                          background: isHovered ? '#f8fafc' : 'transparent',
                          transition: 'background 0.15s'
                        }}
                      >
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 }}>
                          <span style={{ width: 10, height: 10, borderRadius: '50%', background: slice.color, flexShrink: 0 }}></span>
                          <span style={{ fontSize: 12.5, color: isHovered ? '#0f172a' : '#334155', fontWeight: isHovered ? 700 : 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {slice.name}
                          </span>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexShrink: 0 }}>
                          <span style={{ fontSize: 12.5, fontWeight: 700, color: '#0f172a' }}>
                            {slice.count.toLocaleString()}
                          </span>
                          <span style={{ fontSize: 11, color: '#94a3b8', width: 32, textAlign: 'right' }}>
                            {slice.percentage}%
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            ) : (
              <div style={{ padding: 40, color: '#94a3b8', fontSize: 13 }}>
                No distribution data for this filter.
              </div>
            )}
          </div>
        </div>

        {/* Chart 2: Monthly Trends (Inflow vs Disposal) */}
        <div className="card">
          <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div className="card-title">
              <span>{selected_circle ? `${selected_circle.name} Inflow vs Disposal Trend (${year})` : `Agency Inflow vs Disposal Trend (${year})`}</span>
            </div>
            <div style={{ display: 'flex', gap: 14, fontSize: 12 }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: 5, color: '#2563eb', fontWeight: 600 }}>
                <span style={{ width: 10, height: 10, borderRadius: 2, background: '#2563eb' }}></span> Received
              </span>
              <span style={{ display: 'flex', alignItems: 'center', gap: 5, color: '#16a34a', fontWeight: 600 }}>
                <span style={{ width: 10, height: 10, borderRadius: 2, background: '#16a34a' }}></span> Disposed
              </span>
            </div>
          </div>

          <div className="card-body" style={{ height: 280, padding: '16px 20px' }}>
            <svg viewBox="0 0 600 240" preserveAspectRatio="none" style={{ width: '100%', height: '100%' }}>
              <defs>
                <linearGradient id="trendRecGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#2563eb" stopOpacity="0.25" />
                  <stop offset="100%" stopColor="#2563eb" stopOpacity="0.0" />
                </linearGradient>
                <linearGradient id="trendResGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#16a34a" stopOpacity="0.25" />
                  <stop offset="100%" stopColor="#16a34a" stopOpacity="0.0" />
                </linearGradient>
              </defs>

              {[0, 0.25, 0.5, 0.75, 1].map((ratio, i) => (
                <line
                  key={i}
                  x1={30} y1={200 - ratio * 170}
                  x2={580} y2={200 - ratio * 170}
                  stroke="#f1f5f9" strokeWidth="1"
                />
              ))}

              {monthlyTrends.length > 0 && (
                <>
                  <polygon
                    fill="url(#trendRecGrad)"
                    points={`30,200 ${monthlyTrends.map((m, i) => `${i * 48 + 40},${200 - (m.received / maxMonthlyVal) * 170}`).join(' ')} ${11 * 48 + 40},200`}
                  />
                  <polygon
                    fill="url(#trendResGrad)"
                    points={`30,200 ${monthlyTrends.map((m, i) => `${i * 48 + 40},${200 - (m.resolved / maxMonthlyVal) * 170}`).join(' ')} ${11 * 48 + 40},200`}
                  />
                  <polyline
                    fill="none" stroke="#2563eb" strokeWidth="2.5"
                    points={monthlyTrends.map((m, i) => `${i * 48 + 40},${200 - (m.received / maxMonthlyVal) * 170}`).join(' ')}
                  />
                  <polyline
                    fill="none" stroke="#16a34a" strokeWidth="2.5"
                    points={monthlyTrends.map((m, i) => `${i * 48 + 40},${200 - (m.resolved / maxMonthlyVal) * 170}`).join(' ')}
                  />

                  {monthlyTrends.map((m, i) => {
                    const cx = i * 48 + 40;
                    const cyRec = 200 - (m.received / maxMonthlyVal) * 170;
                    const cyRes = 200 - (m.resolved / maxMonthlyVal) * 170;
                    return (
                      <g key={m.month}>
                        <circle cx={cx} cy={cyRec} r={3.5} fill="#2563eb" stroke="#fff" strokeWidth="1.5" />
                        <circle cx={cx} cy={cyRes} r={3.5} fill="#16a34a" stroke="#fff" strokeWidth="1.5" />
                        <text x={cx} y={222} textAnchor="middle" fontSize="10.5" fill="#64748b" fontWeight="600">
                          {m.month}
                        </text>
                      </g>
                    );
                  })}
                </>
              )}
            </svg>
          </div>
        </div>
      </div>

      {/* Charts Section Row 2: Circle Comparative Bar Chart (when viewing all circles) */}
      {!selected_circle && (
        <div className="card" style={{ marginTop: 24 }}>
          <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div className="card-title">
              <span>Circle-by-Circle Comparative Performance (Top Circles by Volume)</span>
            </div>
            <div style={{ display: 'flex', gap: 14, fontSize: 12 }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: 5, color: '#0284c7', fontWeight: 600 }}>
                <span style={{ width: 10, height: 10, borderRadius: 2, background: '#0284c7' }}></span> Disposed
              </span>
              <span style={{ display: 'flex', alignItems: 'center', gap: 5, color: '#f97316', fontWeight: 600 }}>
                <span style={{ width: 10, height: 10, borderRadius: 2, background: '#f97316' }}></span> Active / Pending
              </span>
            </div>
          </div>

          <div className="card-body" style={{ padding: '20px 24px' }}>
            {barChartData.length > 0 ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {barChartData.map((circle) => {
                  const disposedPercent = circle.total > 0 ? Math.round((circle.disposed / circle.total) * 100) : 0;
                  const pendingPercent = 100 - disposedPercent;
                  const widthPercent = Math.max(5, (circle.total / maxBarValue) * 100);

                  return (
                    <div key={circle.name} style={{ display: 'grid', gridTemplateColumns: '180px 1fr 100px', alignItems: 'center', gap: 16 }}>
                      <div style={{ minWidth: 0 }}>
                        <div style={{ fontSize: 13, fontWeight: 700, color: '#1e293b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                          {circle.name}
                        </div>
                        <div style={{ fontSize: 11, color: '#64748b' }}>
                          {circle.code ? `Code: ${circle.code}` : ''}
                        </div>
                      </div>

                      <div style={{ width: '100%', background: '#f1f5f9', borderRadius: 8, height: 24, overflow: 'hidden', display: 'flex', position: 'relative' }}>
                        <div style={{ width: `${widthPercent}%`, height: '100%', display: 'flex', borderRadius: 8, overflow: 'hidden' }}>
                          <div
                            style={{
                              width: `${disposedPercent}%`,
                              background: '#0284c7',
                              height: '100%',
                              transition: 'width 0.4s ease'
                            }}
                            title={`Disposed: ${circle.disposed}`}
                          />
                          <div
                            style={{
                              width: `${pendingPercent}%`,
                              background: '#f97316',
                              height: '100%',
                              transition: 'width 0.4s ease'
                            }}
                            title={`Pending: ${circle.pending}`}
                          />
                        </div>
                      </div>

                      <div style={{ textAlign: 'right', display: 'flex', flexDirection: 'column', alignItems: 'flex-end' }}>
                        <span style={{ fontSize: 13, fontWeight: 800, color: '#0f172a' }}>
                          {circle.total} cases
                        </span>
                        <span style={{
                          fontSize: 11, fontWeight: 700,
                          color: circle.rate >= 70 ? '#15803d' : (circle.rate >= 40 ? '#b45309' : '#dc2626')
                        }}>
                          {circle.rate}% Disposed
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div style={{ textAlign: 'center', padding: 30, color: '#94a3b8' }}>
                No circle performance data available for this filter.
              </div>
            )}
          </div>
        </div>
      )}

      {/* Circle Detailed Progress Table (with direct "Open Circle Portal" action) */}
      {!selected_circle && (
        <div ref={circlesSectionRef} className="card" style={{ marginTop: 24 }}>
          <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12 }}>
            <div>
              <div className="card-title">Comprehensive Department &amp; Circle Progress</div>
              <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>
                Individual performance metrics, officers deployed &amp; one-click dedicated circle portal view
              </div>
            </div>

            <div className="table-search-box no-print" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <input
                type="text"
                placeholder="Search circle name or code..."
                value={tableSearch}
                onChange={e => setTableSearch(e.target.value)}
                className="filter-input"
                style={{ width: 220, fontSize: 12.5 }}
              />
            </div>
          </div>

          <div className="card-body" style={{ padding: 0 }}>
            <div className="table-responsive">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Circle / Department</th>
                    <th>Officers Deployed</th>
                    <th>Complaints (CMU)</th>
                    <th>Enquiries</th>
                    <th>FIR Cases</th>
                    <th>Legal Pending</th>
                    <th style={{ width: 170 }}>Disposal Progress</th>
                    <th className="action-col no-print" style={{ textAlign: 'center' }}>Portal Action</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredCircles.map(c => (
                    <tr key={c.id}>
                      <td>
                        <div style={{ fontWeight: 700, color: '#0f172a' }}>{c.name}</div>
                        {c.code && <span className="table-id" style={{ marginTop: 2 }}>{c.code}</span>}
                      </td>
                      <td>
                        <button
                          type="button"
                          onClick={() => handleCircleOfficersClick(c.id)}
                          style={{
                            display: 'inline-flex', alignItems: 'center', gap: 5, padding: '4px 9px',
                            background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd',
                            borderRadius: 20, fontSize: 12, fontWeight: 700, cursor: 'pointer'
                          }}
                          title={`View ${c.name} officers`}
                        >
                          <span>👥 {c.officers_count || 0} Officers</span>
                          <span style={{ fontSize: 10 }}>↓</span>
                        </button>
                      </td>
                      <td>
                        <div style={{ fontWeight: 600, color: '#1e293b' }}>{c.total_complaints}</div>
                        <div style={{ fontSize: 11, color: '#16a34a' }}>{c.resolved_complaints} resolved</div>
                      </td>
                      <td>
                        <div style={{ fontWeight: 600, color: '#1e293b' }}>{c.total_enquiries}</div>
                      </td>
                      <td>
                        <div style={{ fontWeight: 600, color: '#1e293b' }}>{c.total_cases}</div>
                        <div style={{ fontSize: 11, color: '#0d9488' }}>{c.finalized_cases} finalized</div>
                      </td>
                      <td>
                        {c.pending_legal > 0 ? (
                          <span style={{
                            background: '#fef3c7', color: '#b45309', padding: '3px 8px',
                            borderRadius: 6, fontSize: 11, fontWeight: 700
                          }}>
                            {c.pending_legal} pending
                          </span>
                        ) : (
                          <span style={{ color: '#94a3b8', fontSize: 12 }}>0</span>
                        )}
                      </td>
                      <td>
                        <ProgressBar value={c.disposal_rate} showLabel />
                      </td>
                      <td className="action-cell no-print" style={{ textAlign: 'center' }}>
                        <button
                          type="button"
                          onClick={() => {
                            setCircleId(String(c.id));
                            setOfficerCircleFilter(String(c.id));
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                          }}
                          style={{
                            padding: '5px 12px', background: '#0284c7', color: '#fff', border: 'none',
                            borderRadius: 6, fontSize: 11.5, fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap'
                          }}
                        >
                          🏛️ Open {c.code || c.name} Portal
                        </button>
                      </td>
                    </tr>
                  ))}
                  {!filteredCircles.length && (
                    <tr>
                      <td colSpan={8} style={{ textAlign: 'center', padding: 30, color: '#94a3b8' }}>
                        No circles found matching "{tableSearch}"
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Circle-wise Officers Directory & Individual Workload */}
      <div className="card" ref={officersSectionRef} style={{ marginTop: 24, borderTop: '4px solid #015C94' }}>
        <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 14 }}>
          <div>
            <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span>👮‍♂️ {selected_circle ? `${selected_circle.name} Officers Directory & Workload` : 'Circle Officers Directory & Workload'}</span>
              <span style={{
                background: '#015C94', color: '#fff', fontSize: 11, fontWeight: 700,
                padding: '2px 8px', borderRadius: 10
              }}>
                {filteredOfficers.length} Officers
              </span>
            </div>
            <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>
              {selected_circle
                ? `Active officers deployed specifically in ${selected_circle.name} (${selected_circle.zone_name}) with real-time caseloads.`
                : 'Officers posted across Gujranwala, Lahore, Karachi, Islamabad and other circles with live workload tracking.'}
            </div>
          </div>

          {/* Officers Search & Filter Controls */}
          <div className="filter-controls no-print" style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
            {!selected_circle && (
              <select
                className="filter-select"
                value={officerCircleFilter}
                onChange={e => setOfficerCircleFilter(e.target.value)}
                style={{ fontSize: 12.5, minWidth: 170 }}
              >
                <option value="">All Circles ({circle_officers.length})</option>
                {circles.map(c => {
                  const count = circle_officers.filter(o => String(o.circle_id) === String(c.id)).length;
                  return (
                    <option key={c.id} value={c.id}>
                      {c.name} ({count})
                    </option>
                  );
                })}
              </select>
            )}

            {/* Role Filter */}
            <select
              className="filter-select"
              value={officerRoleFilter}
              onChange={e => setOfficerRoleFilter(e.target.value)}
              style={{ fontSize: 12.5, minWidth: 150 }}
            >
              <option value="">All Roles</option>
              <option value="circle_incharge">Circle Incharge</option>
              <option value="investigation_officer">Investigation Officer</option>
              <option value="enquiry_officer">Enquiry Officer</option>
              <option value="verification_officer">Verification Officer</option>
              <option value="reader_branch">Reader Branch</option>
              <option value="moharrar">Moharrar</option>
              <option value="operator">Front Desk / CMU</option>
              <option value="legal">Legal Officer</option>
            </select>

            {/* Officer Search Input */}
            <input
              type="text"
              placeholder="Search officer name, email..."
              value={officerSearch}
              onChange={e => setOfficerSearch(e.target.value)}
              className="filter-input"
              style={{ width: 200, fontSize: 12.5 }}
            />

            {(officerCircleFilter || officerRoleFilter || officerSearch) && (
              <button
                type="button"
                onClick={() => {
                  if (!selected_circle) setOfficerCircleFilter('');
                  setOfficerRoleFilter('');
                  setOfficerSearch('');
                }}
                style={{
                  background: 'transparent', border: 'none', color: '#ef4444',
                  fontSize: 12, fontWeight: 700, cursor: 'pointer', padding: '4px 6px'
                }}
              >
                Reset
              </button>
            )}
          </div>
        </div>

        {/* Quick Circle Chips (only when All Circles is active) */}
        {!selected_circle && (
          <div className="chips-bar no-print" style={{
            display: 'flex', gap: 8, padding: '10px 16px', overflowX: 'auto',
            background: '#f8fafc', borderBottom: '1px solid #e2e8f0', flexWrap: 'wrap'
          }}>
            <button
              type="button"
              onClick={() => setOfficerCircleFilter('')}
              style={{
                padding: '5px 12px', borderRadius: 20, fontSize: 12, fontWeight: 700,
                cursor: 'pointer', border: 'none',
                background: !officerCircleFilter ? '#015C94' : '#e2e8f0',
                color: !officerCircleFilter ? '#fff' : '#334155'
              }}
            >
              All Circles ({circle_officers.length})
            </button>
            {circles.map(c => {
              const count = circle_officers.filter(o => String(o.circle_id) === String(c.id)).length;
              const isSelected = String(officerCircleFilter) === String(c.id);
              return (
                <button
                  key={c.id}
                  type="button"
                  onClick={() => setOfficerCircleFilter(isSelected ? '' : String(c.id))}
                  style={{
                    padding: '5px 12px', borderRadius: 20, fontSize: 12, fontWeight: 700,
                    cursor: 'pointer', border: 'none',
                    background: isSelected ? '#015C94' : (count > 0 ? '#e0f2fe' : '#f1f5f9'),
                    color: isSelected ? '#fff' : (count > 0 ? '#0369a1' : '#94a3b8')
                  }}
                >
                  {c.name} ({count})
                </button>
              );
            })}
          </div>
        )}

        {/* Officers Table */}
        <div className="card-body" style={{ padding: 0 }}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Officer</th>
                  <th>Circle / Posting</th>
                  <th>Designation / Role</th>
                  <th>Assigned Tasks</th>
                  <th>Completed / Disposed</th>
                  <th>Active Backlog</th>
                  <th style={{ width: 150 }}>Completion Rate</th>
                  <th className="action-col no-print" style={{ textAlign: 'center' }}>Handover / Lifecycle</th>
                </tr>
              </thead>
              <tbody>
                {filteredOfficers.map(off => (
                  <tr key={off.id}>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div style={{
                          width: 34, height: 34, borderRadius: '50%',
                          background: off.status === 'suspended' ? '#dc2626' : '#015C94', color: '#fff',
                          display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, fontWeight: 800, flexShrink: 0
                        }}>
                          {off.name ? off.name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() : 'OF'}
                        </div>
                        <div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                            <span style={{ fontWeight: 700, color: '#0f172a' }}>{off.name}</span>
                            {off.status === 'suspended' && (
                              <span style={{ background: '#fee2e2', color: '#b91c1c', fontSize: 9.5, fontWeight: 800, padding: '1px 5px', borderRadius: 4 }}>
                                ⛔ SUSPENDED
                              </span>
                            )}
                          </div>
                          <div style={{ fontSize: 11, color: '#64748b' }}>{off.email}</div>
                          {off.phone && <div style={{ fontSize: 10.5, color: '#94a3b8' }}>📞 {off.phone}</div>}
                        </div>
                      </div>
                    </td>
                    <td>
                      <span style={{
                        display: 'inline-flex', alignItems: 'center', gap: 4,
                        background: '#f1f5f9', color: '#0f172a', padding: '3px 9px',
                        borderRadius: 6, fontSize: 12, fontWeight: 700, border: '1px solid #e2e8f0'
                      }}>
                        🏛️ {off.circle_name}
                      </span>
                    </td>
                    <td>
                      <span style={{
                        background: '#e0f2fe', color: '#0284c7', padding: '3px 8px',
                        borderRadius: 6, fontSize: 11, fontWeight: 700
                      }}>
                        {off.designation}
                      </span>
                    </td>
                    <td>
                      <span style={{ fontWeight: 800, color: '#0f172a', fontSize: 13 }}>
                        {off.assigned_workload}
                      </span>
                    </td>
                    <td>
                      <span style={{ fontWeight: 700, color: '#16a34a', fontSize: 13 }}>
                        {off.completed_workload}
                      </span>
                    </td>
                    <td>
                      <span style={{
                        fontWeight: 700, fontSize: 13,
                        color: off.pending_workload > 0 ? '#ea580c' : '#64748b'
                      }}>
                        {off.pending_workload}
                      </span>
                    </td>
                    <td>
                      <ProgressBar value={off.completion_rate} showLabel />
                    </td>
                    <td className="action-cell no-print" style={{ textAlign: 'center' }}>
                      <button
                        type="button"
                        onClick={() => setSelectedOfficerForHandover(off)}
                        style={{
                          display: 'inline-flex', alignItems: 'center', gap: 4, padding: '4px 9px',
                          background: off.status === 'suspended' ? '#fee2e2' : '#e0f2fe',
                          border: '1px solid',
                          borderColor: off.status === 'suspended' ? '#fca5a5' : '#bae6fd',
                          borderRadius: 6, fontSize: 11, fontWeight: 700,
                          color: off.status === 'suspended' ? '#b91c1c' : '#0369a1',
                          cursor: 'pointer', whiteSpace: 'nowrap'
                        }}
                        title="Transfer, Suspend, or Handover Workload"
                      >
                        <span>🔄 Handover</span>
                      </button>
                    </td>
                  </tr>
                ))}
                {!filteredOfficers.length && (
                  <tr>
                    <td colSpan={8} style={{ textAlign: 'center', padding: 36, color: '#94a3b8' }}>
                      No officers found matching the selected circle or filters.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Legal Scrutiny & Approval Backlog */}
      <div ref={legalSectionRef} className="card" style={{ marginTop: 24, borderTop: '3px solid #f59e0b' }}>
        <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span>⚖️ Central Legal Scrutiny &amp; Approval Backlog</span>
              <span style={{
                background: '#fef3c7', color: '#b45309', fontSize: 11, fontWeight: 700,
                padding: '2px 8px', borderRadius: 10
              }}>
                {legal_backlog.length} Awaiting Scrutiny
              </span>
            </div>
            <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>
              {selected_circle
                ? `CFRs and files originating from ${selected_circle.name} awaiting legal opinion.`
                : 'CFRs and Enquiry/Case files currently queued for AD Legal, DD Legal, Additional Director, and DG opinions.'}
            </div>
          </div>
          <Link
            to="/enquiries"
            className="no-print"
            style={{
              fontSize: 12, fontWeight: 600, color: '#015C94', textDecoration: 'none'
            }}
          >
            View All Enquiries →
          </Link>
        </div>

        <div className="card-body" style={{ padding: 0 }}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Item Type</th>
                  <th>Reference Number</th>
                  <th>Originating Circle</th>
                  <th>Current Approval Stage</th>
                  <th>Last Movement</th>
                  <th className="action-col no-print" style={{ textAlign: 'right' }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {legal_backlog.map(item => (
                  <tr key={item.id}>
                    <td>
                      <span style={{
                        background: '#e0f2fe', color: '#0369a1', fontSize: 11, fontWeight: 700,
                        padding: '2px 8px', borderRadius: 4
                      }}>
                        {item.type}
                      </span>
                    </td>
                    <td style={{ fontWeight: 700, color: '#0f172a' }}>
                      {item.number}
                    </td>
                    <td style={{ color: '#334155' }}>
                      {item.circle}
                    </td>
                    <td>
                      <span style={{
                        background: '#fef3c7', color: '#92400e', fontSize: 11, fontWeight: 700,
                        padding: '2px 8px', borderRadius: 4, textTransform: 'uppercase'
                      }}>
                        {item.status.replace(/_/g, ' ')}
                      </span>
                    </td>
                    <td style={{ fontSize: 12, color: '#64748b' }}>
                      {item.updated_at}
                    </td>
                    <td className="action-cell no-print" style={{ textAlign: 'right' }}>
                      <Link
                        to={`/enquiries/${item.id}/edit`}
                        style={{
                          padding: '4px 10px', background: '#015C94', color: '#fff',
                          borderRadius: 4, fontSize: 11, fontWeight: 600, textDecoration: 'none'
                        }}
                      >
                        Review Opinion
                      </Link>
                    </td>
                  </tr>
                ))}
                {!legal_backlog.length && (
                  <tr>
                    <td colSpan={6} style={{ textAlign: 'center', padding: 24, color: '#16a34a', fontWeight: 600 }}>
                      ✓ No pending legal scrutiny backlogs for this view.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Official Print-Only Footer Authentication */}
      <div className="print-only-footer">
        <div style={{ marginTop: 28, paddingTop: 14, pageBreakInside: 'avoid', breakInside: 'avoid' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', border: 'none' }}>
            <tbody>
              <tr>
                {selected_circle ? (
                  <>
                    <td style={{ width: '33%', verticalAlign: 'top', textAlign: 'left', border: 'none', padding: '0 8px' }}>
                      <div style={{ fontSize: 9.5, fontWeight: 700, color: '#475569', textTransform: 'uppercase' }}>Compiled &amp; Verified By:</div>
                      <div style={{ marginTop: 42, borderTop: '1.5px dashed #475569', paddingTop: 4 }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: '#0f172a' }}>Moharrar / Reader Branch</div>
                        <div style={{ fontSize: 9.5, color: '#475569' }}>{selected_circle.name} Regional Circle</div>
                      </div>
                    </td>
                    <td style={{ width: '33%', verticalAlign: 'top', textAlign: 'center', border: 'none', padding: '0 8px' }}>
                      <div style={{ fontSize: 9.5, fontWeight: 700, color: '#475569', textTransform: 'uppercase' }}>Supervising Authority:</div>
                      <div style={{ marginTop: 42, borderTop: '1.5px dashed #475569', paddingTop: 4 }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: '#0f172a' }}>{selected_circle.incharge_name || 'Circle Incharge'}</div>
                        <div style={{ fontSize: 9.5, color: '#475569' }}>Circle Incharge / Incharge Officer</div>
                      </div>
                    </td>
                    <td style={{ width: '33%', verticalAlign: 'top', textAlign: 'right', border: 'none', padding: '0 8px' }}>
                      <div style={{ fontSize: 9.5, fontWeight: 700, color: '#475569', textTransform: 'uppercase' }}>Zonal Countersignature:</div>
                      <div style={{ marginTop: 42, borderTop: '1.5px dashed #475569', paddingTop: 4, display: 'inline-block', textAlign: 'left' }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: '#0f172a' }}>Zonal Director / AD</div>
                        <div style={{ fontSize: 9.5, color: '#475569' }}>{selected_circle.zone_name || 'Zonal Directorate'}</div>
                      </div>
                    </td>
                  </>
                ) : (
                  <>
                    <td style={{ width: '33%', verticalAlign: 'top', textAlign: 'left', border: 'none', padding: '0 8px' }}>
                      <div style={{ fontSize: 9.5, fontWeight: 700, color: '#475569', textTransform: 'uppercase' }}>Prepared By:</div>
                      <div style={{ marginTop: 42, borderTop: '1.5px dashed #475569', paddingTop: 4 }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: '#0f172a' }}>Monitoring &amp; CMS Cell</div>
                        <div style={{ fontSize: 9.5, color: '#475569' }}>Federal Headquarters, Islamabad</div>
                      </div>
                    </td>
                    <td style={{ width: '33%', verticalAlign: 'top', textAlign: 'center', border: 'none', padding: '0 8px' }}>
                      <div style={{ fontSize: 9.5, fontWeight: 700, color: '#475569', textTransform: 'uppercase' }}>Reviewed By:</div>
                      <div style={{ marginTop: 42, borderTop: '1.5px dashed #475569', paddingTop: 4 }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: '#0f172a' }}>Additional Director (Operations)</div>
                        <div style={{ fontSize: 9.5, color: '#475569' }}>NCCIA Federal Headquarters</div>
                      </div>
                    </td>
                    <td style={{ width: '33%', verticalAlign: 'top', textAlign: 'right', border: 'none', padding: '0 8px' }}>
                      <div style={{ fontSize: 9.5, fontWeight: 700, color: '#475569', textTransform: 'uppercase' }}>Approved By:</div>
                      <div style={{ marginTop: 42, borderTop: '1.5px dashed #475569', paddingTop: 4, display: 'inline-block', textAlign: 'left' }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: '#0f172a' }}>The Director General</div>
                        <div style={{ fontSize: 9.5, color: '#475569' }}>NCCIA Pakistan, Islamabad</div>
                      </div>
                    </td>
                  </>
                )}
              </tr>
            </tbody>
          </table>

          <div style={{
            marginTop: 20, textAlign: 'center', fontSize: 8.5, color: '#64748b',
            borderTop: '1px solid #cbd5e1', paddingTop: 6
          }}>
            CONFIDENTIAL &bull; National Cyber Crime Investigation Agency (NCCIA) CMS System-Generated Briefing &bull; Tampering or unauthorized circulation is strictly prohibited under PECA 2016.
          </div>
        </div>
      </div>

      {/* Officer Lifecycle & Handover Modal */}
      {selectedOfficerForHandover && (
        <OfficerHandoverModal
          officer={selectedOfficerForHandover}
          isOpen={Boolean(selectedOfficerForHandover)}
          onClose={() => setSelectedOfficerForHandover(null)}
          onSuccess={() => fetchMonitoringData(true)}
        />
      )}
    </div>
  );
}
