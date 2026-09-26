@extends('emails.layouts.revision-alpha')

@section('content')
<tr>
	<td>
		<div style="text-align: center; margin-bottom: 30px">
			<h1 style="font-size: 28px; color: #2a333d; margin: 0; font-weight: 700">🔐 Inicio de Sesión</h1>
			<div
				style="
					width: 50px;
					height: 3px;
					background: linear-gradient(90deg, #36f1cd 0%, #ff1a1d 100%);
					margin: 15px auto;
				"
			></div>
		</div>
	</td>
</tr>

<tr>
	<td>
		<div
			style="
				background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
				border-radius: 12px;
				padding: 30px;
				margin: 20px 0;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
			"
		>
			<h2 style="color: #2a333d; margin: 0 0 15px 0; font-size: 20px; font-weight: 600">👋 ¡Hola!</h2>
			<p style="color: #555; font-size: 16px; line-height: 1.6; margin: 0">
				Has solicitado iniciar sesión en tu cuenta. Usá este código en la app. Caduca en 10 minutos.
			</p>
			<p style="color: #2a333d; font-size: 16px; line-height: 1.6; margin: 24px 0 0 0">Tu código de acceso es</p>
			<p style="color: #2a333d; font-size: 36px; letter-spacing: 8px; font-weight: 700; margin: 8px 0 0 0">
				{{ $code }}
			</p>
		</div>
	</td>
</tr>

<tr>
	<td>
		<div
			style="
				background: linear-gradient(135deg, #2a333d 0%, #1a252f 100%);
				border-radius: 12px;
				padding: 25px;
				margin: 30px 0;
				text-align: center;
			"
		>
			<h3 style="color: #36f1cd; margin: 0 0 10px 0; font-size: 18px; font-weight: 600">⚡ Importante</h3>
			<p style="color: #fff; margin: 0; line-height: 1.6; font-size: 15px">
				Este código es válido por 10 minutos. Si no solicitaste este acceso, por favor ignora este correo.
			</p>
		</div>
	</td>
</tr>

<tr>
	<td>
		<div style="text-align: center; margin: 30px 0">
			<div
				style="
					width: 100%;
					height: 1px;
					background: linear-gradient(90deg, transparent 0%, #36f1cd 50%, transparent 100%);
					margin: 20px 0;
				"
			></div>
			<p style="color: #999; font-size: 12px; font-style: italic; margin: 0; line-height: 1.5">
				Este es un mensaje automático del sistema de <strong style="color: #36f1cd">REVISION ALPHA</strong
				><br />
				No responder a este email. Para cualquier consulta, utiliza nuestro sistema de soporte.
			</p>
			<div style="margin-top: 15px">
				<span style="color: #36f1cd; font-size: 20px">✨</span>
				<span style="color: #ff1a1d; font-size: 16px; margin: 0 8px">•</span>
				<span style="color: #36f1cd; font-size: 20px">✨</span>
			</div>
			<p style="color: #2a333d; font-size: 16px; font-weight: 600; margin: 15px 0 0 0">
				¡Gracias por confiar en nosotros!
			</p>
		</div>
	</td>
</tr>
@endsection
