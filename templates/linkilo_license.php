<?php
	/**
	* This file is a template file of license checking page
	*
	*/
    /*  Commented unusable code ref:license

    // get the license status data
    $license    = get_option(LINKILO_LICENSE_KEY_OPTION, '');
    $status     = get_option(LINKILO_STATUS_OF_LICENSE_OPTION);
    $last_error = get_option(LINKILO_LAST_ERROR_FOR_LICENSE_OPTION, '');

    // get the current licensing state
    $licensing_state;
    if(empty($license) && empty($last_error) || ('invalid' === $status && 'Deactivated manually' === $last_error)){
        $licensing_state = 'not_activated';
    }elseif(!empty($license) && 'valid' === $status){
        $licensing_state = 'activated';
    }else{
        $licensing_state = 'error';
    }

    // create titles for the license statuses
    $status_titles   = array(
        'not_activated' => __('License Not Active', 'linkilo'),
        'activated'     => __('License Active', 'linkilo'),
        'error'         => __('License Error', 'linkilo')
    );

    // create some helpful text to tell the user what's going on
    $status_messages = array(
        'not_activated' => __('Please enter your Linkilo License Key to activate Linkilo.', 'linkilo'),
        'activated'     => __('Congratulations! Your Linkilo License Key has been confirmed and Linkilo is now active!', 'linkilo'),
        'error'         => $last_error
    );
?>
<div class="wrap linkilo_styles" id="licensing_page">
    <?=Linkilo_Build_Root::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('Linkilo Settings page', 'linkilo'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
                <a class="nav-tab disabled" id="linkilo-general-settings" href="#" disabled="disabled"><?php _e('General Settings', 'linkilo'); ?></a>
                <a class="nav-tab disabled" id="linkilo-content-ignoring-settings" href="#" disabled="disabled"><?php _e('Content Ignoring', 'linkilo'); ?></a>
                <a class="nav-tab disabled" id="linkilo-advanced-settings" href="#" disabled="disabled"><?php _e('Advanced Settings', 'linkilo'); ?></a>
                <a class="nav-tab nav-tab-active" id="linkilo-licensing" href="#"><?php _e('Licensing', 'linkilo'); ?></a>
            </h2>
            <div id="post-body-content" style="position: relative;">
                <div class="linkilo_licensing_background">
                    <div class="wrap linkilo_licensing_wrap postbox">
                        <div class="linkilo_licensing_container">
                            <div class="linkilo_licensing" style="">
                                <h2 class="linkilo_licensing_header hndle ui-sortable-handle">
                                    <span>Linkilo Licensing</span>
                                </h2>
                                <div class="linkilo_licensing_content inside">
                                    <form method="post">
                                        <?php settings_fields('linkilo_license'); ?>
                                        <input type="hidden" name="hidden_action" value="activate_license">
                                        <table class="form-table">
                                            <tbody>
                                                <tr>
                                                    <td class="linkilo_license_table_title"><?php _e('License Key:', 'linkilo');?></td>
                                                    <td><input id="linkilo_license_key" name="linkilo_license_key" type="text" class="regular-text" value="" /></td>
                                                </tr>
                                                <tr>
                                                    <td class="linkilo_license_table_title"><?php _e('License Status:', 'linkilo');?></td>
                                                    <td><span class="linkilo_licensing_status_text <?php echo esc_attr($licensing_state); ?>"><?php echo esc_attr($status_titles[$licensing_state]); ?></span></td>
                                                </tr>
                                                <tr>
                                                    <td class="linkilo_license_table_title"><?php _e('License Message:', 'linkilo');?></td>
                                                    <td><p class="linkilo_licensing_status_text <?php echo esc_attr($licensing_state); ?>"><?php echo esc_attr($status_messages[$licensing_state]); ?></p></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <?php wp_nonce_field( 'linkilo_activate_license_nonce', 'linkilo_activate_license_nonce' ); ?>
                                        <button type="submit" class="button button-primary linkilo_licensing_activation_button"><?php _e('Activate License', 'linkilo'); ?></button>
                                        <div class="linkilo_licensing_version_number"><?php echo Linkilo_Build_Root::showVersion(); ?></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!--/frmSaveSettings-->
            </div>
        </div>
    </div>
</div>

<?php

    */