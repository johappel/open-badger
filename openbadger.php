<?php
/**
 * @package openBadger
 */
/*
Plugin Name: openBadger
Plugin URI: http://badges.blogs.rpi-virtuell.net/download
Description: "Mozilla OpenBadge Issuer" Plugin, mit Hilfe dessen eine Organisation offene Zerfifikate (OpenBadges) erstellen und an Besucher verleihen kann. Die Programmierung bassiert vor allem auf den wpBadger Plugins von Dave Lester und deren Forks. Besondern Dank an Steven Butler, der den Code überarbeitet hat.
Version: 1.0
Author: Joachim happel
Author URI: http://joachimhappel.de
*/
load_plugin_textdomain('rpibadger', false, basename( dirname( __FILE__ ) ) . '/languages' );

add_action('admin_init', 'openbadger_admin_init');
add_action('admin_head', 'openbadger_admin_head');
add_action('admin_menu', 'openbadger_admin_menu');
add_action('admin_notices', 'openbadger_admin_notices');
//add_action('openbadges_shortcode', 'openbadger_shortcode');

add_shortcode( 'mybadges', 'openbadger_shortcode' );

register_activation_hook(__FILE__,'openbadger_activate');
register_deactivation_hook(__FILE__,'openbadger_deactivate');

add_action('wp_enqueue_scripts', 'openbadger_enqueue_scripts');

require_once( dirname(__FILE__) . '/includes/badges.php' );
require_once( dirname(__FILE__) . '/includes/badges_designer.php' );
require_once( dirname(__FILE__) . '/includes/badges_stats.php' );
require_once( dirname(__FILE__) . '/includes/awards.php' );

global $openbadger_db_version;
$openbadger_db_version = "1.0.0";

