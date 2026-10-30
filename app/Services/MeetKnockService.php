<?php

namespace App\Services;

use App\Mail\MeetKnockMail;
use App\Models\Team;
use App\Models\User;
use App\Support\MeetRoomLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MeetKnockService
{
    private const REMIND_EVERY_SECONDS = 180;

    private const EXPIRES_AFTER_SECONDS = 720;

    private const CACHE_GRACE_SECONDS = 300;

    /**
     * @return array{id: string, token: string, room: string, status: string}
     */
    public function request(int $teamId, string $name, string $email, ?string $appUrl = null): array
    {
        $name = $this->name($name);
        $email = strtolower(trim($email));
        $team = $this->team($teamId);
        $sender = $this->sender($team);
        $recipients = $this->recipients($team);

        if ($sender === null || $recipients === [])
        {
            throw new RuntimeException('No se pudo avisar al equipo.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            throw new RuntimeException('Ingresá un email válido.');
        }

        $id = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(16));
        $room = MeetRoomLink::slug();
        $appBase = $this->appUrl($appUrl);
        $knock = [
            'id' => $id,
            'token' => $token,
            'name' => $name,
            'email' => $email,
            'room' => $room,
            'app_url' => $appBase,
            'status' => 'waiting',
            'created_at' => Carbon::now()->getTimestamp(),
            'notified_at' => Carbon::now()->getTimestamp(),
        ];

        app(MeetBookingService::class)->scheduleWalkIn(
            $teamId,
            $name,
            $email,
            $appBase.'/meet/'.$room,
        );

        if ($this->send($knock, $sender, $recipients) < 1)
        {
            throw new RuntimeException('No se pudo avisar al equipo.');
        }

        $this->store($teamId, $knock);

        return [
            'id' => $id,
            'token' => $token,
            'room' => $room,
            'status' => 'waiting',
        ];
    }

    /**
     * @return array{status: string, room?: string}|null
     */
    public function status(int $teamId, string $id): ?array
    {
        $knock = $this->find($teamId, $id);
        if ($knock === null)
        {
            return null;
        }

        if ($this->expired($knock))
        {
            return ['status' => 'expired'];
        }

        if ($knock['status'] === 'joined')
        {
            return [
                'status' => 'joined',
                'room' => $knock['room'],
            ];
        }

        $this->remind($teamId, $knock);

        return ['status' => 'waiting'];
    }

    /**
     * @return array{status: string, room: string, name: string}|null
     */
    public function admit(int $teamId, string $token): ?array
    {
        $id = Cache::get($this->tokenKey($teamId, $token));
        if (! is_string($id))
        {
            return null;
        }

        $knock = $this->find($teamId, $id);
        if ($knock === null || $this->expired($knock))
        {
            return null;
        }

        $knock['status'] = 'joined';
        $this->store($teamId, $knock);

        return [
            'status' => 'joined',
            'room' => $knock['room'],
            'name' => $knock['name'],
        ];
    }

    /**
     * @param  array{id: string, token: string, name: string, room: string, status: string, created_at: int, notified_at: int}  $knock
     */
    private function remind(int $teamId, array $knock): void
    {
        if (Carbon::now()->getTimestamp() - $knock['notified_at'] < self::REMIND_EVERY_SECONDS)
        {
            return;
        }

        $team = $this->team($teamId);
        $sender = $this->sender($team);
        $recipients = $sender === null ? [] : $this->recipients($team);
        if ($sender !== null && $recipients !== [])
        {
            $this->send($knock, $sender, $recipients);
        }

        $knock['notified_at'] = Carbon::now()->getTimestamp();
        $this->store($teamId, $knock);
    }

    /**
     * @param  array{name: string, room: string, token: string}  $knock
     * @param  array{from_name: string, from_address: string}  $sender
     * @param  list<string>  $recipients
     */
    private function send(array $knock, array $sender, array $recipients): int
    {
        $previousUrl = config('services.revisionalpha.url');
        config(['services.revisionalpha.url' => $knock['app_url'] ?? $previousUrl]);

        try
        {
            Mail::to($recipients)->send(new MeetKnockMail(
                guestName: $knock['name'],
                joinUrl: $this->joinUrl($knock),
                fromAddress: $sender['from_address'],
                fromName: $sender['from_name'],
            ));

            return count($recipients);
        } catch (\Throwable $exception)
        {
            Log::warning('Immediate meeting request email failed', [
                'room' => $knock['room'],
                'error' => $exception->getMessage(),
            ]);

            return 0;
        } finally
        {
            config(['services.revisionalpha.url' => $previousUrl]);
        }
    }

    /**
     * @param  array{room: string, token: string, app_url?: string}  $knock
     */
    private function joinUrl(array $knock): string
    {
        $base = rtrim((string) ($knock['app_url'] ?? config('services.revisionalpha.url')), '/');

        return $base.'/meet/'.$knock['room'].'?entrar='.$knock['token'];
    }

    private function appUrl(?string $appUrl): string
    {
        $appUrl = rtrim(trim((string) $appUrl), '/');
        $scheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
        $host = (string) parse_url($appUrl, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '')
        {
            return rtrim((string) config('services.revisionalpha.url'), '/');
        }

        return $appUrl;
    }

    /**
     * @param  array{id: string, token: string, name: string, room: string, status: string, created_at: int, notified_at: int}  $knock
     */
    private function store(int $teamId, array $knock): void
    {
        $expiresAt = Carbon::createFromTimestamp($knock['created_at'])
            ->addSeconds(self::EXPIRES_AFTER_SECONDS + self::CACHE_GRACE_SECONDS);

        Cache::put($this->recordKey($teamId, $knock['id']), $knock, $expiresAt);
        Cache::put($this->tokenKey($teamId, $knock['token']), $knock['id'], $expiresAt);
    }

    /**
     * @return array{id: string, token: string, name: string, room: string, status: string, created_at: int, notified_at: int}|null
     */
    private function find(int $teamId, string $id): ?array
    {
        $knock = Cache::get($this->recordKey($teamId, $id));
        if (! is_array($knock) || ! isset($knock['id'], $knock['token'], $knock['room'], $knock['status'], $knock['created_at']))
        {
            return null;
        }

        return $knock;
    }

    /**
     * @param  array{created_at: int}  $knock
     */
    private function expired(array $knock): bool
    {
        return Carbon::now()->getTimestamp() - $knock['created_at'] >= self::EXPIRES_AFTER_SECONDS;
    }

    private function team(int $teamId): Team
    {
        $team = Team::query()->find($teamId);
        if (! $team instanceof Team)
        {
            throw new RuntimeException('No se pudo avisar al equipo.');
        }

        return $team;
    }

    private function name(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', strip_tags($name)) ?? '');
        $name = mb_substr($name, 0, 60);

        if ($name === '')
        {
            throw new RuntimeException('Escribí tu nombre.');
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private function recipients(Team $team): array
    {
        $recipients = [];

        foreach ($team->allUsers() as $user)
        {
            if (! $user instanceof User || ! $user->canManageTeam($team))
            {
                continue;
            }

            $email = strtolower(trim((string) $user->email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($recipients[$email]))
            {
                continue;
            }

            $recipients[$email] = true;
        }

        return array_keys($recipients);
    }

    /**
     * @return array{from_name: string, from_address: string}|null
     */
    private function sender(Team $team): ?array
    {
        $sender = $team->getCommunicationEmailSender();
        $address = $sender['from_address'] !== ''
            ? $sender['from_address']
            : trim((string) config('mail.from.address'));
        $name = $sender['from_name'] !== ''
            ? $sender['from_name']
            : trim((string) config('mail.from.name'));

        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL))
        {
            return null;
        }

        return [
            'from_name' => $name !== '' ? $name : $address,
            'from_address' => $address,
        ];
    }

    private function recordKey(int $teamId, string $id): string
    {
        return 'meet-knock:'.$teamId.':'.$id;
    }

    private function tokenKey(int $teamId, string $token): string
    {
        return 'meet-knock-token:'.$teamId.':'.$token;
    }
}
