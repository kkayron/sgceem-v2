import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';

export default function UsuariosCadastro() {
  const [formData, setFormData] = useState({
    nomeguerra: '',
    postograd: '',
    role_id: '',
    senha: ''
  });
  
  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(false);
  const [mensagem, setMensagem] = useState({ tipo: '', texto: '' });
  
  // Posto/Graduação options baseadas nas mais comuns militares
  const postos = [
    'Sd', 'Cb', '3º Sgt', '2º Sgt', '1º Sgt', 'S Ten',
    'Asp Of', '2º Ten', '1º Ten', 'Cap', 'Maj', 'Ten Cel', 'Cel', 'Gen', 'Civil'
  ];

  useEffect(() => {
    // Carregar os cargos/funções disponíveis do banco de dados (roles)
    fetch('/api/v1/auth/roles', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}`
      }
    })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'sucesso') {
          setRoles(data.dados);
        }
      })
      .catch(err => console.error("Erro ao carregar funções:", err));
  }, []);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMensagem({ tipo: '', texto: '' });

    try {
      const response = await fetch('/api/v1/usuarios', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}`
        },
        body: JSON.stringify(formData)
      });
      
      const data = await response.json();
      
      if (response.ok && data.status === 'sucesso') {
        setMensagem({ 
          tipo: 'success', 
          texto: `${data.mensagem} O login de acesso gerado é: "${data.usuario_gerado}"` 
        });
        setFormData({ nomeguerra: '', postograd: '', role_id: '', senha: '' }); // Limpa o form
      } else {
        setMensagem({ tipo: 'danger', texto: data.mensagem || 'Erro ao cadastrar.' });
      }
    } catch (error) {
      setMensagem({ tipo: 'danger', texto: 'Erro de comunicação com o servidor.' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <motion.div 
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="page-inner"
    >
      <div className="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
        <div>
          <h3 className="fw-bold mb-3"><i className="fas fa-user-plus text-primary me-2"></i> Cadastro de Usuário</h3>
          <h6 className="op-7 mb-2">Adicionar novo militar/civil ao sistema com base em cargos.</h6>
        </div>
      </div>

      <div className="row">
        <div className="col-md-8 offset-md-2">
          <div className="card shadow-sm border-0 rounded-4">
            <div className="card-header bg-white border-0 pt-4 pb-0">
              <h5 className="fw-bold">Dados de Acesso e Perfil</h5>
            </div>
            <div className="card-body p-4">
              
              {mensagem.texto && (
                <div className={`alert alert-${mensagem.tipo} alert-dismissible fade show rounded-3`} role="alert">
                  {mensagem.texto}
                  <button type="button" className="btn-close" onClick={() => setMensagem({tipo:'',texto:''})}></button>
                </div>
              )}

              <form onSubmit={handleSubmit}>
                <div className="row g-3">
                  
                  {/* Posto / Graduação */}
                  <div className="col-md-4">
                    <label className="form-label fw-semibold">Posto/Graduação</label>
                    <select 
                      className="form-select border-0 bg-light" 
                      name="postograd" 
                      value={formData.postograd} 
                      onChange={handleChange} 
                      required
                    >
                      <option value="">Selecione...</option>
                      {postos.map(p => (
                        <option key={p} value={p}>{p}</option>
                      ))}
                    </select>
                  </div>

                  {/* Nome de Guerra */}
                  <div className="col-md-8">
                    <label className="form-label fw-semibold">Nome de Guerra</label>
                    <input 
                      type="text" 
                      className="form-control border-0 bg-light" 
                      name="nomeguerra" 
                      placeholder="Ex: SILVA"
                      value={formData.nomeguerra} 
                      onChange={handleChange} 
                      required 
                    />
                  </div>

                  {/* Função / Role */}
                  <div className="col-md-6 mt-4">
                    <label className="form-label fw-semibold">Função (Nível de Acesso)</label>
                    <select 
                      className="form-select border-0 bg-light" 
                      name="role_id" 
                      value={formData.role_id} 
                      onChange={handleChange} 
                      required
                    >
                      <option value="">Selecione a Função...</option>
                      {roles.map(role => (
                        <option key={role.id} value={role.id}>{role.name}</option>
                      ))}
                    </select>
                    <small className="text-muted">Isso definirá o que ele pode ver e editar.</small>
                  </div>

                  {/* Senha */}
                  <div className="col-md-6 mt-4">
                    <label className="form-label fw-semibold">Senha Inicial</label>
                    <input 
                      type="text" 
                      className="form-control border-0 bg-light" 
                      name="senha" 
                      placeholder="Crie uma senha de acesso..."
                      value={formData.senha} 
                      onChange={handleChange} 
                      required 
                    />
                  </div>

                </div>

                <div className="mt-5 text-end">
                  <button 
                    type="submit" 
                    className="btn btn-primary px-5 py-2 rounded-3 fw-bold" 
                    disabled={loading}
                    style={{ background: 'linear-gradient(135deg, #0d6efd 0%, #004aad 100%)', border: 'none' }}
                  >
                    {loading ? (
                      <><i className="fas fa-spinner fa-spin me-2"></i> Salvando...</>
                    ) : (
                      <><i className="fas fa-check-circle me-2"></i> Criar Usuário</>
                    )}
                  </button>
                </div>

              </form>

            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
