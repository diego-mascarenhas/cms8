{{-- Action column template for DataTables --}}
<div class="d-flex justify-content-center align-items-center">
	@if (! empty($cart['chat_url']))
		<a href="{{ $cart['chat_url'] }}" class="text-body" title="{{ __('Chat') }}">
			<i class="ti ti-message-chatbot ti-sm"></i>
		</a>
	@endif
</div>
