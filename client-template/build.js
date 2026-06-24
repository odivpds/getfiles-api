const fs = require('fs');
const path = require('path');
const JavaScriptObfuscator = require('javascript-obfuscator');
const { minify } = require('html-minifier-terser');

const DIST_DIR = path.join(__dirname, 'dist');
const APP_JS_PATH = path.join(__dirname, 'app.js');
const INDEX_HTML_PATH = path.join(__dirname, 'index.html');
const CONFIG_JS_PATH = path.join(__dirname, 'config.js');

if (!fs.existsSync(DIST_DIR)) {
    fs.mkdirSync(DIST_DIR);
}

async function build() {
    console.log('🚀 Memulai proses Obfuscation & Minification...');

    console.log('🔒 Mengacak app.js...');
    const jsCode = fs.readFileSync(APP_JS_PATH, 'utf8');
    const obfuscationResult = JavaScriptObfuscator.obfuscate(jsCode, {
        compact: true,
        controlFlowFlattening: true,
        controlFlowFlatteningThreshold: 1,
        numbersToExpressions: true,
        simplify: true,
        stringArrayShuffle: true,
        splitStrings: true,
        stringArrayThreshold: 1
    });

    const obfuscatedCode = obfuscationResult.getObfuscatedCode();
    fs.writeFileSync(path.join(DIST_DIR, 'app.js'), obfuscatedCode);
    console.log('✅ app.js berhasil diacak dan disimpan ke dist/app.js');

    console.log('🗜️ Memadatkan index.html...');
    const htmlCode = fs.readFileSync(INDEX_HTML_PATH, 'utf8');
    const minifiedHtml = await minify(htmlCode, {
        collapseWhitespace: true,
        removeComments: true,
        minifyCSS: true,
        minifyJS: true
    });

    fs.writeFileSync(path.join(DIST_DIR, 'index.html'), minifiedHtml);
    console.log('✅ index.html berhasil dipadatkan dan disimpan ke dist/index.html');

    console.log('📄 Menyalin config.js (Tidak diacak)...');
    fs.copyFileSync(CONFIG_JS_PATH, path.join(DIST_DIR, 'config.js'));
    console.log('✅ config.js berhasil disalin ke dist/config.js');

    console.log('🎉 Selesai! Folder "dist" kini siap di-upload ke Github Pages.');
}

build().catch(console.error);
