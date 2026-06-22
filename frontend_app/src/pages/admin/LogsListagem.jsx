import CrudTable from "../../components/CrudTable.jsx";

export default function LogsListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "usuario_id", label: "ID Usuário" },
    { key: "acao", label: "Ação" },
    { key: "tabela", label: "Tabela" },
    { key: "registro_id", label: "Registro" },
    { key: "ip_address", label: "IP" },
    { key: "data_hora", label: "Data/Hora" },
    { key: "detalhes", label: "Detalhes" },
  ];

  // Logs são somente leitura (auditoria)
  const formulario = [];

  return (
    <CrudTable 
      titulo="Rastreador de Auditoria (God Mode)" 
      subtitulo="Trilha de auditoria completa imutável de todas as ações de usuários (somente leitura)" 
      endpoint="crud/logs_auditoria" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
