import { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from "../../utils/api.js";
import { DollarSign, FileText, AlertTriangle, CheckCircle, TrendingUp, Plus, List, CreditCard, Cpu, Database, Activity, Zap, Receipt, ShieldAlert } from 'lucide-react';
import Swal from 'sweetalert2';

export default function DashboardFinanceiro() {
  const [empenhos, setEmpenhos] = useState([]);
  const [notas, setNotas] = useState([]);
  const [siafiCorrente, setSiafiCorrente] = useState([]);
  const [siafiRp, setSiafiRp] = useState([]);
  const [loading, setLoading] = useState(true);

  const carregarDados = () => {
    setLoading(true);
    Promise.all([
      apiFetch('crud/fin_empenhos'),
      apiFetch('crud/fin_notas_fiscais')
    ]).then(([resEmp, resNf]) => {
      setEmpenhos(resEmp.dados || []);
      setNotas(resNf.dados || []);
      setLoading(false);
    }).catch(e => {
      console.error(e);
      setLoading(false);
    });
  };

  useEffect(() => {
    carregarDados();

    // Sincronia em Tempo Real (Telemetria)
    const handleSync = () => carregarDados();
    
    window.addEventListener('db_updated', handleSync);
    window.addEventListener('focus', handleSync); // Atualiza ao voltar para a aba

    return () => {
      window.removeEventListener('db_updated', handleSync);
      window.removeEventListener('focus', handleSync);
    };
  }, []);

  const handleLancarNF = async (empenho) => {
    const { value: formValues } = await Swal.fire({
      title: 'Lançar Nota Fiscal',
      html: `
        <h5 class="text-start mb-3">Empenho: <b>${empenho.nmr_empenho}</b></h5>
        <input id="swal-input1" class="swal2-input" placeholder="Número da NF (Ex: 001.234)">
        <input id="swal-input2" type="number" step="0.01" class="swal2-input" placeholder="Valor (R$)">
        <select id="swal-input3" class="swal2-select w-100 mx-0 mt-3">
          <option value="Liquidada">Liquidada (Aprovada)</option>
          <option value="Pendente">Pendente</option>
          <option value="Paga">Paga</option>
        </select>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Registrar NF',
      cancelButtonText: 'Cancelar',
      preConfirm: () => {
        return {
          numero_nf: document.getElementById('swal-input1').value,
          valor: document.getElementById('swal-input2').value,
          status: document.getElementById('swal-input3').value,
        }
      }
    });

    if (formValues && formValues.numero_nf && formValues.valor) {
      if (Number(formValues.valor) <= 0) return Swal.fire('Erro', 'O valor deve ser maior que zero.', 'error');
      
      const payload = {
        empenho_id: empenho.id,
        numero_nf: formValues.numero_nf,
        valor: Number(formValues.valor),
        status: formValues.status,
        data_emissao: new Date().toISOString().split('T')[0]
      };

      try {
        const res = await apiFetch('crud/fin_notas_fiscais', 'POST', payload);
        if (res.status === 'sucesso') {
          Swal.fire('Sucesso!', 'Nota Fiscal registrada com sucesso.', 'success');
          carregarDados(); // Recarrega para atualizar saldos (via Trigger do BD)
        } else {
          Swal.fire('Erro', res.mensagem || 'Falha ao registrar.', 'error');
        }
      } catch (e) {
        Swal.fire('Erro', 'Erro de comunicação.', 'error');
      }
    }
  };

  const fileInputNERef = useRef(null);
  const fileInputNFRef = useRef(null);

  const handleExtrator = async (file, rotaDestino) => {
    if (!file) return;
    
    const formData = new FormData();
    formData.append('documento', file);
    
    Swal.fire({ title: 'Processando com IA Python...', text: `Lendo ${file.name}...`, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    try {
        const token = localStorage.getItem('sgceem_token');
        const res = await fetch('/api/v1/extract_document', {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            const extracao = data.dados;
            
            let htmlResumo = `
              <div class="text-start small">
                <b>Tipo:</b> ${data.tipo}<br>
                <b>CNPJ:</b> ${extracao.cnpj_fornecedor || 'Não encontrado'}<br>
                <b>Fornecedor:</b> ${extracao.nome_empresa || 'Não encontrado'}<br>
                <b>Nº Documento:</b> ${extracao.numero_nf || 'Não encontrado'}<br>
                ${extracao.chave_acesso ? `<b>Chave de Acesso:</b> ${extracao.chave_acesso}<br>` : ''}
                ${extracao.natureza_despesa ? `<b>Natureza da Despesa:</b> ${extracao.natureza_despesa}<br>` : ''}
                ${extracao.data_emissao ? `<b>Data de Emissão:</b> ${extracao.data_emissao}<br>` : ''}
                <hr class="my-2">
                ${extracao.descricao_empenho ? `<b>Descrição:</b> <small>${extracao.descricao_empenho}</small><br>` : ''}
                <b>Item de Compra/Serviço:</b> ${extracao.item_descricao || '-'}<br>
                <b>Quantidade:</b> ${extracao.quantidade_item || '-'}<br>
                <b>Valor Unitário:</b> R$ ${extracao.valor_unitario || extracao.valor_produto || '0,00'}<br>
                <b class="text-success">Valor Total:</b> R$ ${extracao.valor_total || '0,00'}
              </div>
            `;

            Swal.fire({
              title: `Extrator IA: ${data.tipo}`,
              html: htmlResumo,
              icon: 'success',
              confirmButtonText: 'Preencher Formulário'
            }).then((result) => {
              if(result.isConfirmed) {
                  // Salva os dados no localStorage para o CrudTable capturar ao abrir
                  localStorage.setItem('sgceem_extrator_data', JSON.stringify({
                     ...extracao,
                     nmr_empenho: data.tipo === 'Nota de Empenho' ? extracao.numero_nf : ''
                  }));
                  window.location.href = `${rotaDestino}?autoOpen=true&extrator=true`;
              }
            });
        } else {
            Swal.fire('Erro na Extração', data.mensagem || 'O motor IA não conseguiu processar o documento.', 'error');
        }
    } catch (e) {
        Swal.fire('Erro Técnico', 'Falha ao comunicar com o Motor de IA.', 'error');
    }
  };

  const cardStyle = {
    background: 'rgba(255, 255, 255, 0.95)',
    backdropFilter: 'blur(10px)',
    border: '1px solid rgba(0,0,0,0.03)',
    boxShadow: '0 8px 32px 0 rgba(31, 38, 135, 0.05)',
    borderRadius: 24,
    transition: 'all 0.3s ease'
  };

  const totalEmpenhado = empenhos.reduce((acc, curr) => acc + Number(curr.valor_total || 0), 0);
  const totalRetido = empenhos.reduce((acc, curr) => acc + Number(curr.saldo_retido || 0), 0);
  const totalConsumido = empenhos.reduce((acc, curr) => acc + Number(curr.saldo_consumido || 0), 0);
  const totalDisponivel = totalEmpenhado - totalRetido - totalConsumido;

  const statCards = [
    { label: "Dotação Global (SIAFI)", value: `R$ ${totalEmpenhado.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`, icon: <DollarSign size={32} />, color: "#3b82f6", bg: "linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05))" },
    { label: "Total Pago / S4", value: `R$ ${totalConsumido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`, icon: <CheckCircle size={32} />, color: "#10b981", bg: "linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05))" },
    { label: "Provisão Retida (NFs)", value: `R$ ${totalRetido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`, icon: <AlertTriangle size={32} />, color: "#f59e0b", bg: "linear-gradient(135deg, rgba(245,158,11,0.2), rgba(245,158,11,0.05))" },
    { label: "Saldo Global Disponível", value: `R$ ${totalDisponivel.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`, icon: <CreditCard size={32} />, color: "#1e293b", bg: "linear-gradient(135deg, rgba(30,41,59,0.2), rgba(30,41,59,0.05))" },
  ];

  // Lógica da Esquerda: Top 5 Empenhos Críticos
  const empenhosCriticos = [...empenhos].map(e => {
    const vt = Number(e.valor_total || 0);
    const consumidoGlobal = Number(e.saldo_consumido || 0) + Number(e.saldo_retido || 0);
    const pct = vt > 0 ? (consumidoGlobal / vt) * 100 : 0;
    return { ...e, pct, consumidoGlobal, vt };
  }).sort((a, b) => b.pct - a.pct).slice(0, 5);

  // Lógica da Direita: Últimas 5 NFs
  const ultimasNFs = [...notas].sort((a, b) => new Date(b.data_emissao || 0) - new Date(a.data_emissao || 0)).slice(0, 5);

  if (loading) {
    return (
      <div className="page-inner d-flex justify-content-center align-items-center" style={{ minHeight: '80vh' }}>
        <div className="spinner-border text-success" style={{ width: 48, height: 48 }}></div>
      </div>
    );
  }

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
      <div className="mb-4 position-relative overflow-hidden" style={{ 
        background: 'linear-gradient(135deg, #064e3b 0%, #022c22 100%)', 
        borderRadius: '28px', padding: '45px', color: 'white', 
        boxShadow: '0 20px 40px -10px rgba(6, 78, 59, 0.4)' 
      }}>
        <div className="position-absolute" style={{ right: '-5%', top: '-30%', width: '350px', height: '350px', background: 'radial-gradient(circle, rgba(16,185,129,0.15) 0%, rgba(0,0,0,0) 70%)', borderRadius: '50%' }}></div>
        <div className="d-flex justify-content-between align-items-center position-relative z-1 flex-wrap gap-4">
            <div className="d-flex align-items-center gap-4">
              <motion.div whileHover={{ rotate: 15 }} className="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur-sm border border-white border-opacity-20 shadow-lg">
                  <CreditCard size={48} className="text-success" />
              </motion.div>
              <div>
                <h1 className="fw-bolder mb-1" style={{ fontSize: '36px', letterSpacing: '-1px' }}>Centro Financeiro</h1>
                <p className="text-success mb-0 fs-5 fw-medium opacity-75">Gestão tática de Empenhos e Notas Fiscais</p>
              </div>
            </div>
            <motion.button whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="btn btn-success shadow-lg px-4 py-3 rounded-pill fw-bold border-0 text-white d-flex align-items-center" onClick={carregarDados}>
                Atualizar Matriz
            </motion.button>
        </div>
      </div>

      <div className="row g-4 mb-4">
        {statCards.map((card, i) => (
          <motion.div whileHover={{ y: -8 }} className="col-xl-3 col-lg-6" key={i}>
            <div className="card h-100 p-4" style={{ ...cardStyle, display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <div>
                <p className="text-muted fw-bolder mb-2 text-uppercase" style={{ fontSize: '12px', letterSpacing: '1px' }}>{card.label}</p>
                <div className="d-flex align-items-baseline gap-2">
                  <h2 className="fw-black mb-0 text-dark" style={{ fontSize: card.value.toString().length > 10 ? '1.8rem' : '2.2rem', fontWeight: '900', letterSpacing: '-1px' }}>{card.value}</h2>
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
        {/* Lado Esquerdo: Radar de Empenhos Críticos */}
        <div className="col-lg-6">
          <motion.div className="card h-100" style={cardStyle}>
            <div className="card-header bg-transparent border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
              <h5 className="fw-bolder text-dark mb-0 d-flex align-items-center"><ShieldAlert className="text-danger me-2" size={24} /> Alertas de Queima de Empenho</h5>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 px-4">Nº Empenho</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Consumo Real</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Disp.</th>
                    </tr>
                  </thead>
                  <tbody>
                    {empenhosCriticos.length === 0 ? (
                      <tr><td colSpan="3" className="text-center py-4 text-muted">Sem empenhos registrados.</td></tr>
                    ) : empenhosCriticos.map(e => {
                      const pctArredondado = Math.min(e.pct, 100).toFixed(0);
                      const barColor = e.pct < 50 ? 'bg-success' : e.pct < 85 ? 'bg-warning' : 'bg-danger';
                      return (
                      <tr key={e.id}>
                        <td className="px-4 py-3">
                          <div className="fw-bold text-dark fs-6">{e.nmr_empenho || 'N/A'}</div>
                          <div className="text-muted text-xs text-truncate" style={{ maxWidth: '150px' }}>{e.categoria || '-'}</div>
                        </td>
                        <td className="text-center px-3" style={{ width: '40%' }}>
                          <div className="d-flex justify-content-between align-items-center mb-1">
                            <span className="text-xs fw-bold text-muted">R$ {e.consumidoGlobal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                            <span className={`text-xs fw-bolder ${e.pct >= 85 ? 'text-danger' : 'text-dark'}`}>{pctArredondado}%</span>
                          </div>
                          <div className="progress" style={{ height: '6px', borderRadius: '3px' }}>
                            <div className={`progress-bar ${barColor}`} role="progressbar" style={{ width: `${pctArredondado}%` }}></div>
                          </div>
                        </td>
                        <td className="text-center px-4">
                           <span className="badge bg-light text-dark border">
                             R$ {(e.vt - e.consumidoGlobal).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                           </span>
                        </td>
                      </tr>
                    )})}
                  </tbody>
                </table>
              </div>
            </div>
          </motion.div>
        </div>

        {/* Lado Direito: Fila de Trabalho (Últimas NFs) */}
        <div className="col-lg-6">
          <motion.div className="card h-100" style={cardStyle}>
            <div className="card-header bg-transparent border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
              <h5 className="fw-bolder text-dark mb-0 d-flex align-items-center"><Activity className="text-primary me-2" size={24} /> Fila de Trabalho (Últimas NFs)</h5>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 px-4">Número NF</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Valor</th>
                      <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {ultimasNFs.length === 0 ? (
                      <tr><td colSpan="3" className="text-center py-4 text-muted">Nenhuma NF recente.</td></tr>
                    ) : ultimasNFs.map(n => {
                      const s = n.status || 'Pendente';
                      const badgeClass = s === 'Paga' ? 'bg-success' : s.includes('Retid') || s === 'Pendente' || s === 'No Destacamento' ? 'bg-warning text-dark' : 'bg-secondary';
                      return (
                      <tr key={n.id}>
                        <td className="px-4 py-3">
                          <div className="fw-bold text-dark fs-6">{n.numero_nf || 'S/N'}</div>
                          <div className="text-muted text-xs">Empenho: {n.numero_empenho || n.empenho_id}</div>
                        </td>
                        <td>
                           <div className="fw-bolder text-dark">R$ {Number(n.valor_total || n.valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                           <div className="text-muted text-xs">{n.data_emissao ? n.data_emissao.split('-').reverse().join('/') : '-'}</div>
                        </td>
                        <td className="text-center px-4">
                           <span className={`badge ${badgeClass}`}>{s}</span>
                        </td>
                      </tr>
                    )})}
                  </tbody>
                </table>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </motion.div>
  );
}
