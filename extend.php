<?php

namespace Shebaoting\Money;

use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Discussion\Event\Started;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\User\Event\Saving;
use Flarum\Post\Event\Saving as SavingPostEvent;
use Flarum\Likes\Event\PostWasLiked;
use Flarum\Likes\Event\PostWasUnliked;
use Flarum\User\Event\Registered;
use Flarum\User\User;
use Shebaoting\Money\Api\Resource\MoneyLogResource;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    new Extend\ApiResource(MoneyLogResource::class),

    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Number::make('money')
                ->get(fn (User $user) => (float) ($user->money ?? 0))
                ->writable(fn (User $user, Context $context) => $context->updating() && $context->getActor()->can('edit_money', $user))
                // Leave the actual balance change and log creation to User\Event\Saving.
                ->set(fn (User $user, mixed $value, Context $context) => null),
            Schema\Boolean::make('canEditMoney')
                ->get(fn (User $user, Context $context) => $context->getActor()->can('edit_money', $user)),
        ]),

    (new Extend\Settings())
        ->default('shebaoting-money.moneyname', '[money]')
        ->default('shebaoting-money.initialmoney', 0)
        ->default('shebaoting-money.level_names', '')
        ->default('shebaoting-money.money_scale', 100)
        ->default('shebaoting-money.moneyforpost', 0)
        ->default('shebaoting-money.moneyforpost_type', 'reward')
        ->default('shebaoting-money.moneyforreply', 0)
        ->default('shebaoting-money.moneyforreply_type', 'reward')
        ->default('shebaoting-money.moneyforreply_feedback', 'no_feedback')
        ->default('shebaoting-money.moneyforlike', 0)
        ->default('shebaoting-money.moneyforlike_type', 'reward')
        ->default('shebaoting-money.moneyforlike_feedback', 'no_feedback')
        ->default('shebaoting-money.autoremove', 1)
        ->default('shebaoting-money.noshowzero', false)
        ->default('shebaoting-money.extra_char_threshold', 0)
        ->default('shebaoting-money.extra_char_increment', 1)
        ->default('shebaoting-money.extra_char_points', 0)
        ->serializeToForum('shebaoting-money.moneyname', 'shebaoting-money.moneyname')
        ->serializeToForum('shebaoting-money.level_names', 'shebaoting-money.level_names')
        ->serializeToForum('shebaoting-money.money_scale', 'shebaoting-money.money_scale', 'intval')
        ->serializeToForum('shebaoting-money.noshowzero', 'shebaoting-money.noshowzero', 'boolval')
        ->serializeToForum('shebaoting-money.moneyforlike', 'shebaoting-money.moneyforlike', 'floatval')
        ->serializeToForum('shebaoting-money.moneyforlike_type', 'shebaoting-money.moneyforlike_type')
        ->serializeToForum('shebaoting-money.extra_char_threshold', 'shebaoting-money.extra_char_threshold', 'intval')
        ->serializeToForum('shebaoting-money.extra_char_increment', 'shebaoting-money.extra_char_increment', 'intval')
        ->serializeToForum('shebaoting-money.extra_char_points', 'shebaoting-money.extra_char_points', 'floatval'),

    (new Extend\Event())
        ->listen(SavingPostEvent::class, Listeners\GiveMoney::class . '@postWillBeSaved')
        ->listen(Posted::class, Listeners\GiveMoney::class . '@postWasPosted')
        ->listen(PostRestored::class, Listeners\GiveMoney::class . '@postWasRestored')
        ->listen(PostHidden::class, Listeners\GiveMoney::class . '@postWasHidden')
        ->listen(PostDeleted::class, Listeners\GiveMoney::class . '@postWasDeleted')
        ->listen(Started::class, Listeners\GiveMoney::class . '@discussionWasStarted')
        ->listen(DiscussionRestored::class, Listeners\GiveMoney::class . '@discussionWasRestored')
        ->listen(DiscussionHidden::class, Listeners\GiveMoney::class . '@discussionWasHidden')
        ->listen(DiscussionDeleted::class, Listeners\GiveMoney::class . '@discussionWasDeleted')
        ->listen(Saving::class, Listeners\GiveMoney::class . '@userWillBeSaved')
        ->listen(Registered::class, Listeners\GiveMoney::class . '@userWasRegistered')
        ->listen(PostWasLiked::class, Listeners\GiveMoney::class . '@postWasLiked')
        ->listen(PostWasUnliked::class, Listeners\GiveMoney::class . '@postWasUnliked'),
];
