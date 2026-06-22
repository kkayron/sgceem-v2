import CrudTable from "../../components/CrudTable.jsx";

export default function FinpedidosListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "id_fornecedor", label: "ID Fornecedor" },
    { key: "descricao_item", label: "Descrição do Item" },
    { key: "quantidade", label: "Qtd" },
    { key: "valor_unitario", label: "Valor Unit." },
    { key: "valor_total", label: "Valor Total" },
    { key: "status", label: "Status" },
    { key: "data_pedido", label: "Data do Pedido" },
  ];

  const formulario = [
    { name: "id_fornecedor", label: "ID do Fornecedor", type: "number", col: 4, required: true },
    { name: "descricao_item", label: "Descrição do Item", type: "text", col: 8, required: true },
    { name: "quantidade", label: "Quantidade", type: "number", col: 3, required: true },
    { name: "valor_unitario", label: "Valor Unitário (R$)", type: "number", col: 3, placeholder: "0.00" },
    { name: "valor_total", label: "Valor Total (R$)", type: "number", col: 3, placeholder: "0.00" },
    { name: "status", label: "Status", type: "select", col: 3, required: true, options: [
      { value: "Pendente", label: "Pendente" },
      { value: "Aprovado", label: "Aprovado" },
      { value: "Entregue", label: "Entregue" },
      { value: "Cancelado", label: "Cancelado" },
    ]},
    { name: "data_pedido", label: "Data do Pedido", type: "date", col: 4 },
    { name: "observacao", label: "Observação", type: "text", col: 8 },
  ];

  return (
    <CrudTable 
      titulo="Pedidos de Fornecedor" 
      subtitulo="Gestão de pedidos realizados aos fornecedores" 
      endpoint="crud/fin_pedidos_forn" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
