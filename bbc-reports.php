<?php
/*
Plugin Name: BBC Place Award
Description: Custom plugin to place award on the BBC site
Author: Kevin Price-Ward
Version: 1.5
*/

add_action( 'admin_enqueue_scripts', 'bbc_pa_load_admin_style' );
function bbc_pa_load_admin_style() {
    wp_register_style( 'bbc_pa_style', plugin_dir_url( __FILE__ ) . 'css/admin.css', false, '1.0.0' );
//OR
    wp_enqueue_style( 'bbc_pa_style' );
}

function bbc_pa_add_meta_boxes(){
    global $post;
    $submitted = get_field('nomination_submitted', $post->ID);
    $types = ['community-project', 'top-community-prize', 'trade-hero'];
    if($submitted===true){
        foreach($types as $type){
            add_meta_box(
                'bbc_pa_meta_box',
                'Votes/Awards',
                'bbc_pa_meta_box_content',
                $type,
                'side',
                'high',
                'callback value'
            );
        }
    }
}
add_action('add_meta_boxes', 'bbc_pa_add_meta_boxes');

function bbc_pa_meta_box_content($post, $args){
    bbc_pa_print_votes($post->ID);
    bbc_pa_print_award_info($post->ID);
    bbc_pa_assign_prize_form();
}

function bbc_pa_print_votes($post_id){
    $vote_types = ['staff', 'public'];
    foreach($vote_types as $vote_type){
        if(function_exists('\Bbc\get_vote_print')){
            printf('<p class="%s">%s votes: <strong>%s</strong></p>', $vote_type, ucfirst($vote_type), \Bbc\get_vote_print($post_id, $vote_type));
        }
    }
}

// ONLY MOVIE CUSTOM TYPE POSTS
add_filter('manage_trade-hero_posts_columns', 'bbc_pa_head_only', 10);
add_action('manage_trade-hero_posts_custom_column', 'bbc_pa_column_only', 10, 2);
add_filter('manage_top-community-prize_posts_columns', 'bbc_pa_head_only', 10);
add_action('manage_top-community-prize_posts_custom_column', 'bbc_pa_column_only', 10, 2);
add_filter('manage_community-project_posts_columns', 'bbc_pa_head_only', 10);
add_action('manage_community-project_posts_custom_column', 'bbc_pa_column_only', 10, 2);
// ADD NEW COLUMN
function bbc_pa_head_only($defaults) {
    $defaults['public_votes'] = 'Public votes';
    $defaults['staff_votes'] = 'Staff votes';
    if(\Bbc\is_site_mode(['Judging', 'Awards'])){
        $defaults['award_amount'] = 'Award';
    }

    return $defaults;
}
//SHOW PUBLIC VOTES
function bbc_pa_column_only($column_name, $post_ID){
    if ($column_name == 'public_votes') {
        echo \Bbc\get_vote_print($post_ID, 'public');
    }
    if ($column_name == 'staff_votes') {
        echo \Bbc\get_vote_print($post_ID, 'staff');
    }
    if(\Bbc\is_site_mode(['Judging', 'Awards'])) {
        if ($column_name == 'award_amount') {
            $award = get_post_meta($post_ID, 'bbc_pa_prize_award', true);
            echo '&pound;' . number_format((float)$award, 2);
        }
    }
}

function bbc_pa_print_award_info($post_id){
    if(\Bbc\is_site_mode(['Judging'])){
        echo '<hr/>';
        $prize_fund_total = get_field('total_prize_fund', 'options');
        $prize_fund_summary = bbc_pa_summary_prize_total();
        $prize_fund_available = $prize_fund_total - $prize_fund_summary;

        printf('<p>You have <strong>&pound;%s</strong> available out of a total prize fund of <strong>&pound;%s</strong>.</p>', number_format($prize_fund_available, 2), number_format($prize_fund_total, 2));
    }
}

function bbc_pa_summary_prize_total(){
    global $post;
    $tmp = $post;
    $value = 0;
    $args = array(
        'post_type' => ['community-project', 'trade-hero', 'top-community-prize'],
        'posts_per_page' => -1,
        'meta_key' => 'bbc_pa_prize_award',
        'meta_value_num' => 0,
        'meta_compare' => '>',
    );

    $query = new WP_Query( $args );
    if($query->have_posts()){
        while($query->have_posts()){
            $query->the_post();
            if(!empty(get_post_meta($post->ID, 'bbc_pa_prize_award')[0])){
                $value+= get_post_meta($post->ID, 'bbc_pa_prize_award')[0];
            }
        }
    }

    wp_reset_postdata();
    $post = $tmp;
    setup_postdata($post);

    return $value;
}

