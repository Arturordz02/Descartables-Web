/**
 * ====================================================================
 * SCRIPT DE EMPAQUETADO SEGURO PARA PRODUCCIÓN (BUILD & PACKAGER)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * Este script genera un paquete distribuible 'dist/package/'
 * basado en una lista blanca estricta (allowlist).
 * 
 * GARANTÍAS DE SEGURIDAD:
 * 1. Excluye automáticamente .git, .gitignore, .env, api/secrets.php, dumps privados, scripts/, scratch/, logs.
 * 2. Incluye el esquema canónico schema.sql y el sistema de migraciones versionado database/migrations/.
 * 3. Realiza una auditoría estática automatizada post-construcción sobre el paquete generado.
 * 4. Aborta y destruye el paquete si detecta cualquier secreto o credencial no sanitizada.
 */

const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');
const packageDir = path.resolve(distDir, 'package');

// 1. Extensiones y archivos raíz permitidos (Allowlist)
const ALLOWED_ROOT_FILES = new Set([
    'index.html',
    'catalogo.html',
    'admin.html',
    'perfil.html',
    'login.html',
    'registro.html',
    'contacto.html',
    'libro-de-reclamaciones.html',
    'terminos-y-condiciones.html',
    '404.html',
    'schema.sql',
    '.htaccess',
    'robots.txt',
    'manifest.json',
    'favicon.ico'
]);

const FORBIDDEN_NAMES = new Set([
    'secrets.php',
    '.env',
    '.git',
    '.gitignore',
    '.DS_Store',
    'logs',
    'backups',
    'docs',
    'scripts',
    'tests',
    'node_modules',
    'package.json',
    'package-lock.json',
    'tailwind.config.js',
    'tailwind-input.css'
]);

