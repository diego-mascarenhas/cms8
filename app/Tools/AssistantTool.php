<?php

namespace App\Tools;

use App\Services\AssistantToolsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Laravel AI Tool wrapper that delegates to AssistantToolsService.
 * Used so the chat assistant can use laravel/ai agent() with tools (Prism gateway) instead of direct Claude API.
 */
class AssistantTool implements Tool
{
    public function __construct(
        protected AssistantToolsService $service,
        protected string $name,
        protected string $description,
        /** @var array{type: string, properties?: array, required?: array} */
        protected array $inputSchema,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): Stringable|string
    {
        return $this->description;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->service->execute($this->name, $request->all());
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = $this->inputSchema['properties'] ?? [];
        $required = $this->inputSchema['required'] ?? [];
        $result = [];

        foreach ($properties as $key => $prop)
        {
            $type = $prop['type'] ?? 'string';
            $desc = $prop['description'] ?? '';
            $isRequired = in_array($key, $required);

            $typeMethod = match ($type)
            {
                'integer' => 'integer',
                'number' => 'number',
                'boolean' => 'boolean',
                'array' => 'array',
                'object' => 'object',
                default => 'string',
            };

            $t = $schema->{$typeMethod}();
            if ($desc !== '')
            {
                $t = $t->description($desc);
            }
            if ($isRequired)
            {
                $t = $t->required();
            }
            $result[$key] = $t;
        }

        return $result;
    }
}
