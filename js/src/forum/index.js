import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserCard from 'flarum/forum/components/UserCard';
import UserControls from 'flarum/forum/utils/UserControls';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import UserMoneyModal from './components/UserMoneyModal';
import UserPage from 'flarum/forum/components/UserPage';

export { default as extend } from './extend';

function moneyValue(user) {
  return Number(user.money ? user.money() : user.data?.attributes?.money || 0);
}

app.initializers.add('shebaoting-money', () => {
  extend(UserCard.prototype, 'infoItems', function (items) {
    const money = moneyValue(this.attrs.user);

    if (money === 0 && app.forum.attribute('shebaoting-money.noshowzero')) return;

    const moneyName = app.forum.attribute('shebaoting-money.moneyname') || '[money]';
    const levelNames = (app.forum.attribute('shebaoting-money.level_names') || '').split(',').filter(Boolean);
    const scale = parseInt(app.forum.attribute('shebaoting-money.money_scale') || '100', 10);

    if (levelNames.length > 1 && scale > 1) {
      let remainingMoney = money;
      const levelValues = [];

      for (let i = 0; i < levelNames.length; i++) {
        const divisor = Math.pow(scale, levelNames.length - 1 - i);
        const levelValue = Math.floor(remainingMoney / divisor);
        remainingMoney %= divisor;

        levelValues.push(m('span', { style: { marginRight: '15px' } }, `${levelNames[i]} ${levelValue}`));
      }

      items.add('money', m('div', levelValues));
    } else {
      items.add('money', m('span', moneyName.replace('[money]', money)));
    }
  });

  extend(UserPage.prototype, 'navItems', function (items) {
    const user = this.user;

    if (app.session.user && user && app.session.user.id() === user.id()) {
      items.add(
        'moneyLogs',
        <LinkButton href={app.route('user.money-log', { username: user.slug() })} icon="fas fa-coins">
          {app.translator.trans('shebaoting-money.forum.point_log.link')}
        </LinkButton>,
        10
      );
    }
  });

  extend(UserControls, 'moderationControls', (items, user) => {
    if (user.canEditMoney && user.canEditMoney()) {
      items.add(
        'money',
        <Button icon="fas fa-money-bill" onclick={() => app.modal.show(UserMoneyModal, { user })}>
          {app.translator.trans('shebaoting-money.forum.user_controls.money_button')}
        </Button>
      );
    }
  });
});
