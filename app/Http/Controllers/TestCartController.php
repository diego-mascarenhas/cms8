<?php

namespace App\Http\Controllers;

use App\Services\ShoppingCartService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;

class TestCartController extends Controller
{
    public function __construct(
        protected WhatsAppMessageService $whatsAppMessageService,
        protected ShoppingCartService $shoppingCarts,
    ) {}

    public function index()
    {
        return view('test-cart.index');
    }

    public function processMessage(Request $request)
    {
        $phone = $request->input('phone', '5491112345678');
        $message = $request->input('message');
        $teamId = (int) $request->input('team_id', 1);

        if (! $message)
        {
            return response()->json(['error' => 'Message is required'], 400);
        }

        $response = [
            'phone' => $phone,
            'message' => $message,
            'processed' => false,
            'result' => null,
            'cart_status' => null,
        ];

        $cartResult = $this->whatsAppMessageService->processCartCommands($phone, $message);
        if ($cartResult)
        {
            $response['processed'] = true;
            $response['type'] = 'cart';
            $response['result'] = $cartResult;
        } else
        {
            $productResult = $this->whatsAppMessageService->processProductCommands($phone, $message);
            if ($productResult)
            {
                $response['processed'] = true;
                $response['type'] = 'product';
                $response['result'] = $productResult;
            }
        }

        $response['cart_status'] = $this->cartStatusPayload($teamId, $phone);

        return response()->json($response);
    }

    public function cartStatus(Request $request)
    {
        $phone = $request->input('phone', '5491112345678');
        $teamId = (int) $request->input('team_id', 1);

        return response()->json(array_merge(
            ['phone' => $phone],
            $this->cartStatusPayload($teamId, $phone),
        ));
    }

    public function clearCart(Request $request)
    {
        $phone = $request->input('phone', '5491112345678');
        $teamId = (int) $request->input('team_id', 1);
        $cart = $this->shoppingCarts->findWhatsApp($teamId, $phone);
        if ($cart)
        {
            $this->shoppingCarts->clear($cart);
        }

        return response()->json([
            'phone' => $phone,
            'message' => 'Cart cleared successfully',
            'items_count' => 0,
            'total' => 0,
        ]);
    }

    /**
     * @return array{items_count: int, total: float, items: array<int, array<string, mixed>>}
     */
    private function cartStatusPayload(int $teamId, string $phone): array
    {
        $lines = $this->shoppingCarts->whatsAppLines($teamId, $phone);

        return [
            'items_count' => $lines->count(),
            'total' => (float) $lines->sum(fn (object $item): float => (float) $item->price * (int) $item->quantity),
            'items' => $lines->map(fn (object $item): array => (array) $item)->all(),
        ];
    }
}
