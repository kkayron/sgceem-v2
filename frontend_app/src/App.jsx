import { useState, useEffect } from 'react'
import { BrowserRouter as Router, Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { AnimatePresence } from 'framer-motion'
import { startTelemetry, stopTelemetry } from './utils/telemetry.js';
import Login from './Login.jsx'
import Layout from './components/Layout.jsx'
import Home from './pages/Home.jsx'
import Frota from './pages/frota/Frota.jsx'
import Empenhos from './pages/financeiro/Empenhos.jsx'
import NotasFiscais from './pages/financeiro/NotasFiscais.jsx'
import Almoxarifado from './pages/almoxarifado/Almoxarifado.jsx'
import UnderConstruction from './pages/UnderConstruction.jsx'
import Perfil from './pages/Perfil.jsx'
import SuporteListagem from './pages/suporte/SuporteListagem.jsx'
import SuporteSuporte from './pages/suporte/SuporteSuporte.jsx'

// Scaffolding Modules
import UsuariosListagem from './pages/admin/UsuariosListagem.jsx';
import FrotaListagem from './pages/frota/FrotaListagem.jsx';
import OsListagem from './pages/os_manutencao/OsListagem.jsx';
import FinpedidosListagem from './pages/financeiro/FinpedidosListagem.jsx';
import FinordensdefornecimentoListagem from './pages/financeiro/FinordensdefornecimentoListagem.jsx';
import PreventivaListagem from './pages/os_manutencao/PreventivaListagem.jsx';
import OdometroControle from './pages/frota/OdometroControle.jsx';
import FinfornecedoresListagem from './pages/financeiro/FinfornecedoresListagem.jsx';
import FinpregoesListagem from './pages/financeiro/FinpregoesListagem.jsx';
import FinrequisicoesListagem from './pages/financeiro/FinrequisicoesListagem.jsx';
import StafichasListagem from './pages/sta/StafichasListagem.jsx';
import AlmoxentradasListagem from './pages/almoxarifado/AlmoxentradasListagem.jsx';
import LogsListagem from './pages/admin/LogsListagem.jsx';
import FrotaCombustivelListagem from './pages/frota/FrotaCombustivelListagem.jsx';

// Scaffolding Modules Part 2
import FrotaCadastro from './pages/frota/FrotaCadastro.jsx';
import FrotaAtivosemprestados from './pages/frota/FrotaAtivosemprestados.jsx';
import PlanomntListagem from './pages/os_manutencao/PlanomntListagem.jsx';
import PlanomntControle from './pages/os_manutencao/PlanomntControle.jsx';
import FinconrazaoConrazaocorrente from './pages/financeiro/FinconrazaoConrazaocorrente.jsx';
import FinconrazaoConrazaorp from './pages/financeiro/FinconrazaoConrazaorp.jsx';
import FintiposvtrListagem from './pages/frota/FintiposvtrListagem.jsx';
import AlmoxpedidosListagem from './pages/almoxarifado/AlmoxpedidosListagem.jsx';
import AlmoxestoqueListagem from './pages/almoxarifado/AlmoxestoqueListagem.jsx';
import AlmoxdepositosListagem from './pages/almoxarifado/AlmoxdepositosListagem.jsx';
import MarcasmodelosListagem from './pages/frota/MarcasmodelosListagem.jsx';
import ConfigdestinosListagem from './pages/admin/ConfigdestinosListagem.jsx';
import FuncoesmilitaresListagem from './pages/admin/FuncoesmilitaresListagem.jsx';
import PaginasListagem from './pages/admin/PaginasListagem.jsx';
import OmsListagem from './pages/admin/OmsListagem.jsx';
import StafichasEmprego from './pages/sta/StafichasEmprego.jsx';
import StafichasSolicitacaovtr from './pages/sta/StafichasSolicitacaovtr.jsx';
import DashboardAlmoxarifado from './pages/almoxarifado/DashboardAlmoxarifado.jsx';
import DashboardOS from './pages/os_manutencao/DashboardOS.jsx';

import DashboardFrota from './pages/frota/DashboardFrota.jsx';
import DashboardPreventiva from './pages/os_manutencao/DashboardPreventiva.jsx';
import DashboardFinanceiro from './pages/financeiro/DashboardFinanceiro.jsx';
import UsuariosCadastro from './pages/admin/UsuariosCadastro.jsx';
import PermissoesAdmin from './pages/admin/PermissoesAdmin.jsx';
import MontadorDashboard from './pages/admin/MontadorDashboard.jsx';
import ConfiguracoesAdmin from './pages/admin/ConfiguracoesAdmin.jsx';
import AdminDashboard from './pages/admin/AdminDashboard.jsx';

const AdminRoute = ({ children, userData }) => {
  if (!userData) return <Navigate to="/" />;
  const roleId = Number(userData.role_id || userData.funcao_id);
  if (roleId === 1 || roleId === 16) {
    return children;
  }
  return <Navigate to="/" />;
};

function AnimatedRoutes({ userData }) {
  const location = useLocation();
  return (
    <AnimatePresence mode="wait">
      <Routes location={location} key={location.pathname}>
        <Route path="/" element={<Layout userData={userData} />}>
          <Route index element={<Home userData={userData} />} />
          <Route path="perfil" element={<Perfil userData={userData} />} />
          <Route path="frota" element={<Frota />} />
          <Route path="includes/fin_empenhos/listagem" element={<Empenhos />} />
          <Route path="includes/fin_notas_fiscais/listagem" element={<NotasFiscais />} />
          <Route path="includes/almox_produtos/listagem" element={<Almoxarifado />} />
          <Route path="usuarios_cadastro" element={<AdminRoute userData={userData}><UsuariosCadastro /></AdminRoute>} />
          <Route path="admin_permissoes" element={<AdminRoute userData={userData}><PermissoesAdmin /></AdminRoute>} />
          <Route path="admin_config" element={<AdminRoute userData={userData}><ConfiguracoesAdmin /></AdminRoute>} />
          
          <Route path="usuarios_listagem" element={<AdminRoute userData={userData}><UsuariosListagem /></AdminRoute>} />
          <Route path="includes/frota/listagem" element={<FrotaListagem />} />
          <Route path="includes/os/listagem" element={<OsListagem />} />
          <Route path="includes/fin_pedidos/listagem" element={<FinpedidosListagem />} />
          <Route path="includes/fin_ordensdefornecimento/listagem" element={<FinordensdefornecimentoListagem />} />
          <Route path="includes/preventiva/listagem" element={<PreventivaListagem />} />
          <Route path="includes/odometro/controle" element={<OdometroControle />} />
          <Route path="includes/fin_fornecedores/listagem" element={<FinfornecedoresListagem />} />
          <Route path="includes/fin_pregoes/listagem" element={<FinpregoesListagem />} />
          <Route path="includes/fin_requisicoes/listagem" element={<FinrequisicoesListagem />} />
          <Route path="includes/sta_fichas/listagem" element={<StafichasListagem />} />
          <Route path="includes/almox_entrada/listagem" element={<AlmoxentradasListagem />} />
          <Route path="includes/logs/listagem" element={<LogsListagem />} />

          {/* New Scaffolds */}
          <Route path="includes/frota/cadastro" element={<FrotaCadastro />} />
          <Route path="includes/frota/ativos_emprestados" element={<FrotaAtivosemprestados />} />
          <Route path="includes/plano_mnt/listagem" element={<PlanomntListagem />} />
          <Route path="includes/plano_mnt/controle" element={<PlanomntControle />} />
          <Route path="includes/fin_conrazao/conrazao_corrente" element={<FinconrazaoConrazaocorrente />} />
          <Route path="includes/fin_conrazao/conrazao_rp" element={<FinconrazaoConrazaorp />} />
          <Route path="includes/fin_tipos_vtr/listagem" element={<FintiposvtrListagem />} />
          <Route path="includes/almox_pedidos/listagem" element={<AlmoxpedidosListagem />} />
          <Route path="includes/almox_estoque/listagem" element={<AlmoxestoqueListagem />} />
          <Route path="includes/almox_depositos/listagem" element={<AlmoxdepositosListagem />} />
          <Route path="includes/marcas_modelos/listagem" element={<MarcasmodelosListagem />} />
          <Route path="includes/config_destinos/listagem" element={<ConfigdestinosListagem />} />
          <Route path="includes/funcoes_militares/listagem" element={<FuncoesmilitaresListagem />} />
          <Route path="includes/paginas/listagem" element={<PaginasListagem />} />
          <Route path="includes/oms/listagem" element={<OmsListagem />} />
          <Route path="includes/frota_combustivel/listagem" element={<FrotaCombustivelListagem />} />
          <Route path="includes/sta_fichas/emprego" element={<StafichasEmprego />} />
          <Route path="includes/sta_fichas/solicitacao_vtr" element={<StafichasSolicitacaovtr />} />
          <Route path="includes/suporte/listagem" element={<SuporteListagem />} />
          <Route path="includes/suporte/suporte" element={<SuporteSuporte />} />
          <Route path="includes/em_construcao" element={<UnderConstruction />} />
          <Route path="montador_dashboard" element={<AdminRoute userData={userData}><MontadorDashboard /></AdminRoute>} />
          
          <Route path="home" element={<Home userData={userData} />} />
          <Route path="dashboard" element={<DashboardFrota />} />
          <Route path="dashboard_2" element={<DashboardPreventiva />} />
          <Route path="dashboard_3" element={<DashboardFinanceiro />} />
          <Route path="dashboard_4" element={<DashboardAlmoxarifado />} />
          <Route path="dashboard_5" element={<DashboardOS />} />

          {/* Atalhos/Redirecionamentos de Rotas Fantasmas */}
          <Route path="financeiro" element={<Navigate to="/dashboard_3" replace />} />
          <Route path="empenhos" element={<Navigate to="/includes/fin_empenhos/listagem" replace />} />
          <Route path="notasfiscais" element={<Navigate to="/includes/fin_notas_fiscais/listagem" replace />} />
          <Route path="nf" element={<Navigate to="/includes/fin_notas_fiscais/listagem" replace />} />
          <Route path="almoxarifado" element={<Navigate to="/dashboard_4" replace />} />
          <Route path="estoque" element={<Navigate to="/includes/almox_produtos/listagem" replace />} />
          <Route path="os" element={<Navigate to="/dashboard_5" replace />} />
          <Route path="manutencao" element={<Navigate to="/dashboard_5" replace />} />
          <Route path="config" element={<Navigate to="/admin_config" replace />} />
          <Route path="configuracoes" element={<Navigate to="/admin_config" replace />} />
          <Route path="usuarios" element={<Navigate to="/usuarios_listagem" replace />} />

          <Route path="*" element={<UnderConstruction />} />
        </Route>
        
        {/* Rota para o Master Admin unificado */}
        <Route path="/admin_dashboard" element={<AdminRoute userData={userData}><AdminDashboard userData={userData} /></AdminRoute>} />
        <Route path="/admin" element={<Navigate to="/admin_dashboard" replace />} />
      </Routes>
    </AnimatePresence>
  );
}

function App() {
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [userData, setUserData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('sgceem_token');
    const user = localStorage.getItem('sgceem_user');
    
    if (token && user) {
      try {
        const payload = JSON.parse(atob(token.split('.')[1]));
        if (payload.exp * 1000 < Date.now()) {
          import('./utils/api.js').then(({ handleUnauthorized }) => handleUnauthorized());
          return;
        }
      } catch (e) {}
      
      setIsLoggedIn(true);
      setUserData(JSON.parse(user));
      startTelemetry();
    }
    setLoading(false);
    return () => stopTelemetry();
  }, [])

  if (loading) return <div>Carregando Sistema Operacional...</div>;

  if (!isLoggedIn) {
    return <Login onLoginSuccess={() => {
      setIsLoggedIn(true);
      const user = localStorage.getItem('sgceem_user');
      setUserData(JSON.parse(user));
    }} />
  }

  return (
    <Router>
      <AnimatedRoutes userData={userData} />
    </Router>
  )
}

export default App
