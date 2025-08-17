<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test WhatsApp Cart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-4xl mx-auto" x-data="cartTester()">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">🧪 WhatsApp Cart Tester</h1>
            <p class="text-gray-600 mb-6">Test your WhatsApp cart functionality locally without webhooks</p>

            <!-- Phone Input -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">📱 Phone Number:</label>
                <input
                    type="text"
                    x-model="phone"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="5491112345678"
                >
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Message Input Panel -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h2 class="text-xl font-semibold mb-4">💬 Send Message</h2>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message:</label>
                        <input
                            type="text"
                            x-model="message"
                            @keyup.enter="sendMessage()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="productos, comprar hosting, carrito..."
                        >
                    </div>

                    <button
                        @click="sendMessage()"
                        :disabled="!message || loading"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        <span x-show="!loading">📤 Send Message</span>
                        <span x-show="loading">⏳ Processing...</span>
                    </button>

                    <!-- Quick Commands -->
                    <div class="mt-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">🚀 Quick Commands:</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="message = 'productos'; sendMessage()" class="bg-green-100 text-green-800 py-1 px-2 rounded text-sm hover:bg-green-200">📋 productos</button>
                            <button @click="message = 'comprar hosting'; sendMessage()" class="bg-blue-100 text-blue-800 py-1 px-2 rounded text-sm hover:bg-blue-200">🛒 comprar hosting</button>
                            <button @click="message = 'carrito'; sendMessage()" class="bg-purple-100 text-purple-800 py-1 px-2 rounded text-sm hover:bg-purple-200">👁️ carrito</button>
                            <button @click="message = 'checkout'; sendMessage()" class="bg-orange-100 text-orange-800 py-1 px-2 rounded text-sm hover:bg-orange-200">💳 checkout</button>
                        </div>
                    </div>
                </div>

                <!-- Cart Status Panel -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">🛒 Cart Status</h2>
                        <button
                            @click="clearCart()"
                            class="bg-red-500 text-white py-1 px-3 rounded text-sm hover:bg-red-600"
                        >
                            🗑️ Clear
                        </button>
                    </div>

                    <div class="bg-white rounded p-3 mb-4">
                        <p class="text-sm text-gray-600">Items: <span class="font-semibold" x-text="cartStatus.items_count"></span></p>
                        <p class="text-sm text-gray-600">Total: <span class="font-semibold">$<span x-text="cartStatus.total"></span></span></p>
                    </div>

                    <div x-show="cartStatus.items_count > 0">
                        <h3 class="font-medium mb-2">Cart Items:</h3>
                        <template x-for="(item, id) in cartStatus.items" :key="id">
                            <div class="bg-white rounded p-2 mb-2 text-sm">
                                <p class="font-medium" x-text="item.name"></p>
                                <p class="text-gray-600">$<span x-text="item.price"></span> x <span x-text="item.quantity"></span></p>
                            </div>
                        </template>
                    </div>

                    <div x-show="cartStatus.items_count === 0" class="text-gray-500 text-center py-4">
                        🛒 Cart is empty
                    </div>
                </div>
            </div>

            <!-- Response Panel -->
            <div class="mt-6 bg-gray-50 rounded-lg p-4">
                <h2 class="text-xl font-semibold mb-4">📱 WhatsApp Response</h2>

                <div x-show="!lastResponse" class="text-gray-500 text-center py-4">
                    Send a message to see the response...
                </div>

                <div x-show="lastResponse" class="bg-white rounded p-4">
                    <div x-show="lastResponse.processed" class="mb-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            ✅ Command processed
                        </span>
                        <span class="ml-2 text-sm text-gray-600" x-text="lastResponse.type"></span>
                    </div>

                    <div x-show="!lastResponse.processed" class="mb-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            ❌ Command not recognized
                        </span>
                    </div>

                    <div class="text-sm">
                        <p class="font-medium">Message: <span x-text="lastResponse.message"></span></p>
                        <p class="font-medium">Phone: <span x-text="lastResponse.phone"></span></p>
                        <div x-show="lastResponse.result">
                            <p class="font-medium mt-2">Result: <span x-text="lastResponse.result?.message"></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cartTester() {
            return {
                phone: '5491112345678',
                message: '',
                loading: false,
                lastResponse: null,
                cartStatus: {
                    items_count: 0,
                    total: 0,
                    items: {}
                },

                init() {
                    this.loadCartStatus();
                },

                async sendMessage() {
                    if (!this.message.trim()) return;

                    this.loading = true;

                    try {
                        const response = await fetch('/test-cart/process', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                phone: this.phone,
                                message: this.message
                            })
                        });

                        const data = await response.json();
                        this.lastResponse = data;
                        this.cartStatus = data.cart_status;
                        this.message = '';
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error sending message');
                    } finally {
                        this.loading = false;
                    }
                },

                async loadCartStatus() {
                    try {
                        const response = await fetch(`/test-cart/status?phone=${this.phone}`);
                        const data = await response.json();
                        this.cartStatus = data;
                    } catch (error) {
                        console.error('Error loading cart status:', error);
                    }
                },

                async clearCart() {
                    try {
                        const response = await fetch('/test-cart/clear', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                phone: this.phone
                            })
                        });

                        const data = await response.json();
                        this.cartStatus = {
                            items_count: data.items_count,
                            total: data.total,
                            items: {}
                        };
                    } catch (error) {
                        console.error('Error clearing cart:', error);
                        alert('Error clearing cart');
                    }
                }
            }
        }
    </script>
</body>
</html>
