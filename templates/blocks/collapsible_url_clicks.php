<div class="linkilo-collapsible-wrapper">
    <div class="linkilo-collapsible linkilo-collapsible-static linkilo-clicks-count"><?= (!empty($click_data)) ? $click_data[0]->total_clicks: 0;?></div>
    <div class="linkilo-content">
        <ul class="report_clicks">
            <?php foreach ($click_data as $data) : ?>
                <li>
                    <strong><?php _e('Total Link Clicks:', 'linkilo'); ?> </strong><?php echo $data->total_clicks;?>
                </li>
                <li>
                    <strong><?php _e('Clicks Over the Past 30 Days:', 'linkilo'); ?> </strong><?php echo $data->clicks_over_30_days;?>
                </li>
                <li>
                    <strong><?php _e('Most Clicked Link:', 'linkilo'); ?> </strong><a href="<?=admin_url("admin.php?post_id={$data->link_url}&post_type=url&page=linkilo&type=click_details_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'] . '&direct_return=1'))?>" target="_blank"><strong><?php echo $data->link_anchor;?></strong></a> (<?php echo $data->most_clicked_count;?>)
                </li>
                <li>
                    <strong><a href="<?php echo admin_url("admin.php?post_id={$post->id}&post_type={$post->type}&page=linkilo&type=click_details_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'] . '&direct_return=1')); ?>"><?php _e('View Detailed Click Report', 'linkilo'); ?></a></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>