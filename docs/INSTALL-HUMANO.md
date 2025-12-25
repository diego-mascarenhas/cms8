# Instalación del Sistema Modular Humano

## Arquitectura de Packages

El sistema Humano está dividido en packages modulares:

```
📦 idoneo/humano-core           ← Base framework  
├── Users, Settings, Dashboard, Teams
├── Modular system (Module model)
├── Base layouts y UI
├── Authentication (Jetstream)  
├── Categories, Notes  
└── Core components

📦 idoneo/humano-crm            ← CRM Module
├── Contacts, Projects, Services, Tasks
└── Activity tracking

📦 idoneo/humano-billing        ← Billing Module
├── Invoices, Payments
├── Accounting, Financial
└── Stripe integration

📦 idoneo/humano-communications ← Communications
├── Mail (Imap)
├── Chat, Notifications
├── Mailer
└── Templates

📦 idoneo/humano-hosting        ← Hosting Management
├── Servers, Domains
├── WHM/cPanel integration
└── Hosting automation
```

## Instalación para Desarrollo Local

### 1. Clonar el proyecto base
```bash
cd ~/Sites
git clone [repository-url] humano
cd humano
git checkout package  # Rama con la arquitectura modular
```

### 2. Instalar dependencias
```bash
composer install
```
Los packages se instalarán automáticamente como symlinks desde `packages/idoneo/`

### 3. Configurar el entorno
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configurar la base de datos
```bash
# Configurar DB en .env
php artisan migrate
```

### 5. Instalar módulos específicos
```bash
# Instalación completa
php artisan humano:install --modules=crm,billing,communications,hosting

# Instalación selectiva
php artisan humano:install --modules=crm,billing

# Instalación interactiva
php artisan humano:install
```

## Para Nuevos Clientes

### Crear nuevo proyecto
```bash
composer create-project idoneo/humano-core client-xyz
cd client-xyz  
php artisan humano:install --modules=billing,communications
```

### Configuración específica por cliente
```bash
# Solo CRM
php artisan humano:install --modules=crm

# CRM + Facturación
php artisan humano:install --modules=crm,billing

# Suite completa
php artisan humano:install --modules=crm,billing,communications,hosting
```

## Desarrollo en Packages

### Estructura de desarrollo
```bash
~/Sites/humano/
├── packages/idoneo/
│   ├── humano-core/         ← Desarrollo del core
│   ├── humano-crm/          ← Desarrollo del CRM
│   ├── humano-billing/      ← Desarrollo de facturación
│   ├── humano-communications/ ← Desarrollo de comunicaciones
│   └── humano-hosting/      ← Desarrollo de hosting
└── vendor/idoneo/           ← Symlinks automáticos
    ├── humano-core -> ../../packages/idoneo/humano-core/
    ├── humano-crm -> ../../packages/idoneo/humano-crm/
    └── ...
```

### Trabajar en un package específico
```bash
# Los cambios en packages/ se reflejan automáticamente
cd packages/idoneo/humano-crm/
# Editar archivos...
# Los cambios son inmediatamente visibles en la aplicación
```

### Comandos útiles
```bash
# Regenerar autoloader después de añadir clases
composer dump-autoload

# Limpiar caches
php artisan config:clear
php artisan cache:clear

# Ver packages instalados
composer show idoneo/*
```

## Estructura de un Package

```
packages/idoneo/humano-[module]/
├── composer.json           ← Configuración del package
├── src/
│   ├── Console/           ← Comandos Artisan
│   ├── Http/
│   │   ├── Controllers/   ← Controladores
│   │   ├── Middleware/    ← Middleware específico
│   │   └── Requests/      ← Form Requests
│   ├── Models/            ← Modelos Eloquent
│   ├── Providers/         ← Service Providers
│   └── Services/          ← Lógica de negocio
├── database/
│   ├── migrations/        ← Migraciones
│   ├── seeders/          ← Seeders
│   └── factories/        ← Factories
├── resources/
│   ├── views/            ← Vistas Blade
│   └── lang/             ← Traducciones
├── routes/
│   └── web.php           ← Rutas del package
└── config/
    └── humano-[module].php ← Configuración
```

## Publicar Packages

### Para producción
```bash
# 1. Commitear cambios en cada package
cd packages/idoneo/humano-core && git add . && git commit -m "Update core"

# 2. Crear tags de versión
git tag v1.0.0 && git push origin v1.0.0

# 3. Publicar en repositorio privado
# (configurar repositorios en composer.json de producción)
```

### Configuración para producción
```json
{
  "repositories": [
    {
      "type": "git",
      "url": "https://github.com/idoneo/humano-core.git"
    },
    {
      "type": "git", 
      "url": "https://github.com/idoneo/humano-crm.git"
    }
  ],
  "require": {
    "idoneo/humano-core": "^1.0",
    "idoneo/humano-crm": "^1.0"
  }
}
```

## Comandos Disponibles

```bash
# Instalar sistema modular
php artisan humano:install

# Con módulos específicos
php artisan humano:install --modules=crm,billing

# Ver estado de módulos
php artisan humano:status

# Instalar/desinstalar módulos individuales
php artisan humano:module:install crm
php artisan humano:module:remove communications
```

## Ventajas del Sistema Modular

1. **Desarrollo independiente**: Cada módulo se desarrolla por separado
2. **Instalación selectiva**: Solo instalar lo que el cliente necesita
3. **Mantenimiento simplificado**: Actualizaciones modulares
4. **Testing aislado**: Cada package tiene sus propios tests
5. **Escalabilidad**: Fácil añadir nuevos módulos
6. **Reutilización**: Packages reutilizables entre proyectos

## Próximos Pasos

1. ✅ Estructura base creada
2. ✅ Symlinks configurados
3. ✅ Sistema de instalación modular
4. 🔄 Migrar componentes existentes a packages
5. 🔄 Crear tests unitarios para cada package
6. 🔄 Documentación completa de APIs
7. 🔄 CI/CD para packages individuales
