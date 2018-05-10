<?php
/*
Plugin Name: BBC Reports
Description: Custom plugin to export reports from Jewson Building Better Communities
Author: Kevin Price-Ward
Version: 1.13
*/

add_action('admin_menu', 'bbc_reports_menu_page');

function bbc_reports_menu_page(){
    add_menu_page( 'Reports', 'Reports', 'manage_options', 'bbc-reports', 'bbc_reports_admin', 'dashicons-external' );
}

function bbc_reports_nominations_script()
{
    wp_enqueue_script( 'bbc_reports_script', plugin_dir_url( __FILE__ ) . 'scripts/common.js', ['jquery'] );
}
add_action('admin_enqueue_scripts', 'bbc_reports_nominations_script');

function bbc_reports_admin(){
    echo '<div class="wrap">';
    echo "<h1>Export CSV reports:</h1>";
    echo '<p>Use the drop down menu\'s below to select the criteria for your CSV export:</p>';
    ?>
    <h2>Nominations</h2>
    <form id="bbc_csv_report_form" action="<?php echo get_admin_url().'admin-post.php'; ?>" method="post">
        <div class="row">
            <label for="type-select">Nomination type:</label>
            <select name="type-select" id="type-select">
                <option selected="selected" value="">- All types -</option>
                <option value="trade-hero">Trade Hero</option>
                <option value="community-project">Community Project</option>
                <option value="top-community-prize">Top Community Prize</option>
            </select>
        </div>
        <div class="row mt-4">
            <h4>Submitted?</h4>
            <input type="radio" name="is-submitted" value="yes" id="is-submitted-yes"/><label for="is-submitted-yes">Submitted</label>
            <input type="radio" name="is-submitted" value="no" id="is-submitted-no"/><label for="is-submitted-no">Not submitted</label>
            <input type="radio" name="is-submitted" value="" id="is-submitted-both" checked="checked"/><label for="is-submitted-both">Both</label>
        </div>
        <br><br>
        <input type="hidden" name="action" value="submit-form"/>
        <input class="button-primary" type="submit" name="nominations_submit" value="<?php esc_attr_e( 'Export nominations to CSV' ); ?>" />

        <h2>Users</h2>
        <div class="row">
            <label for="role-select">User role:</label>
            <select name="role-select" id="role-select">
                <option value="all">- All roles -</option>
                <?php wp_dropdown_roles( ); ?>
            </select>
        </div>
        <br><br>
        <input class="button-primary" type="submit" name="registrants_submit" value="<?php esc_attr_e( 'Export users to CSV' ); ?>" />
    </form>
    <?php
    echo '</div>';
}

