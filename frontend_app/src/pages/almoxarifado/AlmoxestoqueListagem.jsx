import CrudTable from "../../components/CrudTable.jsx";

export default function AlmoxestoqueListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "nome_produto", label: "Produto" },
    { key: "codigo_produto", label: "Código" },
    { key: "unidade_medida", label: "Unidade" },
    { key: "estoque_minimo", label: "Estoque Mínimo" },
    { key: "estoque_atual", label: "Estoque Atual" },
    { key: "localizacao", label: "Localização" },
  ];

  const formulario = [
    { name: "nome_produto", label: "Nome do Produto", type: "text", col: 6, required: true },
    { name: "codigo_produto", label: "Código do Produto", type: "text", col: 3, required: true },
    { name: "unidade_medida", label: "Unidade de Medida", type: "select", col: 3, options: [
      { value: "UN", label: "Unidade (UN)" },
      { value: "CX", label: "Caixa (CX)" },
      { value: "LT", label: "Litro (LT)" },
      { value: "KG", label: "Quilograma (KG)" },
      { value: "MT", label: "Metro (MT)" },
      { value: "PC", label: "Peça (PC)" },
      { value: "GL", label: "Galão (GL)" },
    ]},
    { name: "estoque_minimo", label: "Estoque Mínimo", type: "number", col: 4, required: true },
    { name: "estoque_atual", label: "Estoque Atual", type: "number", col: 4 },
    { name: "localizacao", label: "Localização no Depósito", type: "text", col: 4, placeholder: "Ex: Prateleira A3" },
    { name: "descricao", label: "Descrição / Especificação", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Controle de Estoque" 
      subtitulo="Produtos e materiais em estoque no almoxarifado" 
      endpoint="crud/almox_produtos" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
