<?php

namespace App\Support\Search;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A thin client over Elasticsearch's REST API (Laravel's HTTP client, so tests use Http::fake()).
 * Postgres stays the source of truth: the search cluster only holds copies for the marketplace.
 */
class SearchClient
{
    public function enabled(): bool
    {
        return (string) config('services.search.url') !== '';
    }

    /** The physical name of an index or alias: the configured prefix + the logical name. */
    public function name(string $logical): string
    {
        return config('services.search.prefix').$logical;
    }

    /** @return array{name: string, version: string, status: string} */
    public function info(): array
    {
        $root = $this->ok($this->http()->get('/'))->json();
        $health = $this->ok($this->http()->get('/_cluster/health'))->json();

        return ['name' => (string) ($root['cluster_name'] ?? ''), 'version' => (string) ($root['version']['number'] ?? ''), 'status' => (string) ($health['status'] ?? '')];
    }

    public function indexExists(string $index): bool
    {
        return $this->http()->head('/'.$index)->status() === 200;
    }

    /** @param  array<string, mixed>  $definition  settings + mappings */
    public function createIndex(string $index, array $definition): void
    {
        $this->ok($this->http()->put('/'.$index, $definition));
    }

    public function deleteIndex(string $index): void
    {
        $response = $this->http()->delete('/'.$index);
        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /** @return list<string> the indices an alias points to now */
    public function aliasTargets(string $alias): array
    {
        $response = $this->http()->get('/_alias/'.$alias);

        return $response->status() === 404 ? [] : array_keys($this->ok($response)->json() ?? []);
    }

    /** Points the alias at $index only, in one atomic step (readers never see it missing). */
    public function swapAlias(string $alias, string $index): void
    {
        $actions = array_map(fn (string $old) => ['remove' => ['index' => $old, 'alias' => $alias]], array_values(array_diff($this->aliasTargets($alias), [$index])));
        $actions[] = ['add' => ['index' => $index, 'alias' => $alias]];
        $this->ok($this->http()->post('/_aliases', ['actions' => $actions]));
    }

    /**
     * Index / delete many documents at once.
     *
     * @param  array<string, array<string, mixed>|null>  $docs  id => document, or null to delete it
     * @return int how many failed
     */
    public function bulk(string $index, array $docs, bool $refresh = false): int
    {
        if ($docs === []) {
            return 0;
        }
        $lines = [];
        foreach ($docs as $id => $doc) {
            if ($doc === null) {
                $lines[] = json_encode(['delete' => ['_index' => $index, '_id' => (string) $id]]);
            } else {
                $lines[] = json_encode(['index' => ['_index' => $index, '_id' => (string) $id]]);
                $lines[] = json_encode($doc, JSON_UNESCAPED_UNICODE);
            }
        }
        $body = implode("\n", $lines)."\n";
        $response = $this->ok($this->http()->withBody($body, 'application/x-ndjson')->post('/_bulk'.($refresh ? '?refresh=wait_for' : '')));

        return collect($response->json('items') ?? [])
            ->filter(fn (array $item) => ($item[array_key_first($item)]['status'] ?? 500) >= 300 && ($item['delete']['status'] ?? 0) !== 404)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function search(string $index, array $body): array
    {
        return $this->ok($this->http()->post('/'.$index.'/_search', $body))->json() ?? [];
    }

    /**
     * Deletes every document matching a query (e.g. one shop's offers).
     *
     * @param  array<string, mixed>  $query
     */
    public function deleteByQuery(string $index, array $query, bool $refresh = false): int
    {
        $response = $this->ok($this->http()->post('/'.$index.'/_delete_by_query?conflicts=proceed'.($refresh ? '&refresh=true' : ''), ['query' => $query]));

        return (int) $response->json('deleted');
    }

    /** @param  array<string, mixed>|null  $query */
    public function count(string $index, ?array $query = null): int
    {
        $response = $this->http()->post('/'.$index.'/_count', $query === null ? (object) [] : ['query' => $query]);

        return $response->status() === 404 ? 0 : (int) $this->ok($response)->json('count');
    }

    /** @return list<string> the tokens an index's analyzer makes of a text (for checking the Arabic setup) */
    public function analyze(string $index, string $analyzer, string $text): array
    {
        $tokens = $this->ok($this->http()->post('/'.$index.'/_analyze', ['analyzer' => $analyzer, 'text' => $text]))->json('tokens') ?? [];

        return array_map(fn (array $t) => (string) $t['token'], $tokens);
    }

    private function http(): PendingRequest
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Search is not configured (SEARCH_URL).');
        }
        $request = Http::baseUrl((string) config('services.search.url'))
            ->acceptJson()
            ->timeout((int) config('services.search.timeout', 5));
        if ($key = (string) config('services.search.api_key')) {
            $request = $request->withHeaders(['Authorization' => 'ApiKey '.$key]);
        } elseif ($user = (string) config('services.search.username')) {
            $request = $request->withBasicAuth($user, (string) config('services.search.password'));
        }
        if ($ca = (string) config('services.search.ca')) {
            $request = $request->withOptions(['verify' => $ca]);
        }

        return $request;
    }

    private function ok(Response $response): Response
    {
        if ($response->failed()) {
            $reason = $response->json('error.reason') ?? $response->json('error.type') ?? $response->body();
            throw new RuntimeException('Search request failed ('.$response->status().'): '.mb_substr((string) $reason, 0, 300));
        }

        return $response;
    }
}
