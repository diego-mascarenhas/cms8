<?php

namespace Tests\Unit;

use App\Services\Contacts\ContactTitleClassifier;
use PHPUnit\Framework\TestCase;

class ContactTitleClassifierTest extends TestCase
{
    public function test_collapses_executive_titles_and_drops_the_company(): void
    {
        $this->assertSame('CEO', ContactTitleClassifier::classify('CEO'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('C.E.O.'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Chief Executive Officer'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Presidente'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Presidenta'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Chief Executive Officer at Valori Marketing'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Presidente de Valori Marketing'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('CEO, Valori Marketing'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Founder & CEO of Acme'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Owner at Acme', 'Acme'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Director general'));
        $this->assertSame('CEO', ContactTitleClassifier::classify('Gerente general de Humano Labs', 'Humano Labs'));
    }

    public function test_keeps_functional_titles_without_the_company(): void
    {
        $this->assertSame('Director Comercial', ContactTitleClassifier::classify('Director comercial'));
        $this->assertSame(
            'Director Comercial',
            ContactTitleClassifier::classify('Director comercial at Valori Marketing', 'Valori Marketing'),
        );
        $this->assertSame('Director de Marketing', ContactTitleClassifier::classify('Director de Marketing'));
        $this->assertNull(ContactTitleClassifier::classify('  '));
        $this->assertNull(ContactTitleClassifier::classify(null));
    }
}
