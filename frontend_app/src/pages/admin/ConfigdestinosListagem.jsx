import CrudTable from "../../components/CrudTable.jsx";

export default function ConfigdestinosListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "descricao", label: "Descrição do Destino" },
    { key: "tipo", label: "Tipo" },
    { key: "ativo", label: "Ativo" },
  ];

  const formulario = [
    { name: "descricao", label: "Descrição do Destino", type: "text", col: 6, required: true, placeholder: "Ex: Oficina, Campo, Missão Operacional" },
    { name: "tipo", label: "Tipo", type: "select", col: 3, options: [
      { value: "Interno", label: "Interno" },
      { value: "Externo", label: "Externo" },
    ]},
    { name: "ativo", label: "Ativo", type: "select", col: 3, options: [
      { value: "1", label: "Sim" },
      { value: "0", label: "Não" },
    ]},
  ];

  return (
    <CrudTable 
      titulo="Destinos Configurados" 
      subtitulo="Gestão de destinos para alocação de viaturas e equipamentos" 
      endpoint="crud/config_destinos" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
