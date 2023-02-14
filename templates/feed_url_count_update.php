<div class="wrap linkilo-report-page linkilo_styles linkilo-lists linkilo_post_links_count_update_page">
    <br>
    <a href="<?=admin_url("admin.php?page=linkilo")?>" class="page-title-action">Return to Report</a>
    <h1 class='wp-heading-inline'>Updating links stats for <?=$post->type?> #<?=$post->id?>, `<?=$post->getTitle()?>`</h1>
    <p>
        <a href="<?=$post->getLinks()->edit?>" target="_blank">[edit]</a>
        <a href="<?=$post->getLinks()->view?>" target="_blank">[view]</a>
        <a href="<?=$post->getLinks()->export?>" target="_blank">[export]</a>
    </p>
    <h2>Previous data:</h2>
    <p>Date of previous analysis: <?=!empty($prev_t) ? $prev_t : '- not set -'?></p>
    <ul>
        <li>
            <b>Outgoing Inner URLs:</b> <?=$prev_count['outgoing_internal']?>
        </li>
        <li>
            <b>Incoming Inner URLs:</b> <?=$prev_count['incoming_internal']?>
        </li>
        <li>
            <b>Outgoing Outer URLs:</b> <?=$prev_count['outgoing_external']?>
        </li>
    </ul>

    <h2>New data:</h2>
    <p>Date of analysis: <?=$new_time?></p>
    <p>Time spent: <?=number_format($time, 3)?> seconds</p>
    <ul>
        <li>
            <b>Outgoing Inner URLs:</b> <?=$count['outgoing_internal']?> (difference: <?=$count['outgoing_internal'] - $prev_count['outgoing_internal']?>)
        </li>
        <li>
            <b>Incoming Inner URLs:</b> <?=$count['incoming_internal']?> (difference: <?=$count['incoming_internal'] - $prev_count['incoming_internal']?>)
        </li>
        <li>
            <b>Outgoing Outer URLs:</b> <?=$count['outgoing_external']?> (difference: <?=$count['outgoing_external'] - $prev_count['outgoing_external']?>)
        </li>
    </ul>

    <h3>Outgoing Inner URLs (links count: <?=$count['outgoing_internal']?>)</h3>
    <ul>
        <?php foreach ($links_data['outgoing_internal'] as $link) : ?>
            <li>
                <a href="<?=esc_url($link->url)?>" target="_blank" style="text-decoration: underline">
                    <?=esc_url($link->url)?><br> <b>[<?=esc_attr($link->anchor)?>]</b>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Incoming Inner URLs (links count: <?=$count['incoming_internal']?>)</h3>
    <ul>
        <?php foreach ($links_data['incoming_internal'] as $link) : ?>
            <li>
                [<?=$link->post->id?>] <?=$link->post->getTitle()?> <b>[<?=esc_attr($link->anchor)?>]</b>
                <br>
                <a href="<?=$link->post->getLinks()->edit?>" target="_blank">[edit]</a>
                <a href="<?=$link->post->getLinks()->view?>" target="_blank">[view]</a>
                <br>
                <br>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Outgoing Outer URLs (links count: <?=$count['outgoing_external']?>)</h3>
    <ul>
        <?php foreach ($links_data['outgoing_external'] as $link) : ?>
            <li>
                <a href="<?=esc_url($link->url)?>" target="_blank" style="text-decoration: underline">
                    <?=esc_url($link->url)?>
                    <br>
                    <b>[<?=esc_attr($link->anchor)?>]</b>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>