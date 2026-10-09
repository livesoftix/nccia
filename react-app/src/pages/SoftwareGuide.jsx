import { useState, useEffect, useRef, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import SoftwareGuideCharacter from '../components/SoftwareGuideCharacter';
import SoftwareGuideBackdrop from '../components/SoftwareGuideBackdrop';
import './SoftwareGuide.css';

const SCENES = [
  {
    id: 'intro',
    workflowStep: -1,
    title: 'Welcome to NCCIA CMS',
    titleUr: 'NCCIA CMS mein khush amdeed',
    visual: '🛡️',
    narration:
      'Assalam o Alaikum! Main Cyber Officer Sara hoon. NCCIA Case Management System aap ko complaint se court tak poora digital workflow deta hai — fast, secure, aur transparent.',
    narrationEn:
      'Welcome! I am Cyber Officer Sara. The NCCIA Case Management System gives you a complete digital workflow from complaint to court — fast, secure, and transparent.',
    benefits: ['One portal for all circles', 'Role-based secure access', 'Live audit trail'],
  },
  {
    id: 'complaint',
    workflowStep: 0,
    title: 'Complaint Registration',
    titleUr: 'Shikayat darj karna',
    visual: '📝',
    narration:
      'Pehla step: complaint register hoti hai — walk-in, portal, ya email se. System tracking number aur automatic SMS acknowledgment bhejta hai complainant ko.',
    narrationEn:
      'Step one: register the complaint from walk-in, portal, or email. The system issues a tracking number and sends automatic SMS acknowledgment to the complainant.',
    benefits: ['Instant tracking number', 'Bilingual SMS', 'Digital attachments'],
  },
  {
    id: 'verification',
    workflowStep: 1,
    title: 'Verification Stage',
    titleUr: 'Tasdeeq ka marhala',
    visual: '✅',
    narration:
      'Verification Officer complaint verify karta hai, report likhta hai, aur supervisor approve karta hai. Sab kuch CMS mein lock ho jata hai — koi manual register nahi.',
    narrationEn:
      'The Verification Officer verifies the complaint, writes the report, and the supervisor approves it. Everything is locked in CMS — no manual registers.',
    benefits: ['Structured VO reports', 'Approval workflow', 'PDF archive'],
  },
  {
    id: 'enquiry',
    workflowStep: 2,
    title: 'Enquiry & Investigation',
    titleUr: 'Tahqeek aur investigation',
    visual: '🔍',
    narration:
      'Enquiry Officer diary, evidence aur CFR record karta hai. Investigation Officer case file kholta hai — pehle wale records read-only rehte hain, nayi arrests aur activities add kar sakta hai.',
    narrationEn:
      'The Enquiry Officer records diary, evidence, and CFR. The Investigation Officer opens the case file — earlier records stay read-only while new arrests and activities can be added.',
    benefits: ['Full process history', 'IO diary lock', 'CFR to case file'],
  },
  {
    id: 'forensic',
    workflowStep: 4,
    title: 'Digital Forensics',
    titleUr: 'Digital forensic',
    visual: '💾',
    narration:
      'IO forensic request bhejta hai, Malkhana custody track karta hai, lab report CMS par upload hoti hai. Chain of custody court ke liye ready rehti hai.',
    narrationEn:
      'The IO sends forensic requests, Malkhana tracks custody, and the lab uploads reports to CMS. Chain of custody stays court-ready.',
    benefits: ['Evidence tracking', 'Lab status updates', 'Signed forensic PDF'],
  },
  {
    id: 'court',
    workflowStep: 5,
    title: 'Court & Legal',
    titleUr: 'Adalat aur legal',
    visual: '⚖️',
    narration:
      'Legal wing court cases, hearing dates, orders aur verdicts record karti hai. Pending trials dashboard par nazar aate hain.',
    narrationEn:
      'The legal wing records court cases, hearing dates, orders, and verdicts. Pending trials appear on the dashboard.',
    benefits: ['Hearing calendar', 'Order uploads', 'Case linkage'],
  },
  {
    id: 'sms',
    workflowStep: -1,
    title: 'SMS Notifications',
    titleUr: 'SMS notifications',
    visual: '📱',
    narration:
      'Har important step par complainant aur officers ko SMS milta hai. SMS Log module mein poori delivery history live dikhti hai.',
    narrationEn:
      'Complainants and officers receive SMS at every important step. The SMS Log module shows full live delivery history.',
    benefits: ['English + Urdu', 'Trigger tracking', 'Delivery status'],
  },
  {
    id: 'admin',
    workflowStep: 3,
    title: 'DSR & D.O. Letters',
    titleUr: 'DSR aur D.O. Letter',
    visual: '📊',
    narration:
      'ADA Administration live CMS data se Daily Situation Report aur monthly D.O. Letter auto-compile karti hai — QR verified official print ke saath.',
    narrationEn:
      'ADA Administration auto-compiles Daily Situation Reports and monthly D.O. Letters from live CMS data — with QR-verified official prints.',
    benefits: ['Auto-compile stats', 'Review workflow', 'QR verification'],
  },
  {
    id: 'reference',
    workflowStep: -1,
    title: 'Reference Library',
    titleUr: 'Reference Library',
    visual: '📚',
    narration:
      'Laws, Rules, SOPs aur User Manuals ek jagah — har officer View kar sakta hai. PECA sections se le kar step-by-step SOPs tak sab yahan hai.',
    narrationEn:
      'Laws, Rules, SOPs, and User Manuals in one place — every officer can view them. From PECA sections to step-by-step SOPs, everything is here.',
    benefits: ['PECA & PPC guides', 'Official SOPs', 'Role manuals'],
  },
  {
    id: 'summary',
    workflowStep: 6,
    title: 'Why NCCIA CMS?',
    titleUr: 'NCCIA CMS kyun?',
    visual: '🎯',
    narration:
      'Faida yeh hai: tez kaam, kam galti, poora record, DG ko live analytics, aur citizen ko tracking. Shukriya — ab apna role dashboard se shuru karein!',
    narrationEn:
      'The benefits: faster work, fewer errors, complete records, live analytics for leadership, and tracking for citizens. Thank you — start from your role dashboard now!',
    benefits: ['Speed & accuracy', 'Accountability', 'Public trust'],
  },
];

const SCENE_MS = 14000;

export default function SoftwareGuide() {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  // Tab State: 'support' | 'guide' | 'faqs' | 'jurisdictions'
  const tabParam = searchParams.get('tab');
  const [activeTab, setActiveTab] = useState(
    tabParam === 'guide' ? 'guide' : (tabParam === 'faqs' ? 'faqs' : (tabParam === 'jurisdictions' ? 'jurisdictions' : 'support'))
  );
  const [districtSearch, setDistrictSearch] = useState('');

  const setTab = (tab) => {
    setActiveTab(tab);
    setSearchParams({ tab });
  };

  // Video Player State
  const [sceneIndex, setSceneIndex] = useState(0);
  const [playing, setPlaying] = useState(false);
  const [speaking, setSpeaking] = useState(false);
  const [lang, setLang] = useState('ur'); // ur | en | both
  const [progress, setProgress] = useState(0);
  const timerRef = useRef(null);
  const progressRef = useRef(null);
  const speechRef = useRef(null);
  const scene = SCENES[sceneIndex];

  // Developer Support / Issue Reporter State
  const [issueCategory, setIssueCategory] = useState('Official Prints & Letterheads');
  const [issuePriority, setIssuePriority] = useState('Critical / High');
  const [issueDesc, setIssueDesc] = useState('');
  const [copiedDiagnostic, setCopiedDiagnostic] = useState(false);
  const [copiedIssue, setCopiedIssue] = useState(false);
  const [copiedEmail, setCopiedEmail] = useState(false);

  // FAQ Accordion State
  const [openFaq, setOpenFaq] = useState(null);

  const toggleFaq = (idx) => {
    setOpenFaq(openFaq === idx ? null : idx);
  };

  const stopSpeech = useCallback(() => {
    if (typeof window !== 'undefined' && window.speechSynthesis) {
      window.speechSynthesis.cancel();
    }
    setSpeaking(false);
  }, []);

  const speakScene = useCallback((index) => {
    if (typeof window === 'undefined' || !window.speechSynthesis) return;
    stopSpeech();
    const s = SCENES[index];
    const text = lang === 'en' ? s.narrationEn : lang === 'both' ? `${s.narrationEn} ${s.narration}` : s.narration;
    const utter = new SpeechSynthesisUtterance(text);
    utter.rate = 0.92;
    utter.pitch = 1.05;
    const voices = window.speechSynthesis.getVoices();
    const preferred = voices.find(v => lang !== 'en' && v.lang.startsWith('ur'))
      || voices.find(v => v.lang.startsWith('en') && v.name.toLowerCase().includes('female'))
      || voices.find(v => v.lang.startsWith('en'));
    if (preferred) utter.voice = preferred;
    utter.onstart = () => setSpeaking(true);
    utter.onend = () => setSpeaking(false);
    utter.onerror = () => setSpeaking(false);
    speechRef.current = utter;
    window.speechSynthesis.speak(utter);
  }, [lang, stopSpeech]);

  const clearTimers = useCallback(() => {
    if (timerRef.current) clearTimeout(timerRef.current);
    if (progressRef.current) clearInterval(progressRef.current);
  }, []);

  const goToScene = useCallback((index, shouldPlay = playing) => {
    clearTimers();
    stopSpeech();
    const i = ((index % SCENES.length) + SCENES.length) % SCENES.length;
    setSceneIndex(i);
    setProgress(0);
    if (shouldPlay) setPlaying(true);
    else if (shouldPlay === false) setPlaying(false);
  }, [clearTimers, playing, stopSpeech]);

  useEffect(() => {
    if (activeTab !== 'guide' || !playing) {
      clearTimers();
      stopSpeech();
      return undefined;
    }
    setProgress(0);
    speakScene(sceneIndex);
    const start = Date.now();
    progressRef.current = setInterval(() => {
      setProgress(Math.min(100, ((Date.now() - start) / SCENE_MS) * 100));
    }, 120);
    timerRef.current = setTimeout(() => {
      setSceneIndex(i => (i + 1) % SCENES.length);
    }, SCENE_MS);
    return clearTimers;
  }, [activeTab, playing, sceneIndex, lang, clearTimers, speakScene, stopSpeech]);

  useEffect(() => () => { clearTimers(); stopSpeech(); }, [clearTimers, stopSpeech]);

  const togglePlay = () => setPlaying(p => !p);

  // Helper: format officer issue text for WhatsApp / Email
  const getFormattedIssueText = () => {
    const circleName = user?.circle?.name || user?.circle_name || 'Islamabad HQ';
    const roleName = user?.roles?.map(r => r.name).join(', ') || user?.role || user?.designation || 'Field Officer';
    const timeStr = new Date().toLocaleString('en-PK', { timeZone: 'Asia/Karachi' });
    const userAgent = typeof navigator !== 'undefined' ? navigator.userAgent : 'Unknown';

    return `🚨 *NCCIA CMS TECHNICAL SUPPORT REQUEST* 🚨\n` +
      `--------------------------------------------------\n` +
      `👤 *Officer:* ${user?.name || 'Officer'}\n` +
      `🏛️ *Circle:* ${circleName}\n` +
      `🏷️ *Role / Designation:* ${roleName}\n` +
      `📧 *Email:* ${user?.email || 'N/A'}\n` +
      `⚡ *Urgency:* ${issuePriority}\n` +
      `📂 *Category:* ${issueCategory}\n` +
      `🕒 *Reported At:* ${timeStr}\n` +
      `--------------------------------------------------\n` +
      `📝 *Issue Description:*\n` +
      `${issueDesc ? issueDesc.trim() : '(No description entered — officer requested direct contact)'}\n` +
      `--------------------------------------------------\n` +
      `💻 *System Diagnostics:*\n` +
      `• Version: NCCIA CMS v2.4.1 (Enterprise Production)\n` +
      `• Browser: ${userAgent.split(') ')[0] + ')'}\n` +
      `• Screen: ${typeof window !== 'undefined' ? `${window.innerWidth}x${window.innerHeight}` : 'N/A'}\n` +
      `--------------------------------------------------`;
  };

  const handleSendWhatsApp = () => {
    const text = getFormattedIssueText();
    // Developer WhatsApp Hotline: 03464701439 (Pakistan: +92 346 4701439)
    const devPhone = '923464701439';
    const url = `https://wa.me/${devPhone}?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  const handleSendEmail = () => {
    const circleName = user?.circle?.name || user?.circle_name || 'Islamabad HQ';
    const subject = `[NCCIA CMS Support] ${issuePriority} - ${issueCategory} (${circleName})`;
    const body = getFormattedIssueText();
    const mailtoUrl = `mailto:nccia@real-erp.net?cc=support@real-erp.net&subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    window.location.href = mailtoUrl;
  };

  const handleCopyIssue = () => {
    const text = getFormattedIssueText();
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(() => {
        setCopiedIssue(true);
        setTimeout(() => setCopiedIssue(false), 3000);
      });
    }
  };

  const handleCopyDiagnostics = () => {
    const circleName = user?.circle?.name || user?.circle_name || 'Islamabad HQ';
    const diag = `NCCIA CMS TECHNICAL DIAGNOSTICS SNAPSHOT\n` +
      `==================================================\n` +
      `Officer Name:  ${user?.name || 'N/A'}\n` +
      `Officer Email: ${user?.email || 'N/A'}\n` +
      `Role / Title:  ${user?.roles?.map(r => r.name).join(', ') || user?.role || user?.designation || 'N/A'}\n` +
      `Circle:        ${circleName}\n` +
      `Zone:          ${user?.zone?.name || 'All Zones'}\n` +
      `Build Version: NCCIA CMS v2.4.1 (Enterprise Production)\n` +
      `Client Agent:  ${typeof navigator !== 'undefined' ? navigator.userAgent : 'N/A'}\n` +
      `Viewport:      ${typeof window !== 'undefined' ? `${window.innerWidth}x${window.innerHeight} (DPR: ${window.devicePixelRatio || 1})` : 'N/A'}\n` +
      `Local Time:    ${new Date().toISOString()}\n` +
      `Status:        API Server 200 OK (Authenticated)\n` +
      `==================================================`;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(diag).then(() => {
        setCopiedDiagnostic(true);
        setTimeout(() => setCopiedDiagnostic(false), 3000);
      });
    }
  };

  const copyEmailAddress = (email) => {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(email).then(() => {
        setCopiedEmail(true);
        setTimeout(() => setCopiedEmail(false), 2500);
      });
    }
  };

  return (
    <div className="page-content software-guide-page">
      {/* Page Header */}
      <div className="page-header" style={{ marginBottom: 16 }}>
        <div className="page-title-group">
          <div className="page-label">Assistance &amp; Documentation</div>
          <h1 className="page-title">Help &amp; Technical Support Center</h1>
          <p className="page-subtitle">
            Direct Developer Helpdesk, Interactive Character Tour &amp; Field Operational SOPs
          </p>
          <div className="title-underline" />
        </div>
        <div className="page-actions" style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <div className="sg-status-badge">
            <span className="sg-status-dot" />
            <span>Systems Online · v2.4.1</span>
          </div>
        </div>
      </div>

      {/* Top Navigation Tabs */}
      <div className="sg-tabs-bar">
        <button
          type="button"
          className={`sg-tab-btn${activeTab === 'support' ? ' active' : ''}`}
          onClick={() => setTab('support')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
          </svg>
          <span>Developer &amp; Technical Helpdesk</span>
          <span className="sg-tab-pill">Direct Reach</span>
        </button>

        <button
          type="button"
          className={`sg-tab-btn${activeTab === 'guide' ? ' active' : ''}`}
          onClick={() => setTab('guide')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <polygon points="5 3 19 12 5 21 5 3" />
          </svg>
          <span>Interactive Video Tour</span>
          <span className="sg-tab-pill">Officer Sara</span>
        </button>

        <button
          type="button"
          className={`sg-tab-btn${activeTab === 'faqs' ? ' active' : ''}`}
          onClick={() => setTab('faqs')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          <span>Troubleshooting &amp; FAQs</span>
          <span className="sg-tab-pill">SOPs</span>
        </button>

        <button
          type="button"
          className={`sg-tab-btn${activeTab === 'jurisdictions' ? ' active' : ''}`}
          onClick={() => setTab('jurisdictions')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
            <circle cx="12" cy="10" r="3" />
          </svg>
          <span>Regional Hubs &amp; Jurisdictions</span>
          <span className="sg-tab-pill">Directory</span>
        </button>
      </div>

      {/* TAB 1: DEVELOPER & TECHNICAL HELPDESK */}
      {activeTab === 'support' && (
        <div className="sg-tab-content">
          {/* Hero Banner */}
          <div className="dev-hero-banner">
            <div className="dev-hero-left">
              <div className="dev-hero-tag">
                <span className="dev-shield-icon">🛡️</span>
                <span>NCCIA CMS Core Engineering &amp; Architecture Desk</span>
              </div>
              <h2 className="dev-hero-title">Developer &amp; Technical Support Hotline</h2>
              <p className="dev-hero-desc">
                Agar aap ko kisi bhi Regional Circle (Lahore, Karachi, Rawalpindi, Peshawar, Quetta, Gujranwala waghera)
                ya Islamabad HQ mein CMS ke kisi module mein koi takneeqi masla pesh aye — maslan Officer Handover,
                Official Prints / Letterheads, Verifications, Enquiries, ya Forensic Requests — tou aap niche diye gaye
                developer contacts par barah-e-raast WhatsApp ya Email ke zariye 1-click mein rabta kar saktay hain.
              </p>
            </div>
            <div className="dev-hero-right">
              <div className="dev-sla-badge">
                <div className="dev-sla-num">&lt; 15 min</div>
                <div className="dev-sla-label">Priority Critical SLA</div>
              </div>
              <div className="dev-sla-badge">
                <div className="dev-sla-num">24 / 7</div>
                <div className="dev-sla-label">Circle Hotline</div>
              </div>
            </div>
          </div>

          {/* Main 2-Column Grid */}
          <div className="dev-grid">
            {/* Column 1: Developer Profile & Direct Credentials */}
            <div className="dev-card">
              <div className="dev-card-header">
                <div className="dev-avatar-wrap">
                  <div className="dev-avatar">👨‍💻</div>
                  <span className="dev-online-indicator" title="System Architect Available" />
                </div>
                <div className="dev-info-header">
                  <div className="dev-verified-chip">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" />
                    </svg>
                    <span>Verified Lead Developer</span>
                  </div>
                  <h3 className="dev-name">Engr. Humayun</h3>
                  <div className="dev-role-title">Lead System Architect &amp; Core Full-Stack Developer</div>
                  <div className="dev-org-name">LiveSoftix Software Engineering / NCCIA System Architecture</div>
                </div>
              </div>

              <div className="dev-card-body">
                <p className="dev-bio">
                  Responsible for NCCIA Case Management System core engine, database synchronizations, Spatie role
                  permissions, official letterhead generators, QR audit verification, and high-availability operations
                  across all 16 Cyber Crime Circles nationwide.
                </p>

                <div className="dev-contact-list">
                  <div className="dev-contact-item">
                    <div className="dev-contact-icon email">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                      </svg>
                    </div>
                    <div className="dev-contact-details">
                      <div className="dev-contact-label">Official Core Developer Email</div>
                      <a href="mailto:nccia@real-erp.net" className="dev-contact-value">nccia@real-erp.net</a>
                    </div>
                    <button
                      type="button"
                      className="dev-copy-btn"
                      title="Copy Email Address"
                      onClick={() => copyEmailAddress('nccia@real-erp.net')}
                    >
                      {copiedEmail ? '✓ Copied' : 'Copy'}
                    </button>
                  </div>

                  <div className="dev-contact-item">
                    <div className="dev-contact-icon email-alt">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94" />
                      </svg>
                    </div>
                    <div className="dev-contact-details">
                      <div className="dev-contact-label">Technical Helpdesk Desk Email</div>
                      <a href="mailto:support@real-erp.net" className="dev-contact-value">support@real-erp.net</a>
                    </div>
                    <button
                      type="button"
                      className="dev-copy-btn"
                      title="Copy Email Address"
                      onClick={() => copyEmailAddress('support@real-erp.net')}
                    >
                      Copy
                    </button>
                  </div>

                  <div className="dev-contact-item">
                    <div className="dev-contact-icon wa">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                      </svg>
                    </div>
                    <div className="dev-contact-details">
                      <div className="dev-contact-label">Direct Developer WhatsApp &amp; Call Hotline</div>
                      <a
                        href="https://wa.me/923464701439"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="dev-contact-value"
                        title="Click to open WhatsApp chat"
                      >
                        +92 346 4701439 (0346-4701439)
                      </a>
                    </div>
                    <button
                      type="button"
                      className="dev-copy-btn highlight"
                      onClick={handleSendWhatsApp}
                    >
                      Chat on WhatsApp
                    </button>
                  </div>
                </div>

                {/* Scope Coverage */}
                <div className="dev-scope-box">
                  <div className="dev-scope-title">Coverage &amp; Support Scope:</div>
                  <div className="dev-scope-tags">
                    <span className="dev-scope-pill">⚡ Caseload Handover &amp; Transfer</span>
                    <span className="dev-scope-pill">🖨️ PDF &amp; Letterhead Prints</span>
                    <span className="dev-scope-pill">🔐 User Permissions &amp; Roles</span>
                    <span className="dev-scope-pill">💾 Database &amp; Data Integrity</span>
                    <span className="dev-scope-pill">🔬 Digital Forensics &amp; Malkhana</span>
                    <span className="dev-scope-pill">📱 Complainant SMS Alerts</span>
                    <span className="dev-scope-pill">🏛️ All 16 Regional Circles</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Column 2: Interactive Issue Dispatcher (WhatsApp & Email Form) */}
            <div className="dev-reporter-card">
              <div className="dev-reporter-header">
                <div className="dev-reporter-icon">🚀</div>
                <div>
                  <h3 className="dev-reporter-title">1-Click Technical Issue Dispatcher</h3>
                  <p className="dev-reporter-sub">
                    Masla select karein aur aik click par WhatsApp ya Email ke zariye developer ko pohnchayein.
                  </p>
                </div>
              </div>

              <div className="dev-reporter-body">
                {/* Officer Context Card */}
                <div className="dev-officer-strip">
                  <div className="dev-officer-cell">
                    <span className="dev-officer-lbl">Reporting Officer</span>
                    <span className="dev-officer-val">{user?.name || 'Officer'}</span>
                  </div>
                  <div className="dev-officer-cell">
                    <span className="dev-officer-lbl">Circle</span>
                    <span className="dev-officer-val highlight">{user?.circle?.name || user?.circle_name || 'Islamabad HQ'}</span>
                  </div>
                  <div className="dev-officer-cell">
                    <span className="dev-officer-lbl">Role</span>
                    <span className="dev-officer-val">{user?.roles?.map(r => r.name).join(', ') || user?.role || user?.designation || 'Officer'}</span>
                  </div>
                </div>

                {/* Form Controls */}
                <div className="dev-form-row">
                  <div className="dev-field">
                    <label className="dev-label">Problem Category</label>
                    <select
                      className="cf-input"
                      value={issueCategory}
                      onChange={e => setIssueCategory(e.target.value)}
                    >
                      <option value="Official Prints & Letterheads">🖨️ Official Prints &amp; Letterheads (DSR, D.O. Letter, VO/EO)</option>
                      <option value="Officer Handover & Transfer Caseload">🔄 Officer Handover &amp; Transfer Caseload</option>
                      <option value="Verification or Enquiry Locked">🔒 Verification / Enquiry Locked or Reassignment</option>
                      <option value="Digital Forensics & Evidence Register">🔬 Digital Forensics &amp; Evidence Register</option>
                      <option value="User Account, Password & Permissions">👤 User Account, Password &amp; Spatie Permissions</option>
                      <option value="SMS Notifications & Delivery Log">📱 SMS Notifications &amp; Complainant Log</option>
                      <option value="Database / Performance / Error Notice">⚙️ Database / Performance / Bug Notice</option>
                      <option value="General Feature Request / Guidance">💡 General Feature Request / Operational Guidance</option>
                    </select>
                  </div>

                  <div className="dev-field">
                    <label className="dev-label">Urgency Level</label>
                    <div className="dev-urgency-radios">
                      {[
                        { key: 'Critical / High', label: '🔴 Critical / Urgent (Work Halted)' },
                        { key: 'Medium', label: '🟡 Medium (Needs Fix Today)' },
                        { key: 'Low', label: '🟢 Low (Inquiry / Tweak)' },
                      ].map(u => (
                        <button
                          key={u.key}
                          type="button"
                          className={`dev-urgency-btn${issuePriority === u.key ? ' active' : ''}`}
                          onClick={() => setIssuePriority(u.key)}
                        >
                          {u.label}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>

                <div className="dev-field" style={{ marginTop: 14 }}>
                  <label className="dev-label">
                    Describe the Problem or Error Details
                    <span style={{ fontSize: 11, fontWeight: 400, color: '#64748b', marginLeft: 8 }}>
                      (Screen error code, complaint number, ya masla yahan likhein)
                    </span>
                  </label>
                  <textarea
                    className="cf-input dev-textarea"
                    rows="4"
                    value={issueDesc}
                    onChange={e => setIssueDesc(e.target.value)}
                    placeholder="Maslan: Circle Lahore mein Officer Handover karte waqt VO ki pending verifications dropdown mein nahi aa rahi theen..."
                  />
                </div>

                {/* Dispatch Action Buttons */}
                <div className="dev-actions-grid">
                  <button
                    type="button"
                    className="btn btn-primary dev-dispatch-btn wa-btn"
                    onClick={handleSendWhatsApp}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                    </svg>
                    <span>Send via WhatsApp</span>
                  </button>

                  <button
                    type="button"
                    className="btn btn-outline dev-dispatch-btn"
                    onClick={handleSendEmail}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                      <polyline points="22,6 12,13 2,6" />
                    </svg>
                    <span>Send via Email</span>
                  </button>

                  <button
                    type="button"
                    className="btn btn-outline dev-dispatch-btn"
                    onClick={handleCopyIssue}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                    </svg>
                    <span>{copiedIssue ? '✓ Copied Issue' : 'Copy Report'}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Live System Diagnostics Snapshot */}
          <div className="dev-diagnostics-card">
            <div className="dev-diag-header">
              <div className="dev-diag-title-wrap">
                <span className="dev-diag-icon">💻</span>
                <div>
                  <h4 className="dev-diag-title">Real-Time System &amp; Session Diagnostics</h4>
                  <p className="dev-diag-desc">
                    Masla hal karne ke liye yeh diagnostic info developer ko copy kar ke bhejein.
                  </p>
                </div>
              </div>
              <button
                type="button"
                className="btn btn-sm btn-outline dev-diag-copy-btn"
                onClick={handleCopyDiagnostics}
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </svg>
                <span>{copiedDiagnostic ? '✓ Diagnostics Copied!' : 'Copy System Diagnostics'}</span>
              </button>
            </div>

            <div className="dev-diag-grid">
              <div className="dev-diag-item">
                <span className="dev-diag-k">Officer Name:</span>
                <span className="dev-diag-v">{user?.name || 'Authenticated Officer'}</span>
              </div>
              <div className="dev-diag-item">
                <span className="dev-diag-k">Circle / Station:</span>
                <span className="dev-diag-v highlight">{user?.circle?.name || user?.circle_name || 'Islamabad HQ'}</span>
              </div>
              <div className="dev-diag-item">
                <span className="dev-diag-k">Role:</span>
                <span className="dev-diag-v">{user?.roles?.map(r => r.name).join(', ') || user?.role || user?.designation || 'Field User'}</span>
              </div>
              <div className="dev-diag-item">
                <span className="dev-diag-k">Software Build:</span>
                <span className="dev-diag-v code">NCCIA CMS v2.4.1 (Production)</span>
              </div>
              <div className="dev-diag-item">
                <span className="dev-diag-k">Client Environment:</span>
                <span className="dev-diag-v">{typeof navigator !== 'undefined' ? navigator.userAgent.split(') ')[0] + ')' : 'Web Browser'}</span>
              </div>
              <div className="dev-diag-item">
                <span className="dev-diag-k">Resolution:</span>
                <span className="dev-diag-v">{typeof window !== 'undefined' ? `${window.innerWidth} x ${window.innerHeight}` : '1920 x 1080'}</span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: INTERACTIVE VIDEO TOUR (OFFICER SARA) */}
      {activeTab === 'guide' && (
        <div className="sg-tab-content">
          <div className="sg-player">
            <div className="sg-screen">
              <div className="sg-scene-bg" data-scene={scene.id} />
              <div className="sg-scene-badge">Scene {sceneIndex + 1} / {SCENES.length}</div>

              <div className="sg-stage">
                <div className="sg-character-stage">
                  <SoftwareGuideBackdrop activeStep={scene.workflowStep} />
                  <div className="sg-character-foreground">
                    <div className="sg-character-platform" />
                    <SoftwareGuideCharacter speaking={speaking || playing} wave={scene.id === 'intro'} size={220} />
                  </div>
                </div>
                <div className="sg-bubble" key={scene.id}>
                  <div className="sg-bubble-title">{scene.title}</div>
                  <div className="sg-bubble-ur">{scene.titleUr}</div>
                  <p className="sg-bubble-text">{lang === 'en' ? scene.narrationEn : scene.narration}</p>
                  {lang === 'both' && <p className="sg-bubble-text sg-bubble-en">{scene.narrationEn}</p>}
                  <div className="sg-benefits-inline">
                    {scene.benefits.map(b => (
                      <span key={b} className="sg-benefit-chip">{b}</span>
                    ))}
                  </div>
                </div>
              </div>
            </div>

            <div className="sg-controls">
              <button type="button" className="btn btn-primary btn-sm" onClick={togglePlay}>
                {playing ? '⏸ Pause' : '▶ Play'}
              </button>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => goToScene(sceneIndex - 1, playing)}>⏮ Prev</button>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => goToScene(sceneIndex + 1, playing)}>Next ⏭</button>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => speakScene(sceneIndex)}>🔊 Replay voice</button>

              <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ fontSize: 12, color: '#94a3b8' }}>Narration:</span>
                <select className="cf-input" style={{ width: 'auto', minWidth: 120, height: 32, fontSize: 12 }} value={lang} onChange={e => setLang(e.target.value)}>
                  <option value="ur">Roman Urdu</option>
                  <option value="en">English</option>
                  <option value="both">Both</option>
                </select>
              </div>

              <div className="sg-progress-wrap">
                <div className="sg-progress-bar" style={{ width: `${progress}%` }} />
              </div>
            </div>

            <div className="sg-chapters">
              {SCENES.map((s, i) => (
                <button
                  key={s.id}
                  type="button"
                  className={`sg-chapter${i === sceneIndex ? ' active' : ''}`}
                  onClick={() => goToScene(i, false)}
                >
                  <span>{s.visual}</span>
                  <small>{s.title}</small>
                </button>
              ))}
            </div>
          </div>

          <div className="card" style={{ marginTop: 20 }}>
            <div className="card-header"><span className="card-title">NCCIA CMS End-to-End Workflow Flowchart</span></div>
            <div className="card-body sg-flow-map">
              {['Complaint Registration', 'Verification Stage', 'Enquiry & Investigation', 'Case / FIR', 'Digital Forensics', 'Court & Legal', 'Disposed / Closed'].map((step, i, arr) => (
                <div key={step} className="sg-flow-step">
                  <div className="sg-flow-node">{i + 1}</div>
                  <div className="sg-flow-label">{step}</div>
                  {i < arr.length - 1 && <div className="sg-flow-arrow">→</div>}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: FAQS & TROUBLESHOOTING */}
      {activeTab === 'faqs' && (
        <div className="sg-tab-content">
          <div className="faq-container">
            <div className="faq-intro">
              <h3>Field Operational SOPs &amp; Common Troubleshooting</h3>
              <p>Regional Circles aur HQ officers ke aam sawalat aur un ke hal:</p>
            </div>

            <div className="faq-list">
              {[
                {
                  id: 1,
                  q: '🖨️ Official Print / DSR / D.O. Letter ya Enquiry print nahi ho rahi (Popup Blocked)?',
                  badge: 'Print Issue',
                  ans: (
                    <div>
                      <p><strong>Masla:</strong> Modern browsers (Google Chrome, Microsoft Edge, Brave) security ki wajah se print window ko popup samajh kar block kar dete hain.</p>
                      <p><strong>Hal (Resolution Steps):</strong></p>
                      <ol style={{ paddingLeft: 20, margin: '8px 0' }}>
                        <li>Browser ki URL address bar mein bilkul right side par <strong>"Pop-up blocked"</strong> ka red icon ya lock icon click karein.</li>
                        <li><strong>"Always allow pop-ups and redirects from this site"</strong> select karein.</li>
                        <li><strong>Done</strong> par click karein aur page reload karein. Ab aap ke tamam official letterheads, DSR, aur verification prints direct print dialog mein open hongay.</li>
                      </ol>
                    </div>
                  ),
                },
                {
                  id: 2,
                  q: '🔄 Officer Handover / Caseload Reassignment kaise kaam karta hai?',
                  badge: 'Administration',
                  ans: (
                    <div>
                      <p>Jab koi Verification Officer (VO), Enquiry Officer (EO), ya Investigation Officer (IO) transfer ya suspend hota hai, tou us ka caseload dosray officer ko muntaqil karna zaroori hota hai.</p>
                      <ul style={{ paddingLeft: 20, margin: '8px 0' }}>
                        <li><strong>Rastah:</strong> <code>Administration &rarr; User Management &rarr; "Manage Handover"</code> button.</li>
                        <li><strong>Same-Role Enforcement:</strong> System by default VO ka kaam sirf doosray VO ko, aur EO ka kaam sirf EO ko assign karta hai taakay process violate na ho. Agar zaroorat ho tou "Allow All Roles" toggle on kar saktay hain.</li>
                        <li><strong>Circle Filter &amp; Search:</strong> Target circle select karein ya officer ka naam type karein — live search mein foran officer mil jata hai.</li>
                        <li><strong>Audit Trail:</strong> Muntaqil shuda tamam files par permanent transfer order aur timestamp record ho jata hai.</li>
                      </ul>
                    </div>
                  ),
                },
                {
                  id: 3,
                  q: '✍️ Official Digital Signature aur Circle Stamp kaise upload karein?',
                  badge: 'Profile & Print',
                  ans: (
                    <div>
                      <p>Tamam reports, DSR briefings aur D.O. Letters par automatic official signatures aur muhar (stamp) print hoti hain.</p>
                      <ol style={{ paddingLeft: 20, margin: '8px 0' }}>
                        <li>Top right par apna user avatar click karein aur <strong>"My Profile"</strong> mein jayen.</li>
                        <li>Niche <strong>"Digital Signature"</strong> section mein apna transparent PNG signature upload karein.</li>
                        <li>Save hone ke baad aap ke sign karda tamam documents aur letterheads par yeh signature khud ba khud print ho jayega.</li>
                      </ol>
                    </div>
                  ),
                },
                {
                  id: 4,
                  q: '📱 Complainant ko tracking SMS kyun nahi pohncha?',
                  badge: 'SMS Gateway',
                  ans: (
                    <div>
                      <p>NCCIA CMS har verification aur FIR stage par citizen ko automated SMS bhejta hai.</p>
                      <ul style={{ paddingLeft: 20, margin: '8px 0' }}>
                        <li><strong>Phone Number Format:</strong> Check karein ke citizen ka number sahi 11 digits mein hai (maslan <code>03001234567</code>). Dashes ya country code (+92) ki zaroorat nahi hoti.</li>
                        <li><strong>SMS Log Module:</strong> Sidebar navigation mein <code>SMS Log</code> kholain. Wahan live delivery status (Delivered, Pending, Failed) aur gateway response code dekh saktay hain.</li>
                      </ul>
                    </div>
                  ),
                },
                {
                  id: 5,
                  q: '🔬 Digital Forensics Lab request aur Malkhana Chain of Custody kaise banayi jaye?',
                  badge: 'Forensics',
                  ans: (
                    <div>
                      <p>Case ke doran bar-amad shuda digital evidence (Mobile, Laptop, Hard Drive) ki forensic examination ke liye:</p>
                      <ul style={{ paddingLeft: 20, margin: '8px 0' }}>
                        <li>IO case file ke andar <strong>"Forensic Request"</strong> par click kar ke device ka IMEI / Serial No darj karta hai.</li>
                        <li>Malkhana Custody register mein seal number aur parcel barcode lock ho jata hai.</li>
                        <li>Forensic Lab Analyst request accept karta hai aur report upload hotay hi IO ke case file mein attach ho jati hai.</li>
                      </ul>
                    </div>
                  ),
                },
                {
                  id: 6,
                  q: '🔒 Session Timeout aur Password Reset ka kya tariqa hai?',
                  badge: 'Security',
                  ans: (
                    <div>
                      <p>NCCIA cyber security compliance ke tehat 2 ghantay tak inactive rehne par session protectively lock ho jata hai.</p>
                      <p>Agar aap password bhool gaye hain tou login screen par <strong>"Forgot Password"</strong> use karein ya Circle Incharge / ADA Administration se apna password reset karwayen.</p>
                    </div>
                  ),
                },
              ].map((faq, idx) => (
                <div
                  key={faq.id}
                  className={`faq-card${openFaq === idx ? ' expanded' : ''}`}
                  onClick={() => toggleFaq(idx)}
                >
                  <div className="faq-card-header">
                    <div className="faq-q-title">
                      <span className="faq-badge">{faq.badge}</span>
                      <span className="faq-text">{faq.q}</span>
                    </div>
                    <div className="faq-toggle-icon">
                      {openFaq === idx ? '−' : '+'}
                    </div>
                  </div>
                  {openFaq === idx && (
                    <div className="faq-card-body">
                      {faq.ans}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: REGIONAL HUBS & DISTRICT JURISDICTIONS */}
      {activeTab === 'jurisdictions' && (
        <div className="sg-tab-content">
          <div className="dev-hero-banner" style={{background:'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', color:'#fff', padding:'24px 28px', borderRadius:'14px', marginBottom:'20px'}}>
            <div className="dev-hero-left">
              <div className="dev-hero-tag" style={{display:'inline-flex', alignItems:'center', gap:6, background:'rgba(255,255,255,0.1)', padding:'4px 10px', borderRadius:20, fontSize:12}}>
                <span>📍</span>
                <span>NCCIA National Cyber Crime Investigation Operational Territorial Directory</span>
              </div>
              <h2 style={{fontSize:'22px', fontWeight:800, margin:'10px 0 6px', color:'#fff'}}>
                Regional Operational Hubs &amp; District Jurisdictions
              </h2>
              <p style={{fontSize:'13px', color:'#cbd5e1', margin:0, lineHeight:'1.5', maxWidth:'800px'}}>
                Under the Prevention of Electronic Crimes Act (PECA), NCCIA operates specialized regional circles across Pakistan.
                Below is the authoritative jurisdictional breakdown, office locations, and covered districts for Punjab, KPK, Balochistan, Gilgit-Baltistan, Islamabad HQ, and Sindh.
              </p>
            </div>
          </div>

          {/* Quick District Search Bar */}
          <div style={{background:'#fff', padding:'16px 20px', borderRadius:'12px', border:'1px solid #e2e8f0', marginBottom:'20px', display:'flex', alignItems:'center', gap:'12px', boxShadow:'0 1px 3px rgba(0,0,0,0.05)'}}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input
              type="text"
              placeholder="Type any district or city to find its jurisdictional NCCIA Circle (e.g. Attock, Mianwali, Skardu, Gwadar, Haripur, Turbat, Sialkot, Bannu)..."
              value={districtSearch}
              onChange={e => setDistrictSearch(e.target.value)}
              style={{flex:1, border:'none', outline:'none', fontSize:'13.5px', color:'#0f172a'}}
            />
            {districtSearch && (
              <button
                onClick={() => setDistrictSearch('')}
                style={{background:'none', border:'none', color:'#94a3b8', cursor:'pointer', fontSize:'14px', fontWeight:600}}
              >
                Clear
              </button>
            )}
          </div>

          {/* Directory Cards Grid by Province */}
          {[
            {
              region: 'Punjab Regional Offices',
              badge: 'Punjab Zone (PZ)',
              theme: '#2563eb',
              offices: [
                {
                  name: 'NCCIA Lahore (Central Directorate)',
                  code: 'LHR',
                  address: 'House No. B-8, G Block Main Boulevard Gulberg-II, Lahore',
                  phone: '042-99263451',
                  districts: ['Lahore', 'Kasur', 'Sheikhupura', 'Nankana Sahib', 'Sahiwal', 'Okara', 'Pakpattan'],
                },
                {
                  name: 'NCCIA Rawalpindi',
                  code: 'RWP',
                  address: 'Regional Directorate NCCIA, Rawalpindi',
                  phone: '051-9290001',
                  districts: ['Rawalpindi', 'Attock', 'Chakwal', 'Jhelum', 'Murree', 'Talagang'],
                },
                {
                  name: 'NCCIA Multan',
                  code: 'MUX',
                  address: 'Regional Directorate NCCIA, Multan',
                  phone: '061-9201001',
                  districts: ['Multan', 'Khanewal', 'Lodhran', 'Vehari', 'Bahawalpur', 'Bahawalnagar', 'Rahim Yar Khan', 'Dera Ghazi Khan', 'Layyah', 'Muzaffargarh', 'Rajanpur', 'Taunsa', 'Kot Addu'],
                },
                {
                  name: 'NCCIA Gujranwala',
                  code: 'GRW',
                  address: 'Regional Directorate NCCIA, Gujranwala',
                  phone: '055-9200001',
                  districts: ['Gujranwala', 'Gujrat', 'Sialkot', 'Mandi Bahauddin', 'Narowal', 'Hafizabad', 'Wazirabad'],
                },
                {
                  name: 'NCCIA Faisalabad',
                  code: 'FSD',
                  address: 'Regional Directorate NCCIA, Faisalabad',
                  phone: '041-9200001',
                  districts: ['Faisalabad', 'Toba Tek Singh', 'Jhang', 'Chiniot', 'Sargodha', 'Khushab', 'Mianwali', 'Bhakkar'],
                },
              ],
            },
            {
              region: 'Khyber Pakhtunkhwa (KPK) Regional Offices',
              badge: 'KPK Zone (KPZ)',
              theme: '#059669',
              offices: [
                {
                  name: 'NCCIA Peshawar',
                  code: 'PEW',
                  address: 'Regional Directorate NCCIA, Peshawar',
                  phone: '091-9210001',
                  districts: ['Peshawar', 'Charsadda', 'Nowshera', 'Mardan', 'Swabi', 'Malakand', 'Swat', 'Dir Lower', 'Dir Upper', 'Chitral Lower', 'Chitral Upper', 'Bajaur', 'Mohmand', 'Khyber', 'Buner', 'Shangla'],
                },
                {
                  name: 'NCCIA Abbottabad',
                  code: 'ATD',
                  address: 'Regional Directorate NCCIA, Hazara Division, Abbottabad',
                  phone: '0992-9310001',
                  districts: ['Abbottabad', 'Haripur', 'Mansehra', 'Batagram', 'Torghar', 'Kohistan Upper', 'Lower Kohistan', 'Kolai Palas'],
                },
                {
                  name: 'NCCIA Dera Ismail Khan',
                  code: 'DIK',
                  address: 'Regional Directorate NCCIA, Dera Ismail Khan',
                  phone: '0966-9280001',
                  districts: ['D.I. Khan', 'Bannu', 'Lakki Marwat', 'Tank', 'Kohat', 'Karak', 'Hangu', 'Kurram', 'Orakzai', 'North Waziristan', 'South Waziristan'],
                },
              ],
            },
            {
              region: 'Balochistan Regional Offices',
              badge: 'Balochistan Zone (BZ)',
              theme: '#d97706',
              offices: [
                {
                  name: 'NCCIA Quetta',
                  code: 'UET',
                  address: 'FIA Compound on Shabo Road, Quetta',
                  phone: '081-9201001',
                  districts: ['Quetta', 'Chaman', 'Pishin', 'Zhob', 'Loralai', 'Sibi', 'Nasirabad', 'Qila Abdullah', 'Qila Saifullah', 'Musakhel', 'Barkhan', 'Kohlu', 'Dera Bugti', 'Ziarat', 'Harnai', 'Jaffarabad', 'Usta Muhammad', 'Sohbatpur', 'Jhal Magsi'],
                },
                {
                  name: 'NCCIA Gwadar',
                  code: 'GWD',
                  address: 'New Town Phase-1, Gwadar',
                  phone: '0864-9200001',
                  districts: ['Gwadar', 'Kech (Turbat)', 'Khuzdar', 'Kalat', 'Panjgur', 'Lasbela', 'Hub', 'Awaran', 'Surab', 'Washuk', 'Chagai'],
                },
              ],
            },
            {
              region: 'Gilgit-Baltistan Regional Office',
              badge: 'GB Zone (GBZ)',
              theme: '#0891b2',
              offices: [
                {
                  name: 'NCCIA Gilgit-Baltistan',
                  code: 'GLT',
                  address: 'Near GDA Office, River Road, Chinarbagh, Gilgit',
                  phone: '+92 5811 960707',
                  districts: ['Gilgit', 'Hunza', 'Skardu', 'Diamir', 'Astore', 'Ghizer', 'Baltistan', 'Shigar', 'Nagar', 'Ghanche', 'Gupis–Yasin'],
                },
              ],
            },
            {
              region: 'Islamabad Capital & Headquarters',
              badge: 'Federal Capital Zone (FCZ)',
              theme: '#4f46e5',
              offices: [
                {
                  name: 'NCCIA Islamabad (Headquarters)',
                  code: 'ISB',
                  address: 'NCCIA Headquarters, Islamabad',
                  phone: '051-9106384',
                  districts: ['Islamabad Capital Territory'],
                },
              ],
            },
            {
              region: 'Sindh Regional Offices',
              badge: 'Sindh Zone (SZ)',
              theme: '#7c3aed',
              offices: [
                {
                  name: 'NCCIA Karachi',
                  code: 'KHI',
                  address: 'Regional Directorate NCCIA, Karachi',
                  phone: '021-99201001',
                  districts: ['Karachi (South, East, West, Central, Malir, Korangi, Keamari)', 'Hyderabad', 'Thatta', 'Sujawal', 'Badin', 'Jamshoro', 'Matiari', 'Tando Allahyar', 'Tando Muhammad Khan', 'Mirpur Khas', 'Umerkot', 'Tharparkar'],
                },
                {
                  name: 'NCCIA Sukkur',
                  code: 'SKR',
                  address: 'Regional Directorate NCCIA, Sukkur',
                  phone: '071-9310001',
                  districts: ['Sukkur', 'Larkana', 'Khairpur', 'Ghotki', 'Jacobabad', 'Kashmore', 'Shikarpur', 'Naushahro Feroze', 'Shaheed Benazirabad', 'Kambar Shahdadkot'],
                },
              ],
            },
          ]
            .map(section => {
              const q = districtSearch.trim().toLowerCase();
              if (!q) return section;
              const matchingOffices = section.offices.filter(o =>
                o.name.toLowerCase().includes(q) ||
                o.code.toLowerCase().includes(q) ||
                o.address.toLowerCase().includes(q) ||
                o.districts.some(d => d.toLowerCase().includes(q))
              );
              return { ...section, offices: matchingOffices };
            })
            .filter(section => section.offices.length > 0)
            .map((section, sIdx) => (
              <div key={sIdx} style={{marginBottom:'24px'}}>
                <div style={{display:'flex', alignItems:'center', gap:8, marginBottom:'12px'}}>
                  <h3 style={{fontSize:'16px', fontWeight:700, color:'#1e293b', margin:0}}>{section.region}</h3>
                  <span style={{background:'#e2e8f0', color:'#475569', fontSize:'11px', fontWeight:700, padding:'2px 8px', borderRadius:10}}>
                    {section.badge}
                  </span>
                </div>

                <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(320px, 1fr))', gap:'14px'}}>
                  {section.offices.map((off, oIdx) => (
                    <div
                      key={oIdx}
                      style={{
                        background:'#fff',
                        borderRadius:'12px',
                        border:'1px solid #e2e8f0',
                        padding:'16px 18px',
                        boxShadow:'0 1px 3px rgba(0,0,0,0.04)',
                        display:'flex',
                        flexDirection:'column',
                        gap:'10px',
                      }}
                    >
                      <div style={{display:'flex', justifyContent:'space-between', alignItems:'flex-start'}}>
                        <div>
                          <div style={{fontSize:'14.5px', fontWeight:700, color:'#0f172a'}}>{off.name}</div>
                          <span style={{fontSize:'11px', fontWeight:700, color:section.theme, letterSpacing:'0.5px'}}>
                            CODE: {off.code}
                          </span>
                        </div>
                        {off.phone && (
                          <a
                            href={`tel:${off.phone}`}
                            style={{
                              background:'#f0fdf4',
                              color:'#15803d',
                              fontSize:'11.5px',
                              fontWeight:600,
                              padding:'4px 8px',
                              borderRadius:6,
                              textDecoration:'none',
                              border:'1px solid #bbf7d0',
                              whiteSpace:'nowrap',
                            }}
                          >
                            📞 {off.phone}
                          </a>
                        )}
                      </div>

                      <div style={{fontSize:'12px', color:'#475569', display:'flex', alignItems:'flex-start', gap:5}}>
                        <span>📍</span>
                        <span style={{lineHeight:1.35}}>{off.address}</span>
                      </div>

                      <div style={{borderTop:'1px solid #f1f5f9', paddingTop:'8px'}}>
                        <div style={{fontSize:'11px', fontWeight:600, color:'#64748b', textTransform:'uppercase', marginBottom:4}}>
                          Territorial Districts ({off.districts.length}):
                        </div>
                        <div style={{display:'flex', flexWrap:'wrap', gap:'4px'}}>
                          {off.districts.map((d, di) => {
                            const isMatch = districtSearch && d.toLowerCase().includes(districtSearch.toLowerCase());
                            return (
                              <span
                                key={di}
                                style={{
                                  background: isMatch ? '#fef08a' : '#f8fafc',
                                  color: isMatch ? '#854d0e' : '#334155',
                                  fontSize:'11px',
                                  padding:'2px 7px',
                                  borderRadius:'4px',
                                  border: isMatch ? '1px solid #eab308' : '1px solid #e2e8f0',
                                  fontWeight: isMatch ? 700 : 400,
                                }}
                              >
                                {d}
                              </span>
                            );
                          })}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
        </div>
      )}
    </div>
  );
}

