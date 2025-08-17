# Sistema de Productos para WhatsApp

Este documento describe la implementación del sistema de productos consultable por WhatsApp en el proyecto Humano.

## 🚀 Características Implementadas

### ✅ Funcionalidades Básicas
- **Catálogo de productos** consultable por WhatsApp
- **Búsqueda por palabras clave** (productos, servicios, hosting, dominio, etc.)
- **Agrupación por categorías** para mejor organización
- **Información detallada** de cada producto (nombre, precio, descripción)
- **Integración con Twilio** para respuestas automáticas

### 🛍️ Comandos Disponibles
- `productos` - Ver catálogo completo
- `servicios` - Ver lista de servicios
- `catalogo` - Ver catálogo organizado
- `precios` - Ver información de precios
- `hosting` - Consultar servicios de hosting
- `dominio` - Consultar servicios de dominios
- `desarrollo` - Consultar servicios de desarrollo

## 📁 Archivos Creados/Modificados

### Nuevos Archivos
- `app/Models/Product.php` - Modelo de productos
- `database/migrations/xxxx_create_products_table.php` - Migración de productos
- `database/factories/ProductFactory.php` - Factory para productos de prueba
- `database/seeders/ProductSeeder.php` - Seeder para Team Demo
- `app/Console/Commands/TestProductSystem.php` - Comando de prueba
- `config/shopping_cart.php` - Configuración del carrito

### Archivos Modificados
- `app/Services/TwilioService.php` - Agregada funcionalidad de productos

## 🗄️ Estructura de la Base de Datos

### Tabla `products`
```sql
CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    status BOOLEAN DEFAULT TRUE,
    whatsapp_enabled BOOLEAN DEFAULT TRUE,
    team_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (team_id) REFERENCES teams(id)
);
```

## 🔧 Instalación y Configuración

### 1. Instalar Dependencias
```bash
composer require "darryldecode/cart"
```

### 2. Publicar Configuración
```bash
php artisan vendor:publish --provider="Darryldecode\Cart\CartServiceProvider" --tag="config"
```

### 3. Ejecutar Migraciones
```bash
php artisan migrate
```

### 4. Ejecutar Seeders
```bash
php artisan db:seed --class=ProductSeeder
```

## 🧪 Pruebas del Sistema

### Comando de Prueba
```bash
php artisan test:products 5491112345678
```

### Pruebas Manuales
Enviar mensajes de WhatsApp con:
- "productos"
- "servicios"
- "catalogo"
- "hosting"

## 📱 Flujo de WhatsApp

### 1. Usuario Envía Mensaje
```
Usuario: "productos"
```

### 2. Sistema Detecta Comando
- Analiza palabras clave
- Identifica comando de productos
- Ejecuta `processProductCommands()`

### 3. Sistema Responde
- Obtiene productos activos
- Agrupa por categorías
- Formatea respuesta con emojis
- Envía catálogo completo

### 4. Respuesta Ejemplo
```
🛍️ Catálogo de Productos y Servicios

📂 Hosting
• Hosting Web Básico
  💰 $29.99
  📝 Hosting web con 10GB de espacio SSD...

• Hosting Web Premium
  💰 $59.99
  📝 Hosting web premium con 50GB de espacio...

💡 Para contratar:
• Escribe: contratar [nombre del producto]
• O contacta soporte: https://revisionalpha.com/contactenos

🛒 Tu carrito: Escribe carrito para ver tus productos seleccionados
```

## 🎯 Productos del Team Demo

### Hosting y Dominios
- **Hosting Web Básico** - $29.99/mes
- **Hosting Web Premium** - $59.99/mes
- **Dominio .com** - $19.99/año
- **Dominio .net** - $24.99/año

### Seguridad y Certificados
- **Certificado SSL Básico** - $49.99/año
- **Certificado SSL Wildcard** - $199.99/año
- **Backup Automático** - $15.99/mes

### Desarrollo y Consultoría
- **Desarrollo Web Básico** - $999.99
- **Desarrollo Web Premium** - $2,499.99
- **App Móvil Básica** - $1,499.99
- **Consultoría IT** - $199.99/sesión

### Soporte y Servicios
- **Soporte Técnico Básico** - $79.99/mes
- **Soporte Técnico Premium** - $149.99/mes
- **Migración de Servidor** - $299.99
- **Optimización SEO** - $399.99

## 🔮 Próximos Pasos

### Funcionalidades Pendientes
- [ ] **Sistema de carrito** con Laravel Shopping Cart
- [ ] **Comando "contratar"** para agregar productos al carrito
- [ ] **Comando "carrito"** para ver productos seleccionados
- [ ] **Proceso de checkout** por WhatsApp
- [ ] **Integración con pasarelas de pago**
- [ ] **Notificaciones de pedidos** a administradores

### Mejoras Técnicas
- [ ] **Cache de productos** para mejor rendimiento
- [ ] **Búsqueda avanzada** por nombre o descripción
- [ ] **Filtros por precio** y categoría
- [ ] **Imágenes de productos** en respuestas
- [ ] **Sistema de inventario** y disponibilidad

## 🐛 Solución de Problemas

### Error: "No hay productos disponibles"
- Verificar que existan productos en la base de datos
- Ejecutar `php artisan db:seed --class=ProductSeeder`
- Verificar que `status = true` y `whatsapp_enabled = true`

### Error: "No hay categorías disponibles"
- Ejecutar `php artisan db:seed --class=CategorySeeder`
- Verificar que existan categorías en la base de datos

### Error: "No hay monedas disponibles"
- Ejecutar `php artisan db:seed --class=CurrencySeeder`
- Verificar que existan monedas en la base de datos

## 📞 Soporte

Para problemas técnicos o consultas sobre el sistema de productos:
- **Email**: soporte@revisionalpha.com
- **WhatsApp**: +54 9 11 1234-5678
- **Sitio Web**: https://revisionalpha.com/contactenos

---

**Desarrollado por:** Equipo de Desarrollo Humano  
**Última actualización:** Agosto 2025  
**Versión:** 1.0.0
