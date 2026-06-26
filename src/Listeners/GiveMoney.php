<?php

namespace Shebaoting\Money\Listeners;

use Illuminate\Support\Arr;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Flarum\User\User;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Discussion\Event\Started;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\Likes\Event\PostWasLiked;
use Flarum\Likes\Event\PostWasUnliked;
use Shebaoting\Money\Event\MoneyUpdated;
use Shebaoting\Money\Model\MoneyLog;
use Flarum\Post\Event\Saving;
use Flarum\User\Event\Saving as SavingUser;
use Flarum\Discussion\Event\Saving as SavingDiscussion;
use Flarum\Foundation\ValidationException;
use Flarum\User\Event\Registered;

abstract class AutoRemoveEnum
{
    public const NEVER = 0;
    public const HIDDEN = 1;
    public const DELETED = 2;
}

class GiveMoney
{
    protected $settings;
    protected $events;
    protected $autoremove;
    protected $url;

    public function __construct(SettingsRepositoryInterface $settings, Dispatcher $events, \Flarum\Http\UrlGenerator $url)
    {
        $this->settings = $settings;
        $this->events = $events;
        $this->url = $url;
        $this->autoremove = (int)$this->settings->get('shebaoting-money.autoremove', 1);
    }

    /**
     * 给用户添加或扣除金额
     *
     * @param User|null $user 用户对象
     * @param float $amount 积分变动数量
     * @param string $action 动作类型
     * @param string|null $description 积分变动原因
     * @param User|null $targetUser 目标用户
     * @param \Flarum\Post\Post|null $post 相关的帖子对象
     * @param \Flarum\Discussion\Discussion|null $discussion 相关的讨论对象
     */
    public function giveMoney(?User $user, float $amount, string $action, string $description = null, User $targetUser = null, $post = null, $discussion = null)
    {
        if (!is_null($user)) {
            // 更新用户的余额
            $user->money += $amount;
            $user->save();

            // 获取更新后的余额
            $balance = $user->money;

            // 记录积分变动
            MoneyLog::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'balance' => $balance,
                'action' => $action,
                'reason' => $description,
                'target_user_id' => $targetUser ? $targetUser->id : null,
                'post_id' => $post ? $post->id : null,
                'discussion_id' => $discussion ? $discussion->id : ($post ? $post->discussion_id : null),
            ]);

