import CrudTable from "../../components/CrudTable.jsx";

export default function PaginasListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "nome", label: "Nome da Página" },
    { key: "grupo_nome", label: "Grupo / Módulo" },
    { key: "arquivo", label: "Arquivo" },
    { key: "icone", label: "Ícone" },
    { key: "ordem", label: "Ordem" },
    { key: "ativo", label: "Ativo" },
  ];

  const formulario = [
    { name: "nome", label: "Nome da Página", type: "text", col: 6, required: true },
    { name: "tipo", label: "ID do Grupo", type: "number", col: 3, required: true },
    { name: "arquivo", label: "Arquivo / Rota", type: "text", col: 3, placeholder: "Ex: includes/frota/listagem" },
    { name: "icone", label: "Classe do Ícone", type: "text", col: 4, placeholder: "Ex: fas fa-car" },
    { name: "ordem", label: "Ordem de Exibição", type: "number", col: 4 },
    { name: "ativo", label: "Status", type: "select", col: 4, options: [
      { value: "1", label: "Ativo" },
      { value: "0", label: "Inativo" },
    ]},
  ];

  return (
    <CrudTable 
      titulo="Páginas e Permissões" 
      subtitulo="Gestão de páginas e controle de acesso do sistema" 
      endpoint="crud/paginas_principal" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
