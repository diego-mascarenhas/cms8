# 📞 PhoneHelper - Limpieza y Formateo de Números Telefónicos

Este documento explica cómo funciona el helper `PhoneHelper` para limpiar y formatear números telefónicos de manera consistente en toda la aplicación.

## 🎯 **Problemas Resueltos**

### **Antes (Problemas):**
- ❌ Números con doble código de país: `54056991393396`
- ❌ Números móviles con prefijo 15: `1567284492`
- ❌ Inconsistencia en limpieza entre módulos
- ❌ Espacios y caracteres especiales no manejados

### **Después (Soluciones):**
- ✅ Detección automática de códigos duplicados
- ✅ Conversión inteligente de números 15 a 54911
- ✅ Helper centralizado y consistente
- ✅ Soporte para múltiples países

## 🔧 **Funcionalidades del PhoneHelper**

### **1. Limpieza Básica**
```php
use App\Helpers\PhoneHelper;

$cleaned = PhoneHelper::clean('34 722 372 858');
// Resultado: '34722372858'
```

### **2. Detección de Códigos Duplicados**
```php
$cleaned = PhoneHelper::clean('54056991393396');
// Detecta: 54 + 056 (Chile con 0) 
// Resultado: '56991393396'
```

### **3. Conversión de Números 15**
```php
$cleaned = PhoneHelper::clean('1567284492');
// Detecta: 15 + número móvil
// Resultado: '5491167284492' (54911 + número)
```

### **4. Números de 8 Dígitos (Móviles Argentinos)**
```php
$cleaned = PhoneHelper::clean('67284492');
// Detecta: exactamente 8 dígitos
// Resultado: '5491167284492' (54911 + número)
```

### **5. Agregado de Código de País**
```php
$cleaned = PhoneHelper::clean('1167284492', '54');
// Detecta: número argentino sin código
// Resultado: '541167284492'
```

## 📋 **Ejemplos de Transformaciones**

| Input | Output | Explicación |
|-------|--------|-------------|
| `34 722 372 858` | `34722372858` | España - limpieza simple |
| `54 9 11 6728 4492` | `5491167284492` | Argentina móvil - limpieza |
| `52 11 7778 8975` | `521177788975` | México - limpieza |
| `54056991393396` | `56991393396` | Chile - código duplicado corregido |
| `1567284492` | `5491167284492` | Argentina móvil - prefijo 15 convertido |
| `67284492` | `5491167284492` | Argentina móvil - 8 dígitos convertido |
| `911234567` | `54911234567` | Argentina - código agregado |

## 🌍 **Códigos de País Soportados**

| Código | País | Ejemplo |
|--------|------|---------|
| `1` | US/Canadá | `15551234567` |
| `34` | España | `34722372858` |
| `52` | México | `521177788975` |
| `54` | Argentina | `5491167284492` |
| `56` | Chile | `56991393396` |
| `58` | Venezuela | `584121234567` |
| `33` | Francia | `33123456789` |
| `39` | Italia | `39312345678` |
| `49` | Alemania | `491234567890` |
| `44` | Reino Unido | `441234567890` |

## 🚀 **Uso en la Aplicación**

### **En Importaciones**
```php
// app/Console/Commands/ImportDataCommand.php
use App\Helpers\PhoneHelper;

$phone = $data->celular ?? $data->telefono ?? null;
$cleaned_phone = PhoneHelper::clean($phone, '54', true); // Con debug
```

### **En Controladores**
```php
// app/Http/Controllers/ContactController.php
use App\Helpers\PhoneHelper;

$cleanPhone = PhoneHelper::clean($request->phone);
```

### **En Modelos**
```php
// app/Models/Contact.php
use App\Helpers\PhoneHelper;

public function setPhoneAttribute($value)
{
    $this->attributes['phone'] = PhoneHelper::clean($value);
}
```

## 🔧 **Métodos Disponibles**

### **`clean($phone, $defaultCountryCode = '54', $debug = false)`**
Limpia y formatea un número telefónico.

**Parámetros:**
- `$phone`: Número a limpiar
- `$defaultCountryCode`: Código de país por defecto (Argentina: '54')
- `$debug`: Si mostrar logs de transformaciones

**Retorna:** String con número limpio o `null`

### **`hasCountryCode($phone)`**
Verifica si un número ya tiene código de país.

```php
PhoneHelper::hasCountryCode('5491123456789'); // true
PhoneHelper::hasCountryCode('91123456789');   // false
```

