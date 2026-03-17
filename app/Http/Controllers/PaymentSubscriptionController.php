<?php

namespace App\Http\Controllers;

use App\Models\PaymentSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentSubscriptionController extends Controller
{
    private const PROVIDERS = ['stripe', 'paypal', 'mercadopago', 'local'];

    private const STATUSES = ['active', 'canceled', 'past_due', 'trialing', 'pending', 'expired'];

    private function teamId(): ?int
    {
        return auth()->user()->currentTeam?->id;
    }

    private function query(): \Illuminate\Database\Eloquent\Builder
    {
        $teamId = $this->teamId();
        $q = PaymentSubscription::query()->orderBy('name')->orderBy('provider');
        if ($teamId)
        {
            $q->where('team_id', $teamId);
        } else
        {
            $q->whereRaw('1 = 0');
        }

        return $q;
    }

    /**
     * Display a listing of payment subscriptions (forms of payment).
     */
    public function index()
    {
        $items = $this->query()->get();
        $providers = self::getProviders();
        $statuses = self::getStatuses();

        return view('payment-subscription.index', compact('items', 'providers', 'statuses'));
    }

    /**
     * Show the form for creating a new payment subscription.
     */
    public function create()
    {
        return view('payment-subscription.form', [
            'data' => null,
            'providers' => self::getProviders(),
            'statuses' => self::getStatuses(),
        ]);
    }

    /**
     * Store a newly created payment subscription.
     */
    public function store(Request $request)
    {
        $teamId = $this->teamId();
        if (! $teamId)
        {
            return redirect()->route('payment-subscription.index')->with('error', __('Equipo no seleccionado.'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => ['required', 'string', Rule::in(self::PROVIDERS)],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'external_id' => 'nullable|string|max:255',
        ]);

        PaymentSubscription::create([
            'team_id' => $teamId,
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'status' => $validated['status'],
            'external_id' => $validated['external_id'] ?? null,
        ]);

        return redirect()->route('payment-subscription.index')->with('success', __('Forma de pago creada correctamente.'));
    }

    /**
     * Show the form for editing the specified payment subscription.
     */
    public function edit(string $id)
    {
        $data = $this->query()->findOrFail($id);

        return view('payment-subscription.form', [
            'data' => $data,
            'providers' => self::getProviders(),
            'statuses' => self::getStatuses(),
        ]);
    }

    /**
     * Update the specified payment subscription.
     */
    public function update(Request $request, string $id)
    {
        $data = $this->query()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => ['required', 'string', Rule::in(self::PROVIDERS)],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'external_id' => 'nullable|string|max:255',
        ]);

        $data->update([
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'status' => $validated['status'],
            'external_id' => $validated['external_id'] ?? null,
        ]);

        return redirect()->route('payment-subscription.index')->with('success', __('Forma de pago actualizada correctamente.'));
    }

    /**
     * Remove the specified payment subscription.
     */
    public function destroy(string $id)
    {
        $data = $this->query()->findOrFail($id);
        $data->delete();

        return redirect()->route('payment-subscription.index')->with('success', __('Forma de pago eliminada.'));
    }

    public static function getProviders(): array
    {
        return [
            'stripe' => __('Stripe'),
            'paypal' => __('PayPal'),
            'mercadopago' => __('Mercado Pago'),
            'local' => __('Facturación local'),
        ];
    }

    public static function getStatuses(): array
    {
        return [
            'active' => __('Activa'),
            'canceled' => __('Cancelada'),
            'past_due' => __('Vencida'),
            'trialing' => __('Prueba'),
            'pending' => __('Pendiente'),
            'expired' => __('Expirada'),
        ];
    }
}
