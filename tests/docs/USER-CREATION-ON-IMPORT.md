# 👥 Creación Automática de Usuarios en Importación

Este documento explica cómo el comando `import:interactive` ahora crea usuarios automáticamente basado en el campo `area_privada` de los contactos importados.

## 🎯 **Funcionalidad Implementada**

### **Mapeo de `area_privada` a Roles**

| `area_privada` | Rol Asignado | Descripción |
|----------------|--------------|-------------|
| `2` | `admin` | Administrador con permisos completos |
| `3` | `client` | Cliente con acceso limitado |
| `4` | `user` | Usuario básico |
| **Otros valores** | ❌ **No se crea usuario** | Solo se importa como contacto |

### **Requisitos para Crear Usuario**

1. ✅ `area_privada` debe ser `2`, `3`, o `4`
2. ✅ El contacto debe tener email válido
3. ✅ El email no debe existir previamente

## 🔧 **Proceso de Creación**

### **1. Validación del Contacto**
```php
$shouldCreateUser = in_array($data->area_privada, [2, 3, 4]);
if ($shouldCreateUser && $data->email) {
    // Proceder con creación de usuario
}
```

### **2. Mapeo de Roles**
```php
$roleMapping = [
    2 => 'admin',   // Administrador
    3 => 'client',  // Cliente  
    4 => 'user'     // Usuario básico
];
```

### **3. Creación del Usuario**
```php
$user = User::create([
    'name' => trim($data->nombre . ' ' . ($data->apellido ?? '')),
    'email' => $data->email,
    'phone' => $cleaned_phone,
    'password' => Hash::make('password123'), // Password temporal
    'email_verified_at' => now(),
    'created_at' => $data->fecha_alta,
    'updated_at' => $data->fecha_modificacion,
]);

// Asignar rol
$user->assignRole($roleName);

// Asignar al equipo CMS
$user->teams()->attach($teamId, ['role' => $roleName]);
```

### **4. Asociación Contacto-Usuario**
```php
$contactData = [
    'user_id' => $userId,  // ← Usuario vinculado
    // ... resto de datos del contacto
];
```

## 📊 **Estadísticas de Importación**

El comando ahora muestra estadísticas detalladas:

```bash
Successfully imported 25 records.
Updated 5 existing records.
Users created: 8
Users existing: 3  
Users skipped: 14
```

### **Interpretación de Estadísticas**

- **`Users created`**: Nuevos usuarios creados durante la importación
- **`Users existing`**: Usuarios que ya existían con el mismo email
- **`Users skipped`**: Contactos que no requieren usuario o no cumplen criterios

## 🔐 **Configuración de Seguridad**

### **Password Temporal**
- **Password por defecto**: `password123`
- **Verificación de email**: Automáticamente verificado
- **Cambio obligatorio**: Se recomienda forzar cambio en primer login

### **Permisos por Rol**

#### **Admin** (`area_privada = 2`)
- ✅ Acceso completo al sistema
- ✅ Gestión de usuarios
- ✅ Configuración del sistema
- ✅ Todos los módulos disponibles

#### **Client** (`area_privada = 3`)
- ✅ Perfil personal
- ✅ Servicios contratados
- ✅ Facturas y pagos
- ✅ Proyectos asociados
- ❌ Gestión de otros usuarios

#### **User** (`area_privada = 4`)
- ✅ Perfil personal
- ✅ Funciones básicas
- ❌ Acceso limitado a módulos

## 🚀 **Uso del Comando**

### **Importación Interactiva**
```bash
php artisan import:interactive
# Seleccionar: 1. Users
# Seguir menú interactivo
```

### **Importación Automática**
```bash
php artisan import:interactive --auto
# Importa empresas y contactos automáticamente
```

### **Importación Específica**
```bash
php artisan import:interactive
# Seleccionar: 1. Users
# Seleccionar: 4. Import Specific ID
# Ingresar ID del contacto
```

## 📝 **Log de Actividad**

### **Mensajes de Información**
```bash
Usuario creado: user@example.com con rol client (ID: 123)
Usuario existente encontrado: admin@example.com (ID: 456)  
Contacto 789 - area_privada=5 no requiere usuario
Contacto 101 - sin email, no se puede crear usuario
```

### **Mensajes de Error**
```bash
Error creando usuario user@example.com: [detalle del error]
```

## 🔄 **Casos Especiales**

### **Email Duplicado**
- Si el email ya existe, se usa el usuario existente
- No se crea usuario nuevo
- Se actualiza la relación contacto-usuario

### **Area Privada No Válida**
- `area_privada = 1`: No se crea usuario
- `area_privada = 5`: No se crea usuario  
- `area_privada = 6`: No se crea usuario
- Solo se importa como contacto sin usuario asociado

### **Sin Email**
- Contactos sin email no pueden tener usuario
- Se importan solo como contactos
- Se registra en logs como "skipped"

## 🔧 **Datos Adicionales en Contacto**

Los contactos importados incluyen metadata adicional:

```json
{
    "imported_from_cms7": true,
    "area_privada": 3,
    "original_id": 12345
}
```

## ⚠️ **Consideraciones Importantes**

1. **Password Temporal**: Todos los usuarios creados tendrán password `password123`
2. **Verificación**: Los emails se marcan como verificados automáticamente
3. **Equipos**: Los usuarios se asignan automáticamente al equipo CMS
4. **Roles**: Los roles deben existir previamente en el sistema
5. **Reversión**: No hay funcionalidad automática de rollback

## 🧪 **Testing**

### **Verificar Usuario Creado**
```bash
php artisan tinker
>>> User::where('email', 'test@example.com')->first()
>>> User::role('client')->count()
```

### **Verificar Relación Contacto-Usuario**
```bash
>>> $contact = Contact::find(123)
>>> $contact->user
>>> $contact->user->roles
```

---

> **💡 Tip**: Después de la importación, considera enviar emails a los nuevos usuarios con instrucciones para cambiar su password temporal.
