import CrudTable from "../../components/CrudTable.jsx";

export default function AlmoxpedidosListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "solicitante", label: "Solicitante" },
    { key: "setor", label: "Setor" },
    { key: "data_pedido", label: "Data do Pedido" },
    { key: "status", label: "Status" },
    { key: "urgencia", label: "Urgência" },
    { key: "observacao", label: "Observação" },
  ];

  const formulario = [
    { name: "solicitante", label: "Nome do Solicitante", type: "text", col: 6, required: true },
    { name: "setor", label: "Setor / Subunidade", type: "text", col: 6, required: true },
    { name: "data_pedido", label: "Data do Pedido", type: "date", col: 4, required: true },
    { name: "urgencia", label: "Nível de Urgência", type: "select", col: 4, required: true, options: [
      { value: "Normal", label: "Normal" },
      { value: "Urgente", label: "Urgente" },
      { value: "Crítico", label: "Crítico" },
    ]},
    { name: "status", label: "Status", type: "select", col: 4, required: true, options: [
      { value: "Pendente", label: "Pendente" },
      { value: "Aprovado", label: "Aprovado" },
      { value: "Atendido", label: "Atendido" },
      { value: "Negado", label: "Negado" },
    ]},
    { name: "observacao", label: "Observação / Justificativa", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Pedidos de Almoxarifado" 
      subtitulo="Solicitações de materiais ao almoxarifado" 
      endpoint="crud/almox_pedidos_princ" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
