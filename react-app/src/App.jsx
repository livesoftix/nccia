import { useEffect } from 'react';
import { BrowserRouter, HashRouter, Routes, Route, Navigate, useSearchParams } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Layout from './components/Layout';
import ForensicLayout from './components/ForensicLayout';
import Login from './pages/Login';
import ForensicLogin from './pages/ForensicLogin';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import Dashboard from './pages/Dashboard';
import ForensicDashboard from './pages/ForensicDashboard';
import ForensicUsers from './pages/ForensicUsers';
import ForensicRequests from './pages/ForensicRequests';
import ForensicRequestDetail from './pages/ForensicRequestDetail';
import Profile from './pages/Profile';
import MfaSettings from './components/MfaSettings';
import Analytics from './pages/Analytics';
import DepartmentProgress from './pages/DepartmentProgress';
import Complaints from './pages/Complaints';
import ComplaintForm from './pages/ComplaintForm';
import ComplaintPdfImport from './pages/ComplaintPdfImport';
import OcrImports from './pages/OcrImports';
import AdpImport from './pages/AdpImport';
import Verifications from './pages/Verifications';
import VerificationForm from './pages/VerificationForm';
import VerificationReports from './pages/VerificationReports';
import VerificationReportForm from './pages/VerificationReportForm';
import Enquiries from './pages/Enquiries';
import EnquiryForm from './pages/EnquiryForm';
import InvestigationOfficers from './pages/InvestigationOfficers';
import OfficerForm from './pages/OfficerForm';
import OffenceTypes from './pages/OffenceTypes';
import Cases from './pages/Cases';
import CaseForm from './pages/CaseForm';
import Users from './pages/Users';
import UserForm from './pages/UserForm';
import LoginHistory from './pages/LoginHistory';
import Circles from './pages/Circles';
import CourtCases from './pages/CourtCases';
import Laws from './pages/Laws';
import Rules from './pages/Rules';
import SOP from './pages/SOP';
import UserManual from './pages/UserManual';
import SoftwareGuide from './pages/SoftwareGuide';
import Chat from './pages/Chat';
import SmsLog from './pages/SmsLog';
import DsrReports from './pages/DsrReports';
import DsrReportForm from './pages/DsrReportForm';
import DoLetters from './pages/DoLetters';
import DoLetterForm from './pages/DoLetterForm';
import ApprovalSettings from './pages/ApprovalSettings';
import WarrantRequests from './pages/WarrantRequests';
import Proclamations from './pages/Proclamations';
import { canAssignVerification, canCreateComplaint, canCreateDirectVerification, canView, isForensicUser, canViewApprovalSettings, canUseWarrantRequests, canUseProclamations } from './utils/permissions';

const MAIN_ROLES = [
  'admin', 'circle_incharge', 'operator', 'verification_officer',
  'enquiry_officer', 'investigation_officer', 'moharrar', 'reader_branch',
  'ad_legal', 'dd_legal', 'additional_director', 'ad_administration', 'director_general',
];

function hasMainAccess(user) {
  return !!user?.roles?.some(r => MAIN_ROLES.includes(r.name || r));
}

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  if (!user) return <Navigate to="/login" />;
  if (!hasMainAccess(user) && isForensicUser(user)) return <Navigate to="/forensic" />;
  return children;
}

function PublicRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  return user ? <Navigate to="/" /> : children;
}

function ForensicProtectedRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  if (!user) return <Navigate to="/forensic/login" />;
  if (!isForensicUser(user)) return <Navigate to="/" />;
  return children;
}

function ForensicPublicRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  if (user && isForensicUser(user)) return <Navigate to="/forensic" />;
  return user && !isForensicUser(user) ? <Navigate to="/" /> : children;
}

function FeatureRoute({ feature, children, fallback = '/' }) {
  const { user, loading } = useAuth();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  if (!user) return <Navigate to="/login" />;
  if (!canView(feature, user)) return <Navigate to={fallback} replace />;
  return children;
}

function CreateComplaintRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  if (!user) return <Navigate to="/login" />;
  if (!canCreateComplaint(user)) return <Navigate to="/verifications" replace />;
  return children;
}

function AssignVerificationRoute({ children }) {
  const { user, loading } = useAuth();
  const [searchParams] = useSearchParams();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  if (!user) return <Navigate to="/login" />;
  const isDirect = searchParams.get('direct') === '1';
  if (isDirect && canCreateDirectVerification(user)) return children;
  if (!canAssignVerification(user)) return <Navigate to="/verifications" replace />;
  return children;
}

function GuardRoute({ allow, children, fallback = '/' }) {
  const { user, loading } = useAuth();
  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;
  if (!user) return <Navigate to="/login" />;
  if (!allow(user)) return <Navigate to={fallback} replace />;
  return children;
}

function AppRoutes() {
  const { user, loading } = useAuth();
  if (!loading && user?.mfa_enrollment_required) {
    return <main className="page-content" style={{maxWidth:760,margin:'40px auto'}}><h1>Protect your account</h1><MfaSettings enrollment /></main>;
  }
  return (
    <Routes>
      <Route path="/login" element={<PublicRoute><Login /></PublicRoute>} />
      <Route path="/forensic/login" element={<ForensicPublicRoute><ForensicLogin /></ForensicPublicRoute>} />
      <Route path="/forgot-password" element={<PublicRoute><ForgotPassword /></PublicRoute>} />
      <Route path="/reset-password" element={<PublicRoute><ResetPassword /></PublicRoute>} />
      <Route element={<ForensicProtectedRoute><ForensicLayout /></ForensicProtectedRoute>}>
        <Route path="forensic" element={<ForensicDashboard />} />
        <Route path="forensic/requests" element={<ForensicRequests />} />
        <Route path="forensic/requests/:id" element={<ForensicRequestDetail />} />
        <Route path="forensic/users" element={<ForensicUsers />} />
        <Route path="forensic/profile" element={<Profile />} />
        <Route path="forensic/help-support" element={<SoftwareGuide />} />
      </Route>
      <Route element={<ProtectedRoute><Layout /></ProtectedRoute>}>
        <Route index element={<Dashboard />} />
        <Route path="analytics" element={<FeatureRoute feature="analytics"><Analytics /></FeatureRoute>} />
        <Route path="department-progress" element={<FeatureRoute feature="department_progress"><DepartmentProgress /></FeatureRoute>} />
        <Route path="complaints" element={<FeatureRoute feature="complaints"><Complaints /></FeatureRoute>} />
        <Route path="complaints/create" element={<CreateComplaintRoute><ComplaintForm /></CreateComplaintRoute>} />
        <Route path="complaints/import-pdf" element={<CreateComplaintRoute><ComplaintPdfImport /></CreateComplaintRoute>} />
        <Route path="complaints/ocr-imports" element={<CreateComplaintRoute><OcrImports /></CreateComplaintRoute>} />
        <Route path="complaints/adp" element={<CreateComplaintRoute><AdpImport /></CreateComplaintRoute>} />
        <Route path="complaints/:id/edit" element={<FeatureRoute feature="complaints"><ComplaintForm /></FeatureRoute>} />
        <Route path="verifications" element={<FeatureRoute feature="verifications"><Verifications /></FeatureRoute>} />
        <Route path="verifications/create" element={<AssignVerificationRoute><VerificationForm /></AssignVerificationRoute>} />
        <Route path="verifications/:id/edit" element={<FeatureRoute feature="verifications"><VerificationForm /></FeatureRoute>} />
        <Route path="verifications/reports" element={<FeatureRoute feature="reports"><VerificationReports /></FeatureRoute>} />
        <Route path="verifications/reports/create" element={<FeatureRoute feature="reports"><VerificationReportForm /></FeatureRoute>} />
        <Route path="verifications/reports/:id/edit" element={<FeatureRoute feature="reports"><VerificationReportForm /></FeatureRoute>} />
        <Route path="enquiries" element={<FeatureRoute feature="enquiries"><Enquiries /></FeatureRoute>} />
        <Route path="enquiries/create" element={<FeatureRoute feature="enquiries"><EnquiryForm /></FeatureRoute>} />
        <Route path="enquiries/:id/edit" element={<FeatureRoute feature="enquiries"><EnquiryForm /></FeatureRoute>} />
        <Route path="messages" element={<Chat />} />
        <Route path="investigation-officers" element={<FeatureRoute feature="io_records"><InvestigationOfficers /></FeatureRoute>} />
        <Route path="investigation-officers/create" element={<FeatureRoute feature="io_records"><OfficerForm /></FeatureRoute>} />
        <Route path="investigation-officers/:id/edit" element={<FeatureRoute feature="io_records"><OfficerForm /></FeatureRoute>} />
        <Route path="offence-types" element={<FeatureRoute feature="offence_types"><OffenceTypes /></FeatureRoute>} />
        <Route path="cases" element={<FeatureRoute feature="dac_cases"><Cases /></FeatureRoute>} />
        <Route path="cases/create" element={<FeatureRoute feature="dac_cases"><CaseForm /></FeatureRoute>} />
        <Route path="cases/:id/edit" element={<FeatureRoute feature="dac_cases"><CaseForm /></FeatureRoute>} />
        <Route path="profile" element={<Profile />} />
        <Route path="users" element={<FeatureRoute feature="users"><Users /></FeatureRoute>} />
        <Route path="users/create" element={<FeatureRoute feature="users"><UserForm /></FeatureRoute>} />
        <Route path="users/:id/edit" element={<FeatureRoute feature="users"><UserForm /></FeatureRoute>} />
        <Route path="login-history" element={<FeatureRoute feature="login_history"><LoginHistory /></FeatureRoute>} />
        <Route path="circles" element={<FeatureRoute feature="circles"><Circles /></FeatureRoute>} />
        <Route path="court-cases" element={<FeatureRoute feature="court_cases"><CourtCases /></FeatureRoute>} />
        <Route path="laws" element={<FeatureRoute feature="reference"><Laws /></FeatureRoute>} />
        <Route path="laws/create" element={<FeatureRoute feature="reference"><Laws /></FeatureRoute>} />
        <Route path="laws/:id/edit" element={<FeatureRoute feature="reference"><Laws /></FeatureRoute>} />
        <Route path="rules" element={<FeatureRoute feature="reference"><Rules /></FeatureRoute>} />
        <Route path="rules/create" element={<FeatureRoute feature="reference"><Rules /></FeatureRoute>} />
        <Route path="rules/:id/edit" element={<FeatureRoute feature="reference"><Rules /></FeatureRoute>} />
        <Route path="sops" element={<FeatureRoute feature="reference"><SOP /></FeatureRoute>} />
        <Route path="sops/create" element={<FeatureRoute feature="reference"><SOP /></FeatureRoute>} />
        <Route path="sops/:id/edit" element={<FeatureRoute feature="reference"><SOP /></FeatureRoute>} />
        <Route path="user-manuals" element={<FeatureRoute feature="reference"><UserManual /></FeatureRoute>} />
        <Route path="user-manuals/create" element={<FeatureRoute feature="reference"><UserManual /></FeatureRoute>} />
        <Route path="user-manuals/:id/edit" element={<FeatureRoute feature="reference"><UserManual /></FeatureRoute>} />
        <Route path="software-guide" element={<SoftwareGuide />} />
        <Route path="help-support" element={<SoftwareGuide />} />
        <Route path="sms" element={<FeatureRoute feature="sms_logs"><SmsLog /></FeatureRoute>} />
        <Route path="dsr-reports" element={<FeatureRoute feature="dsr_reports"><DsrReports /></FeatureRoute>} />
        <Route path="dsr-reports/create" element={<FeatureRoute feature="dsr_reports"><DsrReportForm /></FeatureRoute>} />
        <Route path="dsr-reports/:id" element={<FeatureRoute feature="dsr_reports"><DsrReportForm /></FeatureRoute>} />
        <Route path="do-letters" element={<FeatureRoute feature="do_letters"><DoLetters /></FeatureRoute>} />
        <Route path="do-letters/create" element={<FeatureRoute feature="do_letters"><DoLetterForm /></FeatureRoute>} />
        <Route path="do-letters/:id" element={<FeatureRoute feature="do_letters"><DoLetterForm /></FeatureRoute>} />
        <Route path="approval-settings" element={<GuardRoute allow={canViewApprovalSettings}><ApprovalSettings /></GuardRoute>} />
        <Route path="warrant-requests" element={<GuardRoute allow={canUseWarrantRequests}><WarrantRequests /></GuardRoute>} />
        <Route path="proclamations" element={<GuardRoute allow={canUseProclamations}><Proclamations /></GuardRoute>} />
      </Route>

      {/* Fallback routes for mobile webview and unmatched paths */}
      <Route path="/index.html" element={<Navigate to="/" replace />} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

const isNativePlatform = typeof window !== 'undefined' && (
  window.Capacitor?.isNativePlatform?.() ||
  window.location.protocol === 'capacitor:' ||
  window.location.protocol === 'file:' ||
  (window.location.hostname === 'localhost' && !import.meta.env.DEV) ||
  window.location.pathname.endsWith('.html')
);

export default function App() {
  useEffect(() => {
    // 1. Strictly block right click context menu
    const handleContextMenu = (e) => {
      e.preventDefault();
      return false;
    };

    // 2. Strictly block DevTools & inspect shortcuts
    const handleKeyDown = (e) => {
      // F12
      if (e.keyCode === 123 || e.key === 'F12') {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
      // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
      if (e.ctrlKey && e.shiftKey && ['I', 'i', 'J', 'j', 'C', 'c'].includes(e.key)) {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
      // Ctrl+U (View Source) or Ctrl+S (Save Page)
      if (e.ctrlKey && ['U', 'u', 'S', 's'].includes(e.key)) {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
    };

    document.addEventListener('contextmenu', handleContextMenu, { capture: true });
    document.addEventListener('keydown', handleKeyDown, { capture: true });

    return () => {
      document.removeEventListener('contextmenu', handleContextMenu, { capture: true });
      document.removeEventListener('keydown', handleKeyDown, { capture: true });
    };
  }, []);

  const Router = isNativePlatform ? HashRouter : BrowserRouter;
  return (
    <Router>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </Router>
  );
}
