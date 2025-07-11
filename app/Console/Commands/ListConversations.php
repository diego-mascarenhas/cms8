<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;

class ListConversations extends Command
{
    protected $signature = 'conversations:list {--channel=} {--phone=} {--limit=10}';

    protected $description = 'List recent conversations';

    public function handle()
    {
        $query = Conversation::query();

        if ($this->option('channel')) {
            $query->where('channel', $this->option('channel'));
        }

        if ($this->option('phone')) {
            $phone = $this->option('phone');
            $query->where(function ($q) use ($phone) {
                $q->where('from', 'like', "%$phone%")
                    ->orWhere('to', 'like', "%$phone%");
            });
        }

        $conversations = $query->latest()
            ->limit($this->option('limit'))
            ->get();

        if ($conversations->isEmpty()) {
            $this->info('No conversations found.');

            return 0;
        }

        $headers = ['ID', 'Channel', 'From', 'To', 'Message', 'Direction', 'Status', 'Created'];

        $rows = $conversations->map(function ($conversation) {
            return [
                $conversation->id,
                $conversation->channel,
                $conversation->from,
                $conversation->to,
                substr($conversation->body, 0, 30).(strlen($conversation->body) > 30 ? '...' : ''),
                $conversation->direction,
                $conversation->status,
                $conversation->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $this->table($headers, $rows);

        return 0;
    }
}
