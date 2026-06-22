import { Link, useLocation } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { apiFetch, performLogout } from '../utils/api.js';
import { 
  Home, ShieldAlert, CarFront, DollarSign, Package, Wrench, 
  Settings, FileText, ClipboardList, LogOut, User 
} from 'lucide-react';

export default function Sidebar({ userData, toggleSidebarDesktop, isSidebarMini, onMouseEnter, onMouseLeave }) {
  const [menu, setMenu] = useState([]);
  const [loading, setLoading] = useState(true);
  const location = useLocation();

  const loadMenu = () => {
    apiFetch('menu')
      .then(data => {
        if (data && data.status === 'sucesso') {
          setMenu(data.menu);
          if (data.permissoes_globais) {
            localStorage.setItem('sgceem_permissoes', JSON.stringify(data.permissoes_globais));
          }
        }
      })
      .catch(err => console.log('Erro ao buscar menu:', err))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadMenu();

    const handleDbUpdate = () => {
      // Atualiza o menu silenciosamente
      loadMenu();
    };

    window.addEventListener('db_updated', handleDbUpdate);
    return () => window.removeEventListener('db_updated', handleDbUpdate);
  }, []);

  const handleLogout = () => {
    performLogout();
  };

  const isSysAdmin = userData?.role_id === 1 || userData?.role_id === 16;

  const renderDynamicIcon = (nome, fallbackClass) => {
    const n = nome.toLowerCase();
    const props = { size: 20, style: { minWidth: '35px', textAlign: 'center', opacity: 0.8 } };
    
    if (n.includes('frota') || n.includes('veículo') || n.includes('viatura')) return <CarFront {...props} />;
    if (n.includes('financeiro') || n.includes('pagamento') || n.includes('empenho')) return <DollarSign {...props} />;
    if (n.includes('almoxarifado') || n.includes('estoque') || n.includes('material')) return <Package {...props} />;
    if (n.includes('manutenção') || n.includes('oficina') || n.includes('serviço')) return <Wrench {...props} />;
    if (n.includes('admin') || n.includes('usuário') || n.includes('config')) return <Settings {...props} />;
    if (n.includes('relatório') || n.includes('gráfico')) return <FileText {...props} />;
    if (n.includes('log') || n.includes('auditoria')) return <ClipboardList {...props} />;
    
    return <i className={fallbackClass} style={{ fontSize: '20px', minWidth: '35px', textAlign: 'center', opacity: 0.8 }}></i>;
  };

  return (
    <div className="sidebar military-sidebar" data-background-color="dark" onMouseEnter={onMouseEnter} onMouseLeave={onMouseLeave}>
      <div className="sidebar-logo">
        <div className="logo-header" data-background-color="dark">
          <Link to="/" className="logo full-logo">
            <img src="/assets/img/kaiadmin/logo_light.png" alt="navbar brand" height="38" />
          </Link>
          <span className="logo-mini text-warning fw-bold">SGC</span>
          <div className="nav-toggle">
            <button className={`btn btn-toggle toggle-sidebar ${isSidebarMini ? 'toggled' : ''}`} onClick={toggleSidebarDesktop}>
              <i className={isSidebarMini ? "gg-more-vertical-alt" : "gg-menu-right"}></i>
            </button>
          </div>
          <button className="topbar-toggler more">
            <i className="gg-more-vertical-alt"></i>
          </button>
        </div>
      </div>

      <div className="sidebar-wrapper scrollbar scrollbar-inner">
        <div className="sidebar-content">
          <ul className="nav nav-secondary">
            
            <li className={`nav-item military-nav-item ${location.pathname === '/' ? 'active' : ''}`}>
              <Link to="/">
                <Home size={20} style={{ minWidth: '35px', textAlign: 'center', opacity: 0.8 }} />
                <p>Painel Principal</p>
              </Link>
            </li>

            {isSysAdmin && (
              <li className={`nav-item ${location.pathname === '/admin_dashboard' ? 'active' : ''}`} style={{ borderLeft: '4px solid #ef4444', background: 'rgba(239, 68, 68, 0.05)' }}>
                <Link to="/admin_dashboard">
                  <ShieldAlert size={20} className="text-danger" style={{ minWidth: '35px', textAlign: 'center', opacity: 0.8 }} />
                  <p className="text-danger fw-bold">Centro de Comando</p>
                </Link>
              </li>
            )}

            {loading ? (
              <li className="nav-item p-3 text-white"><i className="fas fa-spinner fa-spin"></i> Carregando menu...</li>
            ) : (
              menu.map(topico => (
                <li key={topico.id} className="nav-item">
                  <a data-bs-toggle="collapse" href={`#menu${topico.id}`}>
                    {renderDynamicIcon(topico.nome, topico.icone)}
                    <p>{topico.nome}</p>
                    <span className="caret"></span>
                  </a>
                  <div className="collapse" id={`menu${topico.id}`}>
                    <ul className="nav nav-collapse">
                      {topico.filhos.map(filho => (
                        <li key={filho.id}>
                          <Link to={`/${filho.rota}`}>
                            <span className="dot"></span>
                            <span className="sub-item">{filho.nome}</span>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </div>
                </li>
              ))
            )}

            <li className="nav-section mt-3">
              <span className="sidebar-mini-icon"><i className="fa fa-ellipsis-h"></i></span>
              <h4 className="text-section text-uppercase">Atalhos</h4>
            </li>

            <li className={`nav-item military-nav-item ${location.pathname === '/perfil' ? 'active' : ''}`}>
              <Link to="/perfil">
                <User size={20} style={{ minWidth: '35px', textAlign: 'center', opacity: 0.8 }} />
                <p>Editar Perfil</p>
              </Link>
            </li>

            <li className="nav-item military-nav-item">
              <a href="#" onClick={(e) => { e.preventDefault(); handleLogout(); }}>
                <LogOut size={20} style={{ minWidth: '35px', textAlign: 'center', opacity: 0.8 }} />
                <p>Sair</p>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  );
}
