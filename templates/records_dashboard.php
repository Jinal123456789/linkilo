<div class="wrap linkilo-report-page linkilo_styles">
    <?=Linkilo_Build_Root::showVersion();
    $codes = Linkilo_Build_Console::getAllErrorCodes();
    $codes = (!empty($codes)) ? '&codes=' . implode(',', $codes) : '';
    ?>
    <h1 class="wp-heading-inline"> 
        <?php 
            $page_heading = (LINKILO_STATUS_HAS_RUN_SCAN == 1)? "Summary" : "Initial Setup"; 
            echo $page_heading; 
        ?> 
    </h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'records_tabs.php'; ?>
                <?php if(LINKILO_STATUS_HAS_RUN_SCAN){ ?>
                <div id="report_dashboard">
                    <div class="box">
                        <div class="title">General Statistics</div>
                        <div class="body" id="report_stats">
                            <a href="<?=admin_url('admin.php?page=linkilo&type=links')?>"><i class="dashicons dashicons-format-aside"></i><span>Posts Searched</span><?=Linkilo_Build_Console::getPostCount()?></a>
                            <a href="<?=admin_url('admin.php?page=linkilo&type=links')?>"><i class="dashicons dashicons-admin-links"></i><span>URLs Originated</span><?=Linkilo_Build_Console::getLinksCount()?></a>
                            <a href="<?=admin_url('admin.php?page=linkilo&type=links&orderby=linkilo_links_incoming_internal_count&order=desc')?>"><i class="dashicons dashicons-arrow-left-alt"></i><span>Inner URLs</span><?=Linkilo_Build_Console::getInternalLinksCount()?></a>
                            <a href="<?=admin_url('admin.php?page=linkilo&type=links&orphaned=1')?>"><i class="dashicons dashicons-dismiss"></i><span>Orphan URLs</span><?=Linkilo_Build_Console::getOrphanedPostsCount()?></a>
                            <!-- Commented unusable code ref:link
                                <a href="<?=admin_url('admin.php?page=linkilo&type=error' . $codes)?>"><i class="dashicons dashicons-admin-tools"></i><span>Broken Links</span><?=Linkilo_Build_Console::getBrokenLinksCount()?></a> -->
                            <!-- Commented unusable code ref:link
                                <a href="<?=admin_url('admin.php?page=linkilo&type=error&codes=404')?>"><i class="dashicons dashicons-search"></i><span>404 errors</span><?=Linkilo_Build_Console::get404LinksCount()?></a> -->
                        </div>
                    </div>
                    <div class="box">
                        <div class="title">Frequently explored <a href="<?=admin_url('admin.php?page=linkilo&type=domains')?>">URLs</a></div>
                        <div class="body" id="report_dashboard_domains">
                            <?php
                                $i=0;
                                $prev = isset($domains[0]->cnt) ? $domains[0]->cnt : 0;
                            ?>
                            <?php foreach ($domains as $domain) : ?>
                                <?php if ($prev != $domain->cnt) { $i++; $prev = $domain->cnt; } ?>
                                <div>
                                    <div class="count"><?=$domain->cnt?></div>
                                    <div class="host"><?=$domain->host?></div>
                                </div>
                                <div class="line line<?=$i?>"><span style="width: <?=(($domain->cnt/$top_domain)*100)?>%"></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="box">
                        <div class="title">Inner vs Outer URLs Ratio</div>
                        <div class="body">
                            <div id="linkilo_links_chart" style="width: 320px;height: 320px;"></div>
                            <input type="hidden" name="total_links_count" value="<?=Linkilo_Build_Console::getLinksCount()?>">
                            <input type="hidden" name="internal_links_count" value="<?=Linkilo_Build_Console::getInternalLinksCount()?>">
                        </div>
                    </div>
                </div>
                <?php
                }else{ ?>
                <div class="run-first-scan-wrapper">
                    <div class="run-first-scan-container">
                        <div>
                            <p style="font-weight: 600; font-size: 20px !important;">
                                <?php _e('Welcome to Linkilo!', 'linkilo');?>
                            </p>
                            <p style="font-weight: 600; font-size: 20px !important;">
                                <?php
                                _e('We’ll need to complete the first time setup, so we can get up and running.', 'linkilo');
                                ?>
                            </p>
                            <p style="font-weight: 600; font-size: 17px !important;">
                                <?php
                                _e('Please click on run scan.', 'linkilo');
                                ?>
                            </p>
                            <p style="font-weight: 600; font-size: 20px !important;">
                                <?php _e('Why? Because...', 'linkilo');?>
                            </p>
                            <p style="font-weight: 600; font-size: 17px !important;">
                                <?php
                                _e('Linkilo needs to crawl and scan to identify internal link opportunities, link metrics and other advanced functionality like checking for any error, and other features we’ve provided for you..', 'linkilo');
                                ?>
                            </p>
                            <p style="font-weight: 600; font-size: 20px !important;">
                                <?php _e('If you already ran a scan…', 'linkilo');?>
                            </p>
                            <p style="font-weight: 600; font-size: 20px !important;">
                                <?php _e('IPlease wait for the scan to be completed or if you exit out, please run a new scan. We only need to make sure the database is updated and no settings will be affected.', 'linkilo');?>
                            </p>
                            <form action='' method="post" id="linkilo_report_reset_data_form" style="float:none;margin-top:50px;">
                                <input type="hidden" name="reset_data_nonce" value="<?php echo wp_create_nonce($user->ID . 'linkilo_refresh_record_data'); ?>">
                                <?php if (!empty($_GET['type'])) : ?>
                                    <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=$_GET['type']?>" id="linkilo_cvs_export_button">Featured CSV Report</a>
                                    <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=$_GET['type']?>_summary" id="linkilo_cvs_export_button">Concise CSV Report</a>
                                <?php endif; ?>
                                <button type="submit" class="button-primary initial-scan-button"><?php _e('Perform Scan');?></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php }?>
            </div>
        </div>
    </div>
</div>