add_action('admin_post_submit-form', '_handle_form_action'); // If the user is logged in
add_action('admin_post_nopriv_submit-form', '_handle_form_action'); // If the user in not logged in
function _handle_form_action(){
    $array = [];
    global $post;
    if(isset($_POST['nominations_submit'])){
        $type = filter_var($_POST['type-select'], FILTER_SANITIZE_STRING);
        $submitted = filter_var($_POST['is-submitted'], FILTER_SANITIZE_STRING);
        if(!empty($type)){
            $post_type = $type;
        }else{
            $post_type = ['trade-hero', 'community-project', 'top-community-prize'];
        }

        $query = [
            'post_type' => $post_type,
            'posts_per_page' => -1,
        ];

        if(!empty($submitted)){
            if($submitted==='yes'){
                $query['meta_value'] = true;

                $meta_query = [
                    [
                        'key' => 'nomination_submitted',
                        'value' => true,
                        'compare' => '='
                    ]
                ];
            }else if($submitted==='no'){
                $meta_query = [
                    'relation' => 'OR',
                    [
                        'key' => 'nomination_submitted',
                        'value' => false,
                        'compare' => '='
                    ],
                    [
                        'key' => 'nomination_submitted',
                        'value' => true,
                        'compare' => 'NOT EXISTS'
                    ]
                ];
            }
            $query['meta_query'] = $meta_query;
        }

        $tmp = $post;

        $nominations = new \WP_Query($query);
        if($nominations->have_posts()){
            while($nominations->have_posts()){
                $nominations->the_post();
                $post_type = get_post_type($post->ID);
                $region_a = get_the_terms($post->ID, 'nomination-region');
                $region = $region_a[0]->name;
                $author_email = get_the_author_meta('user_email');
                $author_display_name = get_the_author_meta('display_name');
                $author_first_name = get_the_author_meta('first_name');
                $author_last_name = get_the_author_meta('last_name');
                $nominator_title = get_field('nominator_title');
                $nominator_first_name = get_field('nominator_first_name');
                $nominator_last_name = get_field('nominator_last_name');
                $nominator_email = get_field('nominator_email');
                $opt_in_emails = get_field('nomination_opt_in_to_emails')[0];
                if(!empty($opt_in_emails)){
                    $opt_in_emails = 'yes';
                }else{
                    $opt_in_emails = 'no';
                }
                $opt_in_sms = get_field('opt_in_to_sms')[0];
                if(!empty($opt_in_sms)){
                    $opt_in_sms = 'yes';
                }else{
                    $opt_in_sms = 'no';
                }
                $opt_in_telephone = get_field('opt_in_to_telephone')[0];
                if(!empty($opt_in_telephone)){
                    $opt_in_telephone = 'yes';
                }else{
                    $opt_in_telephone = 'no';
                }
                $opt_in_post = get_field('opt_in_to_post')[0];
                if(!empty($opt_in_post)){
                    $opt_in_post = 'yes';
                }else{
                    $opt_in_post = 'no';
                }
                $opt_out = get_field('opt_out')[0];
                if(!empty($opt_out)){
                    $opt_out = 'yes';
                }else{
                    $opt_out = 'no';
                }
                $is_tradesperson = get_field('tradesperson_check')[0];
                if(!empty($is_tradesperson)){
                    $is_tradesperson = 'yes';
                }else{
                    $is_tradesperson = 'no';
                }

                $row = [
                    get_the_ID(),
                    get_the_title(),
                    get_the_permalink(),
                    $post_type,
                    $region,
                    $author_display_name,
                    $author_first_name,
                    $author_last_name,
                    $author_email,
                    $nominator_title,
                    $nominator_first_name,
                    $nominator_last_name,
                    $nominator_email,
                    $opt_in_emails,
                    $opt_in_sms,
                    $opt_in_telephone,
                    $opt_in_post,
                    $opt_out,
                    $is_tradesperson
                ];
                $array[] = $row;
            }
            array_unshift($array, array('id','title','link','post_type','region','login','author_first_name','author_last_name','author_email','nominator_title','nominator_first_name','nominator_last_name','nominator_email','opt_in_emails','opt_in_sms','opt_in_telephone','opt_in_post','opt_out','is_tradesperson'));
        }

        wp_reset_postdata();
        $post = $tmp;
        setup_postdata($post);

        ob_end_clean();

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=export.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        // open the "output" stream
        // see http://www.php.net/manual/en/wrappers.php.php#refsect2-wrappers.php-unknown-unknown-unknown-descriptioq
        $f = fopen('php://output', 'w');

        foreach ($array as $line) {
            fputcsv($f, $line);
        }
        fclose($f);
        die();
    }else if(isset($_POST['registrants_submit'])){
        $role = $_POST['role-select'];
        $user_args = ['orderby' => 'registered'];
        if($role!=='all'){
            $user_args['role'] = $role;
        }else{
            $user_args['role__in'] = ['wpseo_editor', 'wpseo_manager', 'jewson_staff', 'subscriber', 'pending', 'editor', 'contributor', 'author', 'administrator'];
        }
        //var_dump($user_args);

        $users = get_users($user_args);
        foreach($users as $user){
            $row = [];
            $user_id = $user->ID;
            $user_login = $user->data->user_login;
            $user_nicename = $user->data->user_nicename;
            $user_email = $user->data->user_email;
            $user_registered = $user->data->user_registered;
            $user_display_name = $user->data->display_name;
            $user_opt_in_email = get_user_meta($user_id, 'opt_in_email', true);
            $user_opt_in_sms = get_user_meta($user_id, 'opt_in_sms', true);
            $user_opt_in_telephone = get_user_meta($user_id, 'opt_in_telephone', true);
            $user_opt_in_post = get_user_meta($user_id, 'opt_in_post', true);
            $user_opt_out = get_user_meta($user_id, 'opt_out', true);
            $user_opt_in_tradesperson = get_user_meta($user_id, 'opt_in_tradesperson', true);

            $row = [
                $user_id,
                $user_login,
                $user_nicename,
                $user_email,
                $user_registered,
                $user_display_name,
                $user_opt_in_email,
                $user_opt_in_sms,
                $user_opt_in_telephone,
                $user_opt_in_post,
                $user_opt_out,
                $user_opt_in_tradesperson
            ];

            $array[] = $row;
        }

        array_unshift($array, array('id','user_login','user_nicename','user_email','user_registered','user_display_name','opt_in_email','opt_in_sms','opt_in_telephone','opt_in_post','opt_out','is_tradesperson'));

        ob_end_clean();

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=export.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        // open the "output" stream
        // see http://www.php.net/manual/en/wrappers.php.php#refsect2-wrappers.php-unknown-unknown-unknown-descriptioq
        $f = fopen('php://output', 'w');

        foreach ($array as $line) {
            fputcsv($f, $line);
        }
        fclose($f);
        die();
    }
}