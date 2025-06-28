# Development Guidelines

## Automatic Code Formatting with Laravel Pint

Este proyecto usa **Laravel Pint** para formatear automáticamente el código PHP en cada commit.

### 🚀 Setup Inicial (Solo la primera vez)

Cuando clones el repositorio o te unas al equipo, ejecuta:

```bash
./setup-hooks.sh
```

Este script configurará automáticamente:
- ✅ Laravel Pint (si no está instalado)
- ✅ Hooks de Git compartidos
- ✅ Configuración de formateo

### 📝 Configuración

- **Configuración**: `pint.json` (reglas de formateo del proyecto)
- **Hooks**: `.hooks/pre-commit` (script que se ejecuta antes de cada commit)
- **Preset**: Laravel con reglas personalizadas

### 🔧 Qué hace automáticamente

1. **Pre-commit**: Antes de cada commit, Pint formatea automáticamente los archivos PHP modificados
2. **Solo archivos staged**: Solo formatea archivos que están en el staging area
3. **Auto-add**: Los archivos formateados se agregan automáticamente al commit

### 💻 Comandos útiles

```bash
# Formatear todos los archivos PHP
./vendor/bin/pint

# Verificar qué archivos necesitan formateo (sin cambiar)
./vendor/bin/pint --test

# Formatear archivos específicos
./vendor/bin/pint app/Http/Controllers/

# Ver diferencias que aplicaría Pint
./vendor/bin/pint --test --diff
```

### 🎯 Flujo de trabajo

```bash
# 1. Hacer cambios en archivos PHP
vim app/Models/User.php

# 2. Agregar al staging
git add app/Models/User.php

# 3. Hacer commit (¡se formatea automáticamente!)
git commit -m "Update User model"

# ✨ Output del hook:
# 🎨 Checking code formatting with Laravel Pint...
# 📝 Found PHP files to format:
# app/Models/User.php
# 🚀 Formatting files...
# ✅ Files formatted and added to staging area
# 🎉 Pre-commit formatting complete!
```

### 🛠️ Reglas de formateo configuradas

- **Preset**: Laravel
- **Comillas**: Simple (`'`) en lugar de doble (`"`)
- **Espacios**: Un espacio alrededor de operadores binarios
- **Imports**: Ordenados alfabéticamente
- **Trailing commas**: En arrays y parámetros multilínea
- **EOL**: Una línea en blanco al final del archivo

### 🚨 Solución de problemas

#### El hook no funciona
```bash
# Re-ejecutar setup
./setup-hooks.sh

# Verificar configuración
git config core.hooksPath
# Debería mostrar: .hooks
```

#### Pint no está instalado
```bash
composer require laravel/pint --dev
```

#### Deshabilitar temporalmente
```bash
# Para un commit específico
git commit --no-verify -m "Emergency commit"

# Para deshabilitar permanentemente
git config core.hooksPath ""
```

### 👥 Para nuevos miembros del equipo

1. Clona el repositorio
2. Ejecuta `composer install`
3. Ejecuta `./setup-hooks.sh`
4. ¡Listo! El formateo automático está configurado

### 📚 Recursos adicionales

- [Laravel Pint Documentation](https://laravel.com/docs/pint)
- [PHP-CS-Fixer Rules](https://cs.symfony.com/doc/rules/index.html)
- [Git Hooks Documentation](https://git-scm.com/book/en/v2/Customizing-Git-Git-Hooks) 