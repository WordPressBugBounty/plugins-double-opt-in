<?php
/**
 * @var string $nonce
 */
?>
<div class="f12-cf7-doubleoptin-optout-form">
    <div class="f12-cf7-doubleoptin-optout-form--inner">
        <form action="" method="post">
            <h3 for="optout-email"><?php _e('Opt-Out', 'double-opt-in'); ?></h3>
            <label><?php _e('E-Mail', 'double-opt-in'); ?></label>
            <input type="email" id="optout-email" name="email" value=""
                   placeholder="<?php _e('max.mustermann@domain.de', 'double-opt-in'); ?>">
            <input type="submit" name="optout" value="<?php _e('Submit', 'double-opt-in'); ?>"/>
            <?php echo $nonce; ?>
        </form>
    </div>
</div>