<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Mail\ClientLoginCodeMail;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AssistantWhatsAppUsageByLineService;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\RevisionAlphaBilling;
use App\Services\TeamMailerUsageStatsService;
use App\Services\TeamWhatsAppUsageStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientPortalController extends Controller
{
    public function requestCode(Request $request): JsonResponse
    {
        $email = $this->validatedEmail($request);
        $user = $this->findUser($email);

        if ($user === null)
        {
            return response()->json([
                'registered' => false,
                'message' => 'Este email no está registrado.',
            ], 404);
        }

        return $this->sent($user->email);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower($validated['email']);
        if ($this->findUser($email) !== null)
        {
            return response()->json([
                'registered' => true,
                'message' => 'Este email ya está registrado.',
            ], 422);
        }

        $user = User::query()->getModel()->getConnection()->transaction(function () use ($validated, $email)
        {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'phone' => $this->phoneNumber($validated['phone'] ?? null),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $company = trim((string) ($validated['company_name'] ?? ''));
            $team = $user->ownedTeams()->save(Team::forceCreate([
                'user_id' => $user->id,
                'name' => $company !== '' ? $company : $user->name,
                'personal_team' => true,
            ]));
            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user;
        });

        return $this->sent($user->email);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower($validated['email']);
        $user = $this->findUser($email);
        $hashed = Cache::get($this->cacheKey($email));

        if ($user === null || ! is_string($hashed) || ! Hash::check($validated['code'], $hashed))
        {
            return response()->json([
                'message' => 'Código incorrecto o caducado.',
            ], 422);
        }

        Cache::forget($this->cacheKey($email));
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada.',
        ]);
    }

    public function dashboard(Request $request, RevisionAlphaBilling $billing): JsonResponse
    {
        $account = $billing->forEmail((string) $request->user()->email);

        return response()->json([
            'invoices' => $this->presentInvoices($account['invoices'], $account['payments'], $request->user()),
            'services' => $account['services'],
            'payments' => $account['payments'],
            'usage' => $this->usagePayload($request->user()),
            'leads' => [],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json($this->profilePayload($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $user->fill([
            'name' => $validated['name'],
            'phone' => $this->phoneNumber($validated['phone'] ?? null),
        ]);
        $user->save();

        $team = $user->currentTeam;
        if ($team)
        {
            $company = trim((string) ($validated['company_name'] ?? ''));
            if ($company !== '')
            {
                $team->forceFill(['name' => $company])->save();
            }

            TeamSetting::query()->updateOrCreate(
                ['team_id' => $team->id, 'key' => 'client_tax_id'],
                [
                    'value' => trim((string) ($validated['tax_id'] ?? '')),
                    'type' => 'string',
                    'group' => 'client',
                ],
            );
        }

        return response()->json($this->profilePayload($user->fresh()));
    }

    public function invoices(Request $request, RevisionAlphaBilling $billing): JsonResponse
    {
        $account = $billing->forEmail((string) $request->user()->email);

        return response()->json([
            'invoices' => $this->presentInvoices($account['invoices'], $account['payments'], $request->user()),
        ]);
    }

    public function reportPayment(Request $request, string $invoice, RevisionAlphaBilling $billing): JsonResponse
    {
        $account = $billing->forEmail((string) $request->user()->email);
        $current = collect($account['invoices'])->firstWhere('id', $invoice);
        if (! is_array($current))
        {
            return response()->json([
                'message' => 'Factura no encontrada.',
            ], 404);
        }

        $balance = (float) ($current['balance'] ?? 0);
        if ($balance <= 0)
        {
            return response()->json([
                'message' => 'La factura no tiene saldo pendiente.',
            ], 422);
        }

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balance],
            'method' => ['required', 'in:Transferencia,Mercado Pago,Efectivo,Tarjeta'],
            'notes' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ]);

        $team = $this->portalTeam($request->user());
        if ($team === null)
        {
            return response()->json([
                'message' => 'No hay un equipo para registrar el pago.',
            ], 422);
        }

        $payment = Payment::withoutGlobalScope('team')->updateOrCreate(
            [
                'source_provider' => 'manual',
                'source_reference_id' => $this->portalReference($invoice),
            ],
            [
                'team_id' => $team->id,
                'enterprise_id' => null,
                'transaction_type' => TransactionType::INCOME,
                'date' => $validated['paid_on'],
                'invoice_id' => null,
                'account_id' => $this->portalPaymentAccount($team)->id,
                'type_id' => $this->portalPaymentType($validated['method'])->id,
                'amount' => $validated['amount'],
                'remarks' => $this->portalRemarks($current, $validated),
                'status' => 3,
            ],
        );
        $receiptName = $this->storeReceipt($request, $payment);

        $current['payment'] = $this->reportedPayment(
            $payment,
            (string) ($current['currency'] ?? 'EUR'),
            $validated['method'],
            $receiptName,
        );

        return response()->json($current);
    }

    public function tickets(Request $request): JsonResponse
    {
        $email = strtolower((string) $request->user()->email);
        $tickets = Ticket::query()
            ->withoutGlobalScope('team')
            ->with(['responses.user'])
            ->whereHas('user', function ($query) use ($email)
            {
                $query->whereRaw('LOWER(email) = ?', [$email]);
            })
            ->latest('id')
            ->get();

        $staffIds = $tickets
            ->flatMap(fn (Ticket $ticket) => $ticket->responses->pluck('user_id'))
            ->unique()
            ->filter();
        $contacts = Contact::query()
            ->withoutGlobalScopes()
            ->whereIn('user_id', $staffIds)
            ->get()
            ->keyBy('user_id');

        return response()->json([
            'tickets' => $tickets
                ->map(fn (Ticket $ticket) => $this->ticketPayload($ticket, $contacts))
                ->values(),
        ]);
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $ticket = Ticket::query()->withoutGlobalScope('team')->create([
            'team_id' => (int) config('organization.revision_alpha_team_id'),
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        foreach ($request->file('attachments', []) as $file)
        {
            $ticket->addMedia($file)->toMediaCollection('attachments');
        }

        $ticket->load(['responses.user']);

        return response()->json($this->ticketPayload($ticket, collect()), 201);
    }

    private function validatedEmail(Request $request): string
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        return strtolower($validated['email']);
    }

    private function findUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function sent(string $email): JsonResponse
    {
        try
        {
            $this->sendCode($email);
        } catch (\Throwable $exception)
        {
            report($exception);

            return response()->json([
                'message' => 'No se pudo enviar el código. Inténtalo de nuevo en unos minutos.',
            ], 503);
        }

        return response()->json([
            'registered' => true,
            'message' => 'Te enviamos un código de inicio de sesión.',
        ]);
    }

    private function sendCode(string $email): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey(strtolower($email)), Hash::make($code), now()->addMinutes(10));

        $mailer = app()->environment('local') ? 'mailpit' : (string) config('mail.default');
        Mail::mailer($mailer)->to($email)->send(new ClientLoginCodeMail($email, $code));
    }

    private function cacheKey(string $email): string
    {
        return 'client-login-code:'.$email;
    }

    private function phoneNumber(?string $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '')
        {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @return array{name: string, email: string, phone: string, company_name: string, tax_id: string, payment_method: array<string, mixed>|null}
     */
    private function profilePayload(User $user): array
    {
        $team = $user->currentTeam;
        $taxId = '';
        if ($team)
        {
            $taxId = (string) TeamSetting::query()
                ->where('team_id', $team->id)
                ->where('key', 'client_tax_id')
                ->value('value');
        }

        $remote = app(RevisionAlphaBilling::class)->profile((string) $user->email);

        return [
            'name' => $remote['name'] !== '' ? $remote['name'] : (string) $user->name,
            'email' => (string) $user->email,
            'phone' => $remote['phone'] !== '' ? $remote['phone'] : ($user->phone ? (string) $user->phone : ''),
            'company_name' => $remote['company_name'] !== '' ? $remote['company_name'] : (string) ($team->name ?? ''),
            'tax_id' => $remote['tax_id'] !== '' ? $remote['tax_id'] : $taxId,
            'payment_method' => $remote['payment_method'],
        ];
    }

    /**
     * @return array{emails: int, whatsapp: int, ai_tokens: int}
     */
    private function usagePayload(User $user): array
    {
        $empty = [
            'emails' => 0,
            'whatsapp' => 0,
            'ai_tokens' => 0,
        ];
        $team = $user->currentTeam;
        if ($team === null)
        {
            return $empty;
        }

        [$from, $to] = app(AssistantSubscriptionService::class)->usagePeriod($team);
        $billed = app(AssistantWhatsAppUsageByLineService::class)->forTeam($team, $from, $to);

        return [
            'emails' => (int) TeamMailerUsageStatsService::forTeam($team, $from, $to)['emails_sent'],
            'whatsapp' => (int) TeamWhatsAppUsageStatsService::forTeam($team, $from, $to)['messages_sent'],
            'ai_tokens' => (int) ($billed['all']['tokens'] ?? 0),
        ];
    }

    /**
     * @param  array<int, mixed>  $invoices
     * @param  array<int, mixed>  $payments
     * @return array<int, mixed>
     */
    private function presentInvoices(array $invoices, array $payments, User $user): array
    {
        $reports = $this->portalPayments($user);
        $paymentsById = collect($payments)->keyBy('id');

        return array_values(array_map(function ($invoice) use ($reports, $paymentsById)
        {
            if (! is_array($invoice) || is_array($invoice['payment'] ?? null))
            {
                return $invoice;
            }

            $payment = $paymentsById->get($invoice['id'] ?? null);
            if (($invoice['status'] ?? '') === 'paid' && is_array($payment))
            {
                $invoice['payment'] = [
                    'date' => $payment['date'] ?? ($invoice['date'] ?? ''),
                    'amount' => $payment['amount'] ?? ($invoice['amount'] ?? 0),
                    'currency' => $payment['currency'] ?? ($invoice['currency'] ?? 'EUR'),
                    'method' => $payment['method'] ?? '',
                    'status' => 'paid',
                    'status_label' => $payment['status_label'] ?? 'Aprobado',
                ];

                return $invoice;
            }

            $report = $reports->get($this->portalReference((string) ($invoice['id'] ?? '')));
            if ($report instanceof Payment)
            {
                $invoice['payment'] = $this->reportedPayment(
                    $report,
                    (string) ($invoice['currency'] ?? 'EUR'),
                    $this->methodLabel($report),
                    $this->receiptName($report),
                );
            }

            return $invoice;
        }, $invoices));
    }

    private function portalTeam(User $user): ?Team
    {
        return $user->currentTeam ?? $user->ownedTeams()->first();
    }

    private function portalReference(string $invoiceId): string
    {
        return 'portal:'.$invoiceId;
    }

    private function portalPaymentAccount(Team $team): PaymentAccount
    {
        $account = PaymentAccount::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if ($account instanceof PaymentAccount)
        {
            return $account;
        }

        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'PORTAL',
            'name' => 'Pagos informados',
            'status' => 1,
        ]);
    }

    private function portalPaymentType(string $method): PaymentType
    {
        return PaymentType::query()->firstOrCreate([
            'name' => match ($method)
            {
                'Transferencia' => 'Bank Transfer',
                'Mercado Pago' => 'MercadoPago',
                'Efectivo' => 'Cash',
                'Tarjeta' => 'Credit Card',
                default => $method,
            },
        ]);
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $validated
     */
    private function portalRemarks(array $invoice, array $validated): string
    {
        $lines = array_filter([
            'Factura '.(string) ($invoice['number'] ?? '').' ('.(string) ($invoice['id'] ?? '').')',
            'Medio: '.(string) ($validated['method'] ?? ''),
            isset($validated['notes']) ? (string) $validated['notes'] : null,
        ]);

        return implode("\n", $lines);
    }

    private function storeReceipt(Request $request, Payment $payment): ?string
    {
        if (! $request->hasFile('receipt'))
        {
            return $this->receiptName($payment);
        }

        $file = $request->file('receipt');
        $name = $file->getClientOriginalName();
        $directory = 'payment-receipts/'.$payment->id;
        Storage::disk('public')->deleteDirectory($directory);
        $file->storeAs($directory, $name, 'public');

        return $name;
    }

    private function receiptName(Payment $payment): ?string
    {
        $files = Storage::disk('public')->files('payment-receipts/'.$payment->id);
        $path = $files[0] ?? null;

        return is_string($path) ? basename($path) : null;
    }

    private function methodLabel(Payment $payment): string
    {
        $name = (string) PaymentType::query()->whereKey($payment->type_id)->value('name');

        return match ($name)
        {
            'Bank Transfer' => 'Transferencia',
            'MercadoPago' => 'Mercado Pago',
            'Cash' => 'Efectivo',
            'Credit Card' => 'Tarjeta',
            default => $name,
        };
    }

    /**
     * @return Collection<string, Payment>
     */
    private function portalPayments(User $user): Collection
    {
        $team = $this->portalTeam($user);
        if ($team === null)
        {
            return collect();
        }

        return Payment::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('source_provider', 'manual')
            ->where('source_reference_id', 'like', 'portal:%')
            ->where('status', '!=', 0)
            ->get()
            ->keyBy('source_reference_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function reportedPayment(Payment $payment, string $currency, string $method, ?string $receiptName): array
    {
        $status = (int) $payment->status;

        return [
            'date' => $payment->date?->format('d/m/Y') ?? '',
            'amount' => (float) $payment->amount,
            'currency' => $currency,
            'method' => $method,
            'status' => match ($status)
            {
                2 => 'paid',
                4 => 'rejected',
                default => 'pending',
            },
            'status_label' => match ($status)
            {
                2 => 'Aprobado',
                4 => 'Rechazado',
                default => 'Pendiente',
            },
            'receipt_name' => $receiptName,
        ];
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return array<string, mixed>
     */
    private function ticketPayload(Ticket $ticket, Collection $contacts): array
    {
        return [
            'id' => $ticket->id,
            'hash' => (string) $ticket->id,
            'subject' => $ticket->subject,
            'description' => (string) $ticket->description,
            'status' => $ticket->status,
            'status_label' => match ($ticket->status)
            {
                'open' => 'Abierto',
                'in_progress' => 'En progreso',
                'waiting_client' => 'Esperando cliente',
                'closed' => 'Cerrado',
                default => ucfirst((string) $ticket->status),
            },
            'priority' => $ticket->priority,
            'priority_label' => match ($ticket->priority)
            {
                'low' => 'Baja',
                'medium' => 'Media',
                'high' => 'Alta',
                'urgent' => 'Urgente',
                default => ucfirst((string) $ticket->priority),
            },
            'created_at' => $ticket->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
            'responses' => $ticket->responses
                ->filter(fn ($response) => ! $response->is_internal_note)
                ->map(fn ($response) => [
                    'message' => $response->message,
                    'author' => $this->replyAuthor($response, $ticket, $contacts),
                    'is_staff' => $response->user_id !== $ticket->user_id,
                    'created_at' => $response->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
                    'attachments' => $this->attachmentPayload($response),
                ])
                ->values(),
            'attachments' => $this->attachmentPayload($ticket),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function attachmentPayload(object $model): array
    {
        if (! method_exists($model, 'getMedia'))
        {
            return [];
        }

        return $model->getMedia('attachments')->map(fn ($media) => [
            'id' => (string) $media->id,
            'name' => (string) $media->file_name,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    private function replyAuthor(object $response, Ticket $ticket, Collection $contacts): string
    {
        $user = $response->user;
        $name = trim((string) ($user?->name ?? ''));
        if ($name === '')
        {
            return 'Soporte';
        }

        if ($user === null || (int) $response->user_id === (int) $ticket->user_id || ! $this->companyName($name))
        {
            return $name;
        }

        $contact = $contacts->get($user->id);
        $personal = trim(implode(' ', array_filter([
            $contact?->name,
            $contact?->surname,
        ])));

        return $personal !== '' ? $personal : $name;
    }

    private function companyName(string $name): bool
    {
        return (bool) preg_match('/\b(S\.?\s?A\.?\s?S\.?|S\.?\s?R\.?\s?L\.?|S\.?\s?A\.?|S\.?\s?L\.?|S\.?\s?H\.?|LTDA\.?|LLC|INC\.?)\b/iu', $name);
    }
}
