import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from "../../utils/api.js";
import { Wrench, Clock, CheckCircle, AlertOctagon, Car, ShieldAlert, Plus, List, Eye } from 'lucide-react';
import FichaOS from './FichaOS.jsx';

export default function DashboardOS() {
  const [osData, setOsData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedOS, setSelectedOS] = useState(null);

  useEffect(() => {
    apiFetch('crud/os_principal')
      .then(res => {
        if (res.status === 'sucesso') setOsData(res.dados || []);
      })
      .catch(e => console.error(e))
      .finally(() => setLoading(false));
  }, []);

  const cardStyle = {
    background: 'rgba(255, 255, 255, 0.95)',
    backdropFilter: 'blur(10px)',
    border: '1px solid rgba(0,0,0,0.03)',
    boxShadow: '0 8px 32px 0 rgba(31, 38, 135, 0.05)',
    borderRadius: 24,
    transition: 'all 0.3s ease'
  };

  const statusColors = {
    'ABERTA': { bg: 'bg-primary', color: '#3b82f6', grad: "linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05))" },
    'AGUARDANDO PEÇA': { bg: 'bg-warning', color: '#f59e0b', grad: "linear-gradient(135deg, rgba(245,158,11,0.2), rgba(245,158,11,0.05))" },
    'EM MANUTENÇÃO': { bg: 'bg-info', color: '#0ea5e9', grad: "linear-gradient(135deg, rgba(14,165,233,0.2), rgba(14,165,233,0.05))" },
    'CONCLUIDA': { bg: 'bg-success', color: '#10b981', grad: "linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05))" },
  };

  // Safe mapping of status
  const safeStatus = (s) => s ? s.toUpperCase().trim() : 'ABERTA';

  const ativas = osData.filter(os => safeStatus(os.status) !== 'CONCLUIDA' && safeStatus(os.status) !== 'CANCELADA');
  const aguardandoPeca = osData.filter(os => safeStatus(os.status) === 'AGUARDANDO PEÇA');
  const preventivas = osData.filter(os => String(os.tipo_mnt).toUpperCase().includes('PREVENTIVA'));
  
  const concluidasMensal = osData.filter(os => safeStatus(os.status) === 'CONCLUIDA').length;

  const statCards = [
    { label: "OS em Andamento", value: ativas.length, suffix: "ativas", icon: <Wrench size={32} />, color: "#3b82f6", bg: "linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05))" },
    { label: "Gargalo: Falta Peça", value: aguardandoPeca.length, suffix: "paradas", icon: <AlertOctagon size={32} />, color: "#ef4444", bg: "linear-gradient(135deg, rgba(239,68,68,0.2), rgba(239,68,68,0.05))" },
    { label: "Preventivas", value: preventivas.length, suffix: "agendadas", icon: <ShieldAlert size={32} />, color: "#f59e0b", bg: "linear-gradient(135deg, rgba(245,158,11,0.2), rgba(245,158,11,0.05))" },
    { label: "Produtividade", value: concluidasMensal, suffix: "concluídas", icon: <CheckCircle size={32} />, color: "#10b981", bg: "linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05))" },
  ];

  if (loading) {
    return (
      <div className="page-inner d-flex justify-content-center align-items-center" style={{ minHeight: '80vh' }}>
        <div className="spinner-border text-purple" style={{ width: 48, height: 48, color: '#8b5cf6' }}></div>
      </div>
    );
  }

  if (selectedOS) {
    return <FichaOS os={selectedOS} onBack={() => setSelectedOS(null)} />;
  }

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
      <div className="mb-4 position-relative overflow-hidden" style={{ 
        background: 'linear-gradient(135deg, #2e1065 0%, #4c1d95 100%)', 
        borderRadius: '28px', padding: '45px', color: 'white', 
        boxShadow: '0 20px 40px -10px rgba(76, 29, 149, 0.4)' 
      }}>
        <div className="position-absolute" style={{ right: '-5%', top: '-30%', width: '350px', height: '350px', background: 'radial-gradient(circle, rgba(139,92,246,0.15) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        <div className="d-flex justify-content-between align-items-center position-relative z-1 flex-wrap gap-4">
            <div className="d-flex align-items-center gap-4">
              <motion.div whileHover={{ rotate: 15 }} className="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur-sm border border-white border-opacity-20 shadow-lg">
                  <Wrench size={48} className="text-white" style={{ color: '#c4b5fd' }} />
              </motion.div>
              <div>
                <h1 className="fw-bolder mb-1" style={{ fontSize: '36px', letterSpacing: '-1px' }}>Centro de Manutenção</h1>
                <p className="mb-0 fs-5 fw-medium opacity-75" style={{ color: '#ddd6fe' }}>Linha de Produção e Ordens de Serviço</p>
              </div>
            </div>
            <div className="d-flex gap-3">
              <motion.button whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="btn btn-light shadow-lg px-4 py-3 rounded-pill fw-bold border-0 text-dark d-flex align-items-center" onClick={() => window.location.href='/includes/os/listagem'}>
                  <List className="me-2" size={20} /> Ver Todas
              </motion.button>
            </div>
        </div>
      </div>

      <div className="row g-4 mb-4">
        {statCards.map((card, i) => (
          <motion.div whileHover={{ y: -8 }} className="col-xl-3 col-lg-6" key={i}>
            <div className="card h-100 p-4" style={{ ...cardStyle, display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <div>
                <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '12px', letterSpacing: '1px' }}>{card.label}</p>
                <div className="d-flex align-items-baseline gap-2">
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '2.5rem', fontWeight: '900', letterSpacing: '-1px' }}>{card.value}</h2>
                </div>
                {card.suffix && <span className="text-secondary fw-medium" style={{ fontSize: '13px' }}>{card.suffix}</span>}
              </div>
              <div style={{ minWidth: 65, height: 65, borderRadius: 20, background: card.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', color: card.color, boxShadow: `0 10px 20px ${card.color}20` }}>
                {card.icon}
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="row g-4">
        <div className="col-12">
          <motion.div className="card h-100" style={cardStyle}>
            <div className="card-header bg-transparent border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
              <h5 className="fw-bolder text-dark mb-0 d-flex align-items-center"><Clock className="text-primary me-2" size={24} /> WorkFlow: Fila de Manutenção</h5>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 px-4">OS / Viatura</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Problema Relatado</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Tipo MNT</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Status SLA</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    {ativas.length === 0 ? (
                      <tr>
                        <td colSpan="4" className="text-center py-5">
                          <CheckCircle size={48} className="text-success opacity-50 mb-3" />
                          <h6 className="text-muted fw-bold">Oficina zerada. Todas as viaturas prontas.</h6>
                        </td>
                      </tr>
                    ) : ativas.map(os => {
                      const st = safeStatus(os.status);
                      const colorObj = statusColors[st] || statusColors['ABERTA'];
                      return (
                      <tr key={os.id} style={{ transition: 'background 0.2s' }}>
                        <td className="px-4">
                          <div className="fw-bold text-dark">OS #{os.id}</div>
                          <div className="d-flex align-items-center mt-1">
                            <Car size={14} className="text-muted me-1" />
                            <span className="text-muted fw-bold text-xs">{os.placa_vtr || os.prefixo_sga || 'S/N'}</span>
                          </div>
                        </td>
                        <td style={{ maxWidth: '300px' }}>
                          <div className="text-truncate fw-medium text-dark">{os.problema || 'Não especificado'}</div>
                          <div className="text-muted text-xs">Aberto por: {os.aberta_por || 'Sistema'}</div>
                        </td>
                        <td><span className="fw-bold text-secondary text-xs bg-light border px-2 py-1 rounded-1">{os.tipo_mnt || 'CORRETIVA'}</span></td>
                        <td className="text-center">
                          <span className={`badge rounded-pill px-3 py-2 ${colorObj.bg} bg-opacity-10`} style={{ color: colorObj.color, border: `1px solid ${colorObj.color}50` }}>
                            {st}
                          </span>
                        </td>
                        <td className="text-center">
                          <button className="btn btn-sm btn-light border shadow-sm rounded-circle p-2" onClick={() => setSelectedOS(os)} title="Abrir Ficha Tática">
                            <Eye size={16} className="text-primary" />
                          </button>
                        </td>
                      </tr>
                    )})}
                  </tbody>
                </table>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </motion.div>
  );
}
