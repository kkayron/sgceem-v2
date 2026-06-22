const puppeteer = require('puppeteer');
const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

async function runTests() {
    const screenshotsDir = path.join(__dirname, 'screenshots');
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir);
    }

    console.log('[*] Conectando ao Banco de Dados sgceem_v2...');
    const connection = await mysql.createConnection({
        host: '127.0.0.1',
        user: 'root',
        password: '',
        database: 'sgceem_v2'
    });

    const [rows] = await connection.execute("SELECT usuario, nomecompleto, r.name as role_name FROM usuarios u LEFT JOIN roles r ON u.role_id = r.id WHERE u.usuario LIKE 'teste.%'");
    console.log(`[*] Encontrados ${rows.length} usuários de função de teste.`);

    console.log('[*] Iniciando o Puppeteer Invisível (Headless)...');
    const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--disable-setuid-sandbox'] });

    for (const user of rows) {
        const username = user.usuario;
        const roleName = user.role_name.replace(/[^a-zA-Z0-9]/g, '_');
        const password = '123456';
        
        console.log(`\n[->] Testando login para: ${user.nomecompleto} (${username})...`);
        
        // Criar contexto isolado (como uma aba anônima)
        const context = await browser.createBrowserContext();
        const page = await context.newPage();
        
        // Configurar viewport
        await page.setViewport({ width: 1280, height: 720 });
        
        try {
            await page.goto('http://localhost:3002/', { waitUntil: 'networkidle0' });
            
            // Espera o form aparecer
            await page.waitForSelector('input[type="text"]', { timeout: 5000 });
            
            // Digita credenciais
            await page.type('input[type="text"]', username);
            await page.type('input[type="password"]', password);
            
            // Clica no botao de login
            await page.click('button[type="submit"]');
            
            // Espera o menu lateral carregar indicando sucesso no login (ex: text "Dashboard", class "sidebar", ou um seletor conhecido)
            // No nosso App, apos o login ele renderiza o MainLayout
            await page.waitForSelector('.main-content, nav, .menu, aside', { timeout: 8000 });
            
            // Espera um segundo extra para a UI e os gráficos assentarem
            await new Promise(r => setTimeout(r, 1500));
            
            // Tira print
            const printPath = path.join(screenshotsDir, `role_${roleName}.png`);
            await page.screenshot({ path: printPath, fullPage: false });
            console.log(`[OK] Print salvo: ${printPath}`);
            
        } catch (err) {
            console.error(`[ERRO] Falha ao testar usuário ${username}: ${err.message}`);
        } finally {
            await context.close();
        }
    }

    console.log('\n[*] Finalizando navegador e conexões...');
    await browser.close();
    await connection.end();
    console.log('[*] Bateria de testes E2E do Puppeteer concluída!');
}

runTests().catch(console.error);
