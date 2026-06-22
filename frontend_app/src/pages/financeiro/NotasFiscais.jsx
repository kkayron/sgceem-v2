import React, { useState, useEffect } from 'react';
import CrudTable from "../../components/CrudTable.jsx";
import { FileText, DollarSign, Clock, CheckCircle } from 'lucide-react';
import { apiFetch } from "../../utils/api.js";

export default function NotasFiscais() {
  const customColumns = [
    { key: 'numero_nf', label: 'Número da NF', type: 'text', required: true },
    { key: 'chave_acesso', label: 'Chave de Acesso', type: 'text' },
    { key: 'cnpj_fornecedor', label: 'CNPJ', type: 'text', required: true },
    { key: 'nome_empresa', label: 'Fornecedor', type: 'text', required: true },
    { key: 'valor_produto', label: 'Valor do Produto', type: 'text' },
    { key: 'valor_frete', label: 'Valor do Frete', type: 'text' },
    { key: 'valor_total', label: 'Valor Total', type: 'text', required: true },
    { key: 'quantidade_item', label: 'Qtd.', type: 'text' },
    { key: 'item_descricao', label: 'Produto / Serviço', type: 'textarea' },
    { key: 'empenho_id', label: 'Número do Empenho', type: 'text' },
    { key: 'status', label: 'Status', type: 'select', options: ['No Destacamento', 'Pré-Liquidada', 'Enviada pra S4', 'Paga'], required: true }
  ];

  const filtrosStatus = ['No Destacamento', 'Pré-Liquidada', 'Enviada pra S4', 'Paga'];

  const [stats, setStats] = useState({ qtd: 0, valor_total: 0, retido: 0, s4: 0, pago: 0 });

  useEffect(() => {
    apiFetch('crud/fin_notas_fiscais?limit=5000').then(res => {
      if(res.status === 'sucesso') {
        const d = res.dados;
        
        let s4 = 0;
        let pago = 0;
        let retido = 0;
        let totalVal = 0;
        
        d.forEach(nf => {
            const val = parseFloat(nf.valor_total) || 0;
            totalVal += val;
            if (nf.status === 'Paga') {
                pago += val;
            } else if (nf.status === 'Enviada pra S4' || nf.status === 'Liquidada') {
                s4 += val;
            } else {
                retido += val;
            }
        });

        setStats({
          qtd: d.length,
          valor_total: totalVal,
          retido: retido,
          s4: s4,
          pago: pago
        });
      }
    });
  }, []);

  const formatCurrency = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);

  return (
    <div className="page-inner">
      <div className="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
        <div>
          <h3 className="fw-bold mb-3 d-flex align-items-center">
            <FileText className="me-2 text-primary" size={28} /> Gestão de Notas Fiscais
          </h3>
          <h6 className="op-7 mb-2">Controle de liquidação e pagamento de NFs dos Empenhos</h6>
        </div>
      </div>

      <div className="row">
        <div className="col-sm-6 col-md-3">
          <div className="card card-stats card-round border-0 shadow-sm">
            <div className="card-body">
              <div className="row align-items-center">
                <div className="col-icon">
                  <div className="icon-big text-center icon-primary bubble-shadow-small bg-primary text-white rounded-circle">
                    <DollarSign size={24} />
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Total Lançado (R$)</p>
                    <h4 className="card-title fw-bold text-dark">{formatCurrency(stats.valor_total)}</h4>
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
                    <Clock size={24} />
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Aguardando/Retido</p>
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
                  <div className="icon-big text-center icon-info bubble-shadow-small bg-info text-white rounded-circle">
                    <FileText size={24} />
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Enviadas pra S4</p>
                    <h4 className="card-title fw-bold text-info">{formatCurrency(stats.s4)}</h4>
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
                    <CheckCircle size={24} />
                  </div>
                </div>
                <div className="col col-stats ms-3 ms-sm-0">
                  <div className="numbers">
                    <p className="card-category text-muted fw-bold mb-1">Pagas (Finalizadas)</p>
                    <h4 className="card-title fw-bold text-success">{formatCurrency(stats.pago)}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <CrudTable 
        title="Notas Fiscais" 
        endpoint="crud/fin_notas_fiscais" 
        customColumns={customColumns}
        filtrosStatus={filtrosStatus}
        colorTheme="primary"
        deletavel={true}
      />
    </div>
  );
}
