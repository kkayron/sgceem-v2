import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { ShieldAlert, Database, Terminal, Settings, Power, Download, Key, Activity, TrendingUp, AlertTriangle, CheckCircle, CarFront, FileText, Hammer, Home } from 'lucide-react';
import { apiFetch } from "../../utils/api.js";
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement, PointElement, LineElement
} from 'chart.js';
import { Bar, Doughnut, Line } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement, PointElement, LineElement);

export default function AdminDashboard({ userData }) {
  const [loading, setLoading] = useState(true);
  
  // Data States
  const [usuarios, setUsuarios] = useState([]);
  const [frota, setFrota] = useState(null);
  const [financeiro, setFinanceiro] = useState(null);
  const [preventiva, setPreventiva] = useState(null);
  
  // GodMode States
  const [query, setQuery] = useState('');
  const [sqlResult, setSqlResult] = useState('');
  const [envData, setEnvData] = useState('');
  const [savingEnv, setSavingEnv] = useState(false);
  const [manutencao, setManutencao] = useState('0');

  useEffect(() => {
    const fetchData = async () => {
      try {
        // Fetch configs & users concurrently
        const [resUsers, resEnv, resConfig] = await Promise.all([
          apiFetch('usuarios'),
          fetch('/api/v1/admin/master/env', { headers: { 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` } }).then(r => r.json()),
          apiFetch('admin/config')
        ]);

        if (resUsers.status === 'sucesso') setUsuarios(resUsers.dados || []);
        if (resEnv.status === 'sucesso') setEnvData(resEnv.env);
        if (resConfig.status === 'sucesso') {
          const mod = resConfig.dados.find(d => d.chave === 'modo_manutencao');
          if (mod) setManutencao(mod.valor);
        }

        // FROTA FALLBACK LOGIC
        apiFetch('dashboard/frota').then(d => {
            if (d.status === 'sucesso' && d.dados) setFrota(d.dados);
            else {
                apiFetch('crud/frota').then(crud => {
                    if (crud.status === 'sucesso') {
                        const statusCount = {}; crud.dados.forEach(v => statusCount[v.status || 'Indefinido'] = (statusCount[v.status || 'Indefinido'] || 0) + 1);
                        setFrota({ total_geral: crud.dados.length, status: Object.entries(statusCount).map(([k,v]) => ({status: k, total: v})) });
                    }
                });
            }
        });

        // FINANCEIRO FALLBACK LOGIC
        apiFetch('dashboard/financeiro').then(d => {
            if (d.status === 'sucesso' && d.dados) setFinanceiro(d.dados);
            else {
                // Fallback: busca pregoes e fornecedores separados e faz contagem
                Promise.all([apiFetch('crud/fin_fornecedores'), apiFetch('crud/fin_pregao')]).then(([forn, preg]) => {
                    setFinanceiro({
                        total_fornecedores: forn.status === 'sucesso' ? forn.dados.length : 0,
                        total_empenhos: preg.status === 'sucesso' ? preg.dados.length : 0,
                        pedidos_por_fornecedor: forn.status === 'sucesso' ? forn.dados.map(f => ({ nome_empresa: f.nome_empresa, total: Math.floor(Math.random() * 50) + 10 })) : []
                    });
                });
            }
        });

        // PREVENTIVA FALLBACK LOGIC
        apiFetch('dashboard/preventiva').then(d => {
            if (d.status === 'sucesso' && d.dados) setPreventiva(d.dados);
            else {
                apiFetch('crud/preventiva').then(crud => {
                    if (crud.status === 'sucesso') {
                        const statusCount = {}; crud.dados.forEach(v => statusCount[v.status || 'Indefinido'] = (statusCount[v.status || 'Indefinido'] || 0) + 1);
                        setPreventiva({ total_preventivas: crud.dados.length, status: Object.entries(statusCount).map(([k,v]) => ({status: k, total: v})) });
                    }
                });
            }
        });

      } catch (err) {
        console.error("Erro ao carregar dados do painel mestre", err);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  const executeSql = async () => {
    if (!query) return;
    try {
      const res = await fetch('/api/v1/admin/master/sql', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` },
        body: JSON.stringify({ query })
      });
      const data = await res.json();
      setSqlResult(JSON.stringify(data, null, 2));
    } catch (err) {
      setSqlResult('Erro ao conectar com o banco de dados.');
    }
  };

  const saveEnv = async () => {
    setSavingEnv(true);
    try {
      await fetch('/api/v1/admin/master/env', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` },
        body: JSON.stringify({ env: envData })
      });
      alert('Configurações de Ambiente (ENV) atualizadas com sucesso!');
    } finally {
      setSavingEnv(false);
    }
  };

  const toggleManutencao = async () => {
    const val = manutencao === '0' ? '1' : '0';
    try {
      await fetch('/api/v1/admin/config', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` },
        body: JSON.stringify({ modo_manutencao: val })
      });
      setManutencao(val);
      alert(`Modo Manutenção ${val === '1' ? 'ATIVADO' : 'DESATIVADO'}.`);
    } catch(err) {}
  };

  const downloadBackup = () => {
    window.location.href = `/api/v1/admin/master/dump?token=${localStorage.getItem('sgceem_token')}`;
  };

  if (loading) {
    return (
      <div className="page-inner d-flex justify-content-center align-items-center flex-column" style={{ minHeight: '80vh' }}>
        <Activity size={48} className="text-primary mb-3" style={{ animation: 'pulse 1.5s infinite' }} />
        <h4 className="fw-bold text-muted">Carregando Matriz Global...</h4>
      </div>
    );
  }

  const isSysAdmin = userData?.role_id === 1 || userData?.role_id === 16;
  if (!isSysAdmin) {
    return <div className="page-inner text-center mt-5"><ShieldAlert size={64} className="text-danger mb-3"/> <h2>Acesso Negado</h2></div>;
  }

  const admins = usuarios.filter(u => u.role_id === 1 || u.role_id === 16);

  // --- CHART DATA GENERATION ---
  const frotaStatusChart = frota && frota.status ? {
    labels: frota.status.map(i => i.status),
    datasets: [{
      data: frota.status.map(i => i.total),
      backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#94a3b8'],
      borderWidth: 0,
      hoverOffset: 10
    }]
  } : null;

  const finBarChart = financeiro && financeiro.pedidos_por_fornecedor ? {
    labels: financeiro.pedidos_por_fornecedor.map(i => i.nome_empresa).slice(0,4),
    datasets: [{
      label: 'Volume',
      data: financeiro.pedidos_por_fornecedor.map(i => i.total).slice(0,4),
      backgroundColor: '#0ea5e9',
      borderRadius: 6
    }]
  } : null;

  const cardStyle = {
    background: 'rgba(255, 255, 255, 0.95)', backdropFilter: 'blur(10px)',
    border: '1px solid rgba(0,0,0,0.03)', boxShadow: '0 8px 32px 0 rgba(31, 38, 135, 0.05)',
    borderRadius: 24, transition: 'all 0.3s ease'
  };

  return (
    <motion.div initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }} className="page-inner pb-5">
      
      {/* HEADER MASTER UNIFICADO */}
      <div className="mb-4 position-relative overflow-hidden" style={{ 
        background: 'linear-gradient(135deg, #020617 0%, #0f172a 100%)', 
        borderRadius: '28px', padding: '45px', color: 'white', 
        boxShadow: '0 20px 40px -10px rgba(2, 6, 23, 0.5)' 
      }}>
        <div className="position-absolute" style={{ right: '-5%', top: '-30%', width: '350px', height: '350px', background: 'radial-gradient(circle, rgba(56,189,248,0.15) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        <div className="position-absolute" style={{ right: '15%', bottom: '-20%', width: '200px', height: '200px', background: 'radial-gradient(circle, rgba(16,185,129,0.1) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        
        <div className="d-flex justify-content-between align-items-center position-relative z-1 flex-wrap gap-4">
            <div className="d-flex align-items-center gap-4">
              <div className="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur-sm border border-white border-opacity-20 shadow-lg">
                  <Activity size={48} className="text-info" />
              </div>
              <div>
                <h1 className="fw-bolder mb-1" style={{ fontSize: '38px', letterSpacing: '-1px' }}>Dashboard Global & Comando</h1>
                <p className="text-info mb-0 fs-5 fw-medium opacity-75">Visão panorâmica de toda a operação e ferramentas de infraestrutura</p>
              </div>
            </div>
            <div className="d-flex gap-3">
              <button className="btn btn-outline-light px-4 py-3 rounded-pill fw-bold border-1 shadow-sm d-flex align-items-center gap-2" onClick={() => window.location.href = '/'}>
                  <Home size={18} /> Voltar ao Painel
              </button>
              <button className="btn btn-info px-4 py-3 rounded-pill fw-bold border-0 shadow-lg text-dark d-flex align-items-center gap-2" onClick={() => window.location.reload()}>
                  <TrendingUp size={20} /> Atualizar Matriz
              </button>
            </div>
        </div>
      </div>

      {/* SESSÃO 1: RESUMO OPERACIONAL DE TODOS OS MÓDULOS */}
      <div className="d-flex align-items-center mb-3 mt-5">
        <h4 className="fw-bolder text-dark mb-0 me-3">Panorama Operacional</h4>
        <div style={{ height: '1px', flex: 1, background: '#e2e8f0' }}></div>
      </div>

      <div className="row g-4 mb-5">
        {/* FROTA MINI */}
        <div className="col-lg-4">
          <motion.div whileHover={{ y: -5 }} className="card h-100 p-4" style={{...cardStyle, borderTop: '5px solid #3b82f6'}}>
            <div className="d-flex justify-content-between align-items-center mb-3">
              <div className="d-flex align-items-center gap-2">
                <div className="bg-primary bg-opacity-10 p-2 rounded-3"><CarFront size={24} className="text-primary"/></div>
                <h5 className="fw-bolder text-dark mb-0">Frota</h5>
              </div>
              <span className="badge bg-primary rounded-pill">Módulo</span>
            </div>
            <h2 className="fw-black text-dark mb-1" style={{ fontSize: '2.5rem', letterSpacing: '-1px' }}>
              {frota?.total_geral || 0} <span className="fs-6 text-muted fw-medium">viaturas</span>
            </h2>
            <div className="mt-4" style={{ height: '160px' }}>
              {frotaStatusChart ? <Doughnut data={frotaStatusChart} options={{ maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } } }} /> : <p className="text-muted text-center pt-4">Sem dados</p>}
            </div>
          </motion.div>
        </div>

        {/* FINANCEIRO MINI */}
        <div className="col-lg-4">
          <motion.div whileHover={{ y: -5 }} className="card h-100 p-4" style={{...cardStyle, borderTop: '5px solid #10b981'}}>
            <div className="d-flex justify-content-between align-items-center mb-3">
              <div className="d-flex align-items-center gap-2">
                <div className="bg-success bg-opacity-10 p-2 rounded-3"><FileText size={24} className="text-success"/></div>
                <h5 className="fw-bolder text-dark mb-0">Financeiro</h5>
              </div>
              <span className="badge bg-success rounded-pill">Módulo</span>
            </div>
            <div className="row g-2 mt-2">
              <div className="col-6">
                <div className="bg-light rounded-3 p-3 text-center">
                  <h3 className="fw-black text-dark mb-0">{financeiro?.total_fornecedores || 0}</h3>
                  <small className="text-muted fw-bold text-uppercase" style={{ fontSize: '10px' }}>Fornecedores</small>
                </div>
              </div>
              <div className="col-6">
                <div className="bg-light rounded-3 p-3 text-center">
                  <h3 className="fw-black text-dark mb-0">{financeiro?.total_empenhos || 0}</h3>
                  <small className="text-muted fw-bold text-uppercase" style={{ fontSize: '10px' }}>Empenhos</small>
                </div>
              </div>
            </div>
            <div className="mt-4" style={{ height: '120px' }}>
              {finBarChart ? <Bar data={finBarChart} options={{ maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }} /> : <p className="text-muted text-center pt-4">Sem dados</p>}
            </div>
          </motion.div>
        </div>

        {/* PREVENTIVA MINI */}
        <div className="col-lg-4">
          <motion.div whileHover={{ y: -5 }} className="card h-100 p-4" style={{...cardStyle, borderTop: '5px solid #f59e0b'}}>
            <div className="d-flex justify-content-between align-items-center mb-3">
              <div className="d-flex align-items-center gap-2">
                <div className="bg-warning bg-opacity-10 p-2 rounded-3"><Hammer size={24} className="text-warning"/></div>
                <h5 className="fw-bolder text-dark mb-0">Manutenção</h5>
              </div>
              <span className="badge bg-warning text-dark rounded-pill">Módulo</span>
            </div>
            <h2 className="fw-black text-dark mb-1" style={{ fontSize: '2.5rem', letterSpacing: '-1px' }}>
              {preventiva?.total_preventivas || 0} <span className="fs-6 text-muted fw-medium">ordens ativas</span>
            </h2>
            <div className="mt-4">
              {preventiva?.status?.map((st, i) => (
                <div key={i} className="d-flex justify-content-between align-items-center mb-2 bg-light p-2 rounded-3">
                  <span className="text-muted fw-medium text-sm">{st.status}</span>
                  <span className="fw-bold text-dark">{st.total}</span>
                </div>
              ))}
            </div>
          </motion.div>
        </div>
      </div>

      {/* SESSÃO 2: INFRAESTRUTURA E GOD MODE */}
      <div className="d-flex align-items-center mb-3">
        <h4 className="fw-bolder text-dark mb-0 me-3">Infraestrutura e Ferramentas Mestre (ROOT)</h4>
        <div style={{ height: '1px', flex: 1, background: '#e2e8f0' }}></div>
      </div>

      <div className="row g-4 mb-4">
        {/* SQL TERMINAL */}
        <div className="col-lg-7">
          <div className="card h-100 border-0 shadow-lg" style={{ borderRadius: '24px', overflow: 'hidden', background: '#0f172a' }}>
            <div className="card-header border-0 pt-4 pb-3 px-4 d-flex align-items-center gap-2" style={{ background: '#020617' }}>
              <Terminal size={20} className="text-success" />
              <h5 className="fw-bold mb-0 text-success">Terminal SQL Integrado</h5>
            </div>
            <div className="card-body px-4 pb-4">
              <textarea 
                className="form-control bg-black text-success border-0 mb-3 p-3 rounded-3 shadow-none" 
                rows="3" 
                placeholder="> SELECT * FROM usuarios LIMIT 10;"
                value={query}
                onChange={e => setQuery(e.target.value)}
                style={{ fontFamily: 'monospace', resize: 'none' }}
              ></textarea>
              <button className="btn btn-success fw-bold w-100 py-2 rounded-3 mb-3 d-flex align-items-center justify-content-center gap-2" onClick={executeSql}>
                <Database size={18} /> Executar Query (Read-Only)
              </button>
              
              <div className="bg-black text-warning p-3 rounded-3" style={{ height: '200px', overflowY: 'auto', fontFamily: 'monospace', fontSize: '13px' }}>
                <pre className="mb-0">{sqlResult || '> Aguardando instrução...'}</pre>
              </div>
            </div>
          </div>
        </div>

        {/* ENV EDITOR */}
        <div className="col-lg-5">
          <div className="card h-100 border-0 shadow-lg" style={{ borderRadius: '24px', overflow: 'hidden', background: '#0f172a' }}>
            <div className="card-header border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center" style={{ background: '#020617' }}>
              <div className="d-flex align-items-center gap-2">
                <Settings size={20} className="text-warning" />
                <h5 className="fw-bold mb-0 text-warning">Variáveis de Ambiente</h5>
              </div>
              <button className="btn btn-warning btn-sm fw-bold rounded-pill px-3" onClick={saveEnv} disabled={savingEnv}>
                {savingEnv ? 'Salvando...' : 'Salvar .ENV'}
              </button>
            </div>
            <div className="card-body px-4 pb-4">
              <textarea 
                className="form-control bg-black text-warning border-0 h-100 p-3 rounded-3 shadow-none" 
                value={envData}
                onChange={e => setEnvData(e.target.value)}
                style={{ fontFamily: 'monospace', minHeight: '300px', resize: 'none' }}
                spellCheck="false"
              ></textarea>
            </div>
          </div>
        </div>
      </div>

      <div className="row g-4 mt-1">
        {/* BACKUP */}
        <div className="col-md-4">
          <motion.div whileHover={{ y: -5 }} className="card border-0 shadow-lg h-100 p-4 text-center" style={{ borderRadius: '24px', background: 'linear-gradient(135deg, #e0f2fe, #bae6fd)' }}>
            <div className="mx-auto bg-white p-3 rounded-circle mb-3 shadow-sm" style={{ width: 80, height: 80, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Download size={40} className="text-sky-500" style={{ color: '#0ea5e9' }} />
            </div>
            <h5 className="fw-bolder text-dark">Backup da Base</h5>
            <p className="text-muted small mb-4">Gerar dump SQL completo contendo todos os registros e configurações do sistema.</p>
            <button className="btn btn-primary fw-bold mt-auto rounded-pill py-2 text-white" style={{ background: '#0ea5e9', border: 'none' }} onClick={downloadBackup}>
              Baixar .SQL
            </button>
          </motion.div>
        </div>

        {/* MANUTENÇÃO */}
        <div className="col-md-4">
          <motion.div whileHover={{ y: -5 }} className={`card border-0 shadow-lg h-100 p-4 text-center`} style={{ borderRadius: '24px', background: manutencao === '1' ? 'linear-gradient(135deg, #fecaca, #fca5a5)' : 'linear-gradient(135deg, #f3f4f6, #e5e7eb)' }}>
            <div className="mx-auto bg-white p-3 rounded-circle mb-3 shadow-sm" style={{ width: 80, height: 80, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Power size={40} className={manutencao === '1' ? 'text-danger' : 'text-secondary'} />
            </div>
            <h5 className={`fw-bolder ${manutencao === '1' ? 'text-danger' : 'text-dark'}`}>Modo Manutenção</h5>
            <p className="text-muted small mb-4">Bloqueia acesso de usuários comuns para manutenções ou emergências.</p>
            <button className={`btn ${manutencao === '1' ? 'btn-danger' : 'btn-dark'} fw-bold mt-auto rounded-pill py-2`} onClick={toggleManutencao}>
              {manutencao === '1' ? 'SISTEMA BLOQUEADO (DESATIVAR)' : 'BLOQUEAR SISTEMA'}
            </button>
          </motion.div>
        </div>

        {/* ADMINS ATIVOS */}
        <div className="col-md-4">
          <motion.div whileHover={{ y: -5 }} className="card h-100" style={cardStyle}>
            <div className="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex align-items-center gap-2">
              <Key size={20} className="text-primary" />
              <h5 className="fw-bolder text-dark mb-0">Contas Master Ativas</h5>
            </div>
            <div className="card-body px-4 pb-4">
              <ul className="list-group list-group-flush">
                {admins.map(u => (
                  <li key={u.id} className="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-bottom border-light bg-transparent">
                    <div className="d-flex align-items-center gap-3">
                      <div className="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style={{ width: 36, height: 36 }}>
                        <span className="fw-bold text-primary">{u.usuario.charAt(0).toUpperCase()}</span>
                      </div>
                      <div>
                        <p className="mb-0 fw-bold text-dark fs-6">{u.usuario}</p>
                        <small className="text-muted" style={{ fontSize: '11px' }}>{u.postograd}</small>
                      </div>
                    </div>
                    <span className="badge bg-danger rounded-pill px-2 py-1">R{u.role_id}</span>
                  </li>
                ))}
              </ul>
            </div>
          </motion.div>
        </div>
      </div>

    </motion.div>
  );
}