### **`formatForWhatsApp($phone)`**
Formatea para uso en WhatsApp (sin + ni espacios).

```php
PhoneHelper::formatForWhatsApp('54 911 2345 6789');
// Resultado: '5491123456789'
```

### **`formatForDisplay($phone)`**
Formatea para mostrar al usuario (con +).

```php
PhoneHelper::formatForDisplay('5491123456789');
// Resultado: '+5491123456789'
```

### **`isArgentineMobile($phone)`**
Verifica si es un número móvil argentino válido.

```php
PhoneHelper::isArgentineMobile('5491123456789'); // true
PhoneHelper::isArgentineMobile('541123456789');  // false (fijo)
```

### **`getCountryCode($phone)`**
Obtiene información del código de país.

```php
PhoneHelper::getCountryCode('5491123456789');
// Resultado: ['code' => '54', 'country' => 'AR']
```

## 🔍 **Patrones de Detección**

### **Códigos Duplicados Detectados:**
```php
'/^54056(\d+)/' => '56'     // Chile con 0 prefijo
'/^5456(\d+)/' => '56'      // Chile directo
'/^5434(\d+)/' => '34'      // España
'/^5452(\d+)/' => '52'      // México
'/^5433(\d+)/' => '33'      // Francia
'/^5439(\d+)/' => '39'      // Italia
'/^5449(\d+)/' => '49'      // Alemania
'/^5444(\d+)/' => '44'      // Reino Unido
'/^541(\d+)/' => '1'        // US/Canadá
```

### **Números Argentinos Detectados:**
```php
'/^(9|11|221|223|261|351|381|387|388|03\d{2}|0\d{3,4})/'
// Códigos de área argentinos
```

### **Conversión de Números 15:**
```php
'/^15(\d{8,})/' // 15 + 8 o más dígitos → 54911
```

### **Números de 8 Dígitos:**
```php
'/^\d{8}$/' // Exactamente 8 dígitos → 54911XXXXXXXX
```

## 📝 **Logs y Debug**

Cuando `$debug = true`, el helper genera logs:

```bash
[INFO] Phone transformed from 15xxxx to 54911xxxx: 1567284492 → 5491167284492
[INFO] Phone transformed from 8-digit to 54911xxxx: 67284492 → 5491167284492
[WARNING] Detected double country code: 54056991393396 → 56991393396
[INFO] Added country code 54: 1123456789 → 541123456789
```

## 🧪 **Testing**

### **Prueba Manual**
```bash
php artisan tinker

use App\Helpers\PhoneHelper;

$tests = [
    '34 722 372 858',
    '54 9 11 6728 4492', 
    '52 11 7778 8975',
    '54056991393396',
    '1567284492',
    '67284492'  // 8-digit number
];

foreach ($tests as $phone) {
    $cleaned = PhoneHelper::clean($phone, '54', true);
    echo "Input: {$phone} → Output: {$cleaned}" . PHP_EOL;
}
```

### **Unit Tests**
```php
// tests/Unit/PhoneHelperTest.php
public function test_cleans_phone_numbers()
{
    $this->assertEquals('34722372858', PhoneHelper::clean('34 722 372 858'));
    $this->assertEquals('56991393396', PhoneHelper::clean('54056991393396'));
    $this->assertEquals('5491167284492', PhoneHelper::clean('1567284492'));
    $this->assertEquals('5491167284492', PhoneHelper::clean('67284492')); // 8-digit test
}
```

## ⚠️ **Consideraciones Importantes**

### **Limitaciones:**
1. **Solo detecta códigos conocidos** - Países no listados no serán procesados
2. **Asume patrones específicos** - Números muy atípicos pueden no procesarse
3. **Argentina por defecto** - Diseñado principalmente para números argentinos

### **Recomendaciones:**
1. **Usar con debug** durante importaciones para verificar transformaciones
2. **Validar resultados** especialmente para números internacionales
3. **Mantener lista actualizada** de códigos de país según necesidades

### **Casos Especiales:**
- **Números muy cortos** (< 8 dígitos) pueden no procesarse
- **Números con extensiones** no están soportados
- **Códigos de país de 3 dígitos** no están implementados

---

> **💡 Tip**: Usar siempre `PhoneHelper::clean()` en lugar de `preg_replace('/\D/', '', $phone)` para consistencia y funcionalidad avanzada.
