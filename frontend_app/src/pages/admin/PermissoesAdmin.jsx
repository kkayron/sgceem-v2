import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';

export default function PermissoesAdmin() {
  const [roles, setRoles] = useState([]);
  const [modules, setModules] = useState([]);
  const [permissions, setPermissions] = useState({});
  const [usuarios, setUsuarios] = useState([]);
  const [selectedRole, setSelectedRole] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [mensagem, setMensagem] = useState('');

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
      const [resRoles, resModules, resPerms, resUsers] = await Promise.all([
        fetch('/api/v1/auth/roles'), // reaproveitando a rota publica de roles
        fetch('/api/v1/modules', { headers: { 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` } }),
        fetch('/api/v1/permissoes', { headers: { 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` } }),
        fetch('/api/v1/usuarios', { headers: { 'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}` } })
      ]);
      
      const dataRoles = await resRoles.json();
      const dataModules = await resModules.json();
      const dataPerms = await resPerms.json();
      const dataUsers = await resUsers.json();

      if (dataRoles.status === 'sucesso') setRoles(dataRoles.dados);
      if (dataModules.status === 'sucesso') setModules(dataModules.dados);
      if (dataUsers.status === 'sucesso') setUsuarios(dataUsers.dados || []);
      
      if (dataPerms.status === 'sucesso') {
        // Organizar permissoes num formato facil de manipular: { roleId_moduleId: { can_view: 1, ... } }
        const permMap = {};
        dataPerms.dados.forEach(p => {
          permMap[`${p.role_id}_${p.module_id}`] = p;
        });
        setPermissions(permMap);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleToggle = (moduleId, field) => {
    if (!selectedRole) return;
    
    const key = `${selectedRole}_${moduleId}`;
    const currentPerm = permissions[key] || { can_view: 0, can_create: 0, can_edit: 0, can_delete: 0 };
    
    setPermissions({
      ...permissions,
      [key]: {
        ...currentPerm,
        [field]: currentPerm[field] ? 0 : 1
      }
    });
  };

  const savePermissions = async () => {
    if (!selectedRole) return;
    setSaving(true);
    setMensagem('');

    // Pegar apenas as permissoes do role selecionado para enviar
    const rolePerms = Object.keys(permissions)
      .filter(k => k.startsWith(`${selectedRole}_`))
      .map(k => ({
        module_id: k.split('_')[1],
        ...permissions[k]
      }));

    try {
      const res = await fetch('/api/v1/permissoes', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}`
        },
        body: JSON.stringify({ role_id: selectedRole, permissoes: rolePerms })
      });
      const data = await res.json();
      if (data.status === 'sucesso') {
        setMensagem('Permissões salvas com sucesso!');
        setTimeout(() => setMensagem(''), 3000);
      }
    } catch (err) {
      setMensagem('Erro ao salvar permissões.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="page-inner">
      <div className="d-flex align-items-center mb-4 pt-3">
        <i className="fas fa-shield-alt fa-3x me-3 text-primary"></i>
        <div>
          <h3 className="fw-bold mb-0">Gerenciador de Acessos (RBAC)</h3>
          <p className="mb-0 text-muted">Defina exatamente o que cada Função (Cargo) pode visualizar, criar, editar ou excluir no sistema.</p>
        </div>
      </div>

      <div className="row">
        {/* Lista de Roles */}
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

        {/* Matriz de Permissões */}
        <div className="col-md-8">
          <div className="card shadow-sm border-0 rounded-4">
            <div className="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <h5 className="fw-bold mb-0">Matriz de Permissões</h5>
              {selectedRole && (
                <button 
                  className="btn btn-primary rounded-3 fw-bold px-4" 
                  onClick={savePermissions}
                  disabled={saving}
                >
                  {saving ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-save me-2"></i>} 
                  Salvar
                </button>
              )}
            </div>
            <div className="card-body p-4">
              {mensagem && (
                <div className="alert alert-success py-2">{mensagem}</div>
              )}
              
              {!selectedRole ? (
                <div className="text-center text-muted py-5">
                  <i className="fas fa-hand-point-left fa-3x mb-3 opacity-50"></i>
                  <h5>Selecione uma função na lista ao lado para configurar.</h5>
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>Módulo do Sistema</th>
                        <th className="text-center">Visualizar</th>
                        <th className="text-center">Criar</th>
                        <th className="text-center">Editar</th>
                        <th className="text-center">Excluir</th>
                      </tr>
                    </thead>
                    <tbody>
                      {modules.map(mod => {
                        const key = `${selectedRole}_${mod.id}`;
                        const p = permissions[key] || { can_view: 0, can_create: 0, can_edit: 0, can_delete: 0 };
                        
                        return (
                          <tr key={mod.id}>
                            <td className="fw-semibold">
                              <i className={`${mod.icon} me-2 text-primary`}></i> {mod.name}
                            </td>
                            <td className="text-center">
                              <div className="form-check form-switch d-flex justify-content-center">
                                <input className="form-check-input" type="checkbox" checked={!!p.can_view} onChange={() => handleToggle(mod.id, 'can_view')} />
                              </div>
                            </td>
                            <td className="text-center">
                              <div className="form-check form-switch d-flex justify-content-center">
                                <input className="form-check-input" type="checkbox" checked={!!p.can_create} onChange={() => handleToggle(mod.id, 'can_create')} />
                              </div>
                            </td>
                            <td className="text-center">
                              <div className="form-check form-switch d-flex justify-content-center">
                                <input className="form-check-input" type="checkbox" checked={!!p.can_edit} onChange={() => handleToggle(mod.id, 'can_edit')} />
                              </div>
                            </td>
                            <td className="text-center">
                              <div className="form-check form-switch d-flex justify-content-center">
                                <input className="form-check-input border-danger" style={p.can_delete ? {backgroundColor: '#dc3545', borderColor: '#dc3545'} : {}} type="checkbox" checked={!!p.can_delete} onChange={() => handleToggle(mod.id, 'can_delete')} />
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

          {/* Usuários com esta Função */}
          {selectedRole && (
            <div className="card shadow-sm border-0 rounded-4 mt-4">
              <div className="card-header bg-white border-0 pt-4">
                <h5 className="fw-bold mb-0"><i className="fas fa-users me-2 text-primary"></i> Militares com esta Função</h5>
              </div>
              <div className="card-body p-4">
                <ul className="list-group list-group-flush">
                  {usuarios.filter(u => Number(u.role_id) === Number(selectedRole)).length > 0 ? (
                    usuarios.filter(u => Number(u.role_id) === Number(selectedRole)).map(u => (
                      <li key={u.id} className="list-group-item d-flex justify-content-between align-items-center border-0 px-0 mb-2 bg-light rounded-3 p-3">
                        <div>
                          <h6 className="mb-0 fw-bold">{u.postograd} {u.nomeguerra || u.nomecompleto}</h6>
                          <small className="text-muted">@{u.usuario}</small>
                        </div>
                        <a href="/usuarios_listagem" className="btn btn-sm btn-outline-primary rounded-pill">
                          <i className="fas fa-edit me-1"></i> Mudar Função
                        </a>
                      </li>
                    ))
                  ) : (
                    <div className="text-center text-muted py-3">
                      <p className="mb-0">Nenhum militar está alocado nesta função no momento.</p>
                    </div>
                  )}
                </ul>
              </div>
            </div>
          )}

        </div>

      </div>
    </motion.div>
  );
}
