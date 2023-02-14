<h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
    <a class="nav-tab <?=empty($_GET['type'])?'nav-tab-active':''?>" id="general-tab" href="<?=admin_url('admin.php?page=linkilo')?>">
            <?php  //_e( "Summary", 'linkilo' )?>   
            <?php 
                $tab_heading = (LINKILO_STATUS_HAS_RUN_SCAN == 1)? "Summary" : "Initial Setup"; 
                _e( $tab_heading, 'linkilo' ); 
            ?>             
    </a>
    <?php if(LINKILO_STATUS_HAS_RUN_SCAN){ ?>
    <?php 
        // get any filter settings from the user's report selection and apply the settings to the Link Report tab url
        $filter_settings = get_user_meta(get_current_user_id(), 'linkilo_filter_settings', true);
        $filter_vars = '';
        if(isset($filter_settings['report'])){
            $filtering = array();
            if(isset($filter_settings['report']['post_type']) && !empty($filter_settings['report']['post_type'])){
                $filtering['post_type'] = $filter_settings['report']['post_type'];
            }

            if(isset($filter_settings['report']['category']) && !empty($filter_settings['report']['category'])){
                $filtering['category'] = $filter_settings['report']['category'];
            }

            if(isset($filter_settings['report']['location']) && !empty($filter_settings['report']['location'])){
                $filtering['location'] = $filter_settings['report']['location'];
            }

            if(!empty($filtering)){
                $filter_vars = '&' . http_build_query($filtering);
            }
        } 
    ?>
    <a class="nav-tab <?=(!empty($_GET['type']) && $_GET['type']=='links')?'nav-tab-active':''?>" id="home-tab" href="<?=admin_url('admin.php?page=linkilo&type=links' . $filter_vars)?>"><?php  _e( "URL Records", 'linkilo' )?> </a>
    <a class="nav-tab <?=(!empty($_GET['type']) && $_GET['type']=='domains')?'nav-tab-active':''?>" id="home-tab" href="<?=admin_url('admin.php?page=linkilo&type=domains')?>"><?php  _e( "Domain URLs", 'linkilo' )?> </a>
    <?php /* Commented unusable code ref:link if(empty(get_option('linkilo_disable_click_tracking', false))){ ?>
    <a class="nav-tab <?=(!empty($_GET['type']) && $_GET['type']=='clicks')?'nav-tab-active':''?>" id="post_types-tab" href="<?=admin_url('admin.php?page=linkilo&type=clicks')?>"><?php  _e( "URL click records", 'linkilo' )?> </a>
    <?php } */?>
    <!-- Commented unusable code ref:link 
        <a class="nav-tab <?=(!empty($_GET['type']) && $_GET['type']=='error')?'nav-tab-active':''?>" id="post_types-tab" href="<?=admin_url('admin.php?page=linkilo&type=error')?>"><?php  _e( "Error Report", 'linkilo' )?> </a> -->

    <?php if(!empty($_GET['type']) && $_GET['type']=='error'){ ?>
    <form action='' method="post" id="linkilo_reset_broken_url_error_data_form">
        <input type="hidden" name="reset" value="1">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'linkilo_reset_broken_url_error_data'); ?>">
        <a href="javascript:void(0)" class="button-primary csv_button" data-type="error" id="linkilo_cvs_export_button">Export to CSV</a>
        <button type="submit" class="button-primary"><?php _e('Scan for Broken Links', 'linkilo'); ?></button>
    </form>
    <?php }elseif(!empty($_GET['type']) && $_GET['type']==='clicks'){?>
    <!-- <form action='' method="post" id="linkilo_clear_clicks_data_form">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'linkilo_clear_clicks_data'); ?>">
        <button type="submit" class="button-primary">Erase Click Data</button>
    </form> -->
    <?php }else{ ?>
    <form action='' method="post" id="linkilo_report_reset_data_form">
        <input type="hidden" name="reset_data_nonce" value="<?php echo wp_create_nonce($user->ID . 'linkilo_refresh_record_data'); ?>">
        <?php if (!empty($_GET['type'])) : ?>
            <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=$_GET['type']?>" id="linkilo_cvs_export_button">Featured CSV Report</a>
            <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=$_GET['type']?>_summary" id="linkilo_cvs_export_button">Concise CSV Report</a>
        <?php endif; ?>
        <button type="submit" class="button-primary">Perform Scan</button>
    </form>
    <?php } ?>
    <?php } // end link table exist check
    ?>
</h2>
