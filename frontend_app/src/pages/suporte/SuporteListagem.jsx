import { motion } from 'framer-motion';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function SuporteListagem() {
  const [tickets, setTickets] = useState([
    { id: 1, titulo: "Exemplo: Viatura não aparece na listagem", categoria: "Bug", prioridade: "Alta", status: "Aberto", data: "19/06/2026" },
  ]);

  const [form, setForm] = useState({ titulo: '', categoria: 'Bug', prioridade: 'Normal', descricao: '' });

  const criarTicket = (e) => {
    e.preventDefault();
    const novoTicket = {
      id: tickets.length + 1,
      titulo: form.titulo,
      categoria: form.categoria,
      prioridade: form.prioridade,
      status: 'Aberto',
      data: new Date().toLocaleDateString('pt-BR'),
    };
    setTickets([novoTicket, ...tickets]);
    setForm({ titulo: '', categoria: 'Bug', prioridade: 'Normal', descricao: '' });
    Swal.fire('Ticket Criado!', `Ticket #${novoTicket.id} registrado com sucesso.`, 'success');
  };

  const corPrioridade = { 'Baixa': 'success', 'Normal': 'primary', 'Alta': 'warning', 'Crítica': 'danger' };
  const corStatus = { 'Aberto': 'danger', 'Em Andamento': 'warning', 'Resolvido': 'success' };

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
      <div className="d-flex justify-content-between align-items-center py-3">
        <div>
          <h3 className="fw-bold mb-1">Central de Suporte</h3>
          <h6 className="text-muted">Registro de ocorrências e solicitações</h6>
        </div>
      </div>

      <div className="row g-4">
        <div className="col-md-5">
          <div className="card border-0 shadow-sm" style={{ borderRadius: 16 }}>
            <div className="card-header bg-white border-0 pt-4 px-4">
              <h5 className="fw-bold mb-0"><i className="fas fa-plus-circle text-primary me-2"></i>Abrir Novo Ticket</h5>
            </div>
            <div className="card-body px-4 pb-4">
              <form onSubmit={criarTicket}>
                <div className="mb-3">
                  <label className="form-label fw-semibold">Título</label>
                  <input type="text" className="form-control" required value={form.titulo} onChange={e => setForm({...form, titulo: e.target.value})} placeholder="Descreva brevemente o problema" />
                </div>
                <div className="row g-3 mb-3">
                  <div className="col-6">
                    <label className="form-label fw-semibold">Categoria</label>
                    <select className="form-select" value={form.categoria} onChange={e => setForm({...form, categoria: e.target.value})}>
                      <option value="Bug">Bug / Erro</option>
                      <option value="Melhoria">Melhoria</option>
                      <option value="Dúvida">Dúvida</option>
                      <option value="Acesso">Acesso / Permissão</option>
                    </select>
                  </div>
                  <div className="col-6">
                    <label className="form-label fw-semibold">Prioridade</label>
                    <select className="form-select" value={form.prioridade} onChange={e => setForm({...form, prioridade: e.target.value})}>
                      <option value="Baixa">Baixa</option>
                      <option value="Normal">Normal</option>
                      <option value="Alta">Alta</option>
                      <option value="Crítica">Crítica</option>
                    </select>
                  </div>
                </div>
                <div className="mb-3">
                  <label className="form-label fw-semibold">Descrição Detalhada</label>
                  <textarea className="form-control" rows="3" value={form.descricao} onChange={e => setForm({...form, descricao: e.target.value})} placeholder="Descreva os passos para reproduzir o problema..."></textarea>
                </div>
                <button type="submit" className="btn btn-primary w-100"><i className="fas fa-paper-plane me-2"></i>Enviar Ticket</button>
              </form>
            </div>
          </div>
        </div>

        <div className="col-md-7">
          <div className="card border-0 shadow-sm" style={{ borderRadius: 16 }}>
            <div className="card-header bg-white border-0 pt-4 px-4">
              <h5 className="fw-bold mb-0"><i className="fas fa-list text-warning me-2"></i>Tickets Registrados</h5>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover mb-0 align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>#</th>
                      <th>Título</th>
                      <th>Categoria</th>
                      <th>Prioridade</th>
                      <th>Status</th>
                      <th>Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    {tickets.map(t => (
                      <tr key={t.id}>
                        <td className="fw-bold">{t.id}</td>
                        <td>{t.titulo}</td>
                        <td><span className="badge bg-secondary">{t.categoria}</span></td>
                        <td><span className={`badge bg-${corPrioridade[t.prioridade] || 'secondary'}`}>{t.prioridade}</span></td>
                        <td><span className={`badge bg-${corStatus[t.status] || 'secondary'}`}>{t.status}</span></td>
                        <td className="text-muted small">{t.data}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
