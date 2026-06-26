# Money

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/shebaoting/flarum-money.svg)](https://packagist.org/packages/shebaoting/flarum-money) [![Total Downloads](https://img.shields.io/packagist/dt/shebaoting/flarum-money.svg)](https://packagist.org/packages/shebaoting/flarum-money)

A [Flarum](https://flarum.org) extension that adds a configurable virtual currency and point ledger to your community.

> Looking for Chinese documentation? Read the [中文介绍](README.zh-Hans.md).

Money lets a community reward, charge, redistribute, and audit points around common forum actions such as posting, replying, liking, registration, and moderator edits. It is designed for communities that want points to represent attention, contribution, participation cost, or other internal value.

This extension is based on [flarum-ext-money by AntoineFr](https://github.com/AntoineFr/flarum-ext-money). Thanks to the original author for the excellent foundation. This fork keeps the original idea and adds more opinionated logging, redistribution, and Flarum 2.x compatibility features.

## Compatibility

- Flarum: `^2.0.0-beta`
- PHP: `^8.3`
- Requires `flarum/likes` `^2.0@beta`
- Current release line: `2.x`
- Flarum 1.x users should stay on the `0.x` release line.

## Features

### Virtual Currency

- Adds a `money` balance to every Flarum user.
- Displays the balance on user cards.
- Supports a custom display template, such as `[money] points`.
- Can hide zero balances from the forum UI.

### Point Ledger

- Records every balance change in the `money_logs` table.
- Stores the changed amount, resulting balance, action type, reason, related user, post, discussion, and timestamps.
- Adds a "Point Records" page under the signed-in user's profile.
- Supports paginated log loading.
- Links log reasons back to related discussions and posts when possible.
- Restricts the front-end log page and API listing to the current authenticated user.

### Registration Bonus

- Grants new users an initial point balance when they register.
- Records the registration bonus in the ledger.

### Posting And Reply Rules

- Configures a point amount for creating a new discussion post.
- Configures a separate point amount for replies.
- Each action can be either a reward or a deduction.
- Prevents an action when a deduction would make the actor spend more points than they have.
- Supports a minimum post length for reply rewards or deductions.

### Reply Feedback

- Replies can optionally give points back to the discussion author.
- Useful for communities where replying costs the replier but rewards the author who created the discussion.
- Self-feedback is ignored so users do not reward themselves through their own content.

### Like Rewards And Costs

- Configures point changes when a user likes a post.
- Like actions can be rewards or deductions.
- Like deductions can optionally be redistributed to the post author.
- Self-likes are ignored.
- Unliking reverses the configured point effect, including author feedback when enabled.

### Extra Character Rules

- Adds an optional rule for longer content.
- After a configurable character threshold, every additional character increment can trigger extra points.
- Extra points follow the configured reward or deduction direction for posts or replies.
- Extra character changes are also written to the point ledger.

### Moderation And Manual Editing

- Adds an `Edit money` permission.
- Authorized moderators can edit a user's balance from user controls.
- Manual balance changes are logged with the delta and resulting balance.

### Auto Adjustment On Hidden, Restored, Or Deleted Content

- Can automatically adjust balances when discussions or posts are hidden/restored.
- Can instead wait until discussions or posts are deleted.
- Can be disabled entirely.
- These automatic changes are recorded with the related post or discussion context.

### Multi-Level Display

- Supports comma-separated money level names, such as `Gold,Silver,Bronze`.
- Supports a configurable scale, such as `100`, to split one numeric balance into multiple display units.
- Example: a balance can be shown as several named levels instead of a single raw number.

## Admin Settings

| Setting | Description |
| --- | --- |
| Money name | Display template for balances. Use `[money]` as the numeric placeholder. |
| Initial money | Points granted to new users on registration. |
| Level names | Optional comma-separated labels for multi-level display. |
| Money scale | Base used to split balances across level names. |
| New post amount | Amount applied when a user creates the first post in a discussion. |
| Reply amount | Amount applied when a user replies to a discussion. |
| Like amount | Amount applied when a user likes a post. |
| Change type | Choose reward or deduction for post, reply, and like rules. |
| Feedback | Choose whether reply/like deductions reward the original author. |
| Character threshold | Minimum content length before extra character points apply. |
| Character increment | Number of extra characters per additional point change. |
| Points per increment | Points applied for each extra character increment. |
| Auto update money | Choose how hidden/restored/deleted content affects balances. |
| Hide zero | Hide zero balances on user cards. |

## Installation

Install with Composer:

```sh
composer require shebaoting/flarum-money:"^2.0"
```

Run migrations and clear the Flarum cache:

```sh
php flarum migrate
php flarum cache:clear
```

Then enable the extension in the Flarum admin panel and configure the permission and point rules.

## Updating

```sh
composer update shebaoting/flarum-money:"^2.0"
php flarum migrate
php flarum cache:clear
```

If your forum still requires the old package name, switch the Composer requirement first:

```sh
composer remove shebaoting/money --no-update
composer require shebaoting/flarum-money:"^2.0" -W
```

When upgrading from `0.x` to `2.x`, make sure your forum is already running Flarum 2.x beta and PHP 8.3 or newer.

## Development

Install JavaScript dependencies and build the front-end assets from the `js` directory:

```sh
cd js
yarn install
yarn build
```

Validate the Composer package from the extension root:

```sh
composer validate
```

## Philosophy

The design philosophy behind this system is a metaphor for time.

Everything you do in a community consumes time. If a contribution receives no meaningful response, it can feel like the time was spent in vain. A point system can make that invisible cost and feedback loop visible: creating, replying, and liking can all represent attention moving between people.

For a similar concept, I recommend watching the movie _In Time_.

## Contact

You can contact me if you wish to fund or discuss Flarum extension development.

Email: th9th@th9th.com

## Links

- [My Community](https://wyz.xyz)
- [Packagist](https://packagist.org/packages/shebaoting/flarum-money)
- [GitHub](https://github.com/shebaoting/flarum-money)
