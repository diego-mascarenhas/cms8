@extends('emails.layouts.revision-alpha')

@section('content')
<tr>
	<td>
		<h2 style="color: #2a333d; margin: 0 0 12px 0; font-size: 22px; font-weight: 650">
			Alguien pide una reunión ahora
		</h2>
		<p style="color: #555; font-size: 16px; line-height: 1.6; margin: 0 0 16px 0">
			<strong style="color: #2a333d">{{ $guestName }}</strong> está esperando y no tiene cita. La petición sigue activa hasta que alguien entre a la sala.
		</p>
		<p style="margin: 0 0 8px 0">
			<a href="{{ $joinUrl }}" style="display: inline-block; background: #e31013; color: #ffffff; font-size: 16px; font-weight: 700; text-decoration: none; padding: 12px 18px; border-radius: 6px">Unirme a la sala</a>
		</p>
		<p style="color: #777; font-size: 13px; line-height: 1.5; margin: 0">{{ $joinUrl }}</p>
	</td>
</tr>
@endsection
