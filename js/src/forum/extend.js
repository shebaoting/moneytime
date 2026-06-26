import Extend from 'flarum/common/extenders';
import User from 'flarum/common/models/User';
import UserPageResolver from 'flarum/forum/resolvers/UserPageResolver';
import PointLog from './models/PointLog';
import UserMoneyLogPage from './components/UserMoneyLogPage';

export default [
  new Extend.Routes().add('user.money-log', '/u/:username/money-log', UserMoneyLogPage, UserPageResolver),

  new Extend.Store().add('money-logs', PointLog),

  new Extend.Model(User).attribute('money').attribute('canEditMoney'),
];