function openbadger_activate()
{
	// If the current theme does not support post thumbnails, exit install and flash warning
	if(!current_theme_supports('post-thumbnails')) {
		echo "Unable to install plugin, because current theme does not support post-thumbnails. You can fix this by adding the following line to your current theme's functions.php file: add_theme_support( 'post-thumbnails' );";
		exit;
	}

	global $openbadger_db_version;

	add_option("openbadger_db_version", $openbadger_db_version);

	// Flush rewrite rules
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

function openbadger_deactivate()
{
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

function openbadger_admin_init()
{
    //if(is_plugin_active('fancybox-for-wordpress/fancybox.php')) ;
	/* fancybox stuff */
    wp_register_style( 'ob-fancybox-styles', plugins_url('fancybox/jquery.fancybox.css', __FILE__) );
    wp_register_script( 'ob-fancybox', plugins_url('fancybox/jquery.fancybox.js', __FILE__), array( 'jquery' ) );

    wp_register_style( 'openbadger-admin-styles', plugins_url('css/admin-styles.css', __FILE__) );
    wp_register_script( 'openbadger-admin-post', plugins_url('js/admin-post.js', __FILE__), array( 'post', 'ob-fancybox' ) );
}

function openbadger_admin_head()
{
    global $pagenow, $openbadger_badge_schema, $openbadger_award_schema;

    if (get_post_type() != $openbadger_badge_schema->get_post_type_name() &&
        get_post_type() != $openbadger_award_schema->get_post_type_name())
        return;

	//if(is_plugin_active('fancybox-for-wordpress/fancybox.php')) return;
    wp_enqueue_style( 'openbadger-admin-styles' );
    wp_enqueue_style( 'ob-fancybox-styles' );

    if ($pagenow == 'post.php' || $pagenow == 'post-new.php')
        wp_enqueue_script( 'openbadger-admin-post' );
}

function openbadger_enqueue_scripts()
{
    wp_enqueue_style( 'openbadger-styles', plugins_url('css/styles.css', __FILE__) );
}

function openbadger_admin_menu()
{
    global $openbadger_award_schema;

    $award_type = get_post_type_object('award');

	add_submenu_page('options-general.php','Configure rpiBadger Plugin','openBadger','manage_options','openbadger_configure_plugin','openbadger_configure_plugin');
    add_submenu_page(
        'edit.php?post_type=award',
        'rpiBadger | Massen Verleihung',
        'Massen Verleihung',
        (get_option('openbadger_bulk_awards_allow_all') ? $award_type->cap->edit_posts : 'manage_options'),
        'openbadger_bulk_award_badges',
        array( $openbadger_award_schema, 'bulk_award' )
    );
}

function openbadger_admin_notices()
{
    global $openbadger_db_version;

    if ((get_option( 'openbadger_db_version' ) != $openbadger_db_version) && ($_POST[ 'openbadger_db_version' ] != $openbadger_db_version))
    {
		
		$configlink ='<a href="'.admin_url( 'options-general.php?page=openbadger_configure_plugin' ).'">';
		$configlink_end ='</a>';
        ?>
        <div class="updated">
            <p><?php echo sprintf(__('openBadger has been updated! Please go to the %s configuration page %s and update the database', 'rpibadger'),$configlink,$configlink_end);?>.</p>
        </div>
        <?php
    }
    elseif (!openbadger_configured())
    {
        ?>
        <div class="error">
            <p><?php echo sprintf(__('openBadger has been updated! Please go to the %s configuration page %s and update the database', 'rpibadger'),$configlink,$configlink_end);?>.</p>
        </div>
        <?php
    }
}

// Checks two mandatory fields of configured. If options are empty or don't exist, return FALSE
function openbadger_configured()
{
    if (!get_option('openbadger_issuer_org'))
        return false;
    if (!get_option('openbadger_issuer_name'))
        return false;

    if (!get_option('openbadger_awarded_email_subject'))
        return false;
    $tmp = get_option( 'openbadger_awarded_email_html' );
    if (!$tmp || strpos( $tmp, '{AWARD_URL}' ) === false)
        return false;

    return true;
}

function openbadger_shortcode()
{
	
	
	
	
	if(isset($_GET['email'])){
		$email = $_GET['email'];
		$user_name  = str_replace('@', ' (', $email).')';
	}elseif(is_user_logged_in()){
		$user = wp_get_current_user()->data;
		$email = $user->user_email;
		$user_name  = $user->display_name.' ('.$email.')';
	}else{
		$email = '';
		$user_name  = 'Unbekannt';
	}
	
	
	
	
	echo ('Auszeichnungen für '. $user_name.':<br>');
	
		
		$badge_query = new WP_Query(array('post_type' => 'badge'));

		while ( $badge_query->have_posts() ) : 
			
			$badge_query->the_post();
		
			// Query for a user meta, check if email is user meta email
			$award_query = new WP_Query( array(
				'post_status' => 'publish',
				'post_type' => 'award',
				'meta_query' => array(
					array(
						'key' => 'openbadger-award-email-address',
						'value' => $email,
						'compare' => '=',
						'type' => 'CHAR'
						),
					array(
						'key' => 'openbadger-award-choose-badge',
						'value' => get_the_ID(),
						'compare' => '=',
						'type' => 'CHAR'
						)
					)
				)
			);

			// If award has been issued to specific email address, add to params
			if ($award_query) {
				echo('<a href="/awards/'.$award_query->post->post_name.'">'.$award_query->post->post_title. '</a><br>');
				
				//array_push($options, $email);
			}
		endwhile;
		echo '<hr>Verliehene Auszeichnungen anzeigen, die auf die folgende Emailadresse ausgestellt wurden:<form><input type="text" style="width:300px" name="email" value="'.$email.'"><input type="submit" value="Anzeigen"></form>';
}

function openbadger_configure_plugin()
{ 
    global $openbadger_db_version;

    if ($_POST[ 'save' ])
    {
        check_admin_referer( 'openbadger_config' );

        if (!get_option( 'openbadger_issuer_lock' ) || is_super_admin())
        {
            $val = trim( stripslashes( $_POST[ 'openbadger_issuer_name' ] ) );
            if (!empty( $val ))
                update_option( 'openbadger_issuer_name', $val );

            $val = trim( stripslashes( $_POST[ 'openbadger_issuer_org' ] ) );
            if (!empty( $val ))
                update_option( 'openbadger_issuer_org', $val );

            if (is_super_admin())
                update_option( 'openbadger_issuer_lock', (bool)$_POST[ 'openbadger_issuer_lock' ] );
        }

        $val = trim( stripslashes( $_POST[ 'openbadger_issuer_contact' ] ) );
        if (!empty( $val ))
            update_option( 'openbadger_issuer_contact', $val );

        update_option( 'openbadger_bulk_awards_allow_all', (bool)$_POST[ 'openbadger_bulk_awards_allow_all' ] );

        $val = trim( stripslashes( $_POST[ 'openbadger_awarded_email_subject' ] ) );
        if (empty( $val ))
            $val = __( 'You have been awarded the "{BADGE_TITLE}" badge','rpibadger' );
        update_option( 'openbadger_awarded_email_subject', $val );

        $val = trim( stripslashes( $_POST[ 'openbadgerawardedemailhtml' ] ) );
        if (empty( $val ))
            $val = __( <<<EOHTML
Congratulations! {ISSUER_NAME} at {ISSUER_ORG} has awarded you the "<a href="{BADGE_URL}">{BADGE_TITLE}</a>" badge. You can choose to accept or reject the badge into your <a href="http://openbadges.org/">OpenBadges Backpack</a> by following this link:

<a href="{AWARD_URL}">{AWARD_URL}</a>

If you have any issues with this award, please contact <a href="mailto:{ISSUER_CONTACT}">{ISSUER_CONTACT}</a>.
EOHTML
            ,'rpibadger' );
        update_option( 'openbadger_awarded_email_html', $val );

        echo "<div id='message' class='updated'><p>Options successfully updated</p></div>";
    }
    elseif ($_POST[ 'update_db' ])
    {
        global $openbadger_award_schema, $openbadger_badge_schema;

        $query = new WP_Query( array( 'post_type' => $openbadger_badge_schema->get_post_type_name(), 'nopaging' => true ) );
        while ($query->next_post())
        {
            # Migrate the post_content to the description metadata
            $desc = $openbadger_badge_schema->get_post_description( $query->post->ID, $query->post );
            update_post_meta( $query->post->ID, 'openbadger-badge-description', $desc );

            # Validate the post
            $openbadger_badge_schema->save_post_validate( $query->post->ID, $query->post );
        }

        $query = new WP_Query( array( 'post_type' => $openbadger_award_schema->get_post_type_name(), 'nopaging' => true ) );
        while ($query->next_post())
        {
            $openbadger_award_schema->save_post_validate( $query->post->ID, $query->post );
            
            # We just have to assume here that if the award is published then
            # an email was sent
            $tmp = get_post_meta( $query->post->ID, 'openbadger-award-email-sent' );
            if (empty( $tmp ) && $query->post->post_status == 'publish') 
                update_post_meta( $query->post->ID, 'openbadger-award-email-sent', get_post_meta( $query->post->ID, 'openbadger-award-email-address', true ) );
        }

        $tmp = get_option( 'openbadger_awarded_email_subject' );
        if (empty( $tmp ))
            update_option(
                'openbadger_awarded_email_subject',
                __( 'You have been awarded the "{BADGE_TITLE}" badge','rpibadger' )
            );

        $tmp = get_option( 'openbadger_awarded_email_html' );
        if (empty( $tmp ))
        {
            $tmp = get_option( 'openbadger_config_award_email_text' );
            if (empty( $tmp ))
                $tmp = __( <<<EOHTML
Congratulations! {ISSUER_NAME} at {ISSUER_ORG} has awarded you the "<a href="{BADGE_URL}">{BADGE_TITLE}</a>" badge. You can choose to accept or reject the badge into your <a href="http://openbadges.org/">OpenBadges Backpack</a> by following this link:

<a href="{AWARD_URL}">{AWARD_URL}</a>

If you have any issues with this award, please contact <a href="mailto:{ISSUER_CONTACT}">{ISSUER_CONTACT}</a>.
EOHTML
            ,'rpibadger' );

            update_option( 'openbadger_awarded_email_html', $tmp );
        }

        update_option( 'openbadger_db_version', $openbadger_db_version );

        echo "<div class='updated'><p>Database successfully updated</p></div>";
    }

    $issuer_disabled = (get_option('openbadger_issuer_lock') && !is_super_admin()) ? 'disabled="disabled"' : '';

?>
<div class="wrap">
<h2>open Badger Konfiguration</h2>

<form method="POST" action="" name="openbadger_config">
    <?php wp_nonce_field( 'openbadger_config' ); ?>

    <table class="form-table">

        <tr valign="top">
            <th scope="row"><label for="openbadger_issuer_name"><?_e('Issuing Agent Name', 'rpibadger');?></label></th>
            <td>
                <input type="text"
                    id="openbadger_issuer_name"
                    name="openbadger_issuer_name"
                    class="regular-text"
                    value="<?php esc_attr_e( get_option('openbadger_issuer_name') ); ?>"
                    <?php echo $issuer_disabled ?> />
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="openbadger_issuer_org"><?_e('Issuing Organization', 'rpibadger');?></label></th>
            <td>
                <input type="text"
                    id="openbadger_issuer_org"
                    name="openbadger_issuer_org"
                    class="regular-text"
                    value="<?php esc_attr_e( get_option('openbadger_issuer_org') ); ?>"
                    <?php echo $issuer_disabled ?> />
            </td>
        </tr>

        <?php
        if (is_super_admin())
        {
            ?>

            <tr valign="top">
                <th scope="row"></th>
                <td><label>
                    <input type="checkbox"
                        id="openbadger_issuer_lock"
                        name="openbadger_issuer_lock"
                        value="1" <?php echo get_option('openbadger_issuer_lock') ? 'checked="checked"' : '' ?> />
                    <?_e('Disable editting of issuer information for non-admins.', 'rpibadger');?>
                </label></td>
            </tr>
            
            <?php
        }
        ?>

        <tr valign="top">
            <th scope="row"><label for="openbadger_issuer_contact"><?php _e('Contact Email Address', 'rpibadger');?></label></th>
            <td>
                <input type="text"
                    id="openbadger_issuer_contact"
                    name="openbadger_issuer_contact"
                    class="regular-text"
                    value="<?php esc_attr_e( get_option('openbadger_issuer_contact', 'rpibadger') ); ?>" />
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td><label>
                <input type="checkbox"
                    name="openbadger_bulk_awards_allow_all"
                    id="openbadger_bulk_awards_allow_all"
                    value="1"
                    <?php echo get_option('openbadger_bulk_awards_allow_all') ? 'checked="checked"' : '' ?> />
                <? _e('Allow all users to bulk award badges.', 'rpibadger');?>
            </label></td>
        </tr>

    </table>

    <h3 class="title"><? _e('Awarded Email Template', 'rpibadger');?></h3>
    
    <p><? _e('This is the email send when a badge is awarded to a user.  Valid template tags are:', 'rpibadger');?><br>
    <b>{ISSUER_NAME}</b>; <b>{ISSUER_ORG}</b> <b>{ISSUER_CONTACT}</b> <b>{BADGE_TITLE}</b>;
    {BADGE_URL}; {BADGE_IMAGE_URL}; {BADGE_DESCRIPTION}; <b>{AWARD_TITLE}</b>; {AWARD_URL}; {AWARD_ACCEPT_URL}; {EVIDENCE}.<br>
    <? _e('Only <b>bold</b> tags are avilable for the subject.', 'rpibadger');?></p>

    <label for="openbadger-awarded-email-subject"><em><? _e('Subject', 'rpibadger');?></em></label>
    <input type="text"
        name="openbadger_awarded_email_subject"
        id="openbadger-awarded-email-subject"
        class="widefat"
        value="<?php esc_attr_e( get_option( 'openbadger_awarded_email_subject' ) ) ?>" />

    <br /><br />
    <label for="openbadgerawardedemailhtml"><em><? _e('HTML Body', 'rpibadger');?></em></label>
    <?php wp_editor( get_option( 'openbadger_awarded_email_html' ), 'openbadgerawardedemailhtml' ) ?>

    <p class="submit">
        <input type="submit" class="button-primary" name="save" value="<? _e('Save Changes', 'rpibadger'); ?>" />
    </p>

</form>

<form method="POST" action="" name="openbadger_db_update">
    <input type="hidden" name="openbadger_db_version" value="<?php esc_attr_e( $openbadger_db_version ) ?>" />
    <input type="submit" name="update_db" value="<?php _e('Update Database', 'rpibadger'); ?>" />
</form>
</div>

<?php
}

function openbadger_disable_quickedit( $actions, $post ) {
    if( $post->post_type == 'badge' || 'award' ) {
        unset( $actions['inline hide-if-no-js'] );
    }
    return $actions;
}
add_filter( 'post_row_actions', 'openbadger_disable_quickedit', 10, 2 );

function openbadger_template( $template, $values )
{
    $defaults = array(
        '{'                 => '{',
        'ISSUER_NAME'       => get_option( 'openbadger_issuer_name' ),
        'ISSUER_ORG'        => get_option( 'openbadger_issuer_org' ),
        'ISSUER_CONTACT'    => get_option( 'openbadger_issuer_contact' ),
    );

    if (empty( $defaults[ 'ISSUER_CONTACT' ] ))
        $defaults[ 'ISSUER_CONTACT' ] = get_bloginfo( 'admin_email' );

    $values = array_merge( $defaults, $values );

    /*
     * Possible states:
     *
     * - text
     * - tag-open
     * - tag-close
     * - tag-sub
     * - end
     */
    $state = 'text';
    $tag = null;
    $pos = 0;
    $result = '';

    while ($state != 'end')
    {
        #printf( "DEBUG: state = %s; tag = %s; pos = %d\n", $state, $tag, $pos );

        switch ($state)
        {
        case 'text':
            if ($pos >= strlen( $template ))
            {
                $state = 'end';
                break;
            }

            $found = strpos( $template, '{', $pos );
            if ($found === false)
            {
                # No opening tags found. Just append the substring to the
                # result and exit
                $result .= substr( $template, $pos );
                $state = 'end';
            }
            else
            {
                # Found a tag! Append the substring before the tag to
                # the result, advance the $pos, and go to tag-open, unless
                $result .= substr( $template, $pos, ($found - $pos) );
                $pos = $found + 1;

                $state = 'tag-open';
            }
            break;

        case 'tag-open':
            $found = strpos( $template, '}', $pos );

            if ($found === false)
            {
                # We didn't find a valid close tag after our start tag.
                # Just output the start tag and continue on as text.
                $result .= '{';
                $state = 'text';
            }
            else
            {
                # Grab the tag and go to tag-close
                $tag = substr( $template, $pos, ($found - $pos) );
                $state = 'tag-close';
            }
            break;

        case 'tag-close':
            if (!preg_match( '/^(([a-z_]+)|{)$/i', $tag ))
            {
                # Not a valid tag. Output the start tag and continue on.
                $result .= '{';
                $state = 'text';
            }
            else
            {
                # Advance our position and do the tag sub
                $pos += strlen( $tag ) + 1;
                $state = 'tag-sub';
            }
            break;

        case 'tag-sub':
            if (isset( $values[ $tag ] ))
            {
                $result .= $values[ $tag ];
                $state = 'text';
            }
            else
            {
                # If we don't have a substitution to make then go ahead
                # and output the raw tag
                $result .= '{' . $tag . '}';
                $state = 'text';
            }
            break;
        }
    }

    return $result;
}



