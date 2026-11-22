import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { SiteProvider } from './site';
import App from './App';
import './site.css';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter basename={(import.meta.env.BASE_URL || '/').replace(/\/$/, '')}>
      <SiteProvider><App /></SiteProvider>
    </BrowserRouter>
  </StrictMode>,
);
// the ERP used to register a service worker at "/" — fetching /sw.js again replaces it with a self-removing one
if ('serviceWorker' in navigator) navigator.serviceWorker.getRegistrations().then((rs) => rs.forEach((r) => { if (!/\/erp\/$/.test(r.scope)) r.update().catch(() => r.unregister()); })).catch(() => {});
