import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from "../../utils/api.js";
import { Package, AlertTriangle, Layers, Plus, Database, Activity, CheckCircle } from 'lucide-react';

export default function DashboardAlmoxarifado() {
  const [produtos, setProdutos] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch('crud/almox_produtos')
      .then(res => {
        if (res.status === 'sucesso') setProdutos(res.dados || []);
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

  const totalItens = produtos.length;
  const estoqueBaixo = produtos.filter(p => Number(p.estoque_atual || 0) <= Number(p.estoque_minimo || 0));
  const categoriasUnicas = [...new Set(produtos.map(p => p.categoria_produto).filter(Boolean))].length;
  const totalPecas = produtos.reduce((acc, curr) => acc + Number(curr.estoque_atual || 0), 0);

  const statCards = [
    { label: "Total de Produtos", value: totalItens, suffix: "cadastros", icon: <Database size={32} />, color: "#3b82f6", bg: "linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05))" },
    { label: "Volume Físico", value: totalPecas, suffix: "unidades", icon: <Package size={32} />, color: "#10b981", bg: "linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05))" },
    { label: "Nível Crítico", value: estoqueBaixo.length, suffix: "alertas", icon: <AlertTriangle size={32} />, color: "#ef4444", bg: "linear-gradient(135deg, rgba(239,68,68,0.2), rgba(239,68,68,0.05))" },
    { label: "Categorias", value: categoriasUnicas, suffix: "tipos", icon: <Layers size={32} />, color: "#8b5cf6", bg: "linear-gradient(135deg, rgba(139,92,246,0.2), rgba(139,92,246,0.05))" },
  ];

  if (loading) {
    return (
      <div className="page-inner d-flex justify-content-center align-items-center" style={{ minHeight: '80vh' }}>
        <div className="spinner-border text-warning" style={{ width: 48, height: 48 }}></div>
      </div>
    );
  }

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
      <div className="mb-4 position-relative overflow-hidden" style={{ 
        background: 'linear-gradient(135deg, #78350f 0%, #451a03 100%)', 
        borderRadius: '28px', padding: '45px', color: 'white', 
        boxShadow: '0 20px 40px -10px rgba(120, 53, 15, 0.4)' 
      }}>
        <div className="position-absolute" style={{ right: '-5%', top: '-30%', width: '350px', height: '350px', background: 'radial-gradient(circle, rgba(245,158,11,0.15) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        <div className="d-flex justify-content-between align-items-center position-relative z-1 flex-wrap gap-4">
            <div className="d-flex align-items-center gap-4">
              <motion.div whileHover={{ rotate: 15 }} className="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur-sm border border-white border-opacity-20 shadow-lg">
                  <Package size={48} className="text-warning" />
              </motion.div>
              <div>
                <h1 className="fw-bolder mb-1" style={{ fontSize: '36px', letterSpacing: '-1px' }}>Comando de Suprimentos</h1>
                <p className="text-warning mb-0 fs-5 fw-medium opacity-75">Gestão de Almoxarifado e Estoque Tático</p>
              </div>
            </div>
            <div className="d-flex gap-3">
              <motion.button whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="btn btn-warning shadow-lg px-4 py-3 rounded-pill fw-bold border-0 text-dark d-flex align-items-center" onClick={() => window.location.href='/includes/almox_produtos/listagem'}>
                  <Database className="me-2" size={20} /> Base de Peças
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
        <div className="col-lg-8">
          <motion.div className="card h-100" style={cardStyle}>
            <div className="card-header bg-transparent border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
              <h5 className="fw-bolder text-dark mb-0 d-flex align-items-center"><AlertTriangle className="text-danger me-2" size={24} /> Radar de Estoque Crítico</h5>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 px-4">Código / Produto</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Categoria</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Mínimo</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Atual</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {estoqueBaixo.length === 0 ? (
                      <tr>
                        <td colSpan="5" className="text-center py-5">
                          <CheckCircle size={48} className="text-success opacity-50 mb-3" />
                          <h6 className="text-muted fw-bold">Estoque estabilizado. Nenhuma peça em nível crítico.</h6>
                        </td>
                      </tr>
                    ) : estoqueBaixo.map(p => (
                      <tr key={p.id} style={{ background: 'rgba(239, 68, 68, 0.02)' }}>
                        <td className="px-4">
                          <div className="fw-bold text-dark">{p.codigo_produto || '-'}</div>
                          <span className="text-muted text-xs">{p.nome_produto}</span>
                        </td>
                        <td><span className="fw-medium text-dark">{p.categoria_produto || 'Geral'}</span></td>
                        <td className="text-center fw-bold text-muted">{p.estoque_minimo || 0}</td>
                        <td className="text-center fw-black text-danger fs-5">{p.estoque_atual || 0}</td>
                        <td className="text-center">
                          <span className="badge bg-danger rounded-pill px-3">Crítico</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </motion.div>
        </div>
        
        <div className="col-lg-4">
          <motion.div className="card h-100" style={cardStyle}>
             <div className="card-header bg-transparent border-0 pt-4 pb-3 px-4">
              <h5 className="fw-bolder text-dark mb-0 d-flex align-items-center"><Activity className="text-primary me-2" size={24} /> Ações Rápidas</h5>
            </div>
            <div className="card-body px-4">
              <div className="d-flex flex-column gap-3">
                <button className="btn btn-outline-primary d-flex align-items-center p-3 rounded-4" onClick={() => window.location.href='/includes/almox_entradas/listagem'}>
                  <div className="bg-primary bg-opacity-10 p-2 rounded-3 me-3"><Plus size={24} className="text-primary" /></div>
                  <div className="text-start">
                    <h6 className="mb-0 fw-bold">Registrar Entrada</h6>
                    <small className="text-muted">Adicionar NF de peças</small>
                  </div>
                </button>
                <button className="btn btn-outline-danger d-flex align-items-center p-3 rounded-4" onClick={() => window.location.href='/includes/almox_pedidos_princ/listagem'}>
                  <div className="bg-danger bg-opacity-10 p-2 rounded-3 me-3"><Layers size={24} className="text-danger" /></div>
                  <div className="text-start">
                    <h6 className="mb-0 fw-bold">Fornecer Material</h6>
                    <small className="text-muted">Despachar peças para OS</small>
                  </div>
                </button>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </motion.div>
  );
}
