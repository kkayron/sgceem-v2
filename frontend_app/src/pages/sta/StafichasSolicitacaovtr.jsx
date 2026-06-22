import CrudTable from "../../components/CrudTable.jsx";

export default function StafichasSolicitacaovtr() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "motorista", label: "Solicitante / Motorista" },
    { key: "destino", label: "Destino / Finalidade" },
    { key: "data_saida", label: "Data Prevista" },
    { key: "hora_saida", label: "Hora Prevista" },
    { key: "status", label: "Status" },
    { key: "autorizado_por", label: "Autorizado por" },
  ];

  const formulario = [
    { name: "motorista", label: "Solicitante / Motorista", type: "text", col: 6, required: true },
    { name: "destino", label: "Destino / Finalidade", type: "text", col: 6, required: true },
    { name: "data_saida", label: "Data Prevista de Saída", type: "date", col: 4, required: true },
    { name: "hora_saida", label: "Hora Prevista", type: "time", col: 4 },
    { name: "status", label: "Status", type: "select", col: 4, required: true, options: [
      { value: "Pendente", label: "Aguardando Aprovação" },
      { value: "Aprovada", label: "Aprovada" },
      { value: "Negada", label: "Negada" },
      { value: "Em Uso", label: "Em Uso" },
      { value: "Finalizada", label: "Finalizada" },
    ]},
    { name: "observacao", label: "Justificativa / Observação", type: "text", col: 12, required: true },
  ];

  return (
    <CrudTable 
      titulo="Solicitação de Viatura" 
      subtitulo="Solicitações de emprego de viaturas — fluxo de aprovação" 
      endpoint="crud/sta_fichas" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
