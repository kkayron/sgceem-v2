import CrudTable from "../../components/CrudTable.jsx";

export default function FinrequisicoesListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "fornecedor", label: "Fornecedor" },
    { key: "nmr_pregao", label: "Pregão" },
    { key: "requisitante", label: "Requisitante" },
    { key: "valor_empenhado", label: "Valor Empenhado" },
    { key: "status_requisicao", label: "Status" },
    { key: "data_requisicao", label: "Data" },
  ];

  const formulario = [
    { name: "id_fornecedor", label: "ID do Fornecedor", type: "number", col: 4, required: true },
    { name: "id_pregao", label: "ID do Pregão", type: "number", col: 4 },
    { name: "requisitante", label: "Requisitante", type: "text", col: 4, required: true },
    { name: "valor_empenhado", label: "Valor Empenhado (R$)", type: "number", col: 4, required: true, placeholder: "0.00" },
    { name: "status_requisicao", label: "Status", type: "select", col: 4, required: true, options: [
      { value: "0", label: "Pendente" },
      { value: "1", label: "Aprovada" },
      { value: "2", label: "Empenhada" },
      { value: "3", label: "Negada" },
    ]},
    { name: "data_requisicao", label: "Data da Requisição", type: "date", col: 4, required: true },
    { name: "descricao", label: "Descrição / Objeto da Requisição", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Requisições de Empenho" 
      subtitulo="Controle de requisições financeiras para empenho" 
      endpoint="crud/fin_requisicao" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
