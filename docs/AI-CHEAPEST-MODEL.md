# AI Cheapest Model Implementation (Laravel AI)

This document explains how to implement the "use the cheapest model" strategy with `laravel/ai`, based on the setup applied in this project.

## Goal

Use the provider's cheapest text model by default (instead of hardcoding a model ID), while still allowing an explicit model override via `.env`.

---

## 1) Agent-level cheapest selector

In your agent class, add the `UseCheapestModel` attribute.

Example (`app/Ai/Agents/OrderBlockRecommendationAgent.php`):

```php
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\UseCheapestModel;

#[MaxTokens(8192)]
#[UseCheapestModel]
class OrderBlockRecommendationAgent implements Agent, HasStructuredOutput
{
    use Promptable;
}
```

Why: when no explicit `model` is passed to `->prompt(...)`, Laravel AI resolves the model from the agent attribute and picks the provider's cheapest text model.

---

## 2) Config default to selector, not fixed model

In `config/ai.php`, use a selector string (`cheapest`) as default:

```php
'recommend_model' => env('AI_RECOMMEND_MODEL', 'cheapest'),
'recommend_provider' => env('AI_RECOMMEND_PROVIDER', 'anthropic'),
```

Why: keeps behavior consistent across environments without forcing a concrete model ID.

---

## 3) Service call: pass `model: null` when selector is `cheapest`

In the service where you call the agent, convert `cheapest` to `null` before calling `prompt`.

Example (`app/Services/OrderBlockAiRecommendationService.php`):

```php
$model = config('ai.recommend_model', 'cheapest');
$provider = config('ai.recommend_provider', 'anthropic');
$failover = config('ai.recommend_failover');
$providerParam = $failover !== null && is_array($failover) ? array_merge([$provider], $failover) : $provider;
$timeout = (int) config('ai.recommend_timeout', 60);

$modelParam = is_string($model) && strtolower(trim($model)) === 'cheapest'
    ? null
    : (is_string($model) ? trim($model) : null);

$response = OrderBlockRecommendationAgent::make()->prompt(
    $userPrompt,
    provider: $providerParam,
    model: $modelParam,
    timeout: $timeout,
);
```

Why:
- `model: null` lets `#[UseCheapestModel]` take effect.
- If `.env` sets a concrete model ID, that explicit model is used.

---

## 4) Optional `.env` behavior

If you do **not** set `AI_RECOMMEND_MODEL`, config fallback (`cheapest`) is used.

If you want to force a specific model, set:

```ini
AI_RECOMMEND_MODEL=claude-haiku-4-5-20251001
```

---

## 5) Verify effective runtime config

Run:

```bash
php artisan tinker --execute="dump(config('ai.recommend_provider')); dump(config('ai.recommend_model')); dump(config('ai.recommend_failover'));"
```

Expected with this setup:
- provider: `anthropic`
- model: `cheapest` (unless overridden)
- failover: `null` (unless configured)

---

## 6) (Anthropic) Which model does "cheapest" map to?

In this project's `laravel/ai` version, Anthropic provider resolves:
- cheapest: `claude-haiku-4-5-20251001`
- default: `claude-sonnet-4-6`
- smartest: `claude-opus-4-6`

Quick check:

```bash
php artisan tinker --execute='$m = app(\Laravel\Ai\AiManager::class); $p = $m->textProvider("anthropic"); dump($p->cheapestTextModel(), $p->defaultTextModel(), $p->smartestTextModel());'
```

---

## 7) Common pitfalls

1. **Config cache not refreshed**
   - If using cached config, run:
   ```bash
   php artisan config:clear
   ```

2. **Unexpected Sonnet billing**
   - Often caused by another app/service using the same Anthropic API key.
   - Also possible if another environment overrides `AI_RECOMMEND_MODEL`.

3. **Passing a model string always**
   - If you always pass a concrete model into `prompt(...)`, `UseCheapestModel` is bypassed.

---

## Minimal checklist for a new project

- Add `#[UseCheapestModel]` to your agent.
- Set config fallback to `'cheapest'`.
- In service calls, pass `model: null` when config is `cheapest`.
- Keep provider explicit (e.g. `anthropic`).
- Verify runtime config with `tinker`.

