<?php

namespace App\Console\Commands;

use App\Models\PaymentAccount;
use Illuminate\Console\Command;
use App\Mail\BalanceMail;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'email:send-test';
    protected $description = 'Send a test balance email';

    public function handle()
    {
        $balances = $this->getBalances(); // Define cómo obtienes los balances
        Mail::to('diego.mascarenhas@icloud.com')->send(new BalanceMail($balances));
        //Mail::to('pablo@revisionalpha.com')->send(new BalanceMail($balances));

        $this->info('Test email sent!');
    }

    private function getBalances()
    {
        // Lógica para obtener balances, similar a la del job
        return PaymentAccount::all()->map(function ($account) {
            $income = $account->payments()
                ->approvedStatus()
                ->where('transaction_type', 'I')
                ->sum('amount');

            $expense = $account->payments()
                ->approvedStatus()
                ->where('transaction_type', 'E')
                ->sum('amount');

            $balance = $income - $expense;

            return [
                'name' => $account->name,
                'balance' => $balance,
                'currency_code' => $account->currency ? $account->currency->code : 'N/A',
            ];
        });
    }
}
