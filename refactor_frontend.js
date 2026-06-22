const fs = require('fs');
const path = require('path');

const srcPagesDir = path.join(__dirname, 'frontend_app', 'src', 'pages');
const appJsxPath = path.join(__dirname, 'frontend_app', 'src', 'App.jsx');

// Map of categories and the files that belong to them
const categories = {
    almoxarifado: [
        'Almoxarifado.jsx', 'AlmoxdepositosListagem.jsx', 'AlmoxentradasListagem.jsx',
        'AlmoxestoqueListagem.jsx', 'AlmoxpedidosListagem.jsx', 'DashboardAlmoxarifado.jsx'
    ],
    financeiro: [
        'DashboardFinanceiro.jsx', 'Empenhos.jsx', 'FinconrazaoConrazaocorrente.jsx',
        'FinconrazaoConrazaorp.jsx', 'FinfornecedoresListagem.jsx', 'FinordensdefornecimentoListagem.jsx',
        'FinpedidosListagem.jsx', 'FinpregoesListagem.jsx', 'FinrequisicoesListagem.jsx', 'NotasFiscais.jsx'
    ],
    frota: [
        'DashboardFrota.jsx', 'FintiposvtrListagem.jsx', 'Frota.jsx', 'FrotaAtivosemprestados.jsx',
        'FrotaCadastro.jsx', 'FrotaCombustivelListagem.jsx', 'FrotaListagem.jsx', 'OdometroControle.jsx',
        'MarcasmodelosListagem.jsx', 'HistoricoViatura.jsx'
    ],
    os_manutencao: [
        'DashboardOS.jsx', 'DashboardPreventiva.jsx', 'FichaOS.jsx', 'OsListagem.jsx',
        'PlanomntControle.jsx', 'PlanomntListagem.jsx', 'PreventivaListagem.jsx'
    ],
    admin: [
        'AdminDashboard.jsx', 'ConfiguracoesAdmin.jsx', 'MontadorDashboard.jsx',
        'PermissoesAdmin.jsx', 'UsuariosCadastro.jsx', 'UsuariosListagem.jsx',
        'ConfigdestinosListagem.jsx', 'FuncoesmilitaresListagem.jsx', 'LogsListagem.jsx',
        'OmsListagem.jsx', 'PaginasListagem.jsx'
    ],
    sta: [
        'StafichasEmprego.jsx', 'StafichasListagem.jsx', 'StafichasSolicitacaovtr.jsx'
    ],
    suporte: [
        'SuporteListagem.jsx', 'SuporteSuporte.jsx'
    ],
    unused: [
        'Dashboard4.jsx', 'Conteudo2.jsx', 'Emconstrucao.jsx'
    ] // Will be deleted
};

// Ensure directories exist
for (const dir of Object.keys(categories)) {
    if (dir === 'unused') continue;
    const dirPath = path.join(srcPagesDir, dir);
    if (!fs.existsSync(dirPath)) {
        fs.mkdirSync(dirPath, { recursive: true });
    }
}

// Prepare import updates for App.jsx and others
let appJsxContent = fs.readFileSync(appJsxPath, 'utf-8');

for (const [category, files] of Object.entries(categories)) {
    for (const file of files) {
        const oldPath = path.join(srcPagesDir, file);
        if (fs.existsSync(oldPath)) {
            if (category === 'unused') {
                fs.unlinkSync(oldPath);
                console.log(`Deleted: ${file}`);
                // Remove import from App.jsx if exists
                const regex = new RegExp(`import\\s+.*?\\s+from\\s+['"]\\.\\/pages\\/${file}['"];?\\n?`, 'g');
                appJsxContent = appJsxContent.replace(regex, '');
            } else {
                const newPath = path.join(srcPagesDir, category, file);
                fs.renameSync(oldPath, newPath);
                console.log(`Moved: ${file} -> ${category}/${file}`);
                
                // Update App.jsx imports
                const oldImportPath = `./pages/${file}`;
                const newImportPath = `./pages/${category}/${file}`;
                appJsxContent = appJsxContent.replace(
                    new RegExp(`['"]\\.\\/pages\\/${file}['"]`, 'g'),
                    `'${newImportPath}'`
                );
            }
        }
    }
}

fs.writeFileSync(appJsxPath, appJsxContent);
console.log('App.jsx imports updated!');
