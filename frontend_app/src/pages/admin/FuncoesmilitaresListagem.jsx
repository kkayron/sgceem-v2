import CrudTable from "../../components/CrudTable.jsx";

export default function FuncoesmilitaresListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "name", label: "Nome da Função" },
    { key: "description", label: "Descrição" }
  ];

  const formulario = [
    { name: "name", label: "Nome da Função", type: "text", col: 6, required: true, placeholder: "Ex: Chefe de Manutenção" },
    { name: "description", label: "Descrição da Função", type: "text", col: 6 }
  ];

  return (
    <CrudTable 
      titulo="Funções dos Usuários" 
      subtitulo="Gestão de funções e cargos do sistema" 
      endpoint="crud/roles" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
