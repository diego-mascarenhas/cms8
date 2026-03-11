<?php

namespace App\Livewire\Settings;

use App\Models\Team;
use App\Services\AssistantChatService;
use App\Services\AstralChartService;
use App\Models\Prompt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Livewire\Component;
use Livewire\WithFileUploads;

use function Laravel\Ai\agent;

class BusinessConfigWizard extends Component
{
    use WithFileUploads;

    public Team $team;

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $config = [];

    public ?string $summary = null;

    public bool $summaryLoading = false;

    protected static array $configKeys = [
        'business_name', 'business_industry', 'business_location', 'business_postal_code',
        'business_phone', 'business_whatsapp', 'business_website', 'business_email',
        'business_tagline', 'business_description', 'business_problematica',
        'first_name', 'last_name', 'birth_date', 'birth_time', 'country', 'language',
        'address', 'landmark', 'pincode', 'city',
        'twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok',
        'whatsapp_url', 'telegram', 'pinterest', 'threads',
    ];

    public function mount(Team $team): void
    {
        Gate::authorize('update', $team);
        $this->team = $team;

        $saved = $team->getSetting('business_config', []);
        if (is_string($saved)) {
            $saved = json_decode($saved, true) ?: [];
        }
        foreach (self::$configKeys as $key) {
            $this->config[$key] = $saved[$key] ?? ($key === 'language' ? [] : '');
        }
    }

    public function nextStep(): void
    {
        $this->persistConfig();
        if ($this->step < 5) {
            $this->step++;
        }
    }

    public function previousStep(): void
    {
        $this->persistConfig();
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function goToStep(int $step): void
    {
        $this->persistConfig();
        if ($step >= 1 && $step <= 5) {
            $this->step = $step;
        }
    }

    protected function persistConfig(): void
    {
        $payload = [];
        foreach (self::$configKeys as $key) {
            $value = $this->config[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== []) {
                $payload[$key] = is_array($value) ? $value : (string) $value;
            }
        }
        $this->team->setSetting('business_config', $payload, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
    }

    public function submit(): void
    {
        $this->persistConfig();
        $this->dispatch('saved');
    }

    public function generateSummary(AssistantChatService $assistant): void
    {
        $this->summaryLoading = true;
        $this->summary = null;

        $problematica = trim((string) ($this->config['business_problematica'] ?? ''));
        $contextParts = [];
        $contextParts[] = 'Datos del negocio:';
        $contextParts[] = '- Nombre: '.trim((string) ($this->config['business_name'] ?? ''));
        $contextParts[] = '- Rubro/Sector: '.trim((string) ($this->config['business_industry'] ?? ''));
        $contextParts[] = '- Ubicación: '.trim((string) ($this->config['business_location'] ?? ''));
        $contextParts[] = '- Código postal: '.trim((string) ($this->config['business_postal_code'] ?? ''));
        $contextParts[] = '- Teléfono: '.trim((string) ($this->config['business_phone'] ?? ''));
        $contextParts[] = '- WhatsApp: '.trim((string) ($this->config['business_whatsapp'] ?? ''));
        $contextParts[] = '- Página web: '.trim((string) ($this->config['business_website'] ?? ''));
        $contextParts[] = '- Email: '.trim((string) ($this->config['business_email'] ?? ''));
        $contextParts[] = '- Eslogan: '.trim((string) ($this->config['business_tagline'] ?? ''));
        $contextParts[] = '- Descripción: '.trim((string) ($this->config['business_description'] ?? ''));

        $birthDate = $this->config['birth_date'] ?? null;
        if ($birthDate) {
            try {
                $astral = new AstralChartService;
                $birthCarbon = Carbon::parse($birthDate);
                $zodiac = $astral->getZodiacSign($birthCarbon);
                $northNode = $astral->getNorthNode($birthCarbon);
                $contextParts[] = '';
                $contextParts[] = 'Arquetipo humano (fecha y hora de nacimiento):';
                $contextParts[] = '- Signo zodiacal: '.($zodiac['sign'] ?? '').' '.($zodiac['symbol'] ?? '').' ('.($zodiac['element'] ?? '').')';
                $contextParts[] = '- Nodo Norte: '.($northNode['north'] ?? '');
                $contextParts[] = '- Nodo Sur: '.($northNode['south'] ?? '');
                if (! empty($this->config['birth_time'])) {
                    $contextParts[] = '- Hora de nacimiento: '.$this->config['birth_time'];
                }
            } catch (\Throwable $e) {
                Log::warning('AstralChartService in business summary', ['error' => $e->getMessage()]);
            }
        }

        $context = implode("\n", $contextParts);
        $userMessage = $problematica !== ''
            ? "Problemática actual del negocio:\n\n".$problematica."\n\n---\n\n".$context
            : $context;

        $prompt = Prompt::findByRoutingKey('landing');
        $teamId = $this->team->id;

        try {
            if ($prompt) {
                $result = $assistant->run($userMessage, $teamId, null, null, false, 'landing');
                $this->summary = $result['response'] ?? '';
            } else {
                $defaultInstruction = 'Eres un consultor de negocio. Con el contexto que te proporcionan (datos del negocio, problemática actual y arquetipo humano por fecha de nacimiento), genera un resumen muy conciso (máximo 1 párrafo corto o 3-5 puntos) de lo que esta empresa necesita para mejorar. Sé directo y práctico.';
                $agent = agent(
                    instructions: $defaultInstruction,
                    messages: [],
                    tools: [],
                );
                $response = $agent->prompt($userMessage, [], Lab::Anthropic);
                $this->summary = $response->text ?? '';
            }
        } catch (\Throwable $e) {
            Log::error('Business summary generation failed', ['error' => $e->getMessage()]);
            $this->summary = 'Error al generar el resumen. Intenta de nuevo.';
        }

        $this->summaryLoading = false;
    }

    public function render()
    {
        return view('livewire.settings.business-config-wizard');
    }
}
