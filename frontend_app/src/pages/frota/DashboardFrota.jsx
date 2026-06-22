import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from "../../utils/api.js";
import { ShieldCheck, CheckCircle, Wrench, AlertTriangle, Truck, Radar, Target, List, Circle, CarFront, BookOpen } from 'lucide-react';
import HistoricoViatura from './HistoricoViatura.jsx';
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement
} from 'chart.js';
import { Pie, Doughnut } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

export default function DashboardFrota() {
  const [loading, setLoading] = useState(true);
  const [dados, setDados] = useState(null);
  const [viaturas, setViaturas] = useState([]);
  const [selectedViatura, setSelectedViatura] = useState(null);

  useEffect(() => {
    apiFetch('dashboard/frota')
      .then(d => { if (d.status === 'sucesso') setDados(d.dados); })
      .catch(e => console.warn("Dashboard endpoint falhou, usando fallback:", e));
    
    apiFetch('crud/frota')
      .then(d => {
        if (d.status === 'sucesso' && d.dados) {
          setViaturas(d.dados);
          if (!dados) {
            const statusCount = {};
            const dispCount = {};
            d.dados.forEach(v => {
              const st = v.status || 'Indefinido';
              const dp = v.disponibilidade || v.status || 'Indefinido';
              statusCount[st] = (statusCount[st] || 0) + 1;
              dispCount[dp] = (dispCount[dp] || 0) + 1;
            });
            setDados({
              total_geral: d.dados.length,
              status: Object.entries(statusCount).map(([status, total]) => ({ status, total })),
              disponibilidade: Object.entries(dispCount).map(([disponibilidade, total]) => ({ disponibilidade, total }))
            });
          }
        }
      })
      .catch(e => console.error("Erro ao carregar viaturas:", e))
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

  const pieOptions = {
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { font: { family: "'Inter', sans-serif", size: 13, weight: '500' }, usePointStyle: true, padding: 25 } },
      tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.95)', padding: 15, cornerRadius: 12, bodyFont: { family: "'Inter', sans-serif", size: 14 } }
    },
    cutout: '70%',
    animation: { animateScale: true, animateRotate: true }
  };

  const statusColors = {
    'Disponível': '#10b981', // Emerald
    'Em Manutenção': '#f59e0b', // Amber
    'Baixada': '#ef4444', // Red
    'Emprestada': '#3b82f6', // Blue
    'Indefinido': '#94a3b8', // Slate
  };

  if (loading) {
    return (
      <div className="page-inner d-flex justify-content-center align-items-center" style={{ minHeight: '80vh' }}>
        <div className="text-center">
          <div className="spinner-border text-info mb-3" style={{ width: 48, height: 48 }}></div>
          <h5 className="text-muted fw-bold">Estabelecendo link com telemetria...</h5>
        </div>
      </div>
    );
  }

  if (selectedViatura) {
    return <HistoricoViatura viatura={selectedViatura} onBack={() => setSelectedViatura(null)} />;
  }

  const statusCounts = {};
  viaturas.forEach(v => {
    const st = v.status || 'Indefinido';
    statusCounts[st] = (statusCounts[st] || 0) + 1;
  });

  const statCards = [
    { label: "Força Total", value: viaturas.length, suffix: "viaturas", icon: <ShieldCheck size={32} />, color: "#3b82f6", bg: "linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05))" },
    { label: "Pronta Resposta", value: statusCounts['Disponível'] || 0, suffix: "disponíveis", icon: <CheckCircle size={32} />, color: "#10b981", bg: "linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05))" },
    { label: "Oficina", value: statusCounts['Em Manutenção'] || 0, suffix: "reparos", icon: <Wrench size={32} />, color: "#f59e0b", bg: "linear-gradient(135deg, rgba(245,158,11,0.2), rgba(245,158,11,0.05))" },
    { label: "Inoperantes", value: (statusCounts['Baixada'] || 0), suffix: "baixadas", icon: <AlertTriangle size={32} />, color: "#ef4444", bg: "linear-gradient(135deg, rgba(239,68,68,0.2), rgba(239,68,68,0.05))" },
  ];

  const chartStatusData = dados ? {
    labels: dados.status.map(i => i.status || 'Indefinido'),
    datasets: [{
      data: dados.status.map(i => i.total),
      backgroundColor: dados.status.map(i => statusColors[i.status] || '#94a3b8'),
      borderWidth: 0,
      hoverOffset: 15
    }]
  } : null;

  const ultimasViaturas = viaturas.slice(0, 8);

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
      {/* HEADER GIGANTE PREMIUM FROTA */}
      <div className="mb-4 position-relative overflow-hidden" style={{ 
        background: 'linear-gradient(135deg, #1e3a8a 0%, #172554 100%)', 
        borderRadius: '28px', padding: '45px', color: 'white', 
        boxShadow: '0 20px 40px -10px rgba(30, 58, 138, 0.4)' 
      }}>
        <div className="position-absolute" style={{ right: '-5%', top: '-30%', width: '350px', height: '350px', background: 'radial-gradient(circle, rgba(56,189,248,0.15) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        <div className="position-absolute" style={{ right: '15%', bottom: '-20%', width: '200px', height: '200px', background: 'radial-gradient(circle, rgba(16,185,129,0.1) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        
        <div className="d-flex justify-content-between align-items-center position-relative z-1 flex-wrap gap-4">
            <div className="d-flex align-items-center gap-4">
              <motion.div whileHover={{ rotate: 15 }} className="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur-sm border border-white border-opacity-20 shadow-lg">
                  <Truck size={48} className="text-info" />
              </motion.div>
              <div>
                <h1 className="fw-bolder mb-1" style={{ fontSize: '36px', letterSpacing: '-1px' }}>Comando da Frota</h1>
                <p className="text-info mb-0 fs-5 fw-medium opacity-75">Telemetria tática e disponibilidade operacional</p>
              </div>
            </div>
            <motion.button whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="btn btn-info shadow-lg px-4 py-3 rounded-pill fw-bold border-0 text-dark d-flex align-items-center" onClick={() => window.location.reload()}>
                <Radar className="me-2" size={20} /> Atualizar Radar
            </motion.button>
        </div>
      </div>

      {/* BENTO GRID DE ESTATÍSTICAS */}
      <div className="row g-4 mb-4">
        {statCards.map((card, i) => (
          <motion.div whileHover={{ y: -8 }} className="col-xl-3 col-lg-6" key={i}>
            <div className="card h-100 p-4" style={{ ...cardStyle, display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <div>
                <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '12px', letterSpacing: '1px' }}>{card.label}</p>
                <div className="d-flex align-items-baseline gap-2">
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '2.5rem', fontWeight: '900', letterSpacing: '-1px' }}>{card.value}</h2>
                </div>
                <span className="text-secondary fw-medium" style={{ fontSize: '13px' }}>{card.suffix}</span>
              </div>
              <div style={{ width: 65, height: 65, borderRadius: 20, background: card.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', color: card.color, boxShadow: `0 10px 20px ${card.color}20` }}>
                {card.icon}
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="row g-4 mb-4">
        {/* GRÁFICO DE STATUS */}
        <div className="col-lg-5">
          <motion.div whileHover={{ y: -5 }} className="card h-100" style={cardStyle}>
            <div className="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex align-items-center">
              <h5 className="fw-bolder text-dark mb-0 d-flex align-items-center"><Target className="text-primary me-2" size={20} /> Raio-X Operacional</h5>
            </div>
            <div className="card-body px-4 pb-4">
              <div style={{ height: '320px', position: 'relative' }}>
                {chartStatusData && <Doughnut data={chartStatusData} options={pieOptions} />}
                <div style={{ position: 'absolute', top: '42%', left: '50%', transform: 'translate(-50%, -50%)', textAlign: 'center', pointerEvents: 'none' }}>
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '2.5rem', letterSpacing: '-1px' }}>{viaturas.length}</h2>
                  <span className="text-muted fw-bold text-uppercase" style={{ fontSize: '11px', letterSpacing: '1px' }}>Total</span>
                </div>
              </div>
            </div>
          </motion.div>
        </div>

        {/* TABELA DE RECENTES */}
        <div className="col-lg-7">
          <motion.div whileHover={{ y: -5 }} className="card h-100" style={cardStyle}>
            <div className="card-header bg-transparent border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
              <h5 className="fw-bolder text-dark mb-0 d-flex align-items-center"><List className="text-warning me-2" size={20} /> Viaturas Monitoradas Recentemente</h5>
            </div>
            <div className="card-body p-0">
              {ultimasViaturas.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 px-4">Placa / Prefixo</th>
                        <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Modelo</th>
                        <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Subunidade</th>
                        <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Status</th>
                        <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      {ultimasViaturas.map(v => (
                        <tr key={v.id} style={{ transition: 'background 0.2s' }}>
                          <td className="px-4">
                            <div className="d-flex flex-column">
                              <span className="fw-bold text-dark">{v.placa || '-'}</span>
                              <span className="text-muted text-xs">{v.prefixo_sga || 'Sem SGA'}</span>
                            </div>
                          </td>
                          <td><span className="fw-medium text-dark">{v.marca} {v.modelo}</span></td>
                          <td><span className="text-muted fw-medium">{v.om_abreviatura || v.subunidade || '-'}</span></td>
                          <td className="text-center">
                            <span className="badge rounded-pill px-3 py-2 d-flex align-items-center justify-content-center" style={{
                              backgroundColor: statusColors[v.status] ? `${statusColors[v.status]}20` : '#f8f9fa',
                              color: statusColors[v.status] || '#6c757d',
                              border: `1px solid ${statusColors[v.status]}50`
                            }}>
                              <Circle className="me-1" size={8} fill="currentColor" stroke="none" />
                              {v.status || 'Indefinido'}
                            </span>
                          </td>
                          <td className="text-center">
                            <button className="btn btn-sm btn-light border shadow-sm rounded-pill px-3 fw-bold text-primary" onClick={() => setSelectedViatura(v)} title="Abrir Livro Histórico">
                              <BookOpen size={14} className="me-1" /> Dossiê
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-5">
                  <CarFront size={48} className="text-muted opacity-25 mb-3" />
                  <h6 className="text-muted">Nenhum dado captado nos radares</h6>
                </div>
              )}
            </div>
          </motion.div>
        </div>
      </div>
    </motion.div>
  );
}
