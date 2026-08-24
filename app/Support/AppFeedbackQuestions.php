<?php

namespace App\Support;

class AppFeedbackQuestions
{
    /**
     * @return array<string, list<string>>
     */
    public static function choices(): array
    {
        return [
            'satisfaction' => [
                'very_satisfied',
                'satisfied',
                'neutral',
                'dissatisfied',
                'very_dissatisfied',
            ],
            'ease' => [
                'very_easy',
                'easy',
                'acceptable',
                'hard',
                'very_hard',
            ],
            'value' => [
                'a_lot',
                'quite_a_bit',
                'somewhat',
                'little',
                'nothing',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::choices());
    }

    /**
     * @param  list<array{key?: mixed, choice?: mixed}>  $answers
     */
    public static function summarize(array $answers, ?string $comment): string
    {
        $lines = [];

        foreach ($answers as $answer)
        {
            $key = (string) ($answer['key'] ?? '');
            $choice = (string) ($answer['choice'] ?? '');
            if ($key === '' || $choice === '')
            {
                continue;
            }

            $lines[] = $key.': '.$choice;
        }

        $comment = trim((string) $comment);
        if ($comment !== '')
        {
            $lines[] = 'comment: '.$comment;
        }

        return implode("\n", $lines);
    }
}
