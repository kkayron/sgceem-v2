import CrudTable from "../../components/CrudTable.jsx";

export default function Almoxarifado() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "nome_produto", label: "Produto" },
    { key: "codigo_produto", label: "Código" },
    { key: "unidade", label: "Unidade" },
    { key: "estoque_minimo", label: "Estoque Mínimo" },
    { key: "categoria_produto", label: "Depósito" },
  ];

  const formulario = [
    { name: "nome_produto", label: "Nome do Produto", type: "text", col: 6, required: true },
    { name: "codigo_produto", label: "Código do Produto", type: "text", col: 3, required: true },
    { name: "unidade", label: "Unidade de Medida", type: "select", col: 3, options: [
      { value: "UN", label: "Unidade (UN)" },
      { value: "CX", label: "Caixa (CX)" },
      { value: "LT", label: "Litro (LT)" },
      { value: "KG", label: "Quilograma (KG)" },
      { value: "MT", label: "Metro (MT)" },
      { value: "PC", label: "Peça (PC)" },
      { value: "GL", label: "Galão (GL)" },
    ]},
    { name: "estoque_minimo", label: "Estoque Mínimo", type: "number", col: 4, required: true },
    { name: "categoria_produto", label: "Depósito / Categoria", type: "text", col: 8, placeholder: "Ex: Depósito Central" },
    { name: "obs_produto", label: "Observações", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Almoxarifado - Produtos" 
      subtitulo="Gerenciamento completo do estoque de peças" 
      endpoint="crud/almox_produtos" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
