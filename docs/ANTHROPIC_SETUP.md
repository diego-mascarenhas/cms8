# Configuración de Anthropic Claude API

## Variables de entorno requeridas

Para que los servicios de IA funcionen correctamente y se registren los logs de tokens, debes configurar las siguientes variables en tu archivo `.env`:

```bash
# Anthropic Claude API Configuration
ANTHROPIC_API_KEY=tu_api_key_aquí
ANTHROPIC_MODEL=claude-sonnet-4-5-20250929
ANTHROPIC_API_URL=https://api.anthropic.com/v1
ANTHROPIC_MAX_TOKENS=4096
ANTHROPIC_TEMPERATURE=0.7
ANTHROPIC_TIMEOUT=30
```

## Obtener tu API Key

1. Ve a [https://console.anthropic.com/](https://console.anthropic.com/)
2. Inicia sesión o crea una cuenta
3. Ve a la sección "API Keys"
4. Crea una nueva API key
5. Copia la key y pégala en tu archivo `.env`

## ¿Qué servicios usan esta configuración?

Los siguientes servicios utilizan la configuración de Anthropic:

### 1. AstralChartService
- **Función**: Genera perfiles astrológicos personalizados
- **Cuándo se ejecuta**: Al generar un perfil astrológico para un contacto
- **Ubicación**: `app/Services/AstralChartService.php`

### 2. ClaudeService
- **Función**: Servicio general para chat con Claude
- **Cuándo se ejecuta**: Conversaciones via WhatsApp, chat interno, etc.
- **Ubicación**: `app/Services/ClaudeService.php`

## Verificar configuración

Puedes verificar si la API key está configurada correctamente:

```bash
php artisan tinker
```

Luego ejecuta:

```php
// Verificar que la API key existe
config('anthropic.api_key') ? 'Configurada ✓' : 'No configurada ✗';

// Ver configuración completa
config('anthropic');
```

## Probar funcionamiento

### Opción 1: Generar un perfil astrológico

1. Ve a un contacto que tenga fecha de nacimiento configurada
2. En la pestaña "General", localiza la sección "Perfil Astrológico"
3. Si no tiene perfil, se generará automáticamente usando Claude
4. Verifica en los logs si se registró:

```bash
php artisan tinker
```

```php
// Ver últimos logs de AstralChartService
\App\Models\TokenUsageLog::where('service', 'AstralChartService')
    ->latest()
    ->take(5)
    ->get(['json_tokens', 'toon_tokens', 'savings_percentage', 'created_at']);
```

### Opción 2: Ver estadísticas en el Dashboard

1. Ve a `/dashboard`
2. En la columna derecha verás el widget "Uso de API & Ahorro"
3. Las estadísticas se actualizarán automáticamente cada vez que se use Claude

## Formato de las variables

### ANTHROPIC_API_KEY (Requerida)
- **Formato**: `sk-ant-...` (comienza con sk-ant-)
- **Dónde obtenerla**: [https://console.anthropic.com/](https://console.anthropic.com/)
- **Ejemplo**: `ANTHROPIC_API_KEY=sk-ant-api03-xxxxx...`

### ANTHROPIC_MODEL (Opcional)
- **Valor por defecto**: `claude-3-5-sonnet-20241022`
- **Opciones disponibles**:
  - `claude-3-5-sonnet-20241022` (Recomendado - Balance entre velocidad y calidad)
  - `claude-3-opus-20240229` (Más potente pero más costoso)
  - `claude-3-haiku-20240307` (Más rápido y económico)

### ANTHROPIC_MAX_TOKENS (Opcional)
- **Valor por defecto**: `4096`
- **Rango recomendado**: `1000` - `8000`
- **Nota**: Más tokens = respuestas más largas pero mayor costo

### ANTHROPIC_TEMPERATURE (Opcional)
- **Valor por defecto**: `0.7`
- **Rango**: `0.0` - `1.0`
- **0.0**: Respuestas más deterministas y consistentes
- **1.0**: Respuestas más creativas y variadas

### ANTHROPIC_TIMEOUT (Opcional)
- **Valor por defecto**: `30`
- **Unidad**: Segundos
- **Rango recomendado**: `10` - `60`

## Troubleshooting

### Problema: Los logs no se guardan

**Solución 1**: Verifica que la API key esté configurada
```bash
php artisan tinker
config('anthropic.api_key')
```

**Solución 2**: Limpia la caché de configuración
```bash
php artisan config:clear
php artisan optimize:clear
```

**Solución 3**: Verifica que la tabla existe
```bash
php artisan tinker
\App\Models\TokenUsageLog::count()
```

Si la tabla no existe:
```bash
php artisan migrate
```

### Problema: Error "API key not configured"

Asegúrate de que la variable `ANTHROPIC_API_KEY` está en tu archivo `.env` y no tiene espacios antes o después del valor:

```bash
# ✅ Correcto
ANTHROPIC_API_KEY=sk-ant-api03-xxxxx

# ❌ Incorrecto (espacios)
ANTHROPIC_API_KEY = sk-ant-api03-xxxxx
ANTHROPIC_API_KEY= sk-ant-api03-xxxxx
```

### Problema: Error "401 Unauthorized"

Tu API key es inválida o ha expirado. Genera una nueva en [https://console.anthropic.com/](https://console.anthropic.com/).

### Problema: Error "429 Too Many Requests"

Has excedido tu límite de rate limit. Espera unos minutos o verifica tu plan en Anthropic.

## Costos estimados

Con Toon activado, el ahorro promedio es de **50-60%** en tokens:

| Servicio | Sin Toon | Con Toon | Ahorro |
|----------|----------|----------|--------|
| Perfil Astrológico | ~2,500 tokens | ~1,000 tokens | ~60% |
| Chat (mensaje simple) | ~500 tokens | ~200 tokens | ~60% |
| Chat (con contexto) | ~5,000 tokens | ~2,000 tokens | ~60% |

### Cálculo de costos (Claude 3.5 Sonnet)

- **Input**: $3 / 1M tokens
- **Output**: $15 / 1M tokens

**Ejemplo con Toon**:
- 1,000 llamadas de perfil astrológico
- ~1,000 tokens input por llamada = 1M tokens
- Costo sin Toon: ~$7.50
- **Costo con Toon: ~$3.00** ✅ (ahorro de $4.50)

## Retrocompatibilidad

El sistema es compatible con la configuración anterior de `services.claude.*`. Si ya tienes configurado:

```bash
CLAUDE_API_KEY=...
CLAUDE_MODEL=...
```

El sistema usará esa configuración como fallback si `ANTHROPIC_API_KEY` no está presente.

## Recomendación final

Para mejor claridad y consistencia con el proyecto fanyion, se recomienda usar las variables `ANTHROPIC_*`:

```bash
# Recomendado
ANTHROPIC_API_KEY=sk-ant-api03-xxxxx

# Funciona pero no recomendado
CLAUDE_API_KEY=sk-ant-api03-xxxxx
```
