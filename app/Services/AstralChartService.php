<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AstralChartService
{
    /**
     * Zodiac sign date ranges (month-day format)
     */
    private const ZODIAC_SIGNS = [
        'Aries' => ['start' => '03-21', 'end' => '04-19', 'symbol' => '♈', 'element' => 'Fuego'],
        'Tauro' => ['start' => '04-20', 'end' => '05-20', 'symbol' => '♉', 'element' => 'Tierra'],
        'Géminis' => ['start' => '05-21', 'end' => '06-20', 'symbol' => '♊', 'element' => 'Aire'],
        'Cáncer' => ['start' => '06-21', 'end' => '07-22', 'symbol' => '♋', 'element' => 'Agua'],
        'Leo' => ['start' => '07-23', 'end' => '08-22', 'symbol' => '♌', 'element' => 'Fuego'],
        'Virgo' => ['start' => '08-23', 'end' => '09-22', 'symbol' => '♍', 'element' => 'Tierra'],
        'Libra' => ['start' => '09-23', 'end' => '10-22', 'symbol' => '♎', 'element' => 'Aire'],
        'Escorpio' => ['start' => '10-23', 'end' => '11-21', 'symbol' => '♏', 'element' => 'Agua'],
        'Sagitario' => ['start' => '11-22', 'end' => '12-21', 'symbol' => '♐', 'element' => 'Fuego'],
        'Capricornio' => ['start' => '12-22', 'end' => '01-19', 'symbol' => '♑', 'element' => 'Tierra'],
        'Acuario' => ['start' => '01-20', 'end' => '02-18', 'symbol' => '♒', 'element' => 'Aire'],
        'Piscis' => ['start' => '02-19', 'end' => '03-20', 'symbol' => '♓', 'element' => 'Agua'],
    ];

    /**
     * North Node positions (approximate, changes every ~18 months)
     * Format: [start_year-month, end_year-month, sign]
     */
    private const NORTH_NODE_POSITIONS = [
        ['start' => '2023-07', 'end' => '2025-01', 'sign' => 'Aries', 'south' => 'Libra'],
        ['start' => '2025-01', 'end' => '2026-07', 'sign' => 'Piscis', 'south' => 'Virgo'],
        ['start' => '2026-07', 'end' => '2028-01', 'sign' => 'Acuario', 'south' => 'Leo'],
        ['start' => '2028-01', 'end' => '2029-07', 'sign' => 'Capricornio', 'south' => 'Cáncer'],
        ['start' => '2029-07', 'end' => '2031-01', 'sign' => 'Sagitario', 'south' => 'Géminis'],
        ['start' => '2031-01', 'end' => '2032-07', 'sign' => 'Escorpio', 'south' => 'Tauro'],
    ];

    /**
     * Get zodiac sign from birth date
     */
    public function getZodiacSign(Carbon $birthDate): array
    {
        $monthDay = $birthDate->format('m-d');

        foreach (self::ZODIAC_SIGNS as $sign => $dates)
        {
            $start = $dates['start'];
            $end = $dates['end'];

            // Handle year wrap-around for Capricorn
            if ($start > $end)
            {
                if ($monthDay >= $start || $monthDay <= $end)
                {
                    return [
                        'sign' => $sign,
                        'symbol' => $dates['symbol'],
                        'element' => $dates['element'],
                    ];
                }
            } else
            {
                if ($monthDay >= $start && $monthDay <= $end)
                {
                    return [
                        'sign' => $sign,
                        'symbol' => $dates['symbol'],
                        'element' => $dates['element'],
                    ];
                }
            }
        }

        return [
            'sign' => 'Desconocido',
            'symbol' => '?',
            'element' => 'Desconocido',
        ];
    }

    /**
     * Get approximate North Node position from birth date
     */
    public function getNorthNode(Carbon $birthDate): array
    {
        $yearMonth = $birthDate->format('Y-m');

        foreach (self::NORTH_NODE_POSITIONS as $position)
        {
            if ($yearMonth >= $position['start'] && $yearMonth <= $position['end'])
            {
                return [
                    'north' => $position['sign'],
                    'south' => $position['south'],
                    'period' => $position['start'].' a '.$position['end'],
                ];
            }
        }

        return [
            'north' => 'No disponible',
            'south' => 'No disponible',
            'period' => 'Fuera de rango',
        ];
    }

    /**
     * Generate complete astral profile
     * Can use Claude AI via MCP if configured, otherwise uses built-in interpretations
     */
    public function generateAstralProfile($contactId, $birthDate, $countryName = null): array
    {
        // Cache key for this specific contact
        $cacheKey = "astral_profile_{$contactId}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($birthDate, $countryName)
        {
            $birthDateCarbon = Carbon::parse($birthDate);
            $zodiacData = $this->getZodiacSign($birthDateCarbon);
            $northNodeData = $this->getNorthNode($birthDateCarbon);

            // Try to use Claude via MCP if enabled
            $useMcp = config('services.mcp.enabled', false);
            $interpretation = null;

            if ($useMcp)
            {
                $prompt = $this->buildAstralPrompt($zodiacData, $northNodeData, $birthDateCarbon, $countryName);

                try
                {
                    $mcpEndpoint = config('services.mcp.endpoint', 'http://localhost:3000/mcp');

                    $response = Http::timeout(10)->post($mcpEndpoint, [
                        'server' => 'user-idoneo-mcp',
                        'tool' => 'claude-interaction',
                        'arguments' => [
                            'prompt' => $prompt,
                            'max_tokens' => 2000,
                            'temperature' => 0.7,
                        ],
                    ]);

                    if ($response->successful())
                    {
                        $data = $response->json();
                        $interpretation = $data['content'][0]['text'] ?? null;
                    }
                } catch (\Exception $e)
                {
                    \Log::warning('MCP Claude request failed: '.$e->getMessage());
                }
            }

            // Use enhanced fallback interpretation if MCP is not available
            if (! $interpretation)
            {
                $interpretation = $this->getEnhancedInterpretation($zodiacData, $northNodeData, $birthDateCarbon->age);
            }

            return [
                'zodiac' => $zodiacData,
                'north_node' => $northNodeData,
                'birth_date' => $birthDateCarbon->format('d/m/Y'),
                'age' => $birthDateCarbon->age,
                'interpretation' => $interpretation,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'ai_generated' => $useMcp && $interpretation !== null,
            ];
        });
    }

    /**
     * Build prompt for Claude AI
     */
    private function buildAstralPrompt($zodiacData, $northNodeData, $birthDate, $countryName): string
    {
        $sign = $zodiacData['sign'];
        $element = $zodiacData['element'];
        $northNode = $northNodeData['north'];
        $southNode = $northNodeData['south'];
        $age = $birthDate->age;
        $country = $countryName ?: 'origen no especificado';

        return <<<PROMPT
Eres un astrólogo profesional experto. Genera un perfil astrológico personalizado y profundo basado en los siguientes datos:

**Datos del cliente:**
- Signo Solar: {$sign} ({$element})
- Edad: {$age} años
- Nodo Norte: {$northNode} (Nodo Sur: {$southNode})
- País: {$country}

**Instrucciones:**
Genera un análisis astrológico conciso pero profundo (máximo 250 palabras) que incluya:

1. **Esencia del Signo Solar**: Características fundamentales de personalidad (2-3 frases)
2. **Lección de Vida (Nodo Norte)**: Propósito evolutivo y hacia dónde debe crecer (2-3 frases)
3. **Patrón a Superar (Nodo Sur)**: Zona de confort que debe trascender (1-2 frases)
4. **Consejo Práctico**: Una recomendación específica para su desarrollo personal

**Estilo:**
- Profesional pero cercano
- Sin jerga técnica excesiva
- Enfocado en crecimiento personal y autoconocimiento
- Tono positivo y empoderador

Genera SOLO el texto del análisis, sin títulos ni formateo markdown.
PROMPT;
    }

    /**
     * Get enhanced interpretation combining zodiac and north node
     */
    private function getEnhancedInterpretation($zodiacData, $northNodeData, $age): string
    {
        $sign = $zodiacData['sign'];
        $element = $zodiacData['element'];
        $northNode = $northNodeData['north'];

        // Base personality by sign
        $personalities = [
            'Aries' => 'Tu naturaleza es pionera y valiente, siempre lista para nuevos desafíos. Posees una energía contagiosa y un espíritu emprendedor que inspira a otros.',
            'Tauro' => 'Destacas por tu estabilidad y perseverancia. Tu conexión con lo material y lo sensorial te hace apreciar la belleza y buscar seguridad en todo lo que construyes.',
            'Géminis' => 'Tu mente inquieta y curiosa te lleva a explorar múltiples intereses. La comunicación es tu fuerte, conectando ideas y personas con naturalidad.',
            'Cáncer' => 'Profundamente emocional e intuitivo, tu sensibilidad es tu mayor don. Proteges y nutres a quienes amas, creando espacios de seguridad emocional.',
            'Leo' => 'Irradias calidez y creatividad natural. Tu generosidad y carisma te convierten en líder nato, inspirando a otros con tu autenticidad y pasión.',
            'Virgo' => 'Tu capacidad analítica y atención al detalle son excepcionales. Buscas la perfección en el servicio a los demás, siempre mejorando y refinando.',
            'Libra' => 'El equilibrio y la armonía guían tus decisiones. Tu diplomacia natural y sentido estético te permiten crear belleza y paz en tu entorno.',
            'Escorpio' => 'Tu intensidad emocional y poder transformador son notables. Posees una profundidad única para ver más allá de las apariencias y regenerarte constantemente.',
            'Sagitario' => 'Tu espíritu libre y optimista te impulsa a explorar el mundo. Buscas la verdad y el significado más profundo de la vida con entusiasmo filosófico.',
            'Capricornio' => 'Tu ambición y disciplina son legendarias. Construyes con paciencia y determinación, alcanzando metas que otros consideran imposibles.',
            'Acuario' => 'Tu visión innovadora y humanitaria te distingue. Piensas en el futuro colectivo, rompiendo esquemas con tu originalidad y desapego emocional.',
            'Piscis' => 'Tu empatía y creatividad no conocen límites. Conectas con lo espiritual y lo artístico, disolviendo fronteras con tu compasión infinita.',
        ];

        // North node lessons
        $northNodeLessons = [
            'Aries' => 'Tu camino evolutivo te invita a desarrollar independencia y valentía personal, dejando atrás la dependencia de la aprobación de otros.',
            'Tauro' => 'Estás aprendiendo a valorar la seguridad material y la autoestima, soltando la necesidad de control sobre recursos ajenos.',
            'Géminis' => 'Tu lección es cultivar la curiosidad y la comunicación flexible, trascendiendo las certezas absolutas y abriéndote al diálogo.',
            'Cáncer' => 'Tu propósito es conectar con tus emociones y crear tu propio hogar interior, equilibrando la vida profesional con la personal.',
            'Leo' => 'Debes aprender a brillar con autenticidad y expresar tu creatividad única, dejando atrás la necesidad de ser solo parte del grupo.',
            'Virgo' => 'Tu camino te lleva a desarrollar discernimiento y servicio práctico, organizando el caos en sistemas útiles y sanos.',
            'Libra' => 'Estás aprendiendo el arte de las relaciones equilibradas y la diplomacia, trascendiendo el impulso de la acción individual.',
            'Escorpio' => 'Tu lección es abrazar la transformación profunda y la intimidad verdadera, soltando el apego a la seguridad material.',
            'Sagitario' => 'Tu propósito es expandir tu visión y buscar la verdad superior, dejando atrás los detalles que te limitan.',
            'Capricornio' => 'Debes aprender a construir estructuras duraderas y asumir responsabilidad, equilibrando la necesidad emocional con la madurez.',
            'Acuario' => 'Tu camino te invita a innovar y pensar en el colectivo, trascendiendo el drama personal y el ego.',
            'Piscis' => 'Estás aprendiendo a desarrollar compasión universal y conexión espiritual, soltando el exceso de análisis crítico.',
        ];

        $personality = $personalities[$sign] ?? '';
        $northNodeLesson = $northNodeLessons[$northNode] ?? '';

        // Build comprehensive interpretation
        $interpretation = $personality.' '.$northNodeLesson;

        // Add age-specific insight
        if ($age < 30)
        {
            $interpretation .= ' En esta etapa de tu vida, estás descubriendo estas cualidades y comenzando tu viaje evolutivo.';
        } elseif ($age < 50)
        {
            $interpretation .= ' Te encuentras en un momento de integración y maduración de estas energías en tu vida.';
        } else
        {
            $interpretation .= ' Tu experiencia te permite manifestar plenamente estas cualidades y guiar a otros en su camino.';
        }

        return $interpretation;
    }

    /**
     * Simple fallback interpretation if everything fails
     */
    private function getFallbackInterpretation($sign): string
    {
        return "Como {$sign}, posees cualidades únicas que te distinguen. Tu camino de vida te invita a desarrollar tu potencial y compartir tus dones con el mundo.";
    }

    /**
     * Clear cached astral profile for a contact
     */
    public function clearCache($contactId): void
    {
        Cache::forget("astral_profile_{$contactId}");
    }
}
