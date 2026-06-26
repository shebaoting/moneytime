import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import Select from 'flarum/common/components/Select';

const settingsPrefix = 'shebaoting-money.admin.settings.';

function settingLabel(key) {
  return app.translator.trans(settingsPrefix + key);
}

function changeAmountSetting(amountKey, typeKey, feedbackKey, labelKey, helpKey) {
  return function () {
    const amount = this.setting(amountKey, '0', settingLabel(labelKey));
    const type = this.setting(typeKey, 'reward');
    const feedback = feedbackKey ? this.setting(feedbackKey, 'no_feedback') : null;

    return (
      <div className="Form-group">
        <label>{settingLabel(labelKey)}</label>
        <div className="MoneySettingRow">
          <input className="FormControl" type="number" min="0" step="any" bidi={amount} placeholder="0" />
          <Select
            className="FormControl"
            wrapperAttrs={{ className: 'MoneySettingSelect MoneySettingSelect--type' }}
            value={type()}
            options={{
              reward: settingLabel('reward'),
              deduct: settingLabel('deduct'),
            }}
            onchange={(value) => type(value)}
          />
          {feedback && (
            <Select
              className="FormControl"
              wrapperAttrs={{ className: 'MoneySettingSelect MoneySettingSelect--feedback' }}
              value={feedback()}
              options={{
                feedback: settingLabel('feedback'),
                no_feedback: settingLabel('no_feedback'),
              }}
              onchange={(value) => feedback(value)}
            />
          )}
        </div>
        <div className="helpText">{settingLabel(helpKey)}</div>
      </div>
    );
  };
}

export default [
  new Extend.Admin()
    .setting(
      () => ({
        setting: 'shebaoting-money.moneyname',
        label: settingLabel('moneyname'),
        type: 'text',
      }),
      100
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.initialmoney',
        label: settingLabel('initialmoney'),
        type: 'number',
        min: 0,
        help: settingLabel('initialmoney_help'),
      }),
      90
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.level_names',
        label: settingLabel('level_names'),
        type: 'text',
        help: settingLabel('level_names_help'),
        placeholder: '金豆,银豆,铜豆',
      }),
      80
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.money_scale',
        label: settingLabel('money_scale'),
        type: 'number',
        min: 1,
        help: settingLabel('money_scale_help'),
      }),
      70
    )
    .customSetting(
      changeAmountSetting(
        'shebaoting-money.moneyforpost',
        'shebaoting-money.moneyforpost_type',
        null,
        'moneyforpost_label',
        'moneyforpost_help'
      ),
      60
    )
    .customSetting(
      changeAmountSetting(
        'shebaoting-money.moneyforreply',
        'shebaoting-money.moneyforreply_type',
        'shebaoting-money.moneyforreply_feedback',
        'moneyforreply_label',
        'moneyforreply_help'
      ),
      50
    )
    .customSetting(
      changeAmountSetting(
        'shebaoting-money.moneyforlike',
        'shebaoting-money.moneyforlike_type',
        'shebaoting-money.moneyforlike_feedback',
        'moneyforlike_label',
        'moneyforlike_help'
      ),
      40
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.extra_char_threshold',
        label: settingLabel('extra_char_threshold_label'),
        type: 'number',
        min: 0,
        help: settingLabel('extra_char_threshold_help'),
      }),
      30
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.extra_char_increment',
        label: settingLabel('extra_char_increment_label'),
        type: 'number',
        min: 1,
        help: settingLabel('extra_char_increment_help'),
      }),
      20
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.extra_char_points',
        label: settingLabel('extra_char_points_label'),
        type: 'number',
        step: 'any',
        help: settingLabel('extra_char_points_help'),
      }),
      10
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.autoremove',
        label: settingLabel('autoremove'),
        type: 'select',
        options: {
          0: app.translator.trans('shebaoting-money.admin.autoremove.0'),
          1: app.translator.trans('shebaoting-money.admin.autoremove.1'),
          2: app.translator.trans('shebaoting-money.admin.autoremove.2'),
        },
        default: '1',
      }),
      0
    )
    .setting(
      () => ({
        setting: 'shebaoting-money.noshowzero',
        label: settingLabel('noshowzero'),
        type: 'checkbox',
      }),
      -10
    )
    .permission(
      () => ({
        icon: 'fas fa-money-bill',
        label: app.translator.trans('shebaoting-money.admin.permissions.edit_money_label'),
        permission: 'user.edit_money',
      }),
      'moderate'
    ),
];
