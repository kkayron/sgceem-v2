import { motion } from 'framer-motion';
import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';

const API = '/api/v1';
const token = () => localStorage.getItem('sgceem_token');
const headers = () => ({ 'Authorization': `Bearer ${token()}`, 'Content-Type': 'application/json' });

export default function UsuariosListagem() {
  const [dados, setDados] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editando, setEditando] = useState(null);
  const [form, setForm] = useState({ postograd:'', nomeguerra:'', nomecompleto:'', usuario:'', senha:'', role_id:'', status:'1' });
  const [roles, setRoles] = useState([]);
  const [busca, setBusca] = useState('');

  const postos = ['Gen','Cel','TC','Maj','Capitão','1º Ten','2º Ten','Asp Of','Sten','1º Sgt','2º Sgt','3º Sgt','Cb','Sd'];

  const carregar = () => {
    setLoading(true);
    fetch(`${API}/usuarios`, { headers: headers() })
      .then(r => r.json()).then(d => { if(d.status==='sucesso') setDados(d.dados); })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    carregar();
    fetch(`${API}/auth/roles`, { headers: headers() }).then(r=>r.json()).then(d => { if(d.status==='sucesso') setRoles(d.dados); });

    const handleDbUpdate = () => {
      if (!editando) carregar();
    };
    window.addEventListener('db_updated', handleDbUpdate);
    return () => window.removeEventListener('db_updated', handleDbUpdate);
  }, []);

  const salvar = () => {
    const method = editando ? 'PUT' : 'POST';
    const url = editando ? `${API}/usuarios/${editando}` : `${API}/usuarios`;
    fetch(url, { method, headers: headers(), body: JSON.stringify(form) })
      .then(r=>r.json()).then(d => {
        if(d.status==='sucesso') {
          const texto = d.usuario_gerado ? `${d.mensagem} Login gerado: "${d.usuario_gerado}"` : d.mensagem;
          Swal.fire({ icon:'success', title: editando ? 'Atualizado!' : 'Cadastrado!', text: texto, timer: d.usuario_gerado ? 3000 : 1500, showConfirmButton:false });
          setShowModal(false); setEditando(null); setForm({ postograd:'', nomeguerra:'', nomecompleto:'', usuario:'', senha:'', role_id:'', status:'1' });
          carregar();
        } else { Swal.fire('Erro', d.mensagem, 'error'); }
      });
  };

  const editar = (u) => {
    setEditando(u.id);
    setForm({ postograd:u.postograd, nomeguerra:u.nomeguerra, nomecompleto:u.nomecompleto, usuario:u.usuario, senha:'', role_id:u.role_id, status:u.status });
    setShowModal(true);
  };

  const deletar = (id) => {
    Swal.fire({ title:'Remover usuário?', text:'Esta ação não pode ser desfeita.', icon:'warning', showCancelButton:true, confirmButtonText:'Sim, remover', cancelButtonText:'Cancelar' })
      .then(r => { if(r.isConfirmed) {
        fetch(`${API}/usuarios/${id}`, { method:'DELETE', headers: headers() }).then(r=>r.json()).then(() => { carregar(); Swal.fire('Removido!','','success'); });
      }});
  };

  return (
    <motion.div className="page-inner" initial={{ opacity:0, y:20 }} animate={{ opacity:1, y:0 }} exit={{ opacity:0, y:-20 }} transition={{ duration:0.3 }}>
      <div className="d-flex justify-content-between align-items-center py-3">
        <div><h3 className="fw-bold mb-1">Listagem de Usuários</h3><h6 className="text-muted">Gestão de acessos do sistema</h6></div>
        <div className="d-flex gap-2 flex-wrap">
          <div className="input-group" style={{ width: 250 }}>
            <input type="text" className="form-control" placeholder="Buscar usuário..." value={busca} onChange={e => setBusca(e.target.value)} />
            <span className="input-group-text"><i className="fas fa-search"></i></span>
          </div>
          <button className="btn btn-primary" onClick={() => { setEditando(null); setForm({ postograd:'', nomeguerra:'', nomecompleto:'', usuario:'', senha:'', role_id:'', status:'1' }); setShowModal(true); }}>
            <i className="fa fa-user-plus me-1"></i> Cadastrar
          </button>
        </div>
      </div>

      <div className="card card-round">
        <div className="card-body">
          {loading ? <div className="text-center py-5"><div className="spinner-border text-primary"></div></div> : (
            <div className="list-group list-group-flush">
              {dados.length === 0 && <div className="text-center text-muted py-4">Nenhum usuário encontrado.</div>}
              {dados.filter(u => !busca || (u.nomecompleto+u.nomeguerra+u.usuario+u.role_name).toLowerCase().includes(busca.toLowerCase())).map(u => (
                <div key={u.id} className={`list-group-item rounded-3 mb-3 shadow-sm border ${u.solicitacao === 'sim' ? 'border-warning bg-warning-subtle' : 'border-light'}`}>
                  <div className="d-flex align-items-center gap-3 flex-wrap">
                    <img src={`/assets/fotoperfil/${u.foto || 'default.png'}`} className="rounded-circle" width="60" height="60" style={{ objectFit:'cover' }} alt="Foto" />
                    <div className="flex-grow-1">
                      <div className="fw-bold text-uppercase">
                        {u.postograd} – {u.nomeguerra}
                        <span className={`badge bg-${u.status == '1' ? 'success' : 'secondary'} ms-2`}>{u.status == '1' ? 'Ativo' : 'Desativado'}</span>
                        {u.solicitacao === 'sim' && <span className="badge bg-warning text-dark ms-2">Solicitação</span>}
                      </div>
                      <div className="text-muted small">
                        <div><b>Função/Nível:</b> {u.role_name || '-'}</div>
                        <div><b>Usuário:</b> {u.usuario}</div>
                      </div>
                    </div>
                    <div className="d-flex gap-2 ms-auto">
                      <button className="btn btn-sm btn-outline-primary" onClick={() => editar(u)} title="Editar"><i className="fa fa-edit"></i></button>
                      <button className="btn btn-sm btn-outline-danger" onClick={() => deletar(u.id)} title="Remover"><i className="fa fa-times"></i></button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {showModal && (
        <div className="modal fade show d-block" style={{ backgroundColor:'rgba(0,0,0,.5)' }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">{editando ? 'Editar Usuário' : 'Cadastrar Usuário'}</h5>
                <button className="btn-close" onClick={() => setShowModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-3">
                  <label className="form-label">Posto/Grad</label>
                  <select className="form-select" value={form.postograd} onChange={e => setForm({...form, postograd: e.target.value})} required>
                    <option value="">Selecione</option>
                    {postos.map(p => <option key={p} value={p}>{p}</option>)}
                  </select>
                </div>
                <div className="mb-3"><label className="form-label">Nome de Guerra</label><input className="form-control" value={form.nomeguerra} onChange={e => setForm({...form, nomeguerra: e.target.value})} required /></div>
                {editando && (
                  <>
                    <div className="mb-3"><label className="form-label">Nome Completo</label><input className="form-control" value={form.nomecompleto} onChange={e => setForm({...form, nomecompleto: e.target.value})} /></div>
                    <div className="mb-3"><label className="form-label">Usuário de Login</label><input className="form-control" value={form.usuario} onChange={e => setForm({...form, usuario: e.target.value})} /></div>
                  </>
                )}
                <div className="mb-3">
                  <label className="form-label">Função (Nível de Acesso)</label>
                  <select className="form-select" value={form.role_id} onChange={e => setForm({...form, role_id: e.target.value})} required>
                    <option value="">Selecione o Nível</option>
                    {roles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                  </select>
                  <small className="text-muted">Isto define os menus e permissões do usuário.</small>
                </div>

                <div className="mb-3"><label className="form-label">Senha {editando && '(deixe vazio para manter)'}</label><input type="password" className="form-control" value={form.senha} onChange={e => setForm({...form, senha: e.target.value})} required={!editando} /></div>
                {editando && (
                  <div className="mb-3">
                    <label className="form-label">Status</label>
                    <select className="form-select" value={form.status} onChange={e => setForm({...form, status: e.target.value})}>
                      <option value="1">Ativo</option>
                      <option value="0">Desativado</option>
                    </select>
                  </div>
                )}
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setShowModal(false)}>Cancelar</button>
                <button className="btn btn-success" onClick={salvar}><i className="fa fa-save me-1"></i> {editando ? 'Salvar Alterações' : 'Cadastrar'}</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </motion.div>
  );
}
