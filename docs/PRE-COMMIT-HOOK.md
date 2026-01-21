# Pre-commit Hook - Auto-formatting

## 🎯 ¿Por qué se reformatean mis archivos al hacer commit?

Tu proyecto tiene un **hook de pre-commit** que ejecuta **Laravel Pint** automáticamente antes de cada commit. Esto garantiza que todo el código siga el mismo estilo de formato.

---

## 🔧 **Ubicación del Hook**

```
.hooks/pre-commit
```

Este script se ejecuta automáticamente cuando haces `git commit`.

---

## ⚙️ **Configuraciones de Formato**

### 1. **EditorConfig** (`.editorconfig`)
Define reglas básicas de indentación:
- PHP: 4 espacios (antes tabs)
- JavaScript: 2 espacios
- Blade: 4 espacios (antes tabs)

### 2. **Prettier** (`.prettierrc`)
Define reglas de formato para todos los archivos:
- PHP: 4 espacios (antes tabs)
- JS/TS: 4 espacios (antes tabs)
- JSON: 4 espacios (antes tabs)
- CSS/SCSS: 4 espacios (antes tabs)

### 3. **Laravel Pint** (`pint.json`)
Formateador PHP específico de Laravel. Respeta las reglas de `.editorconfig`.

---

## 🚫 **Opción 1: Desactivar el Hook** (No recomendado)

### Desactivar permanentemente:
```bash
mv .hooks/pre-commit .hooks/pre-commit.disabled
```

### Desactivar solo para un commit:
```bash
git commit --no-verify -m "tu mensaje"
```

---

## ✅ **Opción 2: Trabajar con el Hook** (Recomendado)

### Paso 1: Configurar tu editor

**VSCode/Cursor** (`.vscode/settings.json`):
```json
{
  "editor.formatOnSave": true,
  "editor.insertSpaces": true,
  "editor.tabSize": 4,
  "[php]": {
    "editor.insertSpaces": true,
    "editor.tabSize": 4,
    "editor.defaultFormatter": "open-southeners.laravel-pint"
  },
  "[javascript]": {
    "editor.insertSpaces": true,
    "editor.tabSize": 2
  },
  "[blade]": {
    "editor.insertSpaces": true,
    "editor.tabSize": 4
  }
}
```

### Paso 2: Instalar extensión EditorConfig
- VSCode: `editorconfig.editorconfig`
- Esta extensión lee automáticamente `.editorconfig`

### Paso 3: Formatear antes de commit (opcional)
```bash
# Formatear todos los archivos PHP modificados
vendor/bin/pint

# Formatear archivo específico
vendor/bin/pint path/to/file.php

# Ver qué cambiaría sin aplicar
vendor/bin/pint --test
```

---

## 🔄 **Cómo Funciona el Hook**

1. Haces `git add` y `git commit`
2. El hook detecta archivos PHP en staging
3. Ejecuta Laravel Pint en esos archivos
4. Reformatea según `.editorconfig` + `pint.json`
5. Vuelve a agregar los archivos formateados
6. Continúa con el commit

**Resultado**: Todos los commits tienen código consistentemente formateado.

---

## 📋 **Comandos Útiles**

```bash
# Ver qué archivos serían formateados
git diff --cached --name-only --diff-filter=ACM -- '*.php'

# Formatear manualmente antes de commit
vendor/bin/pint $(git diff --cached --name-only --diff-filter=ACM -- '*.php')

# Commit sin ejecutar hook (emergencias)
git commit --no-verify -m "mensaje"

# Ver logs del hook (si falla)
cat .git/hooks/pre-commit
```

---

## 🎨 **Beneficios del Auto-formatting**

✅ **Código consistente**: Todo el equipo usa el mismo estilo
✅ **Menos conflictos**: No hay cambios de formato en PRs
✅ **Sin discusiones**: Las herramientas deciden el formato
✅ **Mejor legibilidad**: Código estandarizado es más fácil de leer
✅ **CI/CD feliz**: Los tests de formato siempre pasan

---

## 🛠️ **Personalizar Reglas de Formato**

### Cambiar de espacios a tabs:
```bash
# .editorconfig
[*.php]
indent_style = tab  # space o tab
indent_size = 4
```

### Ajustar Prettier:
```json
// .prettierrc
{
  "useTabs": false,  // true para tabs
  "tabWidth": 4,
  "printWidth": 120
}
```

### Ajustar Pint:
```json
// pint.json
{
  "preset": "laravel",
  "rules": {
    "indentation_type": {
      "type": "space"  // o "tab"
    }
  }
}
```

---

## 🐛 **Troubleshooting**

### El hook no se ejecuta:
```bash
# Verificar que el hook existe y es ejecutable
ls -la .hooks/pre-commit
chmod +x .hooks/pre-commit

# Verificar configuración de Git
git config --local core.hooksPath .hooks
```

### Pint no está instalado:
```bash
composer require laravel/pint --dev
```

### Hook falla y no puedo hacer commit:
```bash
# Commit sin hook (temporal)
git commit --no-verify -m "mensaje"

# Ver error del hook
.hooks/pre-commit
```

### Diferentes formatos en diferentes ramas:
```bash
# Reformatear toda la base de código
vendor/bin/pint

# Commit el cambio masivo
git add .
git commit -m "style: apply consistent formatting"
```

---

## 📚 **Más Información**

- **Laravel Pint**: https://laravel.com/docs/10.x/pint
- **EditorConfig**: https://editorconfig.org/
- **Prettier**: https://prettier.io/
- **Git Hooks**: https://git-scm.com/book/en/v2/Customizing-Git-Git-Hooks

---

## 🎯 **TL;DR**

**Problema**: Los archivos se reformatean automáticamente al hacer commit.

**Causa**: Hook de pre-commit ejecuta Laravel Pint.

**Solución**:
1. ✅ **Recomendado**: Configurar tu editor para usar espacios (4) en PHP
2. ⚠️ **Alternativa**: Desactivar el hook con `git commit --no-verify`
3. ❌ **No recomendado**: Eliminar `.hooks/pre-commit`

**Cambio reciente**: Se cambió de **tabs** a **espacios** en toda la configuración para evitar reformateos constantes.