function bbc_pa_assign_prize_form(){
    global $post;
    $value = get_post_meta($post->ID, 'bbc_pa_prize_award', true);

    if (\Bbc\is_site_mode(['Judging'])) {
        echo '<hr/>';
        ?>
        <label for="bbc_prize_amount">How much are you awarding this candidate nomination?</label>
        <div style="margin-top: 0.5em"><strong>&pound;</strong> <input name="bbc_prize_amount" id="bbc_prize_amount"
                                                                       class="" type="text" value="<?= $value ?>"
                                                                       style="width: 93%;"/></div>
        <?php
        if(get_post_type($post->ID)==='trade-hero'||get_post_type($post->ID)==='top-community-prize'){
            //Get info about the top prize
            $top_prize_info = ba_get_top_prize_info(get_post_type($post->ID));
            //var_dump($top_prize_info);
            if($top_prize_info['count']===0){
                echo '<hr style="margin-top: 1em;"/>';
                echo '<div style="margin-top: 1em;"><input type="checkbox" name="bbc_is_top_prize" id="bbc_is_top_prize" value="yes"/> <label for="bbc_is_top_prize">Top prize winner?</label> </div>';
            }else{
                if(in_array($post->ID, $top_prize_info['posts'])){
                    echo '<hr style="margin-top: 1em;"/>';
                    echo '<p>This nomination has been awarded the top prize, untick the checkbox below to remove...</p>';
                    echo '<div style="margin-top: 1em;"><input type="checkbox" name="bbc_is_top_prize" id="bbc_is_top_prize" value="yes" checked="checked"/><label for="bbc_is_top_prize">Top prize winner?</label></div>';
                    echo '<input type="hidden" name="bbc_post_has_top_prize" id="bbc_post_has_top_prize" value="yes"/>';
                }else{
                    echo '<hr style="margin-top: 1em;"/>';
                    printf('<p>You cannot award the top prize because it has already been awarded to <strong><a href="%s">%s</a></strong></p>', admin_url('post.php?post='.$top_prize_info['posts'][0].'&action=edit'), get_the_title($top_prize_info['posts'][0]));
                }
            }
        }
    } else if (\Bbc\is_site_mode(['Awards'])) {
        if (!empty($value)) {
            echo '<hr/>';
            printf('<p>This nomination was awarded <strong>&pound;%s</strong></p>', number_format($value, 2));
        }
    }
}

function ba_get_top_prize_info($type){
    global $post;
    $tmp = $post;

    $info = [];

    $args = [
        'post_type' => $type,
        'meta_key' => 'bbc_pa_is_top',
        'meta_value' => 'yes'
    ];
    $top_prize = new \WP_Query($args);
    if($top_prize->have_posts()){
        $info['count'] = $top_prize->found_posts;
        while($top_prize->have_posts()){
            $top_prize->the_post();
            $info['posts'][] = $post->ID;
        }
    }else{
        $info['count'] = 0;
    }

    wp_reset_postdata();
    $post = $tmp;
    setup_postdata($post);

    return $info;
}


//SAVING POSTS
add_action('save_post_trade-hero', 'bbc_pa_save_post');
add_action('save_post_community-project', 'bbc_pa_save_post');
add_action('save_post_top-community-prize', 'bbc_pa_save_post');

function bbc_pa_save_post($post_id){
    if (array_key_exists('bbc_prize_amount', $_POST)) {
        //Check value
        $user_id = get_current_user_id();
        $value = filter_var($_POST['bbc_prize_amount'], FILTER_SANITIZE_STRING);
        $top_prize = filter_var($_POST['bbc_is_top_prize'], FILTER_SANITIZE_STRING);
        $has_top_prize = filter_var($_POST['bbc_post_has_top_prize'], FILTER_SANITIZE_STRING);
        if(!empty($value)){
            if(!is_numeric($value)){
                $error_msg = '<strong>' . $value . '</strong> is not a valid prize amount';
            }else{
                if(strpos($value, '.')){
                    $error_msg = '<strong>' . $value . '</strong> is not a whole number';
                }else{
                    //Now check that it would not break the bank
                    $pot = get_field('total_prize_fund', 'options');
                    $sum = bbc_pa_summary_prize_total();
                    $fund = $pot - $sum;
                    if($fund - $value < 0){
                        $error_msg = 'You do not have enough funds to award <strong>&pound;' . number_format($value, 2) .'</strong>';
                    }
                }
            }
        }


        if(!empty($error_msg)){
            $error = new WP_Error('bbc_pa_error', $error_msg);
            set_transient("bbc_pa_save_post_errors_{$post_id}_{$user_id}", $error, 45);
        }else{
            update_post_meta(
                $post_id,
                'bbc_pa_prize_award',
                $_POST['bbc_prize_amount']
            );
        }

        //Top prize
        if($has_top_prize==='yes'){
            //Remove the top prize if it's not set
            if(!$top_prize!=='yes'){
                delete_post_meta($post_id, 'bbc_pa_is_top');
            }
        }else{
            if($top_prize==='yes'){
                update_post_meta(
                    $post_id,
                    'bbc_pa_is_top',
                    $top_prize
                );
            }
        }
    }
}

add_action('admin_notices', 'bbc_pa_admin_notices');
function bbc_pa_admin_notices(){
    global $post;
    if($post){
        $post_id = $post->ID;
        $user_id = get_current_user_id();
        if ( $error = get_transient( "bbc_pa_save_post_errors_{$post_id}_{$user_id}" ) ) { ?>
            <div class="error">
            <p><?php echo $error->get_error_message(); ?></p>
            </div><?php

            delete_transient("bbc_pa_save_post_errors_{$post_id}_{$user_id}");
        }
    }
}