import CrudTable from "../../components/CrudTable.jsx";

export default function FinconrazaoConrazaocorrente() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "conta_contabil", label: "Conta Contábil" },
    { key: "descricao", label: "Descrição" },
    { key: "natureza_despesa", label: "ND" },
    { key: "saldo_anterior", label: "Saldo Anterior" },
    { key: "credito", label: "Crédito" },
    { key: "debito", label: "Débito" },
    { key: "saldo_atual", label: "Saldo Atual" },
    { key: "data_referencia", label: "Data Referência" },
  ];

  const formulario = [
    { name: "conta_contabil", label: "Conta Contábil", type: "text", col: 4, required: true },
    { name: "descricao", label: "Descrição", type: "text", col: 8, required: true },
    { name: "natureza_despesa", label: "Natureza de Despesa (ND)", type: "text", col: 4 },
    { name: "saldo_anterior", label: "Saldo Anterior (R$)", type: "number", col: 4, placeholder: "0.00" },
    { name: "credito", label: "Crédito (R$)", type: "number", col: 4, placeholder: "0.00" },
    { name: "debito", label: "Débito (R$)", type: "number", col: 4, placeholder: "0.00" },
    { name: "saldo_atual", label: "Saldo Atual (R$)", type: "number", col: 4 },
    { name: "data_referencia", label: "Data de Referência", type: "date", col: 4, required: true },
  ];

  return (
    <CrudTable 
      titulo="CONRAZÃO - Corrente" 
      subtitulo="Extrato de conta razão SIAFI — exercício corrente" 
      endpoint="crud/fin_siafi_corrente" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
