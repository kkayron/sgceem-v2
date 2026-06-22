import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';

export default function ConfiguracoesAdmin() {
  const [configs, setConfigs] = useState({
    modo_manutencao: '0',
    mes_fechado: '0',
    master_frota_override: '1'
  });
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [mensagem, setMensagem] = useState('');

  const [usuarioReset, setUsuarioReset] = useState('');
  const [resetMsg, setResetMsg] = useState('');

  useEffect(() => {
    // Bloqueio Anti-Curiosos no Frontend
    const userData = JSON.parse(localStorage.getItem('sgceem_user') || '{}');
    if (userData.role_id !== 1 && userData.role_id !== 16) {
        window.location.href = '/home';
        return;
    }
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [resConfig, resAudit] = await Promise.all([
        fetch('/api/v1/admin/config', { headers: { 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` } }),
        fetch('/api/v1/admin/auditoria', { headers: { 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` } })
      ]);
      
      const dataConfig = await resConfig.json();
      const dataAudit = await resAudit.json();

      if (dataConfig.status === 'sucesso') {
        const cMap = {};
        dataConfig.dados.forEach(c => cMap[c.chave] = c.valor);
        setConfigs(cMap);
      }
      
      if (dataAudit.status === 'sucesso') {
        setLogs(dataAudit.logs);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleSaveConfigs = async () => {
    setSaving(true);
    try {
      const res = await fetch('/api/v1/admin/config', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}`
        },
        body: JSON.stringify(configs)
      });
      const data = await res.json();
      if (data.status === 'sucesso') {
        setMensagem('Configurações Salvas com sucesso!');
        setTimeout(() => setMensagem(''), 3000);
      }
    } catch (err) {
      setMensagem('Erro ao salvar');
    } finally {
      setSaving(false);
    }
  };

  const handleResetSenha = async (e) => {
    e.preventDefault();
    try {
      const res = await fetch('/api/v1/admin/reset-senha', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}`
        },
        body: JSON.stringify({ usuario_id: usuarioReset })
      });
      const data = await res.json();
      setResetMsg(data.mensagem);
      setUsuarioReset('');
      setTimeout(() => setResetMsg(''), 4000);
    } catch(err) {
      setResetMsg('Erro ao resetar.');
    }
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="page-inner">
      <div className="d-flex align-items-center mb-4 pt-3">
        <i className="fas fa-sliders-h fa-3x me-3 text-primary"></i>
        <div>
          <h3 className="fw-bold mb-0">Configurações Globais da OM</h3>
          <p className="mb-0 text-muted">Painel exclusivo do Administrador de Sistema.</p>
        </div>
      </div>

      <div className="row">
        {/* Painel Esquerdo */}
        <div className="col-md-6">
          <div className="card shadow-sm border-0 rounded-4 mb-4">
            <div className="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <h5 className="fw-bold mb-0">Trava de Processos</h5>
              <button className="btn btn-primary btn-sm" onClick={handleSaveConfigs} disabled={saving}>
                {saving ? 'Salvando...' : 'Salvar Alterações'}
              </button>
            </div>
            <div className="card-body p-4">
              {mensagem && <div className="alert alert-success">{mensagem}</div>}
              
              <div className="mb-4">
                <label className="fw-bold d-block mb-2">Fechamento de Mês (Financeiro/Almox)</label>
                <select 
                  className="form-select border-0 bg-light"
                  value={configs.mes_fechado || '0'}
                  onChange={e => setConfigs({...configs, mes_fechado: e.target.value})}
                >
                  <option value="0">Aberto (Permite Editar/Criar Empenhos Retroativos)</option>
                  <option value="1">Fechado (Trava Total - Apenas Leitura)</option>
                </select>
                <small className="text-muted">Use no dia 31 para impedir alterações de auxiliares.</small>
              </div>

              <div className="mb-4">
                <label className="fw-bold d-block mb-2">Override de Frota Mestre</label>
                <select 
                  className="form-select border-0 bg-light"
                  value={configs.master_frota_override || '1'}
                  onChange={e => setConfigs({...configs, master_frota_override: e.target.value})}
                >
                  <option value="1">Ativado (Admin pode autorizar saída negada pelo Gerente)</option>
                  <option value="0">Desativado</option>
                </select>
              </div>

            </div>
          </div>

          <div className="card shadow-sm border-0 rounded-4">
            <div className="card-header bg-white border-0 pt-4 pb-0">
              <h5 className="fw-bold mb-0 text-danger">Ações Rápidas</h5>
            </div>
            <div className="card-body p-4">
              <label className="fw-bold d-block mb-2">Resetar Senha de Militar (Para "123456")</label>
              <form onSubmit={handleResetSenha} className="d-flex gap-2">
                <input 
                  type="number" 
                  className="form-control border-0 bg-light" 
                  placeholder="ID do Usuário" 
                  value={usuarioReset}
                  onChange={e => setUsuarioReset(e.target.value)}
                  required
                />
                <button type="submit" className="btn btn-danger">Resetar</button>
              </form>
              {resetMsg && <small className="text-success mt-2 d-block fw-bold">{resetMsg}</small>}

              <hr className="my-4" />
              
              <label className="fw-bold d-block mb-2 text-warning"><i className="fas fa-hdd"></i> Backup Estratégico (Botão de Ouro)</label>
              <p className="text-muted small mb-3">Extrai um snapshot completo criptografado do banco de dados operacional.</p>
              <a 
                href="/api/v1/system/backup" 
                target="_blank" 
                className="btn btn-warning w-100 fw-bold d-flex align-items-center justify-content-center"
              >
                <i className="fas fa-download me-2"></i> Extrair Snapshot do Banco
              </a>
            </div>
          </div>
        </div>

        {/* Painel Direito (Auditoria) */}
        <div className="col-md-6">
          <div className="card shadow-sm border-0 rounded-4 h-100">
            <div className="card-header bg-white border-0 pt-4 pb-0">
              <h5 className="fw-bold mb-0"><i className="fas fa-list-alt me-2 text-primary"></i> Auditoria Recente</h5>
            </div>
            <div className="card-body p-4">
              {loading ? <p>Carregando logs...</p> : (
                <div 
                  className="bg-dark text-white p-3 rounded-3" 
                  style={{ height: '400px', overflowY: 'auto', fontSize: '12px', fontFamily: 'monospace' }}
                >
                  {logs.map((log, i) => (
                    <div key={i} className="mb-2 pb-2 border-bottom border-secondary">{log}</div>
                  ))}
                  {logs.length === 0 && <span className="text-muted">Nenhum log encontrado.</span>}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
