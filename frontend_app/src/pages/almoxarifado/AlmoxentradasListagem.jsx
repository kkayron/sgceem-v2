import CrudTable from "../../components/CrudTable.jsx";

export default function AlmoxentradasListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "id_produto", label: "ID Produto" },
    { key: "quantidade", label: "Quantidade" },
    { key: "tipo_entrada", label: "Tipo" },
    { key: "nota_fiscal", label: "Nota Fiscal" },
    { key: "fornecedor", label: "Fornecedor" },
    { key: "data_entrada", label: "Data Entrada" },
    { key: "responsavel", label: "Responsável" },
  ];

  const formulario = [
    { name: "id_produto", label: "ID do Produto", type: "number", col: 4, required: true },
    { name: "quantidade", label: "Quantidade", type: "number", col: 4, required: true },
    { name: "tipo_entrada", label: "Tipo de Entrada", type: "select", col: 4, required: true, options: [
      { value: "Compra", label: "Compra" },
      { value: "Doação", label: "Doação" },
      { value: "Transferência", label: "Transferência" },
      { value: "Devolução", label: "Devolução" },
    ]},
    { name: "nota_fiscal", label: "Nº da Nota Fiscal", type: "text", col: 4 },
    { name: "fornecedor", label: "Fornecedor", type: "text", col: 4 },
    { name: "data_entrada", label: "Data de Entrada", type: "date", col: 4, required: true },
    { name: "responsavel", label: "Responsável pelo Recebimento", type: "text", col: 6 },
    { name: "observacao", label: "Observação", type: "text", col: 6 },
  ];

  return (
    <CrudTable 
      titulo="Entradas de Almoxarifado" 
      subtitulo="Registro de entradas de materiais no estoque" 
      endpoint="crud/almox_entradas" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
