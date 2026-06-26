import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import Button from 'flarum/common/components/Button';
import app from 'flarum/forum/app';

function legacyReasonContent(reason) {
  if (!reason) return '';

  const parts = [];
  const anchorPattern = /<a\s+href="([^"]+)">([^<]*)<\/a>/gi;
  let lastIndex = 0;
  let match;

  while ((match = anchorPattern.exec(reason)) !== null) {
    if (match.index > lastIndex) {
      parts.push(reason.slice(lastIndex, match.index));
    }

    parts.push(
      <a href={match[1]} target="_blank" rel="noopener noreferrer">
        {match[2]}
      </a>
    );

    lastIndex = anchorPattern.lastIndex;
  }

  if (lastIndex < reason.length) {
    parts.push(reason.slice(lastIndex));
  }

  return parts.length ? parts : reason;
}

function discussionSlug(discussion) {
  if (!discussion) return null;

  const id = String(discussion.id || '');
  const slug = String(discussion.slug || '');

  if (!id) return null;

  return slug && !slug.startsWith(`${id}-`) ? `${id}-${slug}` : slug || id;
}

function discussionHref(log) {
  const discussion = log.discussion();
  const slug = discussionSlug(discussion);

  if (!slug) return null;

  const post = log.post();
  const near = post && post.number && post.number > 1 ? post.number : null;

  return app.route(near ? 'discussion.near' : 'discussion', {
    id: slug,
    near,
  });
}

function reasonContent(log) {
  const reason = log.reason() || '';

  if (/<a\s/i.test(reason)) {
    return legacyReasonContent(reason);
  }

  const discussion = log.discussion();
  const title = discussion && discussion.title;
  const href = title && discussionHref(log);

  if (!title || !href) return reason;

  const titleIndex = reason.indexOf(title);

  if (titleIndex === -1) {
    return <a href={href}>{title}</a>;
  }

  const before = reason.slice(0, titleIndex);
  const after = reason.slice(titleIndex + title.length).replace(/^\s*[（(]\s*https?:\/\/[^）)]+[）)]/, '');

  return [
    before,
    <a href={href}>{title}</a>,
    after,
  ];
}

export default class UserMoneyLogPage extends UserPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.logs = [];
    this.page = 0;
    this.moreResults = true;

    if (!app.session.user) {
      app.alerts.show({ type: 'error' }, app.translator.trans('core.forum.error.not_authenticated'));
      m.route.set(app.route('index'));
      return;
    }

    this.loadUser(m.route.param('username'))
      .then(() => {
        if (!this.user || app.session.user.id() !== this.user.id()) {
          app.alerts.show({ type: 'error' }, app.translator.trans('shebaoting-money.forum.point_log.no_permission'));
          m.route.set(app.route.user(app.session.user));
          return;
        }

        this.loadLogs();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  loadLogs() {
    if (!this.moreResults) return;

    this.loading = true;

    app.store
      .find('money-logs', {
        page: {
          offset: this.page * 20,
          limit: 20,
        },
      })
      .then((logs) => {
        this.logs = this.logs.concat(logs);
        this.loading = false;
        this.page++;

        if (logs.length < 20) {
          this.moreResults = false;
        }

        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  content() {
    if (this.loading && this.logs.length === 0) {
      return <LoadingIndicator />;
    }

    if (this.logs.length === 0) {
      return <Placeholder text={app.translator.trans('shebaoting-money.forum.point_log.empty_text')} />;
    }

    return (
      <div className="UserMoneyLogPage">
        <div className="UserMoneyLogPage-content">
          <table className="NotificationGrid">
            <thead>
              <tr>
                <th>
                  <i aria-hidden="true" className="icon fas fa-clock"></i>
                  {app.translator.trans('shebaoting-money.forum.point_log.table.headers.date')}
                </th>
                <th>
                  <i aria-hidden="true" className="icon fas fa-list"></i>
                  {app.translator.trans('shebaoting-money.forum.point_log.table.headers.amount')}
                </th>
                <th>
                  <i aria-hidden="true" className="icon fas fa-coins"></i>
                  {app.translator.trans('shebaoting-money.forum.point_log.table.headers.balance')}
                </th>
                <th>
                  <i aria-hidden="true" className="icon fas fa-info-circle"></i>
                  {app.translator.trans('shebaoting-money.forum.point_log.table.headers.reason')}
                </th>
              </tr>
            </thead>
            <tbody>
              {this.logs.map((log) => {
                const createdAt = log.createdAt() ? log.createdAt().toLocaleString() : '';

                return (
                  <tr key={log.id()}>
                    <td>{createdAt}</td>
                    <td>{log.amount()}</td>
                    <td>{log.balance()}</td>
                    <td className="MoneyLogReason">{reasonContent(log)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          {this.moreResults && (
            <div className="UserMoneyLogPage-loadMore">
              <Button className="Button Button--primary" loading={this.loading} onclick={() => this.loadLogs()}>
                {app.translator.trans('shebaoting-money.forum.point_log.load_more')}
              </Button>
            </div>
          )}
        </div>
      </div>
    );
  }
}
