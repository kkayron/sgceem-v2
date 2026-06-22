import { useState, useEffect } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Navbar from './Navbar';

export default function Layout({ userData }) {
  const [isSidebarMini, setIsSidebarMini] = useState(false);
  const [isSidebarHovered, setIsSidebarHovered] = useState(false);
  const [isNavOpen, setIsNavOpen] = useState(false);
  const [isDarkMode, setIsDarkMode] = useState(localStorage.getItem('sgceem_dark') === '1');

  useEffect(() => {
    if (isDarkMode) {
      document.body.classList.add('dark-mode');
      localStorage.setItem('sgceem_dark', '1');
    } else {
      document.body.classList.remove('dark-mode');
      localStorage.setItem('sgceem_dark', '0');
    }
  }, [isDarkMode]);

  const toggleSidebarDesktop = () => {
    setIsSidebarMini(!isSidebarMini);
    setIsSidebarHovered(false); // reseta o hover
    setTimeout(() => window.dispatchEvent(new Event('resize')), 300);
  };

  const toggleSidebarMobile = () => {
    const newState = !isNavOpen;
    setIsNavOpen(newState);
    if (newState) {
      document.documentElement.classList.add('nav_open');
    } else {
      document.documentElement.classList.remove('nav_open');
    }
  };

  const handleMouseEnter = () => {
    if (isSidebarMini) setIsSidebarHovered(true);
  };

  const handleMouseLeave = () => {
    if (isSidebarMini) setIsSidebarHovered(false);
  };

  return (
    <div className={`wrapper ${isSidebarMini ? 'sidebar_minimize' : ''} ${isSidebarMini && isSidebarHovered ? 'sidebar_minimize_hover' : ''}`}>
      <Sidebar 
        userData={userData} 
        toggleSidebarDesktop={toggleSidebarDesktop} 
        isSidebarMini={isSidebarMini}
        onMouseEnter={handleMouseEnter}
        onMouseLeave={handleMouseLeave}
      />
      
      <div className="main-panel">
        <Navbar userData={userData} toggleSidebarDesktop={toggleSidebarDesktop} toggleSidebarMobile={toggleSidebarMobile} isSidebarMini={isSidebarMini} />
        
        <div className="container">
          <Outlet /> {/* Aqui serão injetadas as páginas (Home, Frota, etc) */}
        </div>

        <footer className="footer" style={{ borderTop: '1px solid #ebedf2', background: '#fff' }}>
          <div className="container-fluid d-flex justify-content-between">
            <nav className="pull-left">
              SGCEEM v2.0 - Controle Estratégico
            </nav>
            <div>
              <button 
                className="btn btn-sm text-muted shadow-sm me-3" 
                onClick={() => setIsDarkMode(!isDarkMode)}
                style={{ background: isDarkMode ? '#333' : '#f8f9fa', borderRadius: '20px', border: '1px solid #ccc' }}
              >
                {isDarkMode ? <><i className="fas fa-sun text-warning"></i> Modo Claro</> : <><i className="fas fa-moon"></i> Modo Tático</>}
              </button>
              Uso Restrito Oficial
            </div>
          </div>
        </footer>

        {/* Injeção de CSS Dinâmico para Dark Mode Global */}
        {isDarkMode && (
          <style>{`
            .dark-mode { background-color: #1a1e23 !important; color: #b9b9b9 !important; }
            .dark-mode .main-panel { background: #1a1e23 !important; }
            .dark-mode .card, .dark-mode .footer { background: #22272e !important; border-color: #333 !important; }
            .dark-mode .card-header, .dark-mode .card-body { background: transparent !important; color: #d1d5db !important; }
            .dark-mode .table { color: #d1d5db !important; }
            .dark-mode .table-light th, .dark-mode .table-dark th { background-color: #2d333b !important; border-color: #444 !important; color: #fff !important; }
            .dark-mode td { border-color: #333 !important; }
            .dark-mode .sidebar { background: #1a1e23 !important; border-right: 1px solid #333 !important; }
            .dark-mode .sidebar .nav-item a { color: #b9b9b9 !important; }
            .dark-mode .navbar-header { background: #1a1e23 !important; border-bottom: 1px solid #333 !important; }
            .dark-mode .logo-header { background: #1a1e23 !important; border-right: 1px solid #333 !important; }
            .dark-mode h1, .dark-mode h2, .dark-mode h3, .dark-mode h4, .dark-mode h5, .dark-mode h6 { color: #fff !important; }
            .dark-mode .form-control, .dark-mode .form-select { background-color: #2d333b !important; color: #fff !important; border-color: #444 !important; }
            .dark-mode .modal-content { background-color: #22272e !important; color: #fff !important; }
            .dark-mode .bg-white { background-color: #22272e !important; }
            .dark-mode .text-dark { color: #fff !important; }
          `}</style>
        )}
      </div>
    </div>
  );
}