function ensureDirExists(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

function copyFileSafe(src, dest) {
    ensureDirExists(path.dirname(dest));
    fs.copyFileSync(src, dest);
}

function cleanDist() {
    if (fs.existsSync(distDir)) {
        fs.rmSync(distDir, { recursive: true, force: true });
    }
    ensureDirExists(packageDir);
}

function copyDirectoryRecursive(srcDir, destDir, filterFn) {
    if (!fs.existsSync(srcDir)) return;
    const entries = fs.readdirSync(srcDir, { withFileTypes: true });

    for (const entry of entries) {
        const srcPath = path.join(srcDir, entry.name);
        const destPath = path.join(destDir, entry.name);

        if (filterFn && !filterFn(srcPath, entry)) {
            continue;
        }

        if (entry.isDirectory()) {
            copyDirectoryRecursive(srcPath, destPath, filterFn);
        } else if (entry.isFile()) {
            copyFileSafe(srcPath, destPath);
        }
    }
}

console.log('[BUILD] Iniciando empaquetado seguro para producción...');
cleanDist();

// 2. Copiar archivos raíz permitidos
for (const file of ALLOWED_ROOT_FILES) {
    const src = path.join(rootDir, file);
    if (fs.existsSync(src)) {
        copyFileSafe(src, path.join(packageDir, file));
    }
}

// 3. Copiar assets/
const assetsSrc = path.join(rootDir, 'assets');
const assetsDest = path.join(packageDir, 'assets');
copyDirectoryRecursive(assetsSrc, assetsDest, (p, entry) => {
    if (entry.name.endsWith('.bak') || entry.name.endsWith('.tmp') || entry.name === 'tailwind-input.css') {
        return false;
    }
    // Excluir uploads reales de usuarios (prod_*) en assets/images/productos/
    const normPath = p.replace(/\\/g, '/');
    if (normPath.includes('assets/images/productos/') && entry.name.startsWith('prod_')) {
        return false;
    }
    return true;
});

// 4. Copiar api/ (Excluyendo estrictamente secrets.php, dumps y temporales)
const apiSrc = path.join(rootDir, 'api');
const apiDest = path.join(packageDir, 'api');
copyDirectoryRecursive(apiSrc, apiDest, (p, entry) => {
    if (FORBIDDEN_NAMES.has(entry.name)) {
        return false;
    }
    if (entry.name.endsWith('.sql') || entry.name.endsWith('.log') || entry.name.endsWith('.bak')) {
        return false;
    }
    return true;
});

// 5. Copiar database/ (Migraciones e instalador)
const dbSrc = path.join(rootDir, 'database');
const dbDest = path.join(packageDir, 'database');
copyDirectoryRecursive(dbSrc, dbDest, (p, entry) => {
    if (entry.name.endsWith('.bak') || entry.name.endsWith('.log') || entry.name.startsWith('backup_') || entry.name.startsWith('dump_')) {
        return false;
    }
    return true;
});

// 6. Auditoría post-empaquetado de seguridad
console.log('[AUDIT] Verificando integridad del paquete generado en dist/package/ ...');

let auditErrors = [];
function verifyPackage(currentDir) {
    const entries = fs.readdirSync(currentDir, { withFileTypes: true });
    for (const entry of entries) {
        const fullPath = path.join(currentDir, entry.name);
        const relPath = path.relative(packageDir, fullPath).replace(/\\/g, '/');

        // Comprobación A: Nombres prohibidos
        if (FORBIDDEN_NAMES.has(entry.name) || entry.name === 'secrets.php') {
            auditErrors.push(`[BLOQUEO] Archivo prohibido detectado en el paquete: ${relPath}`);
        }

        // Comprobación B: Extensiones prohibidas fuera de las rutas de migración/esquema
        if (/\.(log|bak|rar|zip|env)$/i.test(entry.name)) {
            auditErrors.push(`[BLOQUEO] Archivo con extensión prohibida detectado: ${relPath}`);
        }
        if (entry.name.endsWith('.sql') && !relPath.startsWith('database/migrations/') && relPath !== 'schema.sql') {
            auditErrors.push(`[BLOQUEO] Archivo SQL no autorizado o dump detectado: ${relPath}`);
        }
        if (relPath.startsWith('assets/images/productos/prod_') || relPath.startsWith('uploads/prod_')) {
            auditErrors.push(`[BLOQUEO] Imagen subida de usuario detectada en el paquete: ${relPath}`);
        }

        if (entry.isDirectory()) {
            if (entry.name === '.git' || entry.name === '.vscode' || entry.name === 'scratch' || entry.name === 'logs' || entry.name === 'backups') {
                auditErrors.push(`[BLOQUEO] Directorio restringido detectado: ${relPath}`);
            }
            verifyPackage(fullPath);
        } else if (entry.isFile()) {
            // Comprobación C: Escaneo de contenido en archivos PHP, JS y SQL por claves privadas o contraseñas
            if (/\.(php|js|json|html|sql)$/i.test(entry.name)) {
                const content = fs.readFileSync(fullPath, 'utf8');
                if (content.includes('Contra246World') || 
                    content.includes('DP_Peru_SecureSalt_2026_x89aF72kL9') ||
                    /'pass'\s*=>\s*['"][a-zA-Z0-9_!@#$%^&*()\-+]{4,}['"]/i.test(content) || 
                    /'pass_raw'\s*=>/i.test(content)) {
                    auditErrors.push(`[BLOQUEO] Patrón de credencial o salt privado detectado dentro de: ${relPath}`);
                }

                // Comprobación D: Prohibición de CDN de Tailwind en HTML empaquetado (F16)
                if (entry.name.endsWith('.html') && content.includes('cdn.tailwindcss.com')) {
                    auditErrors.push(`[BLOQUEO] Referencia a CDN de Tailwind detectada en: ${relPath}`);
                }
            }
        }
    }
}

verifyPackage(packageDir);

// Comprobación E: Asegurar que assets/css/tailwind.css compilado esté presente e íntegro
const distTailwindCss = path.join(packageDir, 'assets', 'css', 'tailwind.css');
if (!fs.existsSync(distTailwindCss)) {
    auditErrors.push('[BLOQUEO] No se encontró assets/css/tailwind.css compilado en el paquete de producción.');
} else {
    const stats = fs.statSync(distTailwindCss);
    if (stats.size < 20000) {
        auditErrors.push(`[BLOQUEO] assets/css/tailwind.css es demasiado pequeño (${stats.size} bytes), posible compilación incompleta.`);
    }
}

if (auditErrors.length > 0) {
    console.error('\n[FATAL] La verificación del paquete falló con los siguientes errores:');
    auditErrors.forEach(err => console.error('  - ' + err));
    fs.rmSync(distDir, { recursive: true, force: true });
    process.exit(1);
}

console.log('[OK] Paquete generado limpiamente en dist/package/ sin secretos ni historial Git.');
