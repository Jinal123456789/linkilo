<div class="wrap linkilo-report-page linkilo_styles">
    <style type="text/css">
        <?php
            $sources = Linkilo_Build_FocusKeyword::get_active_keyword_sources();
            $num = count($sources);

            switch ($num) {
                case '6':
                    ?>
                    tr .linkilo-dropdown-column:nth-of-type(6n+5) .linkilo-content{
                        width: calc(600% + 70px);
                    }
                    tr .linkilo-dropdown-column:nth-of-type(6n+5) .update-post-keywords{
                        width: calc(600% + 80px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+0) .linkilo-content{
                        width: calc(600% + 70px);
                        position: relative;
                        right: calc(100% + 20px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+0) .update-post-keywords{
                        width: calc(600% + 80px);
                        position: relative;
                        right: calc(100% + 20px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+1) .linkilo-content{
                        width: calc(600% + 70px);
                        position: relative;
                        right: calc(200% + 40px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+1) .update-post-keywords{
                        width: calc(600% + 80px);
                        position: relative;
                        right: calc(200% + 40px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+2) .linkilo-content{
                        width: calc(600% + 70px);
                        position: relative;
                        right: calc(300% + 60px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+2) .update-post-keywords{
                        width: calc(600% + 80px);
                        position: relative;
                        right: calc(300% + 60px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+3) .linkilo-content{
                        width: calc(600% + 70px);
                        position: relative;
                        right: calc(400% + 80px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+3) .update-post-keywords{
                        width: calc(600% + 80px);
                        position: relative;
                        right: calc(400% + 80px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+4) .linkilo-content{
                        width: calc(600% + 190px);
                        position: relative;
                        right: calc(500% + 200px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(6n+4) .create-post-keywords{
                        width: calc(600% + 200px);
                        position: relative;
                        right: calc(500% + 200px);
                    }
                    <?php
                break;
                case '5':
                    ?>
                    tr .linkilo-dropdown-column:nth-of-type(5n+0) .linkilo-content{
                        width: calc(500% + 50px);
                    }
                    tr .linkilo-dropdown-column:nth-of-type(5n+0) .update-post-keywords{
                        width: calc(500% + 60px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(5n+1) .linkilo-content{
                        width: calc(500% + 50px);
                        position: relative;
                        right: calc(100% + 20px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(5n+1) .update-post-keywords{
                        width: calc(500% + 60px);
                        position: relative;
                        right: calc(100% + 20px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(5n+2) .linkilo-content{
                        width: calc(500% + 50px);
                        position: relative;
                        right: calc(200% + 40px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(5n+2) .update-post-keywords{
                        width: calc(500% + 60px);
                        position: relative;
                        right: calc(200% + 40px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(5n+3) .linkilo-content{
                        width: calc(500% + 50px);
                        position: relative;
                        right: calc(300% + 60px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(5n+3) .update-post-keywords{
                        width: calc(500% + 60px);
                        position: relative;
                        right: calc(300% + 60px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(5n+4) .linkilo-content{
                        width: calc(500% + 150px);
                        position: relative;
                        right: calc(400% + 160px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(5n+4) .create-post-keywords{
                        width: calc(500% + 160px);
                        position: relative;
                        right: calc(400% + 160px);
                    }
                    <?php
                break;
                case '4':
                    ?>
                    tr .linkilo-dropdown-column:nth-of-type(4n+1) .linkilo-content{
                        width: calc(400% + 30px);
                    }
                    tr .linkilo-dropdown-column:nth-of-type(4n+1) .update-post-keywords{
                        width: calc(400% + 40px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(4n+2) .linkilo-content{
                        width: calc(400% + 30px);
                        position: relative;
                        right: calc(100% + 20px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(4n+2) .update-post-keywords{
                        width: calc(400% + 40px);
                        position: relative;
                        right: calc(100% + 20px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(4n+3) .linkilo-content{
                        width: calc(400% + 30px);
                        position: relative;
                        right: calc(200% + 40px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(4n+3) .update-post-keywords{
                        width: calc(400% + 40px);
                        position: relative;
                        right: calc(200% + 40px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(4n+4) .linkilo-content{
                        width: calc(400% + 110px);
                        position: relative;
                        right: calc(300% + 120px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(4n+4) .create-post-keywords{
                        width: calc(400% + 120px);
                        position: relative;
                        right: calc(300% + 120px);
                    }
                    <?php
                break;
                case '3':
                    ?>
                    tr .linkilo-dropdown-column:nth-of-type(3n+2) .linkilo-content{
                        width: calc(300% + 10px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(3n+2) .update-post-keywords{
                        width: calc(300% + 20px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(3n+3) .linkilo-content{
                        width: calc(300% + 10px);
                        position: relative;
                        right: calc(100% + 20px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(3n+3) .update-post-keywords{
                        width: calc(300% + 20px);
                        position: relative;
                        right: calc(100% + 20px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(3n+1) .linkilo-content{
                        width: calc(300% + 70px);
                        position: relative;
                        right: calc(200% + 80px);
                    }
                    
                    tr .linkilo-dropdown-column:nth-of-type(3n+1) .create-post-keywords{
                        width: calc(300% + 80px);
                        position: relative;
                        right: calc(200% + 80px);
                    }
                    <?php
                break;
                case '2':
                    ?>
                    tr .linkilo-dropdown-column:nth-of-type(2n+1) .linkilo-content{
                        width: calc(200% - 10px);
                        position: relative;
                        right: 0;
                    }

                    tr .linkilo-dropdown-column:nth-of-type(2n+1) .update-post-keywords{
                        width: 200%;
                    }

                    tr .linkilo-dropdown-column:nth-of-type(2n+2) .linkilo-content{
                        width: calc(200% + 30px);
                        position: relative;
                        right: calc(100% + 40px);
                    }

                    tr .linkilo-dropdown-column:nth-of-type(2n+2) .create-post-keywords{
                        width: calc(200% + 40px);
                        position: relative;
                        right: calc(100% + 40px);
                    }
                    <?php
                break;
                case '1':
                    ?>
                    .column-custom .linkilo-content{
                        width: calc(100% - 10px);
                    }
                    .column-custom .create-post-keywords{
                        width: 100%;
                    }
                    <?php
                break;
            }
            ?>
    </style>
    <?=Linkilo_Build_Root::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('Focus Keyword', 'linkilo'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="linkilo_focus_keyword_table">
                    <form>
                        <input type="hidden" name="page" value="linkilo_focus_keywords" />
                        <?php $table->search_box('Search', 'search'); ?>
                    </form>
                    <div style="clear: both"></div>
                    <input type="hidden" id="linkilo_focus_keyword_gsc_authenticated" value="<?php echo (Linkilo_Build_GoogleSearchConsole::is_authenticated()) ? 1: 0; ?>">
                    <input type="hidden" id="linkilo_focus_keyword_reset_notice" value="<?php _e('Please confirm refreshing the focus keywords. If you\'ve authenticated the connection to Google Search Console, this will refresh the keyword data.', 'linkilo'); ?>" >
                    <input type="hidden" id="linkilo_focus_keyword_gsc_not_authtext_a" value="<?php _e('Linkilo can not connect to Google Search Console because it has not been authorized yet.', 'linkilo'); ?>">
                    <input type="hidden" id="linkilo_focus_keyword_gsc_not_authtext_b" value="<?php _e('Please go to the Linkilo Settings and authorize access.', 'linkilo'); ?>">
                    <div class="linkilo_help" style="float:right">
                        <i class="dashicons dashicons-editor-help"></i>
                        <div><?php _e('Clicking Refresh Focus Keyword will clear and re-import any Yoast or Rank Math keywords, and all inactive Google Search Console keywords. If you have just installed Linkilo, authorized the GSC connect, or don\'t see Yoast/Rank Math keywords, please click this button.', 'linkilo'); ?></div>
                    </div>
                    <a href="javascript:void(0)" class="button-primary" id="linkilo_focus_keyword_reset_button"><?php _e('Refresh Records', 'linkilo'); ?></a>
                    <?php if (!$reset) : ?>
                        <div class="table">
                            <?php $table->display(); ?>
                        </div>
                    <?php endif; ?>
                    <div class="progress" <?=$reset?'style="display:block"':''?>>
                        <h4 class="progress_panel_msg"><?php _e('Synchronizing your data..','linkilo'); ?></h4>
                        <div class="progress_panel loader">
                            <div class="progress_count"></div>
                        </div>
                        <div class="progress_panel_center" > Loading </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var linkilo_focus_keyword_nonce = '<?=wp_create_nonce($user->ID . 'linkilo_focus_keyword')?>';
    var is_linkilo_focus_keyword_reset = <?=$reset?'true':'false'?>;
    var admin_url = '<?=admin_url()?>';
</script>