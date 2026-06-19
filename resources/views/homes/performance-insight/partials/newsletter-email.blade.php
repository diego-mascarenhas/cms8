{{-- Email-safe HTML for Performance Insight campaigns. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background-color:#f4f5fb;font-family:Arial,Helvetica,sans-serif;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e4e6ef;">
        <tr>
          <td style="padding:28px 32px 20px;background:linear-gradient(135deg,#696cff 0%,#5a5fd4 100%);">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td>
                  @if (! empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="Humano" width="120" style="display:block;height:auto;max-width:120px;border:0;">
                  @endif
                </td>
                <td align="right" style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.9);">
                  {{ $badge }}
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 12px;font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#696cff;">
              {{ $badge }}
            </p>
            <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;color:#233446;font-weight:800;">
              {{ $headline }}
            </h1>
            <p style="margin:0;font-size:16px;line-height:1.6;color:#566a7f;">
              {{ $intro }}
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:16px 32px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eef0ff;border-radius:12px;border:1px solid #dfe3ff;">
              <tr>
                <td style="padding:18px 20px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td width="50%" valign="top" style="padding-right:8px;">
                        <p style="margin:0 0 6px;font-size:11px;font-weight:700;text-transform:uppercase;color:#696cff;">{{ $ratioLabel }}</p>
                        <p style="margin:0;font-size:28px;font-weight:800;color:#233446;">{{ $ratioValue }}</p>
                      </td>
                      <td width="50%" valign="top" style="padding-left:8px;border-left:1px solid #dfe3ff;">
                        <p style="margin:0 0 6px;font-size:11px;font-weight:700;text-transform:uppercase;color:#696cff;">{{ $focusLabel }}</p>
                        <p style="margin:0;font-size:15px;font-weight:700;color:#233446;line-height:1.4;">{{ $focusValue }}</p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:12px 32px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td width="48%" valign="top" style="padding-right:8px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f8f8fc;border:1px solid #e4e6ef;border-radius:12px;">
                    <tr>
                      <td style="padding:16px;">
                        <p style="margin:0 0 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#696cff;">
                          {{ $adminTitle }}
                        </p>
                        <div style="background:#fff;border:1px solid #e4e6ef;border-radius:8px;padding:12px;margin-bottom:10px;">
                          <p style="margin:0 0 4px;font-size:13px;font-weight:800;color:#233446;">⚡ Enfócate</p>
                          <p style="margin:0 0 8px;font-size:12px;color:#696cff;font-weight:600;">Cobrar facturas vencidas hoy</p>
                          <p style="margin:0;font-size:11px;line-height:1.5;color:#566a7f;">5 facturas impagadas · 8 correos sin leer · 4 citas hoy</p>
                        </div>
                        <ul style="margin:0;padding:0 0 0 18px;font-size:13px;line-height:1.55;color:#566a7f;">
                          @foreach ($adminBullets as $bullet)
                            <li style="margin-bottom:6px;">{{ $bullet }}</li>
                          @endforeach
                        </ul>
                      </td>
                    </tr>
                  </table>
                </td>
                <td width="4%"></td>
                <td width="48%" valign="top" style="padding-left:8px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;">
                    <tr>
                      <td style="padding:16px;">
                        <p style="margin:0 0 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#16a34a;">
                          {{ $userTitle }}
                        </p>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:10px;">
                          @foreach ([85, 62, 55, 40, 30] as $pct)
                            <tr>
                              <td style="padding:3px 0;">
                                <div style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                                  <div style="height:6px;width:{{ $pct }}%;background:linear-gradient(90deg,#696cff,#25d366);border-radius:999px;"></div>
                                </div>
                              </td>
                            </tr>
                          @endforeach
                        </table>
                        <ul style="margin:0;padding:0 0 0 18px;font-size:13px;line-height:1.55;color:#566a7f;">
                          @foreach ($userBullets as $bullet)
                            <li style="margin-bottom:6px;">{{ $bullet }}</li>
                          @endforeach
                        </ul>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:24px 32px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#233446;border-radius:12px;">
              <tr>
                <td style="padding:18px 20px;">
                  <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#fff;">Captura · Respuesta sugerida</p>
                  <p style="margin:0 0 10px;font-size:11px;color:rgba(255,255,255,0.75);">contabilidad@idoneo.dev · Factura pendiente</p>
                  <p style="margin:0;font-size:12px;line-height:1.55;color:rgba(255,255,255,0.9);font-style:italic;">«Hola Laura. Te escribo por la factura F-IDO-2026-01 vencida (4.820,00 €). ¿Podemos coordinar el pago esta semana?»</p>
                  <p style="margin:12px 0 0;font-size:11px;color:#71dd37;font-weight:600;">Programar correo (2 h) · Desprogramar</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:28px 32px 32px;">
            <a href="{{ $landingUrl }}" style="display:inline-block;padding:14px 28px;background:#696cff;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;margin-right:8px;">{{ $cta }}</a>
            <a href="{{ $presentationUrl }}" style="display:inline-block;padding:14px 28px;background:#ffffff;color:#696cff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;border:1px solid #696cff;">{{ $ctaGuide }}</a>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px 28px;text-align:center;">
            <p style="margin:0;font-size:12px;line-height:1.5;color:#a1acb8;">{{ $footer }}</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
