# Using Toon in Humano

## What is Toon?

Toon is a package that optimizes sending structured data to AI APIs (such as Anthropic's Claude) by significantly reducing payload size and, therefore, token costs.

## Services with logging implemented

The following services already have token logging configured:

1. **AstralChartService** - Astrological profile generation
   - Logs every Claude call used to generate interpretations
   - Location: `app/Services/AstralChartService.php`

2. **ClaudeService** - General Claude chat service
   - Logs all conversations with Claude
   - Location: `app/Services/ClaudeService.php`

Every time these services call Claude, usage is automatically recorded in the `token_usage_logs` table.

## Installation

The package is already installed in the project:

```bash
composer require sbsaga/toon:^1.2
```

## How to use Toon

### 1. Import the facade

```php
use Sbsaga\Toon\Facades\Toon;
```

### 2. Encode data with Toon

Instead of sending JSON directly, use Toon to compress the data:

```php
// Without Toon (plain JSON)
$jsonData = json_encode($data);
$jsonSize = strlen($jsonData);
$jsonTokens = round($jsonSize / 4); // Token estimate

// With Toon (optimized)
$toonData = Toon::encode($data);
$toonSize = strlen($toonData);
$toonTokens = round($toonSize / 4); // Token estimate

// Calculate savings
$savings = round((($jsonSize - $toonSize) / $jsonSize) * 100, 2);
```

### 3. Decode Toon responses

If the API responds in Toon format, you can decode it:

```php
$decodedData = Toon::decode($toonResponse);
```

## Usage logging

The system automatically logs token usage in the `token_usage_logs` table:

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

## Complete usage example

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
        // Prepare data
        $jsonData = json_encode($data);
        $jsonSize = strlen($jsonData);

        // Compress with Toon
        $toonData = Toon::encode($data);
        $toonSize = strlen($toonData);

        // Calculate metrics
        $jsonTokens = round($jsonSize / 4);
        $toonTokens = round($toonSize / 4);
        $savings = round((($jsonSize - $toonSize) / $jsonSize) * 100, 2);

        // Log usage
        TokenUsageLog::create([
            'service' => 'ExampleAIService',
            'json_size' => $jsonSize,
            'toon_size' => $toonSize,
            'json_tokens' => $jsonTokens,
            'toon_tokens' => $toonTokens,
            'savings_percentage' => $savings,
            'used_toon' => true,
        ]);

        // Send to the API (use toonData instead of jsonData)
        $response = Http::post('https://api.example.com/endpoint', [
            'data' => $toonData,
        ]);

        return $response->body();
    }
}
```

## Dashboard widget

The "API Usage & Savings" dashboard widget shows:

- **Calls**: Total AI API calls
- **Savings**: Total tokens saved using Toon
- **Savings percentage**: Average savings across all calls
- **Tokens used**: Total tokens used (with Toon)
- **Without Toon**: Total tokens that would have been used without Toon

## Available statistics

The `TokenUsageLog` model provides useful methods:

```php
// Total API calls
TokenUsageLog::getTotalCalls();

// Total tokens saved
TokenUsageLog::getTotalTokensSaved();

// Average savings percentage
TokenUsageLog::getAverageSavingsPercentage();

// Total tokens used (optimized with Toon)
TokenUsageLog::getTotalTokensUsed();

// Total tokens without optimization
TokenUsageLog::getTotalTokensWithoutToon();

// Calls by service
TokenUsageLog::getCallsByService();
```

## Best practices

1. **Always log usage**: This lets you measure real savings
2. **Use Toon for large structured data**: It works best with complex arrays and objects
3. **Do not use Toon for simple text**: For small strings, JSON is enough
4. **Monitor the dashboard**: Regularly review the savings widget to optimize services

## References

- Toon repository: [sbsaga/toon](https://github.com/sbsaga/toon)
- Reference project: `../app.fanyion/`
