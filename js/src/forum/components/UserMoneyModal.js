import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';

export default class UserMoneyModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);

    this.money = Stream(String(this.attrs.user.money ? this.attrs.user.money() : 0));
  }

  className() {
    return 'UserMoneyModal Modal--small';
  }

  title() {
    return app.translator.trans('shebaoting-money.forum.modal.title', { username: this.attrs.user.displayName() });
  }

  content() {
    const moneyName = app.forum.attribute('shebaoting-money.moneyname') || '[money]';
    const currentMoney = this.attrs.user.money ? this.attrs.user.money() : 0;

    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>
              {app.translator.trans('shebaoting-money.forum.modal.current')} {moneyName.replace('[money]', currentMoney)}
            </label>
            <input required className="FormControl" type="number" step="any" bidi={this.money} />
          </div>
          <div className="Form-group">
            <Button className="Button Button--primary" type="submit" loading={this.loading}>
              {app.translator.trans('shebaoting-money.forum.modal.submit_button')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();

    this.loading = true;

    this.attrs.user
      .save({ money: Number(this.money()) }, { errorHandler: this.onerror.bind(this) })
      .then(this.hide.bind(this))
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
