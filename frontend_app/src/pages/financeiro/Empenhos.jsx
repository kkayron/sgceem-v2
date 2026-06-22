import React, { useState, useEffect } from 'react';
import CrudTable from "../../components/CrudTable.jsx";
import { apiFetch } from "../../utils/api.js";

export default function Empenhos() {
  const customColumns = [
    { key: 'nmr_empenho', label: 'Nº do Empenho', type: 'text', required: true },
    { key: 'cnpj_fornecedor', label: 'CNPJ Fornecedor', type: 'text', required: true },
    { key: 'nome_empresa', label: 'Fornecedor', type: 'text', required: true },
    { key: 'data_empenho', label: 'Data do Empenho', type: 'date' },
    { key: 'valor_total', label: 'Valor do Empenho', type: 'text', required: true },
    { key: 'natureza_despesa', label: 'Natureza da Despesa', type: 'text' },
    { key: 'quantidade_item', label: 'Quantidade', type: 'text' },
    { key: 'valor_produto', label: 'Valor Unitário', type: 'text' },
    { key: 'saldo_retido', label: 'Saldo Retido (NFs)', type: 'text', render: (row) => row.saldo_retido ? `R$ ${row.saldo_retido}` : 'R$ 0,00' },
    { key: 'saldo_consumido', label: 'Saldo Liquidado', type: 'text', render: (row) => row.saldo_consumido ? `R$ ${row.saldo_consumido}` : 'R$ 0,00' },
    { key: 'item_descricao', label: 'Item de Compra', type: 'textarea' },
    { key: 'descricao_empenho', label: 'Descrição (Processo/Referência)', type: 'textarea' },
    { key: 'status', label: 'Status', type: 'select', options: ['Ativo', 'Liquidado Parcial', 'Liquidado Total', 'Cancelado'] }
  ];

  const customColumnsNF = [
    { key: 'numero_nf', label: 'Número da NF', type: 'text', required: true },
    { key: 'chave_acesso', label: 'Chave de Acesso', type: 'text' },
    { key: 'cnpj_fornecedor', label: 'CNPJ', type: 'text', required: true },
    { key: 'nome_empresa', label: 'Fornecedor', type: 'text', required: true },
    { key: 'valor_total', label: 'Valor Total', type: 'text', required: true },
    { key: 'quantidade_item', label: 'Qtd.', type: 'text' },
    { key: 'empenho_id', label: 'Número do Empenho', type: 'text' },
    { key: 'status', label: 'Status', type: 'select', options: ['No Destacamento', 'Pré-Liquidada', 'Enviada pra S4', 'Paga'], required: true }
  ];

  const filtrosStatus = ['Ativo', 'Liquidado Parcial', 'Liquidado Total', 'Cancelado'];

  const [stats, setStats] = useState({ total: 0, valor: 0, retido: 0, liquidado: 0 });
  const [modalEmpenho, setModalEmpenho] = useState(null);

  const customActions = [
    {
      icon: <i className="fas fa-chart-pie"></i>,
      tooltip: 'Painel Executivo do Empenho',
      className: 'btn-outline-primary',
      onClick: (item) => setModalEmpenho(item)
    }
  ];

  const carregarStats = () => {
    apiFetch('crud/fin_empenhos?limit=5000').then(res => {
      if(res.status === 'sucesso') {
        const d = res.dados;
        setStats({
          total: d.filter(e => e.status !== 'Cancelado').length,
          valor: d.reduce((acc, curr) => acc + (parseFloat(curr.valor_total) || 0), 0),
          retido: d.reduce((acc, curr) => acc + (parseFloat(curr.saldo_retido) || 0), 0),
          liquidado: d.reduce((acc, curr) => acc + (parseFloat(curr.saldo_consumido) || 0), 0),
        });
      }
    });
  };

  useEffect(() => {
    carregarStats();
  }, []);

  const formatCurrency = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);

  if (modalEmpenho) {
    const valDisp = (parseFloat(modalEmpenho.valor_total) || 0) - (parseFloat(modalEmpenho.saldo_consumido) || 0) - (parseFloat(modalEmpenho.saldo_retido) || 0);
    return (
      <div className="page-inner animate__animated animate__fadeIn">
         <div className="d-flex align-items-center justify-content-between mb-4">
            <button onClick={() => { setModalEmpenho(null); carregarStats(); }} className="btn btn-secondary shadow-sm">
                <i className="fas fa-arrow-left me-2"></i> Voltar aos Empenhos
            </button>
            <h3 className="fw-bold mb-0 text-dark">Painel do Empenho: <span className="text-primary">{modalEmpenho.nmr_empenho}</span></h3>
         </div>
         
         <div className="row mb-4">
           <div className="col-sm-6 col-md-3">
             <div className="card card-stats card-round border-0 shadow-sm h-100">
               <div className="card-body">
                 <div className="row align-items-center">
                   <div className="col-icon">
                     <div className="icon-big text-center icon-primary bubble-shadow-small bg-primary text-white rounded-circle">
                       <i className="fas fa-wallet"></i>
                     </div>
                   </div>
                   <div className="col col-stats ms-3 ms-sm-0">
                     <div className="numbers">
                       <p className="card-category text-muted fw-bold mb-1">Dotação Total</p>
                       <h4 className="card-title fw-bold text-dark mb-0">{formatCurrency(modalEmpenho.valor_total || 0)}</h4>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>
           <div className="col-sm-6 col-md-3">
             <div className="card card-stats card-round border-0 shadow-sm h-100">
               <div className="card-body">
                 <div className="row align-items-center">
                   <div className="col-icon">
                     <div className="icon-big text-center icon-success bubble-shadow-small bg-success text-white rounded-circle">
                       <i className="fas fa-check-double"></i>
                     </div>
                   </div>
                   <div className="col col-stats ms-3 ms-sm-0">
                     <div className="numbers">
                       <p className="card-category text-muted fw-bold mb-1">Liquidado / S4</p>
                       <h4 className="card-title fw-bold text-success mb-0">{formatCurrency(modalEmpenho.saldo_consumido || 0)}</h4>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>
           <div className="col-sm-6 col-md-3">
             <div className="card card-stats card-round border-0 shadow-sm h-100">
               <div className="card-body">
                 <div className="row align-items-center">
                   <div className="col-icon">
                     <div className="icon-big text-center icon-warning bubble-shadow-small bg-warning text-white rounded-circle">
                       <i className="fas fa-lock"></i>
                     </div>
                   </div>
                   <div className="col col-stats ms-3 ms-sm-0">
                     <div className="numbers">
                       <p className="card-category text-muted fw-bold mb-1">Provisão (NFs)</p>
                       <h4 className="card-title fw-bold text-warning mb-0">{formatCurrency(modalEmpenho.saldo_retido || 0)}</h4>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>
           <div className="col-sm-6 col-md-3">
             <div className="card card-stats card-round border-0 shadow-sm h-100">
               <div className="card-body">
                 <div className="row align-items-center">
                   <div className="col-icon">
                     <div className="icon-big text-center icon-dark bubble-shadow-small bg-dark text-white rounded-circle">
                       <i className="fas fa-piggy-bank"></i>
                     </div>
                   </div>
                   <div className="col col-stats ms-3 ms-sm-0">
                     <div className="numbers">
                       <p className="card-category text-muted fw-bold mb-1">Saldo Disponível</p>
                       <h4 className="card-title fw-bold text-dark mb-0">{formatCurrency(valDisp)}</h4>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>
         </div>

         <CrudTable 
            key={`nf-table-${modalEmpenho.id}`}
            titulo={`Notas Fiscais Vinculadas`}
            subtitulo={`Exibindo NFs do Empenho ${modalEmpenho.nmr_empenho}`}
            endpoint={`crud/fin_notas_fiscais?busca=${modalEmpenho.nmr_empenho}`}
            customColumns={customColumnsNF}
            deletavel={true}
            filtrosStatus={['No Destacamento', 'Pré-Liquidada', 'Enviada pra S4', 'Paga']}
         />
      </div>
    );
  }

  return (
    <div className="page-inner">
      <div className="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
        <div>
          <h3 className="fw-bold mb-3 d-flex align-items-center">
            <i className="fas fa-file-contract me-2 text-primary" style={{ fontSize: '28px' }}></i> Controle de Empenhos
          </h3>
          <h6 className="op-7 mb-2">Gestão Financeira e Documental de Empenhos (SIAFI)</h6>
        </div>
      </div>

      <div className="row">
        <div className="col-sm-6 col-md-3">
          <div className="card card-stats card-round border-0 shadow-sm">
            <div className="card-body">
              <div className="row align-items-center">
                <div className="col-icon">
                  <div className="icon-big text-center icon-primary bubble-shadow-small bg-primary text-white rounded-circle">
                    <i className="fas fa-file-signature"></i>
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Empenhos Ativos</p>
                    <h4 className="card-title fw-bold text-dark">{stats.total}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="col-sm-6 col-md-3">
          <div className="card card-stats card-round border-0 shadow-sm">
            <div className="card-body">
              <div className="row align-items-center">
                <div className="col-icon">
                  <div className="icon-big text-center icon-info bubble-shadow-small bg-info text-white rounded-circle">
                    <i className="fas fa-wallet"></i>
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Total Empenhado</p>
                    <h4 className="card-title fw-bold text-info">{formatCurrency(stats.valor)}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="col-sm-6 col-md-3">
          <div className="card card-stats card-round border-0 shadow-sm">
            <div className="card-body">
              <div className="row align-items-center">
                <div className="col-icon">
                  <div className="icon-big text-center icon-warning bubble-shadow-small bg-warning text-white rounded-circle">
                    <i className="fas fa-lock"></i>
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Provisão Retida (NFs)</p>
                    <h4 className="card-title fw-bold text-warning">{formatCurrency(stats.retido)}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="col-sm-6 col-md-3">
          <div className="card card-stats card-round border-0 shadow-sm">
            <div className="card-body">
              <div className="row align-items-center">
                <div className="col-icon">
                  <div className="icon-big text-center icon-success bubble-shadow-small bg-success text-white rounded-circle">
                    <i className="fas fa-check-double"></i>
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Total Liquidado</p>
                    <h4 className="card-title fw-bold text-success">{formatCurrency(stats.liquidado)}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <CrudTable 
        endpoint="crud/fin_empenhos" 
        deletavel={true}
        customColumns={customColumns}
        filtrosStatus={filtrosStatus}
        customActions={customActions}
      />
    </div>
  );
}
