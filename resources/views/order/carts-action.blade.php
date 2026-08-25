{{-- Action column template for DataTables --}}
<div class="d-flex justify-content-center align-items-center">
	<a href="{{ route('order.carts.show', $cart['id']) }}" class="text-body" title="{{ __('Editar') }}">
		<i class="ti ti-edit ti-sm me-2"></i>
	</a>
	@if (! empty($cart['chat_url']))
		<a href="{{ $cart['chat_url'] }}" class="text-body" title="{{ __('Chat') }}">
			<i class="ti ti-message-chatbot ti-sm"></i>
		</a>
	@endif
</div>
