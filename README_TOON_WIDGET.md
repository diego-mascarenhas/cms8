# Widget de Toon - Uso de API & Ahorro

## ✅ Implementación Completada

Se ha implementado exitosamente Toon y el widget de "Uso de API & Ahorro" en el dashboard de Humano.

## ⚠️ CONFIGURACIÓN REQUERIDA

**IMPORTANTE**: Para que los logs se guarden, debes configurar la API key de Anthropic en tu `.env`:

```bash
ANTHROPIC_API_KEY=tu_api_key_aquí
```

Si ya tienes `CLAUDE_API_KEY` configurado, usa el mismo valor:

```bash
ANTHROPIC_API_KEY=el_mismo_valor_de_CLAUDE_API_KEY
```

**Ver instrucciones completas en**: `SETUP_ANTHROPIC.txt` en la raíz del proyecto.

## 📊 ¿Qué hace el widget?

El widget muestra en tiempo real:

1. **Llamadas** - Total de llamadas a APIs de IA realizadas
2. **Ahorro** - Cantidad de tokens ahorrados usando Toon
3. **Porcentaje de ahorro** - Promedio de ahorro en todas las llamadas
4. **Comparativa** - Tokens usados vs. tokens sin optimización

## 🎯 Servicios con logging automático

Los siguientes servicios YA están registrando automáticamente su uso:

### 1. AstralChartService
- **Qué registra**: Generación de perfiles astrológicos con Claude
- **Cuándo se registra**: Cada vez que se genera un perfil astrológico para un contacto
- **Ubicación**: `app/Services/AstralChartService.php`

### 2. ClaudeService
- **Qué registra**: Todas las conversaciones de chat con Claude
- **Cuándo se registra**: Cada mensaje enviado a Claude via WhatsApp o chat
- **Ubicación**: `app/Services/ClaudeService.php`

## 📍 Ubicación del widget

El widget se encuentra en el **Dashboard principal** (`/dashboard`), en la columna derecha, justo arriba del "Balance emocional".

## 🔍 Verificar funcionamiento

### Opción 1: Generar un perfil astrológico

1. Ve a un contacto con fecha de nacimiento
2. En la pestaña "General", busca la sección "Perfil Astrológico"
3. Si no tiene perfil, se generará automáticamente
4. Ve al dashboard y verás que las estadísticas se actualizaron

### Opción 2: Usar Claude via WhatsApp

1. Envía un mensaje via WhatsApp
2. Si Claude responde, se registrará la llamada
3. Ve al dashboard y verás las estadísticas actualizadas

### Opción 3: Verificar la tabla directamente

```bash
php artisan tinker
```

Luego ejecuta:

```php
// Ver total de llamadas
\App\Models\TokenUsageLog::count();

// Ver estadísticas
echo "Llamadas: " . \App\Models\TokenUsageLog::getTotalCalls() . "\n";
echo "Tokens ahorrados: " . \App\Models\TokenUsageLog::getTotalTokensSaved() . "\n";
echo "Promedio ahorro: " . \App\Models\TokenUsageLog::getAverageSavingsPercentage() . "%\n";

// Ver últimos registros
\App\Models\TokenUsageLog::latest()->take(5)->get(['service', 'json_tokens', 'toon_tokens', 'savings_percentage', 'created_at']);
```

## 🗄️ Estructura de la base de datos

La tabla `token_usage_logs` contiene:

| Campo | Descripción |
|-------|-------------|
| `service` | Nombre del servicio (AstralChartService, ClaudeService) |
| `json_size` | Tamaño del JSON sin comprimir (bytes) |
| `toon_size` | Tamaño con Toon (bytes) |
| `json_tokens` | Tokens estimados sin Toon |
| `toon_tokens` | Tokens con Toon |
| `savings_percentage` | Porcentaje de ahorro |
| `used_toon` | Si se usó Toon o no |
| `created_at` | Fecha y hora del registro |

## 📈 Datos de prueba

Se crearon 4 registros de prueba para visualizar el widget:

- **4 llamadas** en total
- **3,325 tokens** ahorrados
- **56.67%** de ahorro promedio
- **3,175 tokens** usados (optimizado)
- **6,500 tokens** sin optimización

## 🚀 Agregar logging a nuevos servicios

Si quieres agregar el logging a un nuevo servicio que haga llamadas a APIs de IA:

```php
use App\Models\TokenUsageLog;
use Sbsaga\Toon\Facades\Toon;

// Antes de hacer la llamada a la API
$jsonData = json_encode($payload);
$jsonSize = strlen($jsonData);
$jsonTokens = round($jsonSize / 4);

// Comprimir con Toon
$useToon = true;
try {
    $toonData = Toon::encode($payload);
    $toonSize = strlen($toonData);
    $toonTokens = round($toonSize / 4);
} catch (\Exception $e) {
    \Log::warning('Toon encoding failed: ' . $e->getMessage());
    $useToon = false;
    $toonSize = $jsonSize;
    $toonTokens = $jsonTokens;
}

// Hacer la llamada a la API...
$response = Http::post('...', $payload);

// Registrar el uso
$savings = $useToon && $jsonSize > 0
    ? round((($jsonSize - $toonSize) / $jsonSize) * 100, 2)
    : 0;

TokenUsageLog::create([
    'service' => 'NombreDelServicio',
    'json_size' => $jsonSize,
    'toon_size' => $toonSize,
    'json_tokens' => $jsonTokens,
    'toon_tokens' => $toonTokens,
    'savings_percentage' => $savings,
    'used_toon' => $useToon,
]);
```

## 📚 Documentación adicional

Para más detalles sobre el uso de Toon, consulta:
- `/docs/TOON_USAGE.md` - Guía completa de uso
- Proyecto de referencia: `../app.fanyion/`

## ⚠️ Notas importantes

1. **Los datos de prueba**: Si ya no necesitas los 4 registros de prueba, puedes eliminarlos:
   ```bash
   php artisan tinker
   \App\Models\TokenUsageLog::where('service', 'TestService')->delete();
   ```

2. **El widget muestra datos en tiempo real**: Cada vez que se hace una llamada a Claude, se actualiza automáticamente.

3. **Formato compacto**: Los números grandes se muestran en formato compacto (1.2K, 1.5M) para mejor legibilidad.

## ✨ Resultado

El widget ahora te permite:
- Monitorear el uso de APIs de IA en tiempo real
- Ver cuánto dinero estás ahorrando con Toon
- Identificar qué servicios hacen más llamadas
- Tomar decisiones informadas sobre el uso de IA

¡El sistema está funcionando y listo para usar! 🎉
