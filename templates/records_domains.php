<div class="wrap linkilo-report-page linkilo_styles">
    <?=Linkilo_Build_Root::showVersion()?>
    <h1 class="wp-heading-inline">Domain URL Records</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'records_tabs.php'; ?>
                <div id="report_domains">
                    <form>
                        <input type="hidden" name="page" value="linkilo" />
                        <input type="hidden" name="type" value="domains" />
                        <?php $table->search_box('Search', 'search'); ?>
                    </form>
                    <?php $table->display(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
