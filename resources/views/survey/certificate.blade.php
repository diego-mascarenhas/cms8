<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Certificado') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}">
</head>
<body class="bg-light">
    <div class="container py-5" style="max-width: 640px;">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-1">{{ __('Descargar certificado') }}</h4>
                <p class="text-muted">{{ $survey->title }}</p>
                <form method="POST" action="{{ route('survey.certificate.download', $survey) }}">
                    @csrf
                    <label class="form-label" for="email">{{ __('Email con el que te inscribiste') }}</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <button type="submit" class="btn btn-primary mt-3">{{ __('Descargar') }}</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