            // 触发金额更新事件
            $this->events->dispatch(new MoneyUpdated($user, $amount, $action, $description));
        }
    }

    /**
     * 处理额外的积分奖励或扣除
     *
     * @param User $user
     * @param string $content 内容
     * @param string $actionType 'post' 或 'reply'
     */
    protected function handleExtraPoints(User $user, string $content, string $actionType)
    {
        $threshold = (int)$this->settings->get('shebaoting-money.extra_char_threshold', 0);
        $increment = (int)$this->settings->get('shebaoting-money.extra_char_increment', 1);
        $points = (float)$this->settings->get('shebaoting-money.extra_char_points', 0);

        if ($threshold <= 0 || $increment <= 0 || $points == 0) {
            // 如果任一设置无效，则不处理
            return;
        }

        // 计算内容长度，去除HTML标签
        $contentLength = mb_strlen(strip_tags($content));
        if ($contentLength <= $threshold) {
            // 不超过阈值，不处理
            return;
        }

        $extraChars = $contentLength - $threshold;
        $increments = floor($extraChars / $increment);
        if ($increments <= 0) {
            return;
        }

        $totalPoints = $increments * $points;

        // 根据默认的奖励或扣除行为确定积分的正负
        // 统一使用 moneyfor_actionType_type
        $typeSetting = $this->settings->get("shebaoting-money.moneyfor{$actionType}_type", 'deduct'); // 'reward' or 'deduct'

        $amount = ($typeSetting === 'deduct') ? -abs($totalPoints) : abs($totalPoints);
        $actionTextKey = ($amount > 0) ? 'shebaoting-money.forum.logs_description.action_rewarded' : 'shebaoting-money.forum.logs_description.action_deducted';
        $actionText = app()->translator->trans($actionTextKey);

        $description = app()->translator->trans('shebaoting-money.forum.logs_description.extra_characters', [
            'points' => abs($totalPoints),
            'threshold' => $threshold,
            'increment' => $increment,
            'extra_chars' => $extraChars,
            'action' => $actionText,
        ]);

        // 创建额外积分记录
        $this->giveMoney($user, $amount, 'extra_characters', $description, null, null, null);
    }

    /**
     * 当帖子将要被保存时执行，检查用户余额是否足够
     */
    public function postWillBeSaved(Saving $event)
    {
        $money = (float)$this->settings->get('shebaoting-money.moneyforpost', 0);

        if ($money < 0) {
            $user = $event->actor;

            if ($user->money < abs($money)) {
                throw new ValidationException([
                    'money' => app()->translator->trans('shebaoting-money.forum.errors.not_enough_money')
                ]);
            }
        }
    }

    /**
     * 讨论将要被保存时执行，检查用户余额是否足够
     */
    public function discussionWillBeSaved(SavingDiscussion $event)
    {
        $money = (float)$this->settings->get('shebaoting-money.moneyfordiscussion', 0);

        if ($money < 0 && $event->actor->money < abs($money)) {
            throw new ValidationException([
                'money' => app()->translator->trans('shebaoting-money.forum.errors.not_enough_money')
            ]);
        }
    }

    /**
     * 当帖子发布时，处理发帖或回帖的积分扣除及回馈
     */
    public function postWasPosted(Posted $event)
    {
        // 获取发帖用户和讨论信息
        $user = $event->actor;
        $discussionAuthor = $event->post->discussion->user; // 被回复的讨论作者
        $discussionTitle = $event->post->discussion->title;

        // 发帖类型判断：新帖或回帖
        if ($event->post['number'] === 1) {
            // 新帖逻辑
            $this->handleNewPost($user, $event->post, $discussionTitle);
            // 处理额外积分
            $this->handleExtraPoints($user, $event->post->content, 'post');
        } else {
            // 回帖逻辑
            $this->handleReplyPost($user, $discussionAuthor, $event->post, $discussionTitle);
            // 处理额外积分
            $this->handleExtraPoints($user, $event->post->content, 'reply');
        }
    }

    /**
     * 处理新帖的积分变动
     */
    private function handleNewPost($user, $post, $discussionTitle)
    {
        // 获取新帖积分设置
        $moneyForPost = (float)$this->settings->get('shebaoting-money.moneyforpost', 0);
        $typeForPost = $this->settings->get('shebaoting-money.moneyforpost_type', 'deduct'); // 'reward' or 'deduct'

        // 确定积分变动金额
        $amount = ($typeForPost === 'deduct') ? -abs($moneyForPost) : abs($moneyForPost);

        // 检查是否有足够的积分
        if ($amount < 0 && $user->money < abs($amount)) {
            throw new ValidationException([
                'money' => app()->translator->trans('shebaoting-money.forum.errors.not_enough_money')
            ]);
        }

        // 生成讨论的 URL
        $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $post->discussion->id, 'slug' => $post->discussion->slug]);

        // 创建描述
        $description = app()->translator->trans('shebaoting-money.forum.logs_description.post_created', [
            'title' => $discussionTitle,
            'url' => $discussionUrl
        ]);

        // 扣除或奖励积分
        $this->giveMoney($user, $amount, ($typeForPost === 'deduct') ? 'post_deduct' : 'post_reward', $description, null, $post, $post->discussion);
    }

    /**
     * 处理回帖的积分变动
     */
    private function handleReplyPost($user, $discussionAuthor, $post, $discussionTitle)
    {
        // 获取回帖积分设置
        $minimumLength = (int)$this->settings->get('shebaoting-money.postminimumlength', 0);
        $moneyForReply = (float)$this->settings->get('shebaoting-money.moneyforreply', 0);
        $typeForReply = $this->settings->get('shebaoting-money.moneyforreply_type', 'deduct'); // 'reward' or 'deduct'
        $feedback = $this->settings->get('shebaoting-money.moneyforreply_feedback', 'no_feedback'); // 'feedback' or 'no_feedback'

        // 确认内容长度满足要求
        if (mb_strlen(strip_tags($post->content)) >= $minimumLength) {
            // 确定扣除或奖励金额
            $amount = ($typeForReply === 'deduct') ? -abs($moneyForReply) : abs($moneyForReply);

            // 检查是否有足够的积分
            if ($amount < 0 && $user->money < abs($amount)) {
                throw new ValidationException([
                    'money' => app()->translator->trans('shebaoting-money.forum.errors.not_enough_money')
                ]);
            }

            // 生成讨论的 URL
            $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $post->discussion->id, 'slug' => $post->discussion->slug]);

            // 创建描述
            $description = app()->translator->trans('shebaoting-money.forum.logs_description.posted_reply', [
                'title' => $discussionTitle,
                'url' => $discussionUrl
            ]);

            // 扣除或奖励回帖用户的积分，并记录 target_user_id 为讨论作者
            $this->giveMoney($user, $amount, ($typeForReply === 'deduct') ? 'withdrawal' : 'post_reward', $description, $discussionAuthor, $post, $post->discussion);

            // 如果启用了反馈，给被回复的作者增加积分
            if ($feedback === 'feedback' && $user->id !== $discussionAuthor->id) {
                $feedbackAmount = abs($moneyForReply);

                // 创建反馈描述
                $feedbackDescription = app()->translator->trans('shebaoting-money.forum.logs_description.reply_received', [
                    'username' => $user->username,
                    'title' => $discussionTitle,
                    'url' => $discussionUrl
                ]);

                // 给被回复的作者增加积分，设置 target_user_id 为回帖用户
                $this->giveMoney($discussionAuthor, $feedbackAmount, 'reply_received', $feedbackDescription, $user, $post, $post->discussion);
            }
        }
    }

    /**
     * 当帖子被恢复时，如果之前因为隐藏被扣除金额，则恢复金额
     */
    public function postWasRestored(PostRestored $event)
    {
        if ($this->autoremove == AutoRemoveEnum::HIDDEN) {
            $minimumLength = (int)$this->settings->get('shebaoting-money.postminimumlength', 0);
            $moneyForReply = (float)$this->settings->get('shebaoting-money.moneyforreply', 0);
            $typeForReply = $this->settings->get('shebaoting-money.moneyforreply_type', 'deduct'); // 'reward' or 'deduct'

            if (mb_strlen(strip_tags($event->post->content)) >= $minimumLength) {
                // 恢复金额与扣除金额相反
                $amount = ($typeForReply === 'deduct') ? abs($moneyForReply) : -abs($moneyForReply);

                // 生成讨论的 URL
                $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->post->discussion->id, 'slug' => $event->post->discussion->slug]);

                $description = app()->translator->trans('shebaoting-money.forum.logs_description.post_restored', [
                    'title' => $event->post->discussion->title,
                    'url' => $discussionUrl
                ]);

                $this->giveMoney($event->post->user, $amount, 'post_restored', $description, null, $event->post, $event->post->discussion);
            }
        }
    }

    /**
     * 当帖子被隐藏时，扣除用户相应的金额
     */
    public function postWasHidden(PostHidden $event)
    {
        if ($this->autoremove == AutoRemoveEnum::HIDDEN) {
            $minimumLength = (int)$this->settings->get('shebaoting-money.postminimumlength', 0);
            $moneyForReply = (float)$this->settings->get('shebaoting-money.moneyforreply', 0);
            $typeForReply = $this->settings->get('shebaoting-money.moneyforreply_type', 'deduct'); // 'reward' or 'deduct'

            if (mb_strlen(strip_tags($event->post->content)) >= $minimumLength) {
                // 确定扣除的金额
                $amount = ($typeForReply === 'deduct') ? -abs($moneyForReply) : abs($moneyForReply);

                // 生成讨论的 URL
                $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->post->discussion->id, 'slug' => $event->post->discussion->slug]);

                $description = app()->translator->trans('shebaoting-money.forum.logs_description.post_hidden', [
                    'title' => $event->post->discussion->title,
                    'url' => $discussionUrl
                ]);

                $this->giveMoney($event->post->user, $amount, 'post_hidden', $description, null, $event->post, $event->post->discussion);
            }
        }
    }

    /**
     * 当帖子被删除时，扣除用户相应的金额
     */
    public function postWasDeleted(PostDeleted $event)
    {
        if ($this->autoremove == AutoRemoveEnum::DELETED && $event->post->type == 'comment') {
            $minimumLength = (int)$this->settings->get('shebaoting-money.postminimumlength', 0);
            $moneyForReply = (float)$this->settings->get('shebaoting-money.moneyforreply', 0);
            $typeForReply = $this->settings->get('shebaoting-money.moneyforreply_type', 'deduct'); // 'reward' or 'deduct'

            if (mb_strlen(strip_tags($event->post->content)) >= $minimumLength) {
                // 确定扣除的金额
                $amount = ($typeForReply === 'deduct') ? -abs($moneyForReply) : abs($moneyForReply);

                // 生成讨论的 URL
                $discussion = $event->post->discussion;
                $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $discussion->id, 'slug' => $discussion->slug]);

                $description = app()->translator->trans('shebaoting-money.forum.logs_description.post_deleted', [
                    'title' => $discussion->title,
                    'url' => $discussionUrl
                ]);

                $this->giveMoney($event->post->user, $amount, 'post_deleted', $description, null, $event->post, $event->post->discussion);
            }
        }
    }

    /**
     * 当讨论被发起时，奖励或扣除发起人的金额
     */
    public function discussionWasStarted(Started $event)
    {
        // 不处理自己发起的讨论
        if ($event->actor->id === $event->discussion->user->id) {
            return;
        }

        $moneyForDiscussion = (float)$this->settings->get('shebaoting-money.moneyfordiscussion', 0);
        $typeForDiscussion = $this->settings->get('shebaoting-money.moneyfordiscussion_type', 'reward'); // 'reward' or 'deduct'

        // 确定金额的正负
        $amount = ($typeForDiscussion === 'deduct') ? -abs($moneyForDiscussion) : abs($moneyForDiscussion);

        if ($amount < 0 && $event->actor->money < abs($amount)) {
            throw new ValidationException([
                'money' => app()->translator->trans('shebaoting-money.forum.errors.not_enough_money')
            ]);
        }

        // 生成讨论的 URL
        $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->discussion->id, 'slug' => $event->discussion->slug]);

        $description = app()->translator->trans('shebaoting-money.forum.logs_description.discussion_started', [
            'title' => $event->discussion->title,
            'url' => $discussionUrl
        ]);

        $this->giveMoney($event->actor, $amount, ($typeForDiscussion === 'deduct') ? 'discussion_deduct' : 'discussion_reward', $description, null, null, $event->discussion);
    }

    /**
     * 当讨论被恢复时，如果之前因为隐藏被扣除金额，则恢复金额
     */
    public function discussionWasRestored(DiscussionRestored $event)
    {
        if ($this->autoremove == AutoRemoveEnum::HIDDEN) {
            $moneyForDiscussion = (float)$this->settings->get('shebaoting-money.moneyfordiscussion', 0);
            $typeForDiscussion = $this->settings->get('shebaoting-money.moneyfordiscussion_type', 'reward'); // 'reward' or 'deduct'

            // 恢复金额与扣除金额相反
            $amount = ($typeForDiscussion === 'deduct') ? abs($moneyForDiscussion) : -abs($moneyForDiscussion);

            // 生成讨论的 URL
            $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->discussion->id, 'slug' => $event->discussion->slug]);

            $description = app()->translator->trans('shebaoting-money.forum.logs_description.discussion_restored', [
                'title' => $event->discussion->title,
                'url' => $discussionUrl
            ]);

            $this->giveMoney($event->discussion->user, $amount, 'discussion_restored', $description, null, null, $event->discussion);
        }
    }

    /**
     * 当讨论被隐藏时，扣除用户相应的金额
     */
    public function discussionWasHidden(DiscussionHidden $event)
    {
        if ($this->autoremove == AutoRemoveEnum::HIDDEN) {
            $moneyForDiscussion = (float)$this->settings->get('shebaoting-money.moneyfordiscussion', 0);
            $typeForDiscussion = $this->settings->get('shebaoting-money.moneyfordiscussion_type', 'deduct'); // 'reward' or 'deduct'

            // 确定扣除的金额
            $amount = ($typeForDiscussion === 'deduct') ? -abs($moneyForDiscussion) : abs($moneyForDiscussion);

            // 生成讨论的 URL
            $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->discussion->id, 'slug' => $event->discussion->slug]);

            $description = app()->translator->trans('shebaoting-money.forum.logs_description.discussion_hidden', [
                'title' => $event->discussion->title,
                'url' => $discussionUrl
            ]);

            $this->giveMoney($event->discussion->user, $amount, 'discussion_hidden', $description, null, null, $event->discussion);
        }
    }

    /**
     * 当讨论被删除时，扣除用户相应的金额
     */
    public function discussionWasDeleted(DiscussionDeleted $event)
    {
        if ($this->autoremove == AutoRemoveEnum::DELETED) {
            $moneyForDiscussion = (float)$this->settings->get('shebaoting-money.moneyfordiscussion', 0);
            $typeForDiscussion = $this->settings->get('shebaoting-money.moneyfordiscussion_type', 'deduct'); // 'reward' or 'deduct'

            // 确定扣除的金额
            $amount = ($typeForDiscussion === 'deduct') ? -abs($moneyForDiscussion) : abs($moneyForDiscussion);

            // 生成讨论的 URL
            $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->discussion->id, 'slug' => $event->discussion->slug]);

            $description = app()->translator->trans('shebaoting-money.forum.logs_description.discussion_deleted', [
                'title' => $event->discussion->title,
                'url' => $discussionUrl
            ]);

            $this->giveMoney($event->discussion->user, $amount, 'discussion_deleted', $description, null, null, $event->discussion);
        }
    }

    /**
     * 当用户信息保存时，检查并处理金钱变动
     */
    public function userWillBeSaved(SavingUser $event)
    {
        $attributes = Arr::get($event->data, 'attributes', []);

        if (array_key_exists('money', $attributes)) {
            $user = $event->user;
            $actor = $event->actor;
            $oldMoney = (float) $user->getOriginal('money');

            // 检查管理员是否有权限编辑积分
            $actor->assertCan('edit_money', $user);

            // 获取新积分值
            $newMoney = (float)$attributes['money'];

            // 计算积分变动量
            $amount = $newMoney - $oldMoney;

            // 根据amount的正负值前面加上 '+' 或者 '-' 符号
            $formattedAmount = ($amount > 0 ? '+' : '') . $amount;

            // 创建描述
            $description = app()->translator->trans('shebaoting-money.forum.logs_description.admin_updated', [
                'amount' => $formattedAmount
            ]);

            // 记录日志，描述为管理员直接设置的积分值
            $this->giveMoney($user, $amount, 'admin_updated', $description, null, null, null);
        }
    }

    /**
     * 当用户注册时，给予初始积分
     */
    public function userWasRegistered(Registered $event)
    {
        $user = $event->user;
        $initialMoney = (float)$this->settings->get('shebaoting-money.initialmoney', 0);

        if ($initialMoney != 0) {
            $description = app()->translator->trans('shebaoting-money.forum.logs_description.initial_registration');

            $this->giveMoney($user, $initialMoney, 'initial_registration', $description, null, null, null);
        }
    }

    /**
     * 当帖子被点赞时，奖励或扣除用户相应的金额
     */
    public function postWasLiked(PostWasLiked $event)
    {
        // 不处理自己点赞自己
        if ($event->user->id === $event->post->user->id) {
            return;
        }

        $moneyForLike = (float)$this->settings->get('shebaoting-money.moneyforlike', 0);
        $typeForLike = $this->settings->get('shebaoting-money.moneyforlike_type', 'reward'); // 'reward' or 'deduct'
        $feedbackLike = $this->settings->get('shebaoting-money.moneyforlike_feedback', 'no_feedback'); // 'feedback' or 'no_feedback'

        $user = $event->user; // 点赞的用户
        $postAuthor = $event->post->user; // 被点赞的帖子作者
        $discussionTitle = $event->post->discussion->title;

        // 确定金额的正负
        $amount = ($typeForLike === 'deduct') ? -abs($moneyForLike) : abs($moneyForLike);

        if ($typeForLike === 'deduct') {
            if ($user->money < abs($moneyForLike)) {
                throw new ValidationException([
                    'money' => app()->translator->trans('shebaoting-money.forum.errors.not_enough_money')
                ]);
            }
        }

        // 生成讨论的 URL
        $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->post->discussion->id, 'slug' => $event->post->discussion->slug]);

        // 给予点赞的用户奖励或扣除
        $action = ($typeForLike === 'deduct') ? 'liked_deduct' : 'liked_reward';
        $description = app()->translator->trans('shebaoting-money.forum.logs_description.liked', [
            'title' => $discussionTitle,
            'url' => $discussionUrl,
            'user' => $postAuthor->username
        ]);
        $this->giveMoney($user, $amount, $action, $description, $postAuthor, $event->post, $event->post->discussion);

        // 如果设置为回馈作者，且点赞用户不是作者本人
        if ($feedbackLike === 'feedback' && $user->id !== $postAuthor->id) {
            // 回馈金额始终为正数
            $feedbackAmount = abs($moneyForLike);

            // 创建反馈描述
            $feedbackDescription = app()->translator->trans('shebaoting-money.forum.logs_description.liked_by', [
                'username' => $user->username,
                'title' => $discussionTitle,
                'url' => $discussionUrl
            ]);

            // 给被点赞的作者增加积分
            $this->giveMoney($postAuthor, $feedbackAmount, 'liked_by', $feedbackDescription, $user, $event->post, $event->post->discussion);
        }
    }

    /**
     * 当帖子取消点赞时，撤销积分变动
     */
    public function postWasUnliked(PostWasUnliked $event)
    {
        $moneyForLike = (float)$this->settings->get('shebaoting-money.moneyforlike', 0);
        $typeForLike = $this->settings->get('shebaoting-money.moneyforlike_type', 'reward'); // 'reward' or 'deduct'
        $feedbackLike = $this->settings->get('shebaoting-money.moneyforlike_feedback', 'no_feedback'); // 'feedback' or 'no_feedback'

        $user = $event->user; // 取消点赞的用户
        $postAuthor = $event->post->user; // 被点赞的帖子作者
        $discussionTitle = $event->post->discussion->title;

        // 生成讨论的 URL
        $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $event->post->discussion->id, 'slug' => $event->post->discussion->slug]);

        if ($typeForLike === 'deduct') {
            // 撤销扣除，即返还积分
            $amount = abs($moneyForLike);
            $action = 'unliked_refund';
            $description = app()->translator->trans('shebaoting-money.forum.logs_description.unliked_post', [
                'title' => $discussionTitle,
                'url' => $discussionUrl,
                'user' => $postAuthor->username
            ]);
            $this->giveMoney($user, $amount, $action, $description, $postAuthor, $event->post, $event->post->discussion);

            // 如果设置为回馈作者，且点赞用户不是作者本人
            if ($feedbackLike === 'feedback' && $user->id !== $postAuthor->id) {
                // 撤销回馈给作者的积分
                $feedbackAmount = abs($moneyForLike);

                // 创建反馈描述
                $feedbackDescription = app()->translator->trans('shebaoting-money.forum.logs_description.unliked_by', [
                    'username' => $user->username,
                    'title' => $discussionTitle,
                    'url' => $discussionUrl
                ]);

                $this->giveMoney($postAuthor, -$feedbackAmount, 'unliked_by', $feedbackDescription, $user, $event->post, $event->post->discussion);
            }
        } else {
            // 如果是奖励，则撤销奖励，即扣除积分
            $amount = -abs($moneyForLike);
            $action = 'unliked_deduct';
            $description = app()->translator->trans('shebaoting-money.forum.logs_description.unliked_by', [
                'title' => $discussionTitle,
                'url' => $discussionUrl,
                'user' => $user->username
            ]);
            $this->giveMoney($postAuthor, $amount, $action, $description, $user, $event->post, $event->post->discussion);
        }
    }
}
