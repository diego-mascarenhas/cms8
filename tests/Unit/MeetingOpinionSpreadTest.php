<?php

namespace Tests\Unit;

use App\Support\MeetingOpinionSpread;
use PHPUnit\Framework\TestCase;

class MeetingOpinionSpreadTest extends TestCase
{
    public function test_points_split_into_polarized_atomized_and_concentrated(): void
    {
        $spread = MeetingOpinionSpread::percentages([
            'points' => [
                ['topic' => 'Claves', 'agree' => ['Ana'], 'disagree' => ['Luis']],
                ['topic' => 'VPN', 'agree' => [], 'disagree' => []],
                ['topic' => 'Equipo', 'agree' => ['Ana'], 'disagree' => []],
            ],
        ]);

        $this->assertSame(33, $spread['polarized']);
        $this->assertSame(33, $spread['atomized']);
        $this->assertSame(33, $spread['concentrated']);
    }

    public function test_objectives_come_from_points_when_nothing_was_agreed(): void
    {
        $objectives = MeetingOpinionSpread::objectives([
            'points' => [
                ['topic' => 'Resguardar las claves'],
            ],
            'objectives' => [],
        ]);

        $this->assertSame('Resguardar las claves', $objectives[0]['text']);
        $this->assertFalse($objectives[0]['marked']);
    }

    public function test_a_stored_mark_is_kept(): void
    {
        $objectives = MeetingOpinionSpread::objectives([
            'objectives' => [
                ['text' => 'Separar los equipos', 'marked' => true],
            ],
            'points' => [
                ['topic' => 'Otro punto'],
            ],
        ]);

        $this->assertCount(1, $objectives);
        $this->assertTrue($objectives[0]['marked']);
    }
}
