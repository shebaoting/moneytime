<?php

namespace Shebaoting\Money\Api\Resource;

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Shebaoting\Money\Model\MoneyLog;
use Tobyz\JsonApiServer\Context as JsonApiContext;

/**
 * @extends AbstractDatabaseResource<MoneyLog>
 */
class MoneyLogResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'money-logs';
    }

    public function model(): string
    {
        return MoneyLog::class;
    }

    public function scope(Builder $query, JsonApiContext $context): void
    {
        $query
            ->where('user_id', $context->getActor()->id)
            ->with(['targetUser', 'post', 'discussion'])
            ->orderByDesc('created_at');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->paginate(20, 50),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Number::make('amount'),
            Schema\Number::make('balance'),
            Schema\Str::make('reason')
                ->nullable(),
            Schema\Str::make('action'),
            Schema\DateTime::make('createdAt'),
            Schema\Arr::make('targetUser')
                ->nullable()
                ->get(fn (MoneyLog $log) => $log->targetUser ? [
                    'id' => (string) $log->targetUser->id,
                    'username' => $log->targetUser->username,
                    'displayName' => $log->targetUser->display_name,
                ] : null),
            Schema\Arr::make('post')
                ->nullable()
                ->get(fn (MoneyLog $log) => $log->post ? [
                    'id' => (string) $log->post->id,
                    'number' => $log->post->number,
                ] : null),
            Schema\Arr::make('discussion')
                ->nullable()
                ->get(fn (MoneyLog $log) => $log->discussion ? [
                    'id' => (string) $log->discussion->id,
                    'title' => $log->discussion->title,
                    'slug' => $log->discussion->slug,
                ] : null),
        ];
    }
}
