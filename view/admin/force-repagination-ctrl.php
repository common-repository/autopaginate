<?php if (!defined ('ABSPATH')) die ('No direct access allowed'); ?>
<?php wp_nonce_field( 'ap_autopagination', '_ap_nonce' ); ?>
<p id="ap-force-repagination-ctrl"><label for="ap_force_repagination"><input type="checkbox" name="ap_force_repagination" value="1" id="ap_force_repagination" /> <?php _e( 'Force repagination', 'true' ); ?></label></p>