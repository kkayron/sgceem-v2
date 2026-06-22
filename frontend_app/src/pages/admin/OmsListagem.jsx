import CrudTable from "../../components/CrudTable.jsx";

export default function OmsListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "nome", label: "Nome Completo" },
    { key: "abreviatura", label: "Abreviatura" },
    { key: "cidade", label: "Cidade" },
    { key: "uf", label: "UF" },
    { key: "comandante", label: "Comandante" },
  ];

  const formulario = [
    { name: "nome", label: "Nome Completo da OM", type: "text", col: 6, required: true, placeholder: "Ex: 367º Batalhão de Engenharia de Construção" },
    { name: "abreviatura", label: "Abreviatura", type: "text", col: 3, required: true, placeholder: "Ex: 367 BEC" },
    { name: "codom", label: "CODOM", type: "text", col: 3 },
    { name: "cidade", label: "Cidade", type: "text", col: 4 },
    { name: "uf", label: "UF", type: "text", col: 2 },
    { name: "comandante", label: "Comandante", type: "text", col: 6 },
    { name: "endereco", label: "Endereço", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Organizações Militares" 
      subtitulo="Gestão de OMs cadastradas no sistema" 
      endpoint="crud/organizacoes_militares" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
