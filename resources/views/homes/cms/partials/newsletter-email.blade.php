{{-- Email-safe HTML for campaigns (table layout, inline styles). --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background-color:#f4f5fb;font-family:Arial,Helvetica,sans-serif;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e4e6ef;">
        <tr>
          <td style="padding:28px 32px 20px;background:linear-gradient(135deg,#696cff 0%,#8b7bff 100%);">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td>
                  @if (! empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="Humano" width="120" style="display:block;height:auto;max-width:120px;border:0;">
                  @endif
                </td>
                <td align="right" style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.9);">
                  CMS + WordPress
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 12px;font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#696cff;">
              Humano CMS
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
          <td style="padding:20px 32px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td width="48%" valign="top" style="padding-right:8px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f8f8fc;border:1px solid #e4e6ef;border-radius:12px;">
                    <tr>
                      <td style="padding:16px;">
                        <p style="margin:0 0 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#696cff;">
                          {{ $adminTitle }}
                        </p>
                        <div style="background:#2f3349;border-radius:8px;padding:10px;margin-bottom:10px;">
                          <p style="margin:0 0 6px;font-size:10px;color:#d5f5e3;background:#005c4b;display:inline-block;padding:6px 8px;border-radius:8px 8px 2px 8px;">
                            ¿Qué páginas del CMS tenemos?
                          </p>
                          <p style="margin:0;font-size:10px;color:#e9edef;background:#1f2c34;display:inline-block;padding:6px 8px;border-radius:8px 8px 8px 2px;">
                            Contacto · Sobre nosotros · Ejemplo
                          </p>
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
                        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:10px;margin-bottom:10px;">
                          <p style="margin:0 0 6px;font-size:10px;color:#fff;background:#696cff;display:inline-block;padding:6px 8px;border-radius:8px 8px 2px 8px;">
                            ¿Tienen página de contacto?
                          </p>
                          <p style="margin:0;font-size:10px;color:#334155;background:#f8fafc;border:1px solid #e2e8f0;display:inline-block;padding:6px 8px;border-radius:8px 8px 8px 2px;">
                            Sí. Horario y formulario publicados.
                          </p>
                        </div>
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
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eef0ff;border-radius:12px;border:1px solid #dfe3ff;">
              <tr>
                <td style="padding:18px 20px;">
                  <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#233446;">Panel Humano</p>
                  <p style="margin:0;font-size:12px;line-height:1.5;color:#566a7f;">
                    Editor con categorías, sync WordPress y asistente multicanal: web, WhatsApp y API con el mismo contenido.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:28px 32px 32px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding-right:10px;">
                  <a href="{{ $landingUrl }}" style="display:inline-block;padding:14px 24px;background:linear-gradient(135deg,#696cff,#8b7bff);color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:999px;">
                    {{ $cta }}
                  </a>
                </td>
                <td>
                  <a href="{{ $presentationUrl }}" style="display:inline-block;padding:14px 24px;background:#ffffff;color:#696cff;font-size:15px;font-weight:700;text-decoration:none;border-radius:999px;border:1px solid #696cff;">
                    {{ $ctaGuide }}
                  </a>
                </td>
              </tr>
            </table>
            <p style="margin:18px 0 0;font-size:13px;">
              <a href="{{ $registerUrl }}" style="color:#696cff;font-weight:600;text-decoration:underline;">{{ __('cms_landing.hero.cta_primary') }}</a>
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 28px;border-top:1px solid #e4e6ef;background:#fafafa;">
            <p style="margin:0;font-size:12px;line-height:1.5;color:#a1acb8;text-align:center;">
              {{ $footer }}
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
