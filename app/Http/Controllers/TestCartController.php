<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppMessageService;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Http\Request;

class TestCartController extends Controller
{
    protected $whatsAppMessageService;

    public function __construct(WhatsAppMessageService $whatsAppMessageService)
    {
        $this->whatsAppMessageService = $whatsAppMessageService;
    }

    public function index()
    {
        return view('test-cart.index');
    }

    public function processMessage(Request $request)
    {
        $phone = $request->input('phone', '5491112345678');
        $message = $request->input('message');

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

        // Set cart session for this phone
        Cart::session($phone);

        // Test cart commands FIRST (higher priority)
        $cartResult = $this->whatsAppMessageService->processCartCommands($phone, $message);
        if ($cartResult)
        {
            $response['processed'] = true;
            $response['type'] = 'cart';
            $response['result'] = $cartResult;
        } else
        {
            // Test product commands
            $productResult = $this->whatsAppMessageService->processProductCommands($phone, $message);
            if ($productResult)
            {
                $response['processed'] = true;
                $response['type'] = 'product';
                $response['result'] = $productResult;
            }
        }

        // Get current cart status
        $cartItems = Cart::getContent();
        $response['cart_status'] = [
            'items_count' => $cartItems->count(),
            'total' => Cart::getTotal(),
            'items' => $cartItems->toArray(),
        ];

        return response()->json($response);
    }

    public function cartStatus(Request $request)
    {
        $phone = $request->input('phone', '5491112345678');
        Cart::session($phone);

        $cartItems = Cart::getContent();

        return response()->json([
            'phone' => $phone,
            'items_count' => $cartItems->count(),
            'total' => Cart::getTotal(),
            'items' => $cartItems->toArray(),
        ]);
    }

    public function clearCart(Request $request)
    {
        $phone = $request->input('phone', '5491112345678');
        Cart::session($phone);
        Cart::clear();

        return response()->json([
            'phone' => $phone,
            'message' => 'Cart cleared successfully',
            'items_count' => 0,
            'total' => 0,
        ]);
    }
}
