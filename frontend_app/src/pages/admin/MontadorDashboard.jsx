import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from "../../utils/api.js";

export default function MontadorDashboard() {
  const [roles, setRoles] = useState([]);
  const [selectedRole, setSelectedRole] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [mensagem, setMensagem] = useState('');
  
  // Aqui definimos os IDs dos widgets disponíveis no nosso Dashboard
  const [availableWidgets] = useState([
    { key: 'widget_frota_operacional', name: 'Métrica: Frota Operacional', icon: 'fas fa-car' },
    { key: 'widget_alerta_estoque', name: 'Métrica: Alerta de Estoque', icon: 'fas fa-boxes' },
    { key: 'widget_empenhos_ativos', name: 'Métrica: Empenhos Ativos', icon: 'fas fa-dollar-sign' },
    { key: 'widget_os_manutencao', name: 'Métrica: Ordens de Manutenção', icon: 'fas fa-wrench' },
    { key: 'widget_grafico_frota', name: 'Gráfico: Status da Frota', icon: 'fas fa-chart-pie' },
  ]);

  const [widgetPermissions, setWidgetPermissions] = useState({});

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
      const [dataRoles, dataWidgets] = await Promise.all([
        apiFetch('auth/roles'),
        apiFetch('widgets')
      ]);

      if (dataRoles.status === 'sucesso') setRoles(dataRoles.dados);
      
      if (dataWidgets && dataWidgets.status === 'sucesso') {
        const permMap = {};
        dataWidgets.dados.forEach(w => {
          permMap[`${w.role_id}_${w.widget_key}`] = w.is_visible;
        });
        setWidgetPermissions(permMap);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleToggle = (widgetKey) => {
    if (!selectedRole) return;
    
    const key = `${selectedRole}_${widgetKey}`;
    const currentValue = widgetPermissions[key];
    
    // Se era 1 vira 0, se era undefined ou 0 vira 1
    setWidgetPermissions({
      ...widgetPermissions,
      [key]: currentValue ? 0 : 1
    });
  };

  const savePermissions = async () => {
    if (!selectedRole) return;
    setSaving(true);
    setMensagem('');

    const roleWidgets = availableWidgets.map(w => {
      const is_visible = widgetPermissions[`${selectedRole}_${w.key}`] ? 1 : 0;
      return { widget_key: w.key, is_visible };
    });

    try {
      const data = await apiFetch('widgets', {
        method: 'POST',
        body: JSON.stringify({ role_id: selectedRole, widgets: roleWidgets })
      });
      if (data.status === 'sucesso') {
        setMensagem('Layout do Dashboard salvo com sucesso!');
        setTimeout(() => setMensagem(''), 3000);
      } else {
        setMensagem('Erro ao salvar layout no servidor.');
      }
    } catch (err) {
      setMensagem('Erro ao salvar layout (falha de rede).');
    } finally {
      setSaving(false);
    }
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="page-inner">
      <div className="d-flex align-items-center mb-4 pt-3">
        <i className="fas fa-layer-group fa-3x me-3 text-primary"></i>
        <div>
          <h3 className="fw-bold mb-0">Montador de Dashboard (Layouts)</h3>
          <p className="mb-0 text-muted">Controle cirúrgico: defina exatamente quais blocos visuais cada Função pode enxergar na tela inicial.</p>
        </div>
      </div>

      <div className="row">
        <div className="col-md-4">
          <div className="card shadow-sm border-0 rounded-4">
            <div className="card-header bg-white border-0 pt-4">
              <h5 className="fw-bold mb-0">Selecione uma Função</h5>
            </div>
            <div className="card-body p-0">
              {loading ? (
                <div className="p-4 text-center"><i className="fas fa-spinner fa-spin"></i> Carregando...</div>
              ) : (
                <div className="list-group list-group-flush rounded-bottom-4">
                  {roles.map(r => (
                    <button 
                      key={r.id} 
                      className={`list-group-item list-group-item-action py-3 px-4 ${selectedRole === r.id ? 'active bg-primary text-white border-primary' : ''}`}
                      onClick={() => setSelectedRole(r.id)}
                      style={{ transition: 'all 0.2s' }}
                    >
                      <i className="fas fa-user-tag me-2"></i> {r.name}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-md-8">
          <div className="card shadow-sm border-0 rounded-4">
            <div className="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <h5 className="fw-bold mb-0">Componentes Visuais do Painel</h5>
              {selectedRole && (
                <button 
                  className="btn btn-primary rounded-3 fw-bold px-4" 
                  onClick={savePermissions}
                  disabled={saving}
                >
                  {saving ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-save me-2"></i>} 
                  Salvar Layout
                </button>
              )}
            </div>
            <div className="card-body p-4">
              {mensagem && (
                <div className="alert alert-success py-2">{mensagem}</div>
              )}
              
              {!selectedRole ? (
                <div className="text-center text-muted py-5">
                  <i className="fas fa-cube fa-3x mb-3 opacity-50"></i>
                  <h5>Selecione uma função na lista ao lado para montar o painel.</h5>
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>Bloco Visual</th>
                        <th className="text-center">Aparece na Tela Inicial?</th>
                      </tr>
                    </thead>
                    <tbody>
                      {availableWidgets.map(widget => {
                        const key = `${selectedRole}_${widget.key}`;
                        const isVisible = !!widgetPermissions[key];
                        
                        return (
                          <tr key={widget.key}>
                            <td className="fw-semibold">
                              <i className={`${widget.icon} me-2 text-primary`}></i> {widget.name}
                            </td>
                            <td className="text-center">
                              <div className="form-check form-switch d-flex justify-content-center">
                                <input 
                                  className="form-check-input form-check-input-lg" 
                                  type="checkbox" 
                                  style={{ transform: 'scale(1.2)' }}
                                  checked={isVisible} 
                                  onChange={() => handleToggle(widget.key)} 
                                />
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
