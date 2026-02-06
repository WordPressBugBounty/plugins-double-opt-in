<?php
/**
 * @var OptIn[] $list
 * @var string  $hash
 */

use forge12\contactform7\CF7DoubleOptIn\OptIn;

$settings = \forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn::getInstance()->getSettings();
$manual_delete_value = isset($settings['delete_unconfirmed']) ? $settings['delete_unconfirmed'] : 1;
$manual_delete_period = isset($settings['delete_unconfirmed_period']) ? $settings['delete_unconfirmed_period'] : 'months';
$admin_email = get_option('admin_email');

?>
<div class="f12-cf7-doubleoptin-optout f12-cf7-doubleoptin-optout-list">
    <div class="f12-cf7-doubleoptin-optout-list--inner">
        <table>
            <tr>
                <th>
                    <?php _e('Hash', 'double-opt-in'); ?>
                </th>
                <th>
                    <?php _e('Date', 'double-opt-in'); ?>
                </th>
                <th>
                    <?php _e('Status', 'double-opt-in'); ?>
                </th>
                <th>
                    <?php _e('Valid until', 'double-opt-in'); ?>*
                </th>
                <th>

                </th>
            </tr>
            <?php foreach ($list as $OptIn): ?>
                <tr>
                    <td>
                        <?php echo esc_html($OptIn->get_hash()); ?>
                    </td>
                    <td>
                        <?php echo esc_html($OptIn->get_createtime('view')); ?>
                    </td>
                    <td>
                        <?php if ($OptIn->is_confirmed()): ?>
                            <div class="dashicons dashicons-yes"></div>
                        <?php else: ?>
                            <div class="dashicons dashicons-no"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($settings['delete'] == 0 || $settings['delete_unconfirmed'] == 0): ?>
                            <center><?php _e('&infin;'); ?></center>
                        <?php else: ?>
                            <?php echo esc_html($OptIn->get_valid_until()); ?>
                        <?php endif; ?>

                        <?php if ($OptIn->is_confirmed() && $settings['delete'] != 0): ?>
                            <small>(<?php echo esc_html($settings['delete']); ?> <?php echo esc_html($settings['delete_period']); ?>
                                )</small>
                        <?php endif; ?>
                        <?php if (!$OptIn->is_confirmed() && $settings['delete_unconfirmed'] != 0): ?>
                            <small>(<?php echo esc_html($settings['delete_unconfirmed']); ?> <?php echo esc_html($settings['delete_unconfirmed_period']); ?>
                                )</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($OptIn->is_confirmed()): ?>
                            <a href="<?php echo esc_url( $OptIn->get_link_optout() . '&hash=' . urlencode( $hash ) ); ?>" target="_blank"
                               class="button"><?php _e('Opt-Out now!', 'double-opt-in'); ?></a>
                        <?php else: /*
                            <a href="<?php echo $OptIn->get_link_optin(); ?>&hash=<?php echo $hash; ?>" target="_blank"
                               class="button"><?php _e('Opt-In now!', 'double-opt-in'); ?></a>
                        */ endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <span class="hint">* <?php _e('The date indicates when your personal data will be deleted.', 'double-opt-in'); ?></span>
    <span class="hint">*
        <?php if ($manual_delete_value == 0): ?>
            <?php printf(__('Please note that even with manual opt-out, your data will be stored. Please contact the administrator to delete your personal data: %s.', 'double-opt-in'), $admin_email); ?>
        <?php else: ?>
            <?php printf(__('Please note that even with manual opt-out, your data will only be deleted in %d %s.', 'double-opt-in'), $manual_delete_value, $manual_delete_period); ?>
        <?php endif; ?>
    </span>
</div>