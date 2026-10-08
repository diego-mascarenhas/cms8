<?php

namespace Tests\Unit;

use App\Services\ApolloService;
use PHPUnit\Framework\TestCase;

class ApolloPersonChannelsTest extends TestCase
{
    public function test_email_comes_from_the_flag_or_an_address(): void
    {
        $this->assertTrue(ApolloService::personHasEmail(['has_email' => true]));
        $this->assertTrue(ApolloService::personHasEmail(['email' => 'ana@acme.test']));
        $this->assertFalse(ApolloService::personHasEmail(['has_email' => false, 'title' => 'Owner']));
    }

    public function test_phone_is_found_on_the_person_or_the_company(): void
    {
        $this->assertTrue(ApolloService::personHasPhone(['has_direct_phone' => 'Yes']));
        $this->assertTrue(ApolloService::personHasPhone([
            'phone_numbers' => [['number' => '+34 600 111 222']],
        ]));
        $this->assertTrue(ApolloService::personHasPhone([
            'organization' => ['primary_phone' => ['number' => '+1 415 555 0100']],
        ]));
        $this->assertTrue(ApolloService::personHasPhone([
            'contact' => ['sanitized_phone' => '34600111222'],
        ]));
        $this->assertFalse(ApolloService::personHasPhone([
            'has_phone' => false,
            'organization' => ['name' => 'Acme'],
        ]));
    }
}
