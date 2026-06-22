import CrudTable from "../../components/CrudTable.jsx";

export default function FinfornecedoresListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "nome_empresa", label: "Empresa" },
    { key: "cnpj_empresa", label: "CNPJ" },
    { key: "categoria_empresa", label: "Categoria" },
    { key: "contato_nome", label: "Contato" },
    { key: "contato_numero", label: "Telefone" },
    { key: "contato_email", label: "E-mail" }
  ];

  const formulario = [
    { name: "categoria_empresa", label: "Categoria", type: "select", options: [
      {value: "Peças", label: "Peças"},
      {value: "Serviço", label: "Serviço"},
      {value: "Lubrificantes", label: "Lubrificantes"},
      {value: "Pneus", label: "Pneus"},
      {value: "Geral", label: "Geral"}
    ], required: true, col: 4 },
    { name: "nome_empresa", label: "Nome da Empresa", type: "text", required: true, col: 4 },
    { name: "cnpj_empresa", label: "CNPJ", type: "text", required: true, mask: "cnpj", col: 4 },
    { name: "contato_nome", label: "Nome do Contato", type: "text", required: true, col: 4 },
    { name: "contato_numero", label: "Número do Contato", type: "text", required: true, col: 4 },
    { name: "contato_email", label: "E-mail", type: "email", required: true, col: 12 }
  ];

  return (
    <CrudTable 
      titulo="Fornecedores" 
      subtitulo="Gestão de fornecedores cadastrados" 
      endpoint="crud/fin_fornecedores" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
