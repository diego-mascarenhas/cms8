<?php

namespace App\Services\Api;

use App\Models\Category;
use App\Models\Certification;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Enterprise;
use App\Models\Fare;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\LanguageVariant;
use App\Models\MeetingTranscript;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\PaidAdAudience;
use App\Models\PaidAdCampaign;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\ShoppingCart;
use App\Models\Software;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Survey;
use App\Models\Task;
use App\Models\Team;
use App\Models\Template;
use App\Models\Ticket;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ApiPerformanceProbe
{
    /**
     * Route segment (or parameter name) to the model that can supply an example id.
     *
     * @var array<string, class-string>
     */
    private const RESOURCES = [
        'tasks' => Task::class,
        'message' => Message::class,
        'deliveries' => MessageDelivery::class,
        'delivery' => MessageDelivery::class,
        'communications' => Communication::class,
        'tickets' => Ticket::class,
        'templates' => Template::class,
        'audiences' => PaidAdAudience::class,
        'paid-ads' => PaidAdCampaign::class,
        'products' => Product::class,
        'product' => Product::class,
        'stores' => Store::class,
        'orders' => Order::class,
        'order' => Order::class,
        'carts' => ShoppingCart::class,
        'contacts' => Contact::class,
        'contact' => Contact::class,
        'clients' => Enterprise::class,
        'enterprises' => Enterprise::class,
        'enterprise' => Enterprise::class,
        'subscriptions' => Subscription::class,
        'emails' => Email::class,
        'projects' => Project::class,
        'project' => Project::class,
        'services' => Service::class,
        'payments' => Payment::class,
        'payment' => Payment::class,
        'invoices' => Invoice::class,
        'posts' => Post::class,
        'meetings' => MeetingTranscript::class,
        'meeting' => MeetingTranscript::class,
        'certificates' => Survey::class,
        'survey' => Survey::class,
        'software' => Software::class,
        'category' => Category::class,
        'certifications' => Certification::class,
        'certification' => Certification::class,
        'fares' => Fare::class,
        'fare' => Fare::class,
        'language-variants' => LanguageVariant::class,
        'languageVariant' => LanguageVariant::class,
    ];

    /**
     * @return list<array{uri: string, url: ?string, auth: string, skip: ?string}>
     */
    public function targets(Team $team, string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $seen = [];
        $targets = [];

        foreach (RouteFacade::getRoutes() as $route)
        {
            if (! $route instanceof Route)
            {
                continue;
            }

            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || isset($seen[$uri]) || ! in_array('GET', $route->methods(), true))
            {
                continue;
            }

            $seen[$uri] = true;
            $middleware = implode(' ', $route->gatherMiddleware());
            $auth = $this->authKind($middleware);
            $skip = null;
            $url = null;

            if (str_contains($middleware, 'signed'))
            {
                $skip = 'signed URL required';
            } else
            {
                $path = $this->fill($route, $team);
                if ($path === null)
                {
                    $skip = 'no sample row for this team';
                } else
                {
                    $url = $baseUrl.'/'.$path;
                }
            }

            $targets[] = [
                'uri' => $uri,
                'url' => $url,
                'auth' => $auth,
                'skip' => $skip,
            ];
        }

        usort($targets, fn (array $left, array $right): int => $left['uri'] <=> $right['uri']);

        return $targets;
    }

    /**
     * @return array{status: int, milliseconds: int, bytes: int, error: ?string}
     */
    public function measure(string $url, ?string $bearer, int $timeout): array
    {
        $pending = Http::acceptJson()->connectTimeout(5)->timeout($timeout);
        if (is_string($bearer) && $bearer !== '')
        {
            $pending = $pending->withToken($bearer);
        }

        $started = hrtime(true);

        try
        {
            $response = $pending->get($url);
        } catch (Throwable $exception)
        {
            return [
                'status' => 0,
                'milliseconds' => (int) round((hrtime(true) - $started) / 1_000_000),
                'bytes' => 0,
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'status' => $response->status(),
            'milliseconds' => (int) round((hrtime(true) - $started) / 1_000_000),
            'bytes' => strlen($response->body()),
            'error' => null,
        ];
    }

    private function authKind(string $middleware): string
    {
        if (str_contains($middleware, 'team.token') || str_contains($middleware, 'TeamTokenAuth'))
        {
            return 'team';
        }

        if (str_contains($middleware, 'auth.api') || str_contains($middleware, 'auth:sanctum') || str_contains($middleware, 'AuthenticateSanctum'))
        {
            return 'user';
        }

        return 'public';
    }

    private function fill(Route $route, Team $team): ?string
    {
        $path = $route->uri();
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        foreach ($matches[1] as $raw)
        {
            $optional = str_ends_with($raw, '?');
            $name = rtrim($raw, '?');
            $value = $this->valueFor($route, $name, $team);

            if ($value === null || $value === '')
            {
                if ($optional)
                {
                    $path = str_replace('/{'.$raw.'}', '', $path);

                    continue;
                }

                return null;
            }

            $constraint = $route->wheres[$name] ?? null;
            if (is_string($constraint) && preg_match('/'.str_replace('/', '\/', $constraint).'/', $value) !== 1)
            {
                return null;
            }

            $path = str_replace('{'.$raw.'}', rawurlencode($value), $path);
        }

        return $path;
    }

    private function valueFor(Route $route, string $name, Team $team): ?string
    {
        $resource = $this->resourceBefore($route->uri(), $name);

        if (in_array($name, ['token', 'sessionKey'], true))
        {
            return null;
        }

        if ($name === 'teamSlug' || ($name === 'slug' && $resource === 'public-shop'))
        {
            return $team->publicCatalogPathSlug();
        }

        if ($name === 'phone')
        {
            $phone = $this->firstColumn(Contact::class, $team, ['phone']);

            return $phone === null ? null : (preg_replace('/\D/', '', $phone) ?: null);
        }

        if ($name === 'domain')
        {
            return $this->firstColumn(Domain::class, $team, ['domain']);
        }

        if ($name === 'postType')
        {
            return $this->firstColumn(Post::class, $team, ['post_type']);
        }

        if ($name === 'slug')
        {
            return $this->firstColumn(Post::class, $team, ['post_name']);
        }

        if ($name === 'code')
        {
            return $this->firstColumn(Product::class, $team, ['code']);
        }

        if ($name === 'type')
        {
            return $this->firstColumn(Fare::class, $team, ['type_id']);
        }

        if (in_array($name, ['language', 'baseLanguage'], true))
        {
            return $this->firstColumn(Language::class, $team, ['code', 'id']);
        }

        $class = self::RESOURCES[$resource ?? ''] ?? self::RESOURCES[$name] ?? null;
        if ($class === null && $resource !== null)
        {
            $guess = 'App\\Models\\'.Str::studly(Str::singular($resource));
            $class = class_exists($guess) ? $guess : null;
        }

        if ($class === null)
        {
            return null;
        }

        return $this->firstColumn($class, $team, ['id']);
    }

    private function resourceBefore(string $uri, string $parameter): ?string
    {
        $parts = explode('/', $uri);
        foreach ($parts as $index => $part)
        {
            if ($part === '{'.$parameter.'}' || $part === '{'.$parameter.'?}')
            {
                return $parts[$index - 1] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $class
     * @param  list<string>  $columns
     */
    private function firstColumn(string $class, Team $team, array $columns): ?string
    {
        if (! class_exists($class))
        {
            return null;
        }

        try
        {
            $model = new $class;
            $table = $model->getTable();
            $query = $class::query()->withoutGlobalScopes();

            if (Schema::hasColumn($table, 'team_id'))
            {
                $query->where($table.'.team_id', $team->id);
            }

            if (Schema::hasColumn($table, 'deleted_at'))
            {
                $query->whereNull($table.'.deleted_at');
            }

            $column = null;
            foreach ($columns as $candidate)
            {
                if (Schema::hasColumn($table, $candidate))
                {
                    $column = $candidate;
                    break;
                }
            }

            if ($column === null)
            {
                return null;
            }

            $value = $query->orderByDesc($model->getKeyName())->value($table.'.'.$column);
        } catch (Throwable)
        {
            return null;
        }

        if ($value === null || $value === '')
        {
            return null;
        }

        return (string) $value;
    }
}
