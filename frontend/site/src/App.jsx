import { lazy, Suspense, useEffect } from 'react';
import { Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { useSite } from './site';
import { Header, Footer, WhatsAppFab, MobileCta, Spinner } from './components';
import { BookModal, ReportModal } from './modals';
import { Cursor, useMotion } from './motion';
import Home from './pages/Home';
import Services from './pages/Services';
import QTrack from './pages/QTrack';
import Blog from './pages/Blog';
import Article from './pages/Article';
import About from './pages/About';
import NotFound, { Verify } from './pages/NotFound';

const Admin = lazy(() => import('./admin/Admin'));

function Public() {
  const { C, book, report, setReport } = useSite();
  const loc = useLocation();
  useEffect(() => { if (new URLSearchParams(loc.search).get('preview') === 'qc-report') setReport(true); }, [loc.search]); // eslint-disable-line
  useEffect(() => { if (!loc.hash) window.scrollTo(0, 0); }, [loc.pathname]);
  useMotion(loc.pathname);
  if (!C) return <Spinner />;
  return <>
    <Cursor />
    <Header />
    <main key={loc.pathname} className="page-in"><Outlet /></main>
    <Footer />
    <WhatsAppFab />
    <MobileCta />
    {book && <BookModal />}
    {report && <ReportModal />}
  </>;
}
function BookRoute() { const { openBook } = useSite(); useEffect(() => { openBook(); }, []); return <Home />; } // eslint-disable-line

export default function App() {
  return <Routes>
    <Route path="/admin/*" element={<Suspense fallback={<Spinner />}><Admin /></Suspense>} />
    <Route element={<Public />}>
      <Route index element={<Home />} />
      <Route path="services" element={<Services />} />
      <Route path="q-track" element={<QTrack />} />
      <Route path="blog" element={<Blog />} />
      <Route path="blog/:slug" element={<Article />} />
      <Route path="about" element={<About />} />
      <Route path="verify" element={<Verify />} />
      <Route path="book" element={<BookRoute />} />
      <Route path="*" element={<NotFound />} />
    </Route>
  </Routes>;
}
