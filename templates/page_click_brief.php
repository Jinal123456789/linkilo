<div class="wrap linkilo-report-page linkilo_styles" id="detailed_clicks_page">
    <?=Linkilo_Build_Root::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e("Click Details", "linkilo"); ?></h1>
    <a href="<?=esc_url($return_url)?>" class="page-title-action return_to_report"><?php _e('Return to Report','linkilo'); ?></a>
    <h2><?php echo sprintf(__('Showing click details for: %s', 'linkilo'), $sub_title); ?></h2>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="linkilo_link-articles" class="postbox">
                    <h2 class="hndle no-drag"><span><?php _e('Linkilo Click Chart', 'linkilo'); ?></span></h2>
                    <div class="inside">
                        <div id="link-click-detail-chart">
                            <input type="hidden" id="link-click-detail-data" value="<?php echo esc_attr(json_encode($click_chart_data)); ?>">
                            <input type="hidden" id="link-click-detail-data-range" value="<?php echo esc_attr(json_encode( array('start' => date('F d, Y', $start_date), 'end' => date('F d, Y', $end_date ) ) )); ?>">
                        </div>
                    </div>
                </div>
                <div class="linkilo-click-detail-controls">
                    <div class="inside" style="margin: 0 0 30px 0; display: inline-block;">
                        <label for="linkilo-click-detail-daterange" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px 2px; display: block; display: inline-block;"><?php _e('Filter Clicks by Date', 'linkilo'); ?></label><br/>
                        <input id="linkilo-click-detail-daterange" type="text" name="daterange" class="linkilo-date-range-filter" value="<?php echo date('m/d/Y', $start_date) . ' - ' . date('m/d/Y', $end_date); ?>">
                    </div>
                    <div id="keywords" style="margin-top: 10px;">
                        <form action="" method="post">
                            <label for="keywords_field">Search by Keyword</label>
                            <textarea name="keywords" id="keywords_field"><?=!empty($_POST['keywords'])?sanitize_textarea_field($_POST['keywords']):''?></textarea>
                            <button type="submit" class="button-primary">Search</button>
                        </form>
                    </div>
                </div>
                <div id="linkilo_link-articles" class="postbox">
                    <h2 class="hndle no-drag"><span><?php _e('Linkilo Click Data Table', 'linkilo'); ?></span></h2>
                    <div class="inside">
                    <?php
                        $table = new Linkilo_Build_Table_PageClickBrief();
                        $table->prepare_items();
                        include LINKILO_PLUGIN_DIR_PATH . '/templates/records_detailed_clicks.php';
                    ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
