# 🛡️ AI Safety Guard - Sistema de Restricciones de Comandos

Este sistema protege el proyecto contra ejecución accidental o no autorizada de comandos peligrosos por parte de asistentes de IA.

## 📋 Archivos del Sistema

### 1. `.ai-command-restrictions`
Lista de comandos bloqueados para la IA:
- **Git commands**: Evita commits automáticos no autorizados
- **File system**: Previene eliminación accidental de archivos
- **System admin**: Bloquea comandos de administración del sistema
- **Database**: Protege contra operaciones destructivas en BD
- **Package management**: Evita publicaciones no autorizadas

### 2. `app/Security/AISafetyGuard.php`
Clase PHP que valida comandos antes de la ejecución:
- Carga la lista de restricciones
- Valida comandos contra la blacklist
- Registra intentos bloqueados
- Proporciona razones de bloqueo

### 3. `ai-blocked-commands.log`
Log de comandos bloqueados (se crea automáticamente)

## 🚫 Comandos Actualmente Bloqueados

### Git Operations
```bash
git add
git commit
git push
git pull
git merge
git rebase
git reset --hard
git clean -fd
git branch -D
git tag -d
```

### File System
```bash
rm -rf
rm -f
rmdir
del
format
fdisk
mkfs
```

### System Administration
```bash
sudo
su
chmod 777
chown
systemctl
service
kill -9
killall
```

### Database Operations
```sql
DROP DATABASE
DROP TABLE
TRUNCATE
DELETE FROM
```

### Package Management
```bash
npm publish
composer publish
docker push
docker rmi
```

### Laravel Destructive Commands
```bash
php artisan migrate:reset
php artisan migrate:fresh
php artisan db:wipe
php artisan queue:clear
php artisan cache:clear --force
```

## 🔧 Cómo Usar

### Para Desarrolladores Humanos
Los comandos funcionan normalmente. Este sistema solo afecta a la IA.

### Para la IA
Antes de ejecutar cualquier comando, el sistema debe validar:

```php
require_once 'app/Security/AISafetyGuard.php';

$command = "git commit -m 'test'";

try {
    AISafetyGuard::validateCommand($command);
    // ✅ Comando permitido
    exec($command);
} catch (Exception $e) {
    // ❌ Comando bloqueado
    echo $e->getMessage();
    AISafetyGuard::logBlockedAttempt($command);
}
```

## ➕ Agregar Nuevas Restricciones

Edita `.ai-command-restrictions` y agrega nuevos comandos:

```bash
# Nuevo comando peligroso
dangerous-command
another-risky-operation
```

## 📊 Monitoreo

Revisa `ai-blocked-commands.log` para ver intentos bloqueados:

```bash
tail -f ai-blocked-commands.log
```

## 🎯 Beneficios

1. **Prevención de Commits Accidentales**: Evita que la IA haga commits sin supervisión
2. **Protección del Sistema**: Bloquea comandos que podrían dañar el entorno
3. **Auditoría**: Registra todos los intentos de comandos peligrosos
4. **Flexibilidad**: Fácil agregar/quitar restricciones
5. **Transparencia**: La IA sabe qué comandos están bloqueados

## ⚡ Comandos Seguros Permitidos

La IA puede ejecutar comandos de solo lectura como:
```bash
ls
cat
grep
find
php artisan tinker --execute="echo 'test'"
php artisan list
```

## 🔄 Mantenimiento

- **Revisar logs**: Verificar intentos bloqueados regularmente
- **Actualizar restricciones**: Agregar nuevos comandos según sea necesario
- **Rotar logs**: Limpiar logs antiguos periódicamente

---

> **⚠️ Importante**: Este sistema es una capa de seguridad adicional. Siempre supervisa las acciones de la IA y revisa cambios antes de aprobarlos.
