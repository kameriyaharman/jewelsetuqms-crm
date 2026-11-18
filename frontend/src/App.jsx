import { Navigate, Route, Routes } from 'react-router-dom';
import { homeFor, useAuth } from './app/auth';
import { EShell, MShell, Shell } from './app/Layout';
import { Spinner } from './app/ui';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Clients from './pages/Clients';
import Vendors from './pages/Vendors';
import Mapping from './pages/Mapping';
import Requests from './pages/Requests';
import RequestView from './pages/RequestView';
import RequestForm from './pages/RequestForm';
import Import from './pages/Import';
import Jobs from './pages/Jobs';
import Schedule from './pages/Schedule';
import Review from './pages/Review';
import ReviewItem from './pages/ReviewItem';
import Reports from './pages/Reports';
import ReportView from './pages/ReportView';
import Approvals from './pages/Approvals';
import Analytics from './pages/Analytics';
import Team from './pages/Team';
import Settings from './pages/Settings';
import { ClientDash, VendorDash } from './pages/PortalDash';
import { Notifications, Profile } from './pages/Account';
import { AppHome, AppJob, AppHistory, AppAlerts, AppProfile } from './pages/Inspector';
import AppInspect from './pages/AppInspect';
import AppNewJob from './pages/AppNewJob';

const INT = ['super_admin', 'back_office', 'qc_manager'];
function Guard({ roles, children }) {
  const { me } = useAuth();
  if (!roles.includes(me.user.role)) return <Navigate to={homeFor(me.user.role)} replace />;
  return children;
}
const G = (roles, el) => <Guard roles={roles}>{el}</Guard>;

export default function App() {
  const { me } = useAuth();
  if (!me) return <Spinner text="Loading Q-TRACK…" />;
  if (!me.auth) return <Routes><Route path="*" element={<Login />} /></Routes>;
  const role = me.user.role;
  return <Routes>
    <Route path="/login" element={<Navigate to={homeFor(role)} replace />} />
    <Route path="/" element={<Navigate to={homeFor(role)} replace />} />
    <Route element={G(['inspector'], <MShell />)}>
      <Route path="/app" element={<AppHome />} />
      <Route path="/app/job/:id" element={<AppJob />} />
      <Route path="/app/inspect/:id" element={<AppInspect />} />
      <Route path="/app/new" element={<AppNewJob />} />
      <Route path="/app/job/:id/add" element={<AppNewJob />} />
      <Route path="/app/history" element={<AppHistory />} />
      <Route path="/app/alerts" element={<AppAlerts />} />
      <Route path="/app/profile" element={<AppProfile />} />
    </Route>
    <Route element={G(['super_admin'], <EShell />)}>
      <Route path="/edit/:id" element={<AppInspect />} />
    </Route>
    <Route element={role === 'inspector' ? <Navigate to="/app" replace /> : <Shell />}>
      <Route path="/dashboard" element={G(INT, <Dashboard />)} />
      <Route path="/client" element={G(['client'], <ClientDash />)} />
      <Route path="/vendor" element={G(['vendor'], <VendorDash />)} />
      <Route path="/clients" element={G(['super_admin', 'back_office'], <Clients />)} />
      <Route path="/vendors" element={G([...INT, 'client'], <Vendors />)} />
      <Route path="/mapping" element={G(['super_admin'], <Mapping />)} />
      <Route path="/requests" element={G([...INT, 'client', 'vendor'], <Requests />)} />
      <Route path="/request/new" element={G(['super_admin', 'back_office', 'client', 'vendor'], <RequestForm />)} />
      <Route path="/request/:id/edit" element={G(['super_admin', 'back_office', 'client', 'vendor'], <RequestForm />)} />
      <Route path="/request/:id" element={G([...INT, 'client', 'vendor'], <RequestView />)} />
      <Route path="/import" element={G(['super_admin', 'back_office', 'vendor'], <Import />)} />
      <Route path="/jobs" element={G(INT, <Jobs />)} />
      <Route path="/schedule" element={G([...INT, 'client', 'vendor'], <Schedule />)} />
      <Route path="/review" element={G(['super_admin', 'qc_manager'], <Review />)} />
      <Route path="/review/:id" element={G(['super_admin', 'qc_manager'], <ReviewItem />)} />
      <Route path="/reports" element={G([...INT, 'client', 'vendor'], <Reports />)} />
      <Route path="/report/:id" element={G([...INT, 'client', 'vendor'], <ReportView />)} />
      <Route path="/approvals" element={G(['client', 'vendor', 'super_admin', 'qc_manager'], <Approvals />)} />
      <Route path="/analytics" element={G([...INT, 'client'], <Analytics />)} />
      <Route path="/team" element={G(['super_admin', 'qc_manager', 'back_office'], <Team />)} />
      <Route path="/settings" element={G(INT, <Settings />)} />
      <Route path="/notifications" element={<Notifications />} />
      <Route path="/profile" element={<Profile />} />
      <Route path="*" element={<Navigate to={homeFor(role)} replace />} />
    </Route>
  </Routes>;
}
