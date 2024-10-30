<?php
/**
 * Plugin Name: Check missing featured images
 * Description: Check missing featured images
 * Version: 1.0
 * Author: termel
 * Author URI: https://www.termel.fr
 */
if (! defined('ABSPATH')) {
    die();
}

function cmfi_log($message)
{
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

class CMFI_CheckMissingFeaturedImages
{

    // static $pdf2txtExe = null;
    function __construct()
    {
        add_action('admin_menu', array(
            $this,
            'cmfi_setup_menu'
        ));
    }

    function cmfi_setup_menu()
    {
        // add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
        add_options_page('Check featured images', 'Check featured images', 'manage_options', 'cmfi-check-featured-images', array(
            $this,
            'cmfi_admin_page'
        ));
    }

    function cmfi_admin_page()
    {
        $this->CMFI_check_featured_images();
        
        ?>
<div style="text-align: center; padding: 5px;">
	<h1>Check missing featured images</h1>

</div>
<div
	style="background: #e0e0e0; border-radius: 4px; padding: 1em; border: 1px solid #a3a3a3; font-size: 1.2em;">
	Your server :
	<?php
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            echo '<span style="color:red;">Unfortunately, this plugin has not been tested on Windows OS.</span>';
        } else {
            echo '<pre>' . php_uname() . '</pre>';
        }
        
        ?>
	
</div>

<h2>Launch check</h2>
<!-- Form to handle the upload - The enctype value here is very important -->
<form method="post" enctype="multipart/form-data">
	
	<?php wp_nonce_field( 'cmfi_check', 'cmfi_check_nonce' ); ?>
	<select name="selected_post_type" id="selected_post_type_id">
    			<?php
        /*
         * $args = array(
         * 'public' => true,
         * '_builtin' => true
         * );
         */
        $args = array();
        
        $output = 'names'; // 'names' or 'objects' (default: 'names')
        $operator = 'and'; // 'and' or 'or' (default: 'and')
        
        $post_types = array(
            'any' => 'all'
        );
        $post_types = array_merge($post_types, get_post_types($args, $output, $operator));
        
        // $statuses = get_post_statuses();
        foreach ($post_types as $key => $val) {
            printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $val);
        }
        ?>
				</select> <select name="status" id="status_id">
    			<?php
        $statuses = array(
            'any' => 'all'
        );
        $statuses = array_merge($statuses, get_post_statuses());
        
        foreach ($statuses as $key => $val) {
            printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $val);
        }
        ?>
				</select>	

    		<?php submit_button('Check missing featured images')?>
    	</form>
<?php
    }

    function CMFI_check_featured_images()
    {
        // 'cmfi_check', 'cmfi_check_nonce' );
        cmfi_log($_POST);
        // First check if the file appears on the _FILES array
        if (isset($_POST['selected_post_type'])) {
            if (wp_verify_nonce($_POST['cmfi_check_nonce'], 'cmfi_check')) {
                cmfi_log("wp_verify_nonce : OK");
                $this->CMFI_displayHTMLStatusOfCmd("wp_verify_nonce", "OK", 0);
                
                $status = sanitize_text_field($_POST['status']);
                $post_type = sanitize_text_field($_POST['selected_post_type']);
                
                cmfi_log($post_type);
                echo "Type choosen : " . $post_type;
                
                $posts_of_specified_type = get_posts(array(
                    'post_type' => $post_type,
                    'posts_per_page' => - 1,
                    'post_status' => $status
                ));
                
                cmfi_log($post_type);
                echo '<hr/>';
                $nbOfPosts = count($posts_of_specified_type);
                if (! $nbOfPosts) {
                    echo "No post matching type " . $post_type . " and status " . $status;
                } else {
                    echo $nbOfPosts . " post(s) matching type <strong>" . $post_type . "</strong> and status <strong>" . $status . "</strong>";
                }
                echo '<br/><br/>';
                $idx = 1;
                $missing = 0;
                $detailledResultsArray = array();
                foreach ($posts_of_specified_type as $post) {
                    $idx ++;
                    $featuredImage = get_the_post_thumbnail($post->ID, 'thumbnail');
                    if (empty($featuredImage)) {
                        $missing ++;
                        $author_name = get_the_author_meta('display_name', $post->post_author);
                        $detailledResults = '<span style="color:red;">' . $post->post_type . " - ID:" . $post->ID . ' - <strong>' . $post->post_title . '</strong> (' . $author_name . ')</span>';
                        $detailledResults .= ' ';
                        $detailledResults .= '<a  target="_blank" href="' . get_edit_post_link($post->ID) . '">Edit Post</a><br/>';
                        $detailledResultsArray[$post->post_type][$post->post_status][] = $detailledResults;
                    }
                }
                $summary = $missing . " items of type <strong>" . $post_type . "</strong>  miss their featured image.";
                if ($missing) {
                    $color = 'red';
                } else {
                    $color = 'green';
                }
                $summary = '<span style="font-size:1.4em;color:' . $color . ';">' . $summary . '</span>';
                echo $summary . '<br/>';
                
                foreach ($detailledResultsArray as $type_of_post => $statusesItems) {
                    echo '<h2 style="color:purple;">' . count($statusesItems) . ' statuses for ' . $type_of_post . '</h2>';
                    
                    foreach ($statusesItems as $status => $items) {
                        echo '<h3 style="color:orange;">' . count($items) . ' items in status ' . $status . ' are missing featured image:</h3>';
                        foreach ($items as $item) {
                            echo $item;
                        }
                    }
                }
                
                echo '<hr/>';
            } else {
                $msg = "The security check failed";
                // The security check failed, maybe show the user an error.
                cmfi_log($msg);
                echo $msg;
                return $msg;
                // echo $msg . '<br/>';
            }
        } else {
            $msg = "No type selected";
            // The security check failed, maybe show the user an error.
            echo $msg;
            cmfi_log($msg);
        }
    }

    function CMFI_displayHTMLStatusOfCmd($cmd, $output, $ret_val, $additionnalMesg = '')
    {
        $show_status = '<span style="color:grey;font-size:0.7em;">' . $cmd . '</span>';
        $show_status .= '<br/><span ';
        if ($ret_val == 0) {
            $color = 'green';
        } else {
            $color = 'red';
        }
        $show_status .= 'style="color:' . $color . '">';
        if (empty($output)) {
            $show_status .= 'Ok';
        } else {
            $show_status .= '<ul><li>';
            if (is_array($output)) {
                $show_status .= implode('</li><li>', $output);
                cmfi_log(implode(' | ', $output));
            } else {
                $show_status .= $output;
                cmfi_log($output);
            }
            $show_status .= '</li></ul>';
        }
        
        $show_status .= '<br/>' . $additionnalMesg;
        $show_status .= '</span><br/>';
        
        echo $show_status;
    }
}

$obj = new CMFI_CheckMissingFeaturedImages();