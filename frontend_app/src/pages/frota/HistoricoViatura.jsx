import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { ArrowLeft, BookOpen, Clock, FileText, CheckCircle, Car, Plus } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { apiFetch } from "../../utils/api.js";
import { useExport } from "../../hooks/useExport.js";

export default function HistoricoViatura({ viatura, onBack }) {
  const [historicoOs, setHistoricoOs] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  const colunasPDF = [
    { key: "data_abertura", label: "Abertura" },
    { key: "id", label: "OS" },
    { key: "tipo_mnt", label: "Tipo" },
    { key: "problema", label: "Defeito/Serviço" },
    { key: "mecanico_responsavel", label: "Mecânico" },
    { key: "status", label: "Status" }
  ];

  const { exportarPDF } = useExport(`Dossiê Histórico - VTR ${viatura.placa}`, colunasPDF, historicoOs);

  useEffect(() => {
    // Busca todas as Ordens de Serviço
    apiFetch('crud/os_principal?limit=5000')
      .then(res => {
        if (res.status === 'sucesso') {
          // Filtra as OSs que pertencem a esta viatura
          // Pode estar salvo como id_frota ou como placa_vtr
          const filtradas = res.dados.filter(os => 
            String(os.id_frota) === String(viatura.id) || 
            (os.placa_vtr && os.placa_vtr === viatura.placa)
          );
          
          // Ordenar da mais recente para a mais antiga (assumindo que id maior é mais recente)
          filtradas.sort((a, b) => b.id - a.id);
          
          setHistoricoOs(filtradas);
        }
      })
      .finally(() => setLoading(false));
  }, [viatura]);

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
      const parts = dateString.split('-');
      if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
      return dateString;
    } catch(e) { return dateString; }
  };

  return (
    <motion.div initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.4 }}>
      {/* Header */}
      <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <button className="btn btn-light shadow-sm text-dark d-flex align-items-center fw-bold rounded-pill" onClick={onBack}>
          <ArrowLeft size={18} className="me-2" /> Voltar à Frota
        </button>
        <div className="d-flex gap-2">
          <button className="btn btn-outline-danger shadow-sm d-flex align-items-center fw-bold rounded-pill px-4" onClick={exportarPDF} disabled={historicoOs.length === 0}>
            <FileText size={18} className="me-2" /> Gerar Dossiê Oficial
          </button>
          <button className="btn btn-primary shadow-sm text-white d-flex align-items-center fw-bold rounded-pill px-4" onClick={() => navigate('/includes/os/listagem#autoOpen=true')}>
            <Plus size={18} className="me-2" /> Nova OS
          </button>
        </div>
      </div>

      {/* Cartão da Viatura */}
      <div className="card border-0 shadow-sm rounded-4 mb-4" style={{ background: 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)', color: 'white' }}>
        <div className="card-body p-4 d-flex align-items-center gap-4">
          <div className="bg-white bg-opacity-10 p-3 rounded-circle border border-white border-opacity-25">
            <Car size={48} className="text-white" />
          </div>
          <div>
            <span className="text-uppercase fw-bold text-white-50" style={{ letterSpacing: '2px', fontSize: '12px' }}>Livro Histórico</span>
            <h2 className="fw-black mb-1">{viatura.placa || 'Sem Placa'} <span className="text-white-50 fs-4 fw-normal">| {viatura.prefixo_sga || 'SGA ñ inf.'}</span></h2>
            <p className="mb-0 text-white-50 fs-5">{viatura.marca} {viatura.modelo}</p>
          </div>
        </div>
      </div>

      {/* Linha do Tempo de Manutenções */}
      <div className="card border-0 shadow-sm rounded-4">
        <div className="card-header bg-white border-0 p-4 d-flex align-items-center">
          <BookOpen className="text-primary me-2" size={24} />
          <h5 className="fw-bolder mb-0">Dossiê de Intervenções (Ordens de Serviço)</h5>
        </div>
        <div className="card-body p-4 pt-0">
          {loading ? (
            <div className="text-center py-5">
              <div className="spinner-border text-primary"></div>
              <p className="mt-2 text-muted fw-bold">Puxando arquivos da viatura...</p>
            </div>
          ) : historicoOs.length === 0 ? (
            <div className="text-center py-5 bg-light rounded-4">
              <CheckCircle size={48} className="text-muted opacity-25 mb-3" />
              <h6 className="text-muted fw-bold">O livro está em branco.</h6>
              <p className="text-muted small">Nenhuma ordem de serviço foi registrada no sistema para esta viatura.</p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover align-middle">
                <thead className="table-light">
                  <tr>
                    <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Data Abertura</th>
                    <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Nº OS</th>
                    <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Tipo</th>
                    <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Defeito Relatado / Causa</th>
                    <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7">Mecânico</th>
                    <th className="text-uppercase text-secondary text-xxs fw-bolder opacity-7 text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {historicoOs.map(os => (
                    <tr key={os.id}>
                      <td className="fw-medium text-muted">
                        <Clock size={14} className="me-1" />
                        {formatDate(os.data_abertura)}
                      </td>
                      <td className="fw-bold text-dark">#{os.id}</td>
                      <td><span className="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">{os.tipo_mnt || 'CORRETIVA'}</span></td>
                      <td style={{ maxWidth: '250px' }}>
                        <div className="text-truncate fw-medium text-dark">{os.problema || 'Não informado'}</div>
                      </td>
                      <td className="text-muted">{os.mecanico_responsavel || 'Não Atribuído'}</td>
                      <td className="text-center">
                        <span className={`badge rounded-pill px-3 py-2 ${os.status === 'CONCLUIDA' ? 'bg-success' : 'bg-warning text-dark'} bg-opacity-10`} 
                              style={{ border: `1px solid ${os.status === 'CONCLUIDA' ? '#10b981' : '#f59e0b'}50` }}>
                          {os.status || 'ABERTA'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </motion.div>
  );
}
