import CrudTable from "../../components/CrudTable.jsx";

export default function FinconrazaoConrazaorp() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "conta_contabil", label: "Conta Contábil" },
    { key: "descricao", label: "Descrição" },
    { key: "natureza_despesa", label: "ND" },
    { key: "exercicio_origem", label: "Exercício Origem" },
    { key: "saldo_inscrito", label: "Saldo Inscrito" },
    { key: "valor_pago", label: "Valor Pago" },
    { key: "saldo_remanescente", label: "Saldo Remanescente" },
    { key: "data_referencia", label: "Data Referência" },
  ];

  const formulario = [
    { name: "conta_contabil", label: "Conta Contábil", type: "text", col: 4, required: true },
    { name: "descricao", label: "Descrição", type: "text", col: 8, required: true },
    { name: "natureza_despesa", label: "Natureza de Despesa (ND)", type: "text", col: 4 },
    { name: "exercicio_origem", label: "Exercício de Origem", type: "number", col: 4, placeholder: "Ex: 2025" },
    { name: "saldo_inscrito", label: "Saldo Inscrito (R$)", type: "number", col: 4, placeholder: "0.00" },
    { name: "valor_pago", label: "Valor Pago (R$)", type: "number", col: 4, placeholder: "0.00" },
    { name: "saldo_remanescente", label: "Saldo Remanescente (R$)", type: "number", col: 4 },
    { name: "data_referencia", label: "Data de Referência", type: "date", col: 4, required: true },
  ];

  return (
    <CrudTable 
      titulo="CONRAZÃO - Restos a Pagar" 
      subtitulo="Extrato de conta razão SIAFI — restos a pagar de exercícios anteriores" 
      endpoint="crud/fin_siafi_restopagar" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
