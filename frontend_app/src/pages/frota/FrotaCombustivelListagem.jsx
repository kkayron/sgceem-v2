import React from 'react';
import CrudTable from "../../components/CrudTable.jsx";

export default function FrotaCombustivelListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "id_frota", label: "ID Viatura" },
    { key: "data_abastecimento", label: "Data" },
    { key: "km_atual", label: "Odômetro" },
    { key: "litros", label: "Litros" },
    { key: "valor_total", label: "Valor Total (R$)" },
    { key: "tipo_combustivel", label: "Combustível" },
    { key: "motorista", label: "Motorista" }
  ];

  const formulario = [
    { key: "id_frota", label: "ID da Viatura (Placa)", type: "text", required: true },
    { key: "data_abastecimento", label: "Data do Abastecimento", type: "date", required: true },
    { key: "km_atual", label: "Odômetro (KM)", type: "number", required: true },
    { key: "litros", label: "Quantidade de Litros", type: "number", step: "0.01", required: true },
    { key: "valor_total", label: "Valor Total da Nota (R$)", type: "number", step: "0.01", required: true },
    { key: "tipo_combustivel", label: "Tipo de Combustível", type: "select", options: ["Diesel", "Diesel S10", "Gasolina", "Etanol"], required: true },
    { key: "motorista", label: "Motorista Responsável", type: "text", required: true }
  ];

  return (
    <CrudTable 
      titulo="Controle de Combustível e Telemetria" 
      subtitulo="Lançamento de notas de abastecimento para cálculo de R$/KM" 
      endpoint="crud/frota_combustivel" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
