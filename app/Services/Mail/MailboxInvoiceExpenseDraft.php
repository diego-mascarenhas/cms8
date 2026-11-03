<?php

namespace App\Services\Mail;

class MailboxInvoiceExpenseDraft
{
    public function isInvoice(string $subject, string $body): bool
    {
        $text = mb_strtolower($subject."\n".$body);

        foreach (['factura', 'invoice', 'comprobante'] as $needle)
        {
            if (str_contains($text, $needle))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function toExpenseQuery(string $from, string $subject, string $body, ?int $enterpriseId = null): array
    {
        $query = [];
        if ($enterpriseId !== null && $enterpriseId > 0)
        {
            $query['enterprise_id'] = (string) $enterpriseId;
        }

        $number = $this->documentNumber($subject.' '.$body);
        if ($number !== null)
        {
            $query['document_number'] = $number;
        }

        $date = $this->documentDate($body);
        if ($date !== null)
        {
            $query['date'] = $date;
        }

        $amount = $this->amount($body);
        if ($amount !== null)
        {
            $query['base_amount'] = $amount['value'];
            if ($amount['currency'] !== null)
            {
                $query['currency'] = $amount['currency'];
            }
        }

        $concept = $this->concept($subject);
        if ($concept !== '')
        {
            $query['concept'] = $concept;
        }

        $from = trim($from);
        if ($from !== '')
        {
            $query['remarks'] = 'Correo de '.$from;
        }

        return $query;
    }

    private function documentNumber(string $text): ?string
    {
        if (preg_match('/(?:factura|invoice|comprobante)\s*(?:n[ºo°.]?)?\s*[:#]?\s*([A-Z0-9]+(?:[-\/][A-Z0-9]+)*)/iu', $text, $matches) !== 1)
        {
            return null;
        }

        $number = trim($matches[1], " \t\n\r\0\x0B-/");
        if ($number === '' || preg_match('/\d/', $number) !== 1)
        {
            return null;
        }

        return $number;
    }

    private function documentDate(string $text): ?string
    {
        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $matches) === 1)
        {
            return $matches[1].'-'.$matches[2].'-'.$matches[3];
        }

        if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/', $text, $matches) !== 1)
        {
            return null;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];
        if ($year < 100)
        {
            $year += 2000;
        }
        if ($day > 12 && $month <= 12)
        {
            // already day/month
        } elseif ($month > 12 && $day <= 12)
        {
            [$day, $month] = [$month, $day];
        }
        if (! checkdate($month, $day, $year))
        {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @return array{value: string, currency: ?string}|null
     */
    private function amount(string $text): ?array
    {
        if (preg_match('/(?:total|importe|amount)\s*[:.]?\s*([0-9][0-9.\s]*,[0-9]{2}|[0-9][0-9,]*(?:\.[0-9]{2})?)\s*(EUR|USD|ARS|€|\$)?/iu', $text, $matches) !== 1)
        {
            return null;
        }

        $raw = str_replace(' ', '', $matches[1]);
        if (str_contains($raw, ','))
        {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw))
        {
            return null;
        }

        $currency = isset($matches[2]) ? strtoupper($matches[2]) : null;
        if ($currency === '€')
        {
            $currency = 'EUR';
        }
        if ($currency === '$')
        {
            $currency = 'USD';
        }

        return [
            'value' => number_format((float) $raw, 2, '.', ''),
            'currency' => $currency,
        ];
    }

    private function concept(string $subject): string
    {
        $subject = trim($subject);
        $previous = null;
        while ($subject !== $previous)
        {
            $previous = $subject;
            $subject = trim((string) preg_replace('/^(?:re|rv|aw|fw|fwd|res|respuesta)\s*:\s*/iu', '', $subject));
        }

        return mb_substr($subject, 0, 180);
    }
}
