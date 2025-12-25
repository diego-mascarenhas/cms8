# 🧪 Guía de Pruebas: Sistema de Mensajes Dinámicos

## Objetivo
Verificar que el sistema crea/elimina entregas automáticamente cuando los contactos cumplen o dejan de cumplir los criterios del mensaje.

---

## ✅ PRUEBA 1: Bloqueo de Edición de Filtros

### Pasos:
1. Visita `http://humano.test/message/list`
2. Crea un mensaje nuevo con:
   - Nombre: "Prueba Dinámica 1"
   - Categoría: Cualquiera con contactos
   - Estado de Contacto: "Lead" (o el que tengas)
   - Actívalo y espera a que se creen entregas
3. Ve a editar el mensaje
4. **Verifica:** Los campos "Categoría" y "Estado de Contacto" deben aparecer **deshabilitados (grises)**
5. **Verifica:** Debe mostrar mensajes de advertencia naranjas debajo de cada campo

### Resultado Esperado:
- ✅ No puedes cambiar categoría ni estado cuando hay entregas
- ✅ Los mensajes de advertencia son claros

---

## ✅ PRUEBA 2: Creación Dinámica de Entregas

### Preparación:
```bash
# Terminal 1: Limpia entregas existentes (opcional)
php artisan tinker
>>> App\Models\MessageDelivery::truncate();
>>> exit
```

### Pasos:
1. Crea un mensaje con:
   - Categoría: Una con 3-5 contactos
   - Estado: "Lead"
2. Activa el mensaje desde la interfaz
3. En terminal, ejecuta:
   ```bash
   php artisan campaigns:process-active
   ```
4. Recarga la página del mensaje y verifica:
   - **Suscriptores:** Debe mostrar el número correcto de contactos
   - **Entregas:** Debe haber creado entregas

### Resultado Esperado:
- ✅ Se crean entregas automáticamente
- ✅ El conteo de suscriptores es correcto

---

## ✅ PRUEBA 3: Sistema Dinámico - Agregar Contacto

### Pasos:
1. Con el mensaje de la prueba 2 **activo y con entregas creadas**
2. Ve a Contactos y crea/edita un contacto para que:
   - Esté en la categoría del mensaje
   - Tenga el estado del mensaje
3. En terminal, ejecuta:
   ```bash
   php artisan campaigns:process-active
   ```
4. Recarga la página del mensaje

### Resultado Esperado:
- ✅ Se crea automáticamente una entrega para el nuevo contacto
- ✅ El contador de suscriptores aumenta en 1

---

## ✅ PRUEBA 4: Sistema Dinámico - Quitar Contacto

### Pasos:
1. Con el mensaje activo y entregas **pendientes** (no enviadas)
2. Toma un contacto que tenga entrega pendiente
3. Cámbialo a una categoría diferente o estado diferente
4. En terminal, ejecuta:
   ```bash
   php artisan campaigns:process-active
   ```
5. Verifica en la tabla de entregas del mensaje

### Resultado Esperado:
- ✅ La entrega pendiente del contacto se elimina automáticamente
- ✅ El contador de suscriptores disminuye en 1
- ✅ Si la entrega ya se envió, NO se elimina (mantiene historial)

---

## ✅ PRUEBA 5: Conteo Correcto con Filtros

### Pasos:
1. Crea un mensaje con:
   - Categoría: "CMS+" (o una con varios contactos)
   - Estado: "Cliente"
2. Antes de activar, verifica en "Información General":
   - **Contactos:** Debe mostrar solo los que son "Cliente" en "CMS+"
3. Activa el mensaje y ejecuta:
   ```bash
   php artisan campaigns:process-active
   ```
4. Verifica que el número de entregas = número de contactos filtrados

### Resultado Esperado:
- ✅ El conteo de contactos respeta ambos filtros (categoría + estado)
- ✅ Se crean entregas solo para contactos que cumplen AMBOS criterios

---

## 🔧 Comandos Útiles

```bash
# Ver entregas de un mensaje específico
php artisan tinker
>>> App\Models\MessageDelivery::where('message_id', 1)->count()

# Ver contactos que cumplen criterios de un mensaje
>>> $message = App\Models\Message::find(1);
>>> $message->category->contacts()->where('status_id', $message->contact_status_id)->count();

# Limpiar todas las entregas (CUIDADO)
>>> App\Models\MessageDelivery::truncate();

# Ver log del scheduler
tail -f storage/logs/laravel.log | grep "ProcessActiveCampaigns"
```

---

## 📊 Resultados de Pruebas

| Prueba | Estado | Notas |
|--------|--------|-------|
| 1. Bloqueo de edición | ⬜ | |
| 2. Creación dinámica | ⬜ | |
| 3. Agregar contacto | ⬜ | |
| 4. Quitar contacto | ⬜ | |
| 5. Conteo con filtros | ⬜ | |

---

## 🐛 Si Algo Falla

1. **No se crean entregas:**
   - Verifica que el mensaje tenga `status_id = 1` y `started_at` no sea null
   - Ejecuta manualmente `php artisan campaigns:process-active`
   - Revisa los logs: `tail -f storage/logs/laravel.log`

2. **Conteo incorrecto:**
   - Verifica que el mensaje tenga `category_id` y `contact_status_id` configurados
   - Usa tinker para verificar manualmente el conteo

3. **Campos no se deshabilitan:**
   - Verifica que el mensaje tenga al menos 1 entrega en `message_deliveries`
   - Limpia caché: `php artisan view:clear`

