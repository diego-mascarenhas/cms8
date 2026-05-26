@php
    $landingUrl = route('humano');
    $onLanding = Route::is('humano');

    $items = [
        [
            'label' => 'Beneficios',
            'url' => $onLanding ? '#landingFeatures' : $landingUrl.'#landingFeatures',
        ],
        [
            'label' => 'Guías',
            'url' => $onLanding ? '#landingManuals' : $landingUrl.'#landingManuals',
        ],
        [
            'label' => 'Planes',
            'url' => $onLanding ? '#landingPlans' : $landingUrl.'#landingPlans',
        ],
        [
            'label' => 'FAQ',
            'url' => $onLanding ? '#landingFAQ' : $landingUrl.'#landingFAQ',
        ],
        [
            'label' => 'Contacto',
            'url' => $onLanding ? '#landingContact' : $landingUrl.'#landingContact',
        ],
    ];
@endphp

@foreach ($items as $item)
  <li class="nav-item">
    <a href="{{ $item['url'] }}" class="nav-link px-2">{{ $item['label'] }}</a>
  </li>
@endforeach
