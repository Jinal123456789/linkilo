<?php 
/*
<div class="wrap linkilo-report-page linkilo_styles">
    <?=Linkilo_Build_Root::showVersion()?>
    <h1 class="wp-heading-inline">URL Click Records</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'records_tabs.php'; ?>
                <div id="report_clicks">
                    <form>
                        <input type="hidden" name="page" value="linkilo" />
                        <input type="hidden" name="type" value="clicks" />
                        <input type="hidden" name="click_delete_confirm_text" value="<?php _e('Do you really want to delete all the click data in the row?', 'linkilo'); ?>" />
                        <?php $table->search_box('Search', 'search'); ?>
                    </form>
                    <?php $table->display(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var admin_url = '<?=admin_url()?>';
</script>
*/ ?>