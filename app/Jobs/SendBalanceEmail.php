<?php

namespace App\Jobs;

use App\Models\PaymentAccount;
use App\Models\User;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBalanceEmail implements ShouldQueue
{
    use ConfiguresTeamMail, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Obtener el balance de todas las cuentas
        $balances = $this->calculateBalances();

        // Obtener los usuarios con rol de administrador
        $adminUsers = User::role('admin')->get();

        // Enviar el correo a cada administrador
        foreach ($adminUsers as $user)
        {
            // Configure mail for the user's current team
            if ($user->currentTeam)
            {
                $this->configureMailForTeam($user->currentTeam);
            }

            Mail::to($user->email)->send(new \App\Mail\BalanceMail($balances));
        }
    }

    protected function calculateBalances()
    {
        return PaymentAccount::all()->map(function ($account)
        {
            $income = $account
                ->payments()
                ->approvedStatus()
                ->where('transaction_type', 'I')
                ->sum('amount');

            $expense = $account
                ->payments()
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
