<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('slash_landing.lead.mail_title') }}</title>
</head>
<body>
    <h2>{{ __('slash_landing.lead.mail_title') }}</h2>
    <p><strong>{{ __('slash_landing.lead.mail_email') }}:</strong> {{ $leadEmail }}</p>
    <p><strong>{{ __('slash_landing.lead.mail_name') }}:</strong> {{ $leadName ?: __('slash_landing.lead.mail_not_provided') }}</p>
    <p><strong>{{ __('slash_landing.lead.mail_phone') }}:</strong> {{ $leadPhone ?: __('slash_landing.lead.mail_not_provided') }}</p>
    <p><strong>{{ __('slash_landing.lead.mail_source') }}:</strong> {{ $sourceLabel }}</p>
    <p><strong>{{ __('slash_landing.lead.mail_submitted_at') }}:</strong> {{ $submittedAt }}</p>
</body>
</html>
