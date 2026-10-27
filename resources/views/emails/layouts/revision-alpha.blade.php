@php
    $siteUrl = rtrim((string) config('services.revisionalpha.url'), '/');
    $dateLabel = ucfirst(now()->locale('es')->isoFormat('dddd')).' '.now()->format('d').' de '.ucfirst(now()->locale('es')->isoFormat('MMMM')).' de '.now()->format('Y');
@endphp
<html>
	<head>
		<title>revision alpha</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<style>
			* {
				padding: 0;
				margin: 0;
				line-height: 1.5;
			}

			body {
				font-family: helvetica, arial, verdana, sans-serif;
			}

			h1,
			h2,
			h3,
			h4,
			h5,
			h6,
			strong {
				font-weight: 600;
			}

			p,
			span,
			a,
			td {
				font-size: 14px;
				font-weight: 300;
				color: #777777;
			}

			a {
				text-decoration: none;
			}
		</style>
	</head>

	<body bgcolor="#F5EFEF" marginheight="0" marginwidth="0">
		<table width="100%" bgcolor="#F5EFEF" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td height="20"></td>
			</tr>
			<tr>
				<td align="center">
					<table width="700" bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0">
						<tr>
							<td align="center">
								<table width="660" bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td height="25" colspan="2"></td>
									</tr>
									<tr>
										<td>
											<h1 style="text-align: left; margin: 0; padding: 0">
												<img
													src="{{ $siteUrl }}/assets/img/logo-header-email.png"
													alt="REVISION ALPHA"
													width="309"
													height="43"
													style="display: block; width: 309px; height: 43px; border: 0; margin: 0; padding: 0"
												/>
											</h1>
										</td>
										<td align="right">
											<span><strong>{{ $dateLabel }}</strong></span><br />
											<span><em>{{ $header ?? 'Administración' }}</em></span>
										</td>
									</tr>
									<tr>
										<td height="25" colspan="2"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td height="2px" bgcolor="#FF1A1D"></td>
						</tr>
						<tr>
							<td align="center">
								<table width="660" bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td height="50"></td>
									</tr>
									@yield('content')
									<tr>
										<td height="50"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td height="10" bgcolor="#FF1A1D"></td>
						</tr>
						<tr>
							<td align="center">
								<table width="100%" bgcolor="#2A333D" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td align="center">
											<table width="660" bgcolor="#2A333D" border="0" cellpadding="0" cellspacing="0">
												<tr>
													<td height="25" colspan="2"></td>
												</tr>
												<tr>
													<td>
														<a href="https://www.revisionalpha.com/" style="text-decoration: none">
															<img
																src="{{ $siteUrl }}/assets/img/logo-footer-email.png"
																alt="REVISION ALPHA"
																width="180"
																height="25"
																style="display: block; width: 180px; height: 25px; border: 0; margin: 0 0 8px 0; padding: 0"
															/>
														</a>
														<a href="https://www.revisionalpha.com/" style="font-size: 17px; color: #ffffff; text-decoration: none">www.revisionalpha.com</a>
													</td>
												</tr>
												<tr>
													<td height="25" colspan="2"></td>
												</tr>
											</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td height="20"></td>
			</tr>
		</table>
	</body>
</html>
