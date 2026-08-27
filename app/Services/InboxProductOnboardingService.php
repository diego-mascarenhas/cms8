<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Prompt;
use App\Models\Team;
use App\Support\HumanoLabsStudios;
use Illuminate\Support\Str;

class InboxProductOnboardingService
{
    public const DATA_KEY = 'idoneo_product_onboarding';

    public const STEP_CHOOSE = 'choose';

    public const STEP_ASSISTANT_MORE = 'assistant_more';

    public const STEP_ASSISTANT_SIGNUP = 'assistant_signup';

    public const STEP_SHOP_OFFER = 'shop_offer';

    public const STEP_SHOP_MORE = 'shop_more';

    public const STEP_SHOP_SIGNUP = 'shop_signup';

    public const STEP_MENTOR_OFFER = 'mentor_offer';

    public const STEP_DONE = 'done';

    public const STRATEGY_PROMPT_KEY = 'contacts:landing';

    /**
     * @return array{ok: bool, messages: list<string>, error?: string}
     */
    public function start(?Contact $contact): array
    {
        if ($contact === null)
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'El hilo necesita un contacto para arrancar el embudo.',
            ];
        }

        $this->saveFlow($contact, [
            'active' => true,
            'step' => self::STEP_CHOOSE,
            'want_shop_after' => false,
            'started_at' => now()->toIso8601String(),
        ]);

        return ['ok' => true, 'messages' => [$this->chooseMessage($contact)]];
    }

    /**
     * @return array{message: string}|null
     */
    public function tryHandleInbound(Team $team, string $fromDigits, string $body): ?array
    {
        $contact = app(UserResolverService::class)->findContactInTeamByPhone((int) $team->id, $fromDigits);
        if ($contact === null)
        {
            return null;
        }

        $flow = $this->flow($contact);
        if (! ($flow['active'] ?? false))
        {
            return null;
        }

        $step = (string) ($flow['step'] ?? self::STEP_CHOOSE);
        if ($step === self::STEP_DONE)
        {
            return null;
        }

        $normalized = $this->normalize($body);
        if ($this->containsAny($normalized, ['cancelar', 'salir', 'stop', 'detener', 'basta']))
        {
            $this->saveFlow($contact, [
                ...$flow,
                'active' => false,
                'step' => self::STEP_DONE,
            ]);

            return ['message' => 'Sin problema, lo dejamos acá. Cuando quieras retomar, escribime o pedime /recomendar.'];
        }

        $message = match ($step)
        {
            self::STEP_CHOOSE => $this->advanceFromChoose($contact, $team, $flow, $normalized),
            self::STEP_ASSISTANT_MORE => $this->advanceFromAssistantMore($contact, $team, $flow, $normalized),
            self::STEP_ASSISTANT_SIGNUP => $this->advanceFromAssistantSignup($contact, $team, $flow, $normalized),
            self::STEP_SHOP_OFFER => $this->advanceFromShopOffer($contact, $team, $flow, $normalized),
            self::STEP_SHOP_MORE => $this->advanceFromShopMore($contact, $team, $flow, $normalized),
            self::STEP_SHOP_SIGNUP => $this->advanceFromShopSignup($contact, $team, $flow, $normalized),
            self::STEP_MENTOR_OFFER => $this->advanceFromMentorOffer($contact, $team, $flow, $normalized),
            default => null,
        };

        return $message !== null ? ['message' => $message] : null;
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function advanceFromChoose(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        if ($this->wantsBusinessProblem($normalized) && ! $this->wantsAssistantPains($normalized) && ! $this->wantsShop($normalized))
        {
            return $this->offerOrHandOffMentor($contact, $team, $flow, $normalized);
        }

        if ($this->wantsBoth($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_ASSISTANT_MORE, 'want_shop_after' => true]);

            return $this->assistantMoreMessage();
        }

        if ($this->wantsShop($normalized) && ! $this->wantsAssistantPains($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_MORE, 'want_shop_after' => false]);

            return $this->shopMoreMessage();
        }

        if ($this->isNegative($normalized) && ! $this->wantsAssistant($normalized) && ! $this->wantsAssistantPains($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_MENTOR_OFFER]);

            return $this->mentorOfferMessage();
        }

        if (
            $this->wantsAssistant($normalized)
            || $this->wantsAssistantPains($normalized)
            || $this->isAffirmative($normalized)
            || $this->wantsToKnowMore($normalized)
        ) {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_ASSISTANT_MORE, 'want_shop_after' => false]);

            return $this->assistantMoreMessage();
        }

        return '¿Les cuesta lo de centralizar, o es más un tema del negocio?';
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function advanceFromAssistantMore(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        if ($this->wantsBusinessProblem($normalized) && ! $this->wantsAssistant($normalized) && ! $this->wantsSignup($normalized))
        {
            return $this->offerOrHandOffMentor($contact, $team, $flow, $normalized);
        }

        if ($this->wantsShop($normalized) && ! $this->wantsAssistant($normalized) && ! $this->wantsSignup($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_MORE]);

            return $this->shopMoreMessage();
        }

        if ($this->isNegative($normalized))
        {
            return $this->afterAssistantWithoutSignup($contact, $flow);
        }

        if ($this->isAffirmative($normalized) || $this->wantsSignup($normalized) || $this->wantsToKnowMore($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_ASSISTANT_SIGNUP]);

            return $this->assistantSignupMessage($team);
        }

        return '¿Te paso un link de acceso de prueba, o lo dejamos acá?';
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function advanceFromAssistantSignup(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        if ($this->wantsBusinessProblem($normalized) && ! $this->saysDone($normalized))
        {
            return $this->offerOrHandOffMentor($contact, $team, $flow, $normalized);
        }

        if ($this->wantsShop($normalized) && ! $this->saysDone($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_MORE, 'want_shop_after' => false]);

            return $this->shopMoreMessage();
        }

        if ($this->isNegative($normalized))
        {
            return $this->afterAssistantWithoutSignup($contact, $flow);
        }

        if ($this->isAffirmative($normalized) || $this->saysDone($normalized) || $this->wantsSignup($normalized))
        {
            return $this->afterAssistantSignup($contact, $flow);
        }

        return 'Cuando lo pruebes, escribime. Sin apuro.';
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function afterAssistantSignup(Contact $contact, array $flow): string
    {
        if ($flow['want_shop_after'] ?? false)
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_MORE, 'want_shop_after' => false]);

            return $this->shopMoreMessage();
        }

        $this->saveFlow($contact, [...$flow, 'step' => self::STEP_MENTOR_OFFER]);

        return $this->mentorOfferMessage();
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function afterAssistantWithoutSignup(Contact $contact, array $flow): string
    {
        if ($flow['want_shop_after'] ?? false)
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_MORE, 'want_shop_after' => false]);

            return $this->shopMoreMessage();
        }

        $this->saveFlow($contact, [...$flow, 'step' => self::STEP_MENTOR_OFFER]);

        return $this->mentorOfferMessage();
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function advanceFromShopOffer(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        if ($this->wantsBusinessProblem($normalized) && ! $this->wantsShop($normalized))
        {
            return $this->offerOrHandOffMentor($contact, $team, $flow, $normalized);
        }

        if ($this->wantsAssistant($normalized) && ! $this->wantsShop($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_ASSISTANT_MORE]);

            return $this->assistantMoreMessage();
        }

        if ($this->isNegative($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_MENTOR_OFFER]);

            return $this->mentorOfferMessage();
        }

        if ($this->isAffirmative($normalized) || $this->wantsShop($normalized) || $this->wantsToKnowMore($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_MORE]);

            return $this->shopMoreMessage();
        }

        return '¿Te cuento Shop, o lo dejamos acá?';
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function advanceFromShopMore(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        if ($this->wantsBusinessProblem($normalized) && ! $this->wantsShop($normalized) && ! $this->wantsSignup($normalized))
        {
            return $this->offerOrHandOffMentor($contact, $team, $flow, $normalized);
        }

        if ($this->wantsAssistant($normalized) && ! $this->wantsShop($normalized) && ! $this->wantsSignup($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_ASSISTANT_MORE]);

            return $this->assistantMoreMessage();
        }

        if ($this->isNegative($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_MENTOR_OFFER]);

            return $this->mentorOfferMessage();
        }

        if ($this->isAffirmative($normalized) || $this->wantsSignup($normalized) || $this->wantsToKnowMore($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_SIGNUP]);

            return $this->shopSignupMessage($team);
        }

        return '¿Te paso un link de acceso de prueba de Shop, o lo dejamos acá?';
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function advanceFromShopSignup(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        if ($this->wantsBusinessProblem($normalized))
        {
            return $this->offerOrHandOffMentor($contact, $team, $flow, $normalized);
        }

        if ($this->wantsAssistant($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_ASSISTANT_MORE]);

            return $this->assistantMoreMessage();
        }

        if ($this->isAffirmative($normalized) || $this->saysDone($normalized) || $this->isNegative($normalized) || $this->wantsSignup($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_MENTOR_OFFER]);

            return $this->mentorOfferMessage();
        }

        return 'Cuando lo pruebes, escribime. Quedo atento.';
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function advanceFromMentorOffer(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        if ($this->wantsAssistant($normalized) && ! $this->wantsBusinessProblem($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_ASSISTANT_MORE]);

            return $this->assistantMoreMessage();
        }

        if ($this->wantsShop($normalized) && ! $this->wantsBusinessProblem($normalized))
        {
            $this->saveFlow($contact, [...$flow, 'step' => self::STEP_SHOP_MORE]);

            return $this->shopMoreMessage();
        }

        if ($this->isNegative($normalized) && ! $this->wantsBusinessProblem($normalized))
        {
            $this->finish($contact, $flow);

            return $this->doneMessage();
        }

        if (
            $this->isAffirmative($normalized)
            || $this->wantsBusinessProblem($normalized)
            || $this->wantsToKnowMore($normalized)
        ) {
            return $this->handOffToStrategy($contact, $team, $flow, $normalized);
        }

        return '¿Es del negocio, de la marca, o de algo más técnico?';
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function offerOrHandOffMentor(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        $studio = HumanoLabsStudios::match($normalized);
        if (($studio !== null && $studio['key'] !== 'consulting') || $this->describedBusinessChallenge($normalized))
        {
            return $this->handOffToStrategy($contact, $team, $flow, $normalized);
        }

        $this->saveFlow($contact, [...$flow, 'step' => self::STEP_MENTOR_OFFER]);

        return $this->mentorOfferMessage();
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function handOffToStrategy(Contact $contact, Team $team, array $flow, string $normalized): string
    {
        $studio = HumanoLabsStudios::match($normalized);
        $this->finish($contact, [
            ...$flow,
            'studio' => $studio['key'] ?? 'consulting',
        ]);
        $this->pinStrategyPrompt($contact, $team);

        return $this->mentorHandoffMessage($studio);
    }

    private function pinStrategyPrompt(Contact $contact, Team $team): void
    {
        $key = self::STRATEGY_PROMPT_KEY;

        try
        {
            $key = app(AssistantPromptCatalog::class)->ensureOnTeam($team, self::STRATEGY_PROMPT_KEY);
        } catch (\Throwable)
        {
            $existing = Prompt::findByRoutingKey(self::STRATEGY_PROMPT_KEY, (int) $team->id)
                ?? Prompt::findByRoutingKey('landing', (int) $team->id);
            if ($existing !== null)
            {
                $existing->loadMissing('module');
                $key = ($existing->module?->key ?: 'contacts').':'.$existing->section_key;
            }
        }

        $data = $this->contactData($contact);
        $data['chat_assistant_prompt_key'] = $key;
        $data['chat_assistant_ai_enabled'] = true;
        $contact->data = (object) $data;
        $contact->save();
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function finish(Contact $contact, array $flow): void
    {
        $this->saveFlow($contact, [
            ...$flow,
            'active' => false,
            'step' => self::STEP_DONE,
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    private function chooseMessage(Contact $contact): string
    {
        $first = $this->firstName($contact);
        $hello = $first !== '' ? 'Hola '.$first : 'Hola';

        return $hello.'. Soy IDONEO, un artesano del software. ¿Les cuesta centralizar, atender en equipo o tener un embudo con intención y emoción?';
    }

    private function assistantMoreMessage(): string
    {
        return 'Eso lo ordena Assistant: un solo lugar para que el equipo atienda. ¿Te paso un link de acceso de prueba?';
    }

    private function assistantSignupMessage(Team $team): string
    {
        return "Acá va el acceso de prueba:\n".$this->registerUrl('https://assistant.idoneo.dev/register', $team);
    }

    private function shopOfferMessage(): string
    {
        return '¿Les cuesta tener productos y pedidos en un solo lugar?';
    }

    private function shopMoreMessage(): string
    {
        return 'Shop junta productos, sucursales y pedidos. ¿Te paso un link de acceso de prueba?';
    }

    private function shopSignupMessage(Team $team): string
    {
        return "Acá va el acceso de prueba:\n".$this->registerUrl('https://shop.idoneo.dev/register', $team);
    }

    private function registerUrl(string $base, Team $team): string
    {
        $code = $this->referralCode($team);
        if ($code === null)
        {
            return $base;
        }

        return $base.'?'.http_build_query(['ref' => $code]);
    }

    private function referralCode(Team $team): ?string
    {
        $builder = app(AffiliateReferralLinkBuilder::class);
        $code = $builder->referralCode($team);
        if ($code !== null)
        {
            return $code;
        }

        app(AffiliateProgramService::class)->ensureStripeCustomer($team);
        $team->refresh();

        return $builder->referralCode($team);
    }

    private function mentorOfferMessage(): string
    {
        return '¿Hoy les duele más el negocio — crecer, que todo depende de uno — o algo concreto, como la marca?';
    }

    /**
     * @param  array{key: string, area: string, name: string, url: string, keywords: list<string>}|null  $studio
     */
    private function mentorHandoffMessage(?array $studio): string
    {
        if ($studio !== null && $studio['key'] !== 'consulting')
        {
            return HumanoLabsStudios::handoffMessage($studio);
        }

        return 'Contame el desafío: ¿qué les está trabando hoy?';
    }

    private function doneMessage(): string
    {
        return 'Dale, quedo por acá. Cuando quieras retomar, escribime o pedime /recomendar. Un gusto.';
    }

    /**
     * @return array<string, mixed>
     */
    private function flow(Contact $contact): array
    {
        $stored = $this->contactData($contact)[self::DATA_KEY] ?? null;
        if (is_array($stored))
        {
            return $stored;
        }
        if (is_object($stored))
        {
            return get_object_vars($stored);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function saveFlow(Contact $contact, array $flow): void
    {
        $data = $this->contactData($contact);
        $data[self::DATA_KEY] = $flow;
        $contact->data = (object) $data;
        $contact->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function contactData(Contact $contact): array
    {
        $data = $contact->data;
        if (is_array($data))
        {
            return $data;
        }
        if (is_object($data))
        {
            return get_object_vars($data);
        }

        return [];
    }

    private function firstName(Contact $contact): string
    {
        $name = trim((string) ($contact->name ?? ''));
        if ($name === '')
        {
            return '';
        }

        return explode(' ', $name, 2)[0];
    }

    private function normalize(string $body): string
    {
        $text = Str::lower(Str::ascii(trim($body)));

        return (string) preg_replace('/\s+/', ' ', $text);
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $normalized, array $needles): bool
    {
        foreach ($needles as $needle)
        {
            if ($needle !== '' && str_contains($normalized, $needle))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function hasToken(string $normalized, array $tokens): bool
    {
        $parts = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        return count(array_intersect($tokens, $parts)) > 0;
    }

    private function wantsAssistant(string $normalized): bool
    {
        return $this->containsAny($normalized, ['assistant', 'asistente', 'whatsapp', 'inbox']);
    }

    private function wantsAssistantPains(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'centralizar',
            'comunicaciones',
            'plataforma',
            'embudo',
            'intencion',
            'intención',
            'emocional',
            'emocion',
            'sentir',
            'atender',
        ]);
    }

    private function wantsShop(string $normalized): bool
    {
        return $this->containsAny($normalized, ['shop', 'tienda', 'mostrador', 'catalogo']);
    }

    private function wantsBusinessProblem(string $normalized): bool
    {
        return HumanoLabsStudios::match($normalized) !== null;
    }

    private function describedBusinessChallenge(string $normalized): bool
    {
        return $this->wantsBusinessProblem($normalized) && mb_strlen($normalized) >= 80;
    }

    private function wantsBoth(string $normalized): bool
    {
        return $this->containsAny($normalized, ['las dos', 'los dos', 'ambas', 'ambos', 'las 2', 'los 2'])
            || ($this->wantsAssistant($normalized) && $this->wantsShop($normalized));
    }

    private function wantsSignup(string $normalized): bool
    {
        return $this->containsAny($normalized, ['alta', 'registro', 'register', 'cuenta', 'link', 'enlace', 'acceso']);
    }

    private function wantsToKnowMore(string $normalized): bool
    {
        return $this->containsAny($normalized, ['saber mas', 'contame', 'conta me', 'explica', 'problemas', 'mas info', 'conocer']);
    }

    private function isAffirmative(string $normalized): bool
    {
        return $this->containsAny($normalized, ['por favor', 'porfavor', 'porfa'])
            || $this->hasToken($normalized, ['si', 'ok', 'dale', 'bueno', 'va', 'quiero', 'perfecto', 'vamos', 'claro']);
    }

    private function isNegative(string $normalized): bool
    {
        if ($this->containsAny($normalized, ['despues', 'luego', 'ahora no', 'otro dia', 'otro dia']))
        {
            return true;
        }

        return $this->hasToken($normalized, ['no']) && ! $this->isAffirmative($normalized);
    }

    private function saysDone(string $normalized): bool
    {
        return $this->containsAny($normalized, ['listo', 'hecho', 'ya esta', 'ya cree', 'termine', 'eso es todo']);
    }
}
