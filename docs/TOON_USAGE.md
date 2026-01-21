# Uso de Toon en Humano

## ¿Qué es Toon?

Toon es un paquete que optimiza el envío de datos estructurados a APIs de IA (como Claude de Anthropic) reduciendo significativamente el tamaño de los payloads y, por lo tanto, los costos de tokens.

## Servicios con Logging Implementado

Actualmente, los siguientes servicios ya tienen el logging de tokens configurado:

1. **AstralChartService** - Generación de perfiles astrológicos
   - Registra cada llamada a Claude para generar interpretaciones
   - Localización: `app/Services/AstralChartService.php`

2. **ClaudeService** - Servicio general de chat con Claude
   - Registra todas las conversaciones con Claude
   - Localización: `app/Services/ClaudeService.php`

Cada vez que estos servicios hacen una llamada a Claude, se registra automáticamente en la tabla `token_usage_logs`.

## Instalación

El paquete ya está instalado en el proyecto:

```bash
composer require sbsaga/toon:^1.2
```

## Cómo usar Toon

### 1. Importar la Facade

```php
use Sbsaga\Toon\Facades\Toon;
```

### 2. Codificar datos con Toon

En lugar de enviar JSON directamente, usa Toon para comprimir los datos:

```php
// Sin Toon (JSON normal)
$jsonData = json_encode($data);
$jsonSize = strlen($jsonData);
$jsonTokens = round($jsonSize / 4); // Estimación de tokens

// Con Toon (Optimizado)
$toonData = Toon::encode($data);
$toonSize = strlen($toonData);
$toonTokens = round($toonSize / 4); // Estimación de tokens

// Calcular ahorro
$savings = round((($jsonSize - $toonSize) / $jsonSize) * 100, 2);
```

### 3. Decodificar respuestas con Toon

Si la API responde con formato Toon, puedes decodificarlo:

```php
$decodedData = Toon::decode($toonResponse);
```

## Logging de uso

El sistema registra automáticamente el uso de tokens en la tabla `token_usage_logs`:

```php
use App\Models\TokenUsageLog;

TokenUsageLog::create([
    'service' => 'NombreDelServicio', // Ej: 'AIAssistanceService'
    'json_size' => $jsonSize,
    'toon_size' => $toonSize,
    'json_tokens' => $jsonTokens,
    'toon_tokens' => $toonTokens,
    'savings_percentage' => $savings,
    'used_toon' => true,
]);
```

## Ejemplo completo de uso

```php
<?php

namespace App\Services;

use App\Models\TokenUsageLog;
use Illuminate\Support\Facades\Http;
use Sbsaga\Toon\Facades\Toon;

class ExampleAIService
{
    public function sendToAI(array $data): string
    {
        // Preparar datos
        $jsonData = json_encode($data);
        $jsonSize = strlen($jsonData);
        
        // Comprimir con Toon
        $toonData = Toon::encode($data);
        $toonSize = strlen($toonData);
        
        // Calcular métricas
        $jsonTokens = round($jsonSize / 4);
        $toonTokens = round($toonSize / 4);
        $savings = round((($jsonSize - $toonSize) / $jsonSize) * 100, 2);
        
        // Registrar uso
        TokenUsageLog::create([
            'service' => 'ExampleAIService',
            'json_size' => $jsonSize,
            'toon_size' => $toonSize,
            'json_tokens' => $jsonTokens,
            'toon_tokens' => $toonTokens,
            'savings_percentage' => $savings,
            'used_toon' => true,
        ]);
        
        // Enviar a la API (usar toonData en lugar de jsonData)
        $response = Http::post('https://api.example.com/endpoint', [
            'data' => $toonData,
        ]);
        
        return $response->body();
    }
}
```

## Dashboard Widget

El widget de "Uso de API & Ahorro" en el dashboard muestra:

- **Llamadas**: Total de llamadas a APIs de IA
- **Ahorro**: Total de tokens ahorrados usando Toon
- **Porcentaje de ahorro**: Promedio de ahorro en todas las llamadas
- **Tokens usados**: Total de tokens utilizados (con Toon)
- **Sin Toon**: Total de tokens que se habrían usado sin Toon

## Estadísticas disponibles

El modelo `TokenUsageLog` proporciona métodos útiles:

```php
// Total de llamadas a APIs
TokenUsageLog::getTotalCalls();

// Total de tokens ahorrados
TokenUsageLog::getTotalTokensSaved();

// Porcentaje promedio de ahorro
TokenUsageLog::getAverageSavingsPercentage();

// Total de tokens usados (optimizado con Toon)
TokenUsageLog::getTotalTokensUsed();

// Total de tokens sin optimización
TokenUsageLog::getTotalTokensWithoutToon();

// Llamadas por servicio
TokenUsageLog::getCallsByService();
```

## Mejores prácticas

1. **Siempre registra el uso**: Esto te permite medir el ahorro real
2. **Usa Toon para datos estructurados grandes**: Funciona mejor con arrays y objetos complejos
3. **No uses Toon para textos simples**: Para strings pequeños, JSON es suficiente
4. **Monitorea el dashboard**: Revisa regularmente el widget de ahorro para optimizar servicios

## Referencias

- Repositorio de Toon: [sbsaga/toon](https://github.com/sbsaga/toon)
- Proyecto de referencia: `../app.fanyion/`
