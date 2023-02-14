<div class="wrap linkilo-report-page linkilo_styles">
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative; float:none;">
                <div id="report_clicks">
                    <form>
                        <input type="hidden" name="page" value="linkilo" />
                        <input type="hidden" name="type" value="clicks" />
                        <?php $table->search_box('Search', 'search'); ?>
                    </form>
                    <?php $table->display(); ?>
                </div>
            </div>
        </div>
    </div>
</div>