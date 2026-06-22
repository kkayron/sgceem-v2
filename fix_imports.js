const fs = require('fs');
const path = require('path');

const srcPagesDir = path.join(__dirname, 'frontend_app', 'src', 'pages');

const categories = fs.readdirSync(srcPagesDir).filter(f => fs.statSync(path.join(srcPagesDir, f)).isDirectory());

categories.forEach(category => {
    const catPath = path.join(srcPagesDir, category);
    const files = fs.readdirSync(catPath).filter(f => f.endsWith('.jsx'));

    files.forEach(file => {
        const filePath = path.join(catPath, file);
        let content = fs.readFileSync(filePath, 'utf-8');

        // Fix imports that used "../" because now they need "../../"
        // E.g. "../components/" -> "../../components/"
        // E.g. "../utils/" -> "../../utils/"
        content = content.replace(/['"]\.\.\/components\//g, '"../../components/');
        content = content.replace(/['"]\.\.\/utils\//g, '"../../utils/');
        content = content.replace(/['"]\.\.\/App\.jsx/g, '"../../App.jsx');

        // Also if they had any './' that meant same directory, but wait, those are fine.

        fs.writeFileSync(filePath, content);
    });
});X'

console.log('Imports inside page files updated!');
