import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from "../../utils/api.js";
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement
} from 'chart.js';
import { Doughnut } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

export default function DashboardPreventiva() {
  const [loading, setLoading] = useState(true);
  const [dados, setDados] = useState(null);

  useEffect(() => {
    apiFetch('dashboard/preventiva')
      .then(d => { if (d.status === 'sucesso') setDados(d.dados); })
      .catch(e => console.error("Erro ao carregar dashboard:", e))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="page-inner d-flex justify-content-center align-items-center" style={{ minHeight: '80vh' }}>
        <div className="text-center">
          <div className="spinner-border text-warning mb-3" style={{ width: 48, height: 48 }}></div>
          <h5 className="text-muted fw-bold">Levantando histórico de manutenção...</h5>
        </div>
      </div>
    );
  }

  if (!dados) {
    return <div className="alert alert-danger m-4 shadow-sm rounded-4 border-0">Falha ao carregar os dados de Manutenção.</div>;
  }

  const chartStatus = dados.status && dados.status.length > 0 ? {
    labels: dados.status.map(i => i.status || 'Indefinido'),
    datasets: [{
      data: dados.status.map(i => i.total),
      backgroundColor: ['#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#8b5cf6'],
      borderWidth: 0,
      hoverOffset: 15
    }]
  } : null;

  const pieOptions = {
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'right', labels: { font: { family: "'Inter', sans-serif", size: 13, weight: '500' }, usePointStyle: true, padding: 25 } },
      tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.95)', padding: 15, cornerRadius: 12, bodyFont: { family: "'Inter', sans-serif", size: 14 } }
    },
    cutout: '75%',
    animation: { animateScale: true, animateRotate: true }
  };

  const cardStyle = {
    background: 'rgba(255, 255, 255, 0.95)',
    backdropFilter: 'blur(10px)',
    border: '1px solid rgba(0,0,0,0.03)',
    boxShadow: '0 8px 32px 0 rgba(31, 38, 135, 0.05)',
    borderRadius: 24,
    transition: 'all 0.3s ease'
  };

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
      {/* HEADER GIGANTE PREMIUM OS */}
      <div className="mb-4 position-relative overflow-hidden" style={{ 
        background: 'linear-gradient(135deg, #4c1d95 0%, #2e1065 100%)', 
        borderRadius: '28px', padding: '45px', color: 'white', 
        boxShadow: '0 20px 40px -10px rgba(76, 29, 149, 0.4)' 
      }}>
        <div className="position-absolute" style={{ right: '-5%', top: '-30%', width: '350px', height: '350px', background: 'radial-gradient(circle, rgba(167,139,250,0.15) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        <div className="position-absolute" style={{ right: '15%', bottom: '-20%', width: '200px', height: '200px', background: 'radial-gradient(circle, rgba(251,146,60,0.1) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        
        <div className="d-flex justify-content-between align-items-center position-relative z-1 flex-wrap gap-4">
            <div className="d-flex align-items-center gap-4">
              <motion.div whileHover={{ rotate: 90 }} transition={{ duration: 0.5 }} className="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur-sm border border-white border-opacity-20 shadow-lg">
                  <i className="fas fa-cogs fa-3x text-warning" style={{ color: '#fbbf24' }}></i>
              </motion.div>
              <div>
                <h1 className="fw-bolder mb-1" style={{ fontSize: '36px', letterSpacing: '-1px' }}>Oficina e Manutenção</h1>
                <p className="mb-0 fs-5 fw-medium opacity-75" style={{ color: '#fde68a' }}>Controle estratégico de reparos e preventivas</p>
              </div>
            </div>
            <motion.button whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="btn btn-warning shadow-lg px-4 py-3 rounded-pill fw-bold border-0 text-dark d-flex align-items-center" onClick={() => window.location.reload()}>
                <i className="fas fa-hammer me-2 fs-5"></i> Atualizar Status
            </motion.button>
        </div>
      </div>

      <div className="row g-4 mb-4">
        {/* CARD PRINCIPAL */}
        <motion.div whileHover={{ y: -8 }} className="col-lg-5">
            <div className="card h-100 p-4" style={{ ...cardStyle, background: 'linear-gradient(135deg, #ffffff, #f8fafc)', display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderLeft: '5px solid #8b5cf6' }}>
              <div>
                <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '13px', letterSpacing: '1.5px' }}>Volume de Ordens</p>
                <div className="d-flex align-items-baseline gap-2">
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: '3.5rem', fontWeight: '900', letterSpacing: '-2px' }}>{dados.total_preventivas}</h2>
                </div>
                <span className="text-secondary fw-bold" style={{ fontSize: '14px' }}>OS Registradas</span>
              </div>
              <div style={{ width: 85, height: 85, borderRadius: 24, background: 'linear-gradient(135deg, rgba(139,92,246,0.2), rgba(139,92,246,0.05))', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#8b5cf6', boxShadow: '0 10px 25px rgba(139,92,246,0.2)' }}>
                <i className="fas fa-clipboard-list fs-1"></i>
              </div>
            </div>
        </motion.div>
        
        {/* GRÁFICO */}
        {chartStatus && (
            <motion.div whileHover={{ y: -5 }} className="col-lg-7">
              <div className="card h-100" style={cardStyle}>
                <div className="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                  <h5 className="fw-bolder text-dark mb-0"><i className="fas fa-chart-pie text-warning me-2"></i> Panorama Operacional</h5>
                </div>
                <div className="card-body px-4 pb-4">
                  <div style={{ height: '300px', position: 'relative' }}>
                    <Doughnut data={chartStatus} options={pieOptions} />
                  </div>
                </div>
              </div>
            </motion.div>
        )}
      </div>

    </motion.div>
  );
}
