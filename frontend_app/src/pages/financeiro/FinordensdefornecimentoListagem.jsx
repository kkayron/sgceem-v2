import CrudTable from "../../components/CrudTable.jsx";

export default function FinordensdefornecimentoListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "empresa_nome", label: "Empresa" },
    { key: "empresa_cnpj", label: "CNPJ" },
    { key: "data_cadastro", label: "Data Cadastro" },
    { key: "data_entrega_limite", label: "Entrega Limite" },
    { key: "status", label: "Status" },
    { key: "local_entrega", label: "Local de Entrega" },
  ];

  const formulario = [
    { name: "empresa_nome", label: "Nome da Empresa", type: "text", col: 6, required: true },
    { name: "empresa_cnpj", label: "CNPJ da Empresa", type: "text", col: 6, mask: "cnpj" },
    { name: "data_cadastro", label: "Data de Cadastro", type: "date", col: 4, required: true },
    { name: "data_entrega_limite", label: "Data Limite de Entrega", type: "date", col: 4, required: true },
    { name: "status", label: "Status", type: "select", col: 4, required: true, options: [
      { value: "Pendente", label: "Pendente" },
      { value: "Em Andamento", label: "Em Andamento" },
      { value: "Entregue", label: "Entregue" },
      { value: "Cancelada", label: "Cancelada" },
    ]},
    { name: "local_entrega", label: "Local de Entrega", type: "text", col: 6 },
    { name: "observacao", label: "Observação", type: "text", col: 6 },
  ];

  return (
    <CrudTable 
      titulo="Ordens de Fornecimento" 
      subtitulo="Controle de ordens de fornecimento emitidas" 
      endpoint="crud/fin_ordemforn" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
