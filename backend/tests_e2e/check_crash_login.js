const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  
  page.on('console', msg => console.log('PAGE LOG:', msg.text()));
  page.on('pageerror', err => console.log('PAGE ERROR:', err.toString()));
  
  await page.goto('http://localhost:3000/login', { waitUntil: 'networkidle0' });
  await page.type('input[placeholder="Usuário"]', 'admin');
  await page.type('input[placeholder="Senha"]', 'admin');
  await page.click('button[type="submit"]');
  
  await page.waitForNavigation({ waitUntil: 'networkidle0' });
  await new Promise(r => setTimeout(r, 2000));
  await browser.close();
})();
