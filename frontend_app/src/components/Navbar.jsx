import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';

export default function Navbar({ userData, toggleSidebarDesktop, toggleSidebarMobile, isSidebarMini }) {
  const [notificacoes, setNotificacoes] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);

  useEffect(() => {
    const token = localStorage.getItem('sgceem_token');
    
    // Toast setup
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true
    });

    const checkNotificacoes = async () => {
      try {
        const res = await fetch('/api/v1/notificacoes', {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        
        if(data.status === 'sucesso') {
          if (data.count > unreadCount) {
            // Nova notificação detectada
            Toast.fire({
              icon: 'info',
              title: data.dados[0]?.mensagem || 'Nova Notificação Operacional'
            });
          }
          setNotificacoes(data.dados);
          setUnreadCount(data.count);
          // Compartilha os dados com o Dashboard para manter o "Inbox Zero" unificado
          window.dispatchEvent(new CustomEvent('notificacoesUpdated', { detail: data.dados }));
        }
      } catch (err) {
        console.log("Erro no ping de notificações", err);
      }
    };

    // Ping Imediato
    checkNotificacoes();

    // Cron local: Ping otimizado para reduzir carga no MySQL (A cada 2 minutos)
    const cronInterval = setInterval(checkNotificacoes, 120000);
    return () => clearInterval(cronInterval);
  }, [unreadCount]);

  return (
    <div className="main-header" data-background-color="dark">
      <div className="main-header-logo">
        <div className="logo-header" data-background-color="dark">
          <a href="#" className="logo">
            <img src="/assets/img/kaiadmin/logo_light.png" alt="navbar brand" className="navbar-brand" height="30" />
          </a>
          <div className="nav-toggle">
            <button className={`btn btn-toggle toggle-sidebar ${isSidebarMini ? 'toggled' : ''}`} onClick={toggleSidebarDesktop}>
              <i className={isSidebarMini ? "gg-more-vertical-alt" : "gg-menu-right"}></i>
            </button>
            <button className="btn btn-toggle sidenav-toggler" onClick={toggleSidebarMobile}>
              <i className="gg-menu-left"></i>
            </button>
          </div>
          <button className="topbar-toggler more">
            <i className="gg-more-vertical-alt"></i>
          </button>
        </div>
      </div>

      <nav className="navbar navbar-header navbar-header-transparent navbar-expand-lg">
        <div className="container-fluid">
          <ul className="navbar-nav topbar-nav ms-md-auto align-items-center">
            
            {/* INÍCIO DO SINO DE NOTIFICAÇÃO (REAL-TIME) */}
            <li className="nav-item topbar-icon dropdown hidden-caret">
              <a className="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i className="fa fa-bell"></i>
                {unreadCount > 0 && <span className="notification">{unreadCount}</span>}
              </a>
              <ul className="dropdown-menu notif-box animated fadeIn" aria-labelledby="notifDropdown">
                <li>
                  <div className="dropdown-title">Você tem {unreadCount} mensagens táticas</div>
                </li>
                <li>
                  <div className="notif-scroll scrollbar-outer">
                    <div className="notif-center">
                      {notificacoes.map(n => (
                        <a href="#" key={n.id}>
                          <div className={`notif-icon notif-primary`}> <i className={n.icone || "fa fa-envelope"}></i> </div>
                          <div className="notif-content">
                            <span className="block">{n.mensagem}</span>
                            <span className="time">{n.tempo}</span> 
                          </div>
                        </a>
                      ))}
                    </div>
                  </div>
                </li>
                <li>
                  <a className="see-all" href="#">Ver todas as mensagens<i className="fa fa-angle-right"></i> </a>
                </li>
              </ul>
            </li>
            {/* FIM DO SINO */}

            <li className="nav-item topbar-user dropdown hidden-caret">
              <a className="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                <div className="avatar-sm">
                  <img src={`/assets/fotoperfil/${userData?.foto || 'default.png'}`} alt="..." className="avatar-img rounded-circle" />
                </div>
                <span className="profile-username">
                  <span className="op-7">Olá,</span>
                  <span className="fw-bold">{userData?.nomeguerra}</span>
                </span>
              </a>
              <ul className="dropdown-menu dropdown-user animated fadeIn">
                <div className="dropdown-user-scroll scrollbar-outer">
                  <li>
                    <div className="user-box">
                      <div className="avatar-lg">
                        <img src={`/assets/fotoperfil/${userData?.foto || 'default.png'}`} alt="Profile" className="avatar-img rounded" />
                      </div>
                      <div className="u-text">
                        <h4>{userData?.nomeguerra}</h4>
                        <p className="text-muted">Batalhão: {userData?.batalhao}</p>
                      </div>
                    </div>
                  </li>
                  <li>
                    <div className="dropdown-divider"></div>
                    <a className="dropdown-item" href="#">Meu Perfil</a>
                    <div className="dropdown-divider"></div>
                    <a className="dropdown-item" href="#" onClick={() => { localStorage.clear(); window.location.href='/'; }}>Sair</a>
                  </li>
                </div>
              </ul>
            </li>
          </ul>
        </div>
      </nav>
    </div>
  );
}
