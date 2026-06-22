import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { apiFetch } from '../utils/api.js';
import { 
  ShieldCheck, AlertTriangle, FileText, Wrench, 
  PieChart, Rocket, CarFront, Package, DollarSign, Bell
} from 'lucide-react';
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement, PointElement, LineElement
} from 'chart.js';
import { Doughnut } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement, PointElement, LineElement);

export default function Home({ userData }) {
  const [loading, setLoading] = useState(true);
  const [metrics, setMetrics] = useState({
    frota: [],
    almoxarifado: [],
    empenhos: [],
    os: []
  });
  const [permissoes, setPermissoes] = useState([]);
  const [widgetLayouts, setWidgetLayouts] = useState({});
  const [notificacoesTaticas, setNotificacoesTaticas] = useState([]);

  useEffect(() => {
    // Carrega permissoes salvas do Sidebar
    try {
      const stored = localStorage.getItem('sgceem_permissoes');
      if (stored) setPermissoes(JSON.parse(stored));
    } catch (e) {}

    // Escuta notificações enviadas pelo Navbar (EventBus)
    const handleNotificacoes = (e) => setNotificacoesTaticas(e.detail || []);
    window.addEventListener('notificacoesUpdated', handleNotificacoes);

    // Buscar estatisticas e layout do dashboard
    Promise.all([
      apiFetch('crud/frota').catch(() => ({ dados: [] })),
      apiFetch('crud/almox_produtos').catch(() => ({ dados: [] })),
      apiFetch('crud/fin_empenhos').catch(() => ({ dados: [] })),
      apiFetch('crud/os_principal').catch(() => ({ dados: [] })),
      apiFetch('widgets').catch(() => ({ dados: [] }))
    ]).then(([resFrota, resAlmox, resEmp, resOs, resWidgets]) => {
      setMetrics({
        frota: resFrota?.dados || [],
        almoxarifado: resAlmox?.dados || [],
        empenhos: resEmp?.dados || [],
        os: resOs?.dados || []
      });
      
      if (resWidgets?.dados) {
        const layoutMap = {};
        resWidgets.dados.forEach(w => {
          if (w.role_id === userData?.role_id) {
            layoutMap[w.widget_key] = w.is_visible === 1;
          }
        });
        setWidgetLayouts(layoutMap);
      }
      
      setLoading(false);
    });

    return () => window.removeEventListener('notificacoesUpdated', handleNotificacoes);
  }, [userData]);

  const marcarNotificacaoComoLida = async (id) => {
    try {
      const token = localStorage.getItem('sgceem_token');
      await fetch('/api/v1/notificacoes/marcar_lida', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ id })
      });
      // Remove localmente para simular Inbox Zero sem precisar refetch na hora
      setNotificacoesTaticas(prev => prev.filter(n => n.id !== id));
    } catch (err) {
      console.error(err);
    }
  };

  const isAdmin = userData?.role_id === 1 || userData?.role_id === 16;
  const hasPermission = (key) => {
    if (isAdmin) return true;
    if (!permissoes) return false;
    
    // Se for array (formato antigo), usamos includes
    if (Array.isArray(permissoes)) {
      return permissoes.includes('god_mode') || permissoes.includes(key);
    }
    
    // Se for objeto (novo formato permissoes_globais), checamos se a chave existe no objeto
    return !!permissoes[key];
  };

  const isWidgetVisible = (widgetKey, fallback) => {
    if (Object.keys(widgetLayouts).length === 0) return fallback;
    return !!widgetLayouts[widgetKey];
  };

  // Aggregations
  const frotaAtiva = metrics.frota.filter(f => f.status === 'Disponível').length;
  const frotaManutencao = metrics.frota.filter(f => f.status === 'Em Manutenção').length;
  const frotaBaixada = metrics.frota.filter(f => f.status === 'Baixada').length;
  
  const estoqueBaixo = metrics.almoxarifado.filter(p => Number(p.estoque_atual || 0) <= Number(p.estoque_minimo || 0)).length;

  const hora = new Date().getHours();
  let saudacao = 'Boa noite';
  if (hora >= 5 && hora < 12) saudacao = 'Bom dia';
  else if (hora >= 12 && hora < 18) saudacao = 'Boa tarde';
  
  const patente = userData?.postograd ? `${userData.postograd} ` : '';
  const nome = userData?.nomeguerra || userData?.nomecompleto || 'Comando';

  const cardStyle = {
    background: 'rgba(255, 255, 255, 0.95)',
    backdropFilter: 'blur(10px)',
    border: 'none',
    boxShadow: '0 8px 32px 0 rgba(31, 38, 135, 0.05)',
    borderRadius: 24,
    transition: 'all 0.3s ease'
  };

  const doughnutData = {
    labels: ['Disponível', 'Manutenção', 'Baixada', 'Indefinido'],
    datasets: [{
      data: [
        frotaAtiva, 
        frotaManutencao, 
        frotaBaixada,
        metrics.frota.length - (frotaAtiva + frotaManutencao + frotaBaixada)
      ],
      backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#94a3b8'],
      borderWidth: 0,
      hoverOffset: 15
    }]
  };

  const chartOptions = {
    maintainAspectRatio: false,
    plugins: { 
      legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25, font: { family: "'Inter', sans-serif", size: 13, weight: '500' } } },
      tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.9)', padding: 12, cornerRadius: 8 }
    },
    cutout: '75%'
  };

  const renderFrotaChart = () => {
    if (!isWidgetVisible('widget_grafico_frota', hasPermission('frota'))) return null;
    return (
      <div className="card border-0 shadow-sm mb-4 h-100" style={{ borderRadius: '15px' }}>
        <div className="card-header bg-white border-0 pt-4 pb-0">
          <h6 className="fw-bold mb-0 text-dark"><CarFront size={18} className="me-2 text-success"/> Status da Frota</h6>
        </div>
        <div className="card-body d-flex justify-content-center">
          <div style={{ width: '220px', height: '220px' }}>
            <Doughnut data={doughnutData} options={chartOptions} />
          </div>
        </div>
      </div>
    );
  };

  if (loading) {
    return (
      <div className="page-inner d-flex justify-content-center align-items-center" style={{ minHeight: '80vh' }}>
        <div className="text-center">
          <div className="spinner-grow text-primary mb-3" style={{ width: '3rem', height: '3rem' }}></div>
          <h5 className="text-muted fw-bold">Compilando Central de Comando...</h5>
        </div>
      </div>
    );
  }

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
      {/* HEADER GIGANTE PREMIUM */}
      <div className="mb-4 position-relative overflow-hidden" style={{ 
        background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', 
        borderRadius: '28px', padding: '45px', color: 'white', boxShadow: '0 20px 40px -10px rgba(15,23,42,0.4)' 
      }}>
        <div className="position-absolute" style={{ right: '-5%', top: '-30%', width: '350px', height: '350px', background: 'radial-gradient(circle, rgba(56,189,248,0.15) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        <div className="d-flex align-items-center gap-4 position-relative z-1">
          <motion.img 
            whileHover={{ scale: 1.05 }}
            src={`/assets/fotoperfil/${userData?.foto || 'default.png'}`} 
            style={{ width: '90px', height: '90px', objectFit: 'cover', borderRadius: '24px', border: '3px solid rgba(255,255,255,0.1)' }} 
            alt="Perfil" 
          />
          <div>
            <h1 className="fw-bolder mb-1" style={{ fontSize: '32px', letterSpacing: '-0.5px' }}>{saudacao}, {patente}{nome}</h1>
            <p className="text-white-50 mb-0 fs-5 fw-medium">Centro de Comando Unificado SGCEEM v2.0</p>
          </div>
        </div>
      </div>

      {/* ÁREA DE AÇÃO IMEDIATA (CARDS CRÍTICOS DE NOTIFICAÇÃO) */}
      <div className="mb-4">
        <h5 className="fw-bold mb-3 d-flex align-items-center text-dark"><Bell size={20} className="me-2 text-danger" /> Atenção Imediata (Inbox)</h5>
        <div className="row g-3">
          {notificacoesTaticas.length > 0 ? (
            notificacoesTaticas.map(notif => {
              const isCritical = notif.nivel === 'CRITICAL';
              const isWarning = notif.nivel === 'WARNING';
              const cardBg = isCritical ? '#fef2f2' : (isWarning ? '#fffbeb' : '#f0f9ff');
              const cardBorder = isCritical ? '#ef4444' : (isWarning ? '#f59e0b' : '#3b82f6');
              const textColor = isCritical ? 'text-danger' : (isWarning ? 'text-warning' : 'text-primary');
              const btnClass = isCritical ? 'btn-danger' : (isWarning ? 'btn-warning text-dark' : 'btn-primary');

              return (
                <div className="col-12 col-xl-6" key={notif.id}>
                  <div className="card border-0 p-3" style={{ background: cardBg, borderLeft: `4px solid ${cardBorder} !important`, borderRadius: '12px' }}>
                    <div className="d-flex justify-content-between align-items-center">
                      <div className="pe-3">
                        <h6 className={`fw-bold mb-1 ${textColor}`}>{notif.titulo}</h6>
                        <p className="mb-0 text-muted" style={{ fontSize: '14px' }}>{notif.mensagem}</p>
                      </div>
                      <div className="d-flex flex-column gap-2">
                        {notif.acao_url && (
                          <Link to={notif.acao_url} className={`btn ${btnClass} btn-sm px-3 fw-bold`}>Acessar</Link>
                        )}
                        <button onClick={() => marcarNotificacaoComoLida(notif.id)} className="btn btn-light btn-sm text-muted fw-medium border">
                          <i className="fa fa-check me-1"></i> Ciente
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })
          ) : (
            <div className="col-12">
              <div className="card border-0 p-3 text-center" style={{ background: '#f8fafc', borderRadius: '12px' }}>
                <p className="mb-0 text-muted">🎉 Caixa de entrada vazia. Não há ações imediatas requeridas para sua função.</p>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* METRIC WIDGETS DINÂMICOS BASEADOS EM PERMISSÃO */}
      <h5 className="fw-bold mb-3 mt-4 text-dark">Widgets de Contexto</h5>
      <div className="row g-4 mb-4">
        {isWidgetVisible('widget_frota_operacional', hasPermission('frota')) && (
          <motion.div className="col-xl-3 col-lg-6" whileHover={{ y: -8 }}>
            <div className="card h-100 p-4" style={{ ...cardStyle, border: '1px solid rgba(0,0,0,0.03)' }}>
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '12px' }}>Frota Operacional</p>
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '2.5rem' }}>{frotaAtiva}</h2>
                  <span className="text-secondary fw-medium" style={{ fontSize: '13px' }}>de {metrics.frota.length} viaturas</span>
                </div>
                <div style={{ width: 60, height: 60, borderRadius: 20, background: '#ecfdf5', color: '#10b981', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <ShieldCheck size={28} />
                </div>
              </div>
            </div>
          </motion.div>
        )}

        {isWidgetVisible('widget_alerta_estoque', hasPermission('almox_produtos')) && (
          <motion.div className="col-xl-3 col-lg-6" whileHover={{ y: -8 }}>
            <div className="card h-100 p-4" style={{ ...cardStyle, border: '1px solid rgba(0,0,0,0.03)' }}>
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '12px' }}>Alerta de Estoque</p>
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '2.5rem' }}>{estoqueBaixo}</h2>
                  <span className="text-secondary fw-medium" style={{ fontSize: '13px' }}>produtos críticos</span>
                </div>
                <div style={{ width: 60, height: 60, borderRadius: 20, background: '#fffbeb', color: '#f59e0b', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <AlertTriangle size={28} />
                </div>
              </div>
            </div>
          </motion.div>
        )}

        {isWidgetVisible('widget_empenhos_ativos', hasPermission('fin_empenhos')) && (
          <motion.div className="col-xl-3 col-lg-6" whileHover={{ y: -8 }}>
            <div className="card h-100 p-4" style={{ ...cardStyle, border: '1px solid rgba(0,0,0,0.03)' }}>
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '12px' }}>Empenhos Ativos</p>
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '2.5rem' }}>{metrics.empenhos.length}</h2>
                  <span className="text-secondary fw-medium" style={{ fontSize: '13px' }}>notas de empenho abertas</span>
                </div>
                <div style={{ width: 60, height: 60, borderRadius: 20, background: '#eff6ff', color: '#3b82f6', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <DollarSign size={28} />
                </div>
              </div>
            </div>
          </motion.div>
        )}

        {isWidgetVisible('widget_os_manutencao', hasPermission('os_principal')) && (
          <motion.div className="col-xl-3 col-lg-6" whileHover={{ y: -8 }}>
            <div className="card h-100 p-4" style={{ ...cardStyle, border: '1px solid rgba(0,0,0,0.03)' }}>
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '12px' }}>OS em Aberto</p>
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '2.5rem' }}>{metrics.os.length}</h2>
                  <span className="text-secondary fw-medium" style={{ fontSize: '13px' }}>ordens de serviço</span>
                </div>
                <div style={{ width: 60, height: 60, borderRadius: 20, background: '#f5f3ff', color: '#8b5cf6', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <Wrench size={28} />
                </div>
              </div>
            </div>
          </motion.div>
        )}


      </div>

      {/* CHARTS E ACESSO RÁPIDO (Seção Inferior) */}
      <div className="row g-4">
        {/* GRÁFICOS VISUAIS (Apenas se o usuário tiver acesso à visualização daquele módulo) */}
        {(hasPermission('frota') || hasPermission('almox_produtos')) && (
          <div className="col-12 col-xl-4">
            <h6 className="fw-bold text-dark mb-3">Widgets de Contexto</h6>
            {renderFrotaChart()}
          </div>
        )}

        {/* MÓDULOS PERMITIDOS */}
        <div className={`col-12 ${(hasPermission('frota') || hasPermission('almox_produtos')) ? 'col-xl-8' : ''}`}>
          <h6 className="fw-bold text-dark mb-3">Módulos Permitidos</h6>
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="card border-0 shadow-sm p-4 h-100">
            <div className="row g-3">
              {hasPermission('frota') && (
                <div className="col-md-6">
                  <Link to="/frota" className="text-decoration-none">
                    <div className="p-3 bg-light rounded-3 border h-100 transition-hover">
                      <h6 className="fw-bold text-dark"><CarFront size={18} className="me-2 text-primary"/> Gestão de Frota</h6>
                      <small className="text-muted">Total de Viaturas: <strong>{metrics.frota.length}</strong> cadastradas. Despacho e controle.</small>
                    </div>
                  </Link>
                </div>
              )}
              {hasPermission('almox_produtos') && (
                <div className="col-md-6">
                  <Link to="/includes/almox_produtos/listagem" className="text-decoration-none">
                    <div className="p-3 bg-light rounded-3 border h-100 transition-hover">
                      <h6 className="fw-bold text-dark"><Package size={18} className="me-2 text-warning"/> Almoxarifado</h6>
                      <small className="text-muted">Gestão de Peças e Suprimentos com <strong>{metrics.almoxarifado.length}</strong> itens listados.</small>
                    </div>
                  </Link>
                </div>
              )}
              {hasPermission('fin_empenhos') && (
                <div className="col-md-6">
                  <Link to="/includes/fin_empenhos/listagem" className="text-decoration-none">
                    <div className="p-3 bg-light rounded-3 border h-100 transition-hover">
                      <h6 className="fw-bold text-dark"><DollarSign size={18} className="me-2 text-success"/> Financeiro (SIAR)</h6>
                      <small className="text-muted">Acompanhamento e Liquidação de Empenhos.</small>
                    </div>
                  </Link>
                </div>
              )}
              {hasPermission('os_principal') && (
                <div className="col-md-6">
                  <Link to="/includes/os/listagem" className="text-decoration-none">
                    <div className="p-3 bg-light rounded-3 border">
                      <h6 className="fw-bold text-dark"><Wrench size={18} className="me-2 text-purple"/> Ordens de Serviço</h6>
                      <small className="text-muted">Controle de manutenções.</small>
                    </div>
                  </Link>
                </div>
              )}
              {isAdmin && (
                <div className="col-md-12 mt-4">
                  <div className="text-white p-4 border-0 rounded-4 shadow-sm" style={{ backgroundImage: 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)' }}>
                    <h6 className="fw-bold mb-3 d-flex align-items-center"><ShieldCheck size={18} className="me-2 text-info"/> Painel de Administração</h6>
                    <div className="d-flex gap-2 flex-wrap">
                       <Link to="/usuarios_listagem" className="btn btn-outline-light btn-sm flex-fill">Gerenciar Usuários</Link>
                       <Link to="/admin_permissoes" className="btn btn-outline-light btn-sm flex-fill">Controle de Módulos</Link>
                       <Link to="/montador_dashboard" className="btn btn-info btn-sm flex-fill fw-bold border-0">Montador de Dashboard</Link>
                       <Link to="/admin_config" className="btn btn-outline-light btn-sm flex-fill">Configurações Gerais</Link>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </motion.div>
        </div>
      </div>
    </motion.div>
  );
}
