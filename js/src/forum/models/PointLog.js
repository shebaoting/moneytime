import Model from 'flarum/common/Model';

export default class PointLog extends Model {
  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  balance() {
    return Model.attribute('balance').call(this);
  }

  amount() {
    return Model.attribute('amount').call(this);
  }

  reason() {
    return Model.attribute('reason').call(this);
  }

  action() {
    return Model.attribute('action').call(this);
  }

  targetUser() {
    return Model.attribute('targetUser').call(this);
  }

  post() {
    return Model.attribute('post').call(this);
  }

  discussion() {
    return Model.attribute('discussion').call(this);
  }
}
