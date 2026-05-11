<?php

namespace Tests\Unit;

use App\Models\Message;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessageSendingScheduleAlignmentTest extends TestCase
{
    public function test_unrestricted_returns_same_moment(): void
    {
        $message = new Message([
            'send_allowed_weekdays' => null,
            'send_window_start' => null,
            'send_window_end' => null,
        ]);

        $candidate = Carbon::parse('2025-06-06 17:05:00', 'UTC');

        $this->assertTrue(
            $message->alignScheduledTimeWithSendingSchedule($candidate)->equalTo($candidate),
        );
    }

    #[DataProvider('weekdayScheduleProvider')]
    public function test_weekday_schedule_with_daily_window_aligns_correctly(
        string $input,
        string $expectedDatetime,
        string $tz,
    ): void {
        $message = new Message([
            'send_allowed_weekdays' => [1, 2, 3, 4, 5],
            'send_window_start' => '09:00',
            'send_window_end' => '17:00',
        ]);

        $candidate = Carbon::parse($input, $tz);
        $aligned = $message->alignScheduledTimeWithSendingSchedule($candidate);

        $this->assertSame(
            $expectedDatetime,
            $aligned->timezone($tz)->format('Y-m-d H:i'),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function weekdayScheduleProvider(): array
    {
        return [
            'before_open_same_day' => [
                '2025-06-06 08:30:00',
                '2025-06-06 09:00',
                'UTC',
            ],
            'after_close_moves_to_next_allowed_opening' => [
                '2025-06-06 17:05:00',
                '2025-06-09 09:00',
                'UTC',
            ],
        ];
    }

    public function test_disallowed_weekday_advances_until_allowed_preserving_clock_without_window(): void
    {
        $message = new Message([
            'send_allowed_weekdays' => [1, 2, 3, 4, 5],
        ]);

        $candidate = Carbon::parse('2025-06-07 10:15:24', 'UTC');
        $aligned = $message->alignScheduledTimeWithSendingSchedule($candidate);

        $this->assertSame('2025-06-09', $aligned->format('Y-m-d'));
        $this->assertSame('10:15', $aligned->format('H:i'));
    }
}
