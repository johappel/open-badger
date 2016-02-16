<?php
/**
 * Award custom post type.
 *
 * @package openbadger
 */

/**
 * Implements all the filters and actions needed to make the award
 * custom post type work.
 */
class OpenBadger_Award_Schema {
    /** Capability type to use when registering the custom post type. */
    private $post_capability_type;
    /** Name to use when registering the custom post type. */
	private $post_type_name;

    /**
     * Constructs the OpenBadger Award Schema instance. It registers all the hooks
     * needed to support the custom post type. This should only be called once.
     */
    function __construct()
    {
		add_action( 'init', array( $this, 'init' ) );

        add_action( 'load-post.php', array( $this, 'meta_boxes_setup' ) );
        add_action( 'load-post-new.php', array( $this, 'meta_boxes_setup' ) );

		// Add rewrite rules
		add_action( 'generate_rewrite_rules', array( $this, 'generate_rewrite_rules' ) );

        add_action( 'parse_request', array( $this, 'parse_request' ) );
        add_filter( 'posts_search', array( $this, 'posts_search' ), 10, 2 );

        add_filter( 'template_include', array( $this, 'template_include' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
        add_action( 'wp_ajax_openbadger_award_ajax', array( $this, 'ajax' ) );
		
		add_action( 'wp_insert_post', array( $this, 'send_email' ) );
        
        // Runs before saving a new post, and filters the post data
        add_filter( 'wp_insert_post_data', array( $this, 'save_title' ), '99', 2 );
        
        // Runs before saving a new post, and filters the post slug
        add_filter( 'name_save_pre', array( $this, 'save_slug' ) );
        
        add_filter( 'the_content', array( $this, 'content_filter' ) );

        add_action( 'save_post', array( $this, 'save_post_validate' ), 99, 2 );
        add_filter( 'display_post_states', array( $this, 'display_post_states' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		
		add_filter('default_content', array($this,  'set_content'),99,2);
 
	}

    // Accessors and Mutators

    public function get_post_capability_type()
    {
        return $this->post_capability_type;
    }

    public function get_post_type_name()
    {
		return $this->post_type_name;
	}

    private function set_post_capability_type( $new_val = 'post' )
    {
		$this->post_capability_type = apply_filters( 'openbadger_award_post_capability_type', $new_val );
	}

    private function set_post_type_name( $new_val = 'award' )
    {
		$this->post_type_name = apply_filters( 'openbadger_award_post_type_name', $new_val );
	}

	private function get_custom_content( $content , $award_id)
    {
		global $openbadger_badge_schema;
		$badge_id = get_post_meta( $award_id, 'openbadger-award-choose-badge', true );
		$badge_title = get_the_title( $badge_id );
		$badge_desc = $openbadger_badge_schema->get_post_description( $badge_id );
		$badge_image_id = get_post_thumbnail_id( $badge_id );
		$badge_image_url = wp_get_attachment_url( $badge_image_id );
		$badge_url = get_permalink( $badge_id );
		$pre_content  =  '<img src="'.$badge_image_url.'" style="float:left; margin-right:10px"/>';
		$pre_content  .= '<strong>Kompetenz: </strong><em>'.$badge_desc.'</em>';
		$pre_content  .= '<br>';
		$pre_content  .= '<a href="'.$badge_url.'" target="_blank">Kriterien für die Verleihung</a>' ;
		
				
		return $pre_content.$content;
	}
	public function set_content( $content ,$post, $bulk_award = false)
    {
		global $openbadger_badge_schema;
		if($this->post_type_name == $post->post_type || $bulk_award){
			
			$issuer_origin_parts = parse_url( get_site_url() );
			$issuer_origin_url = 'http://' . $issuer_origin_parts[ 'host' ];
			$issuer_name = get_option( 'openbadger_issuer_name' );
			$issuer_org = get_option( 'openbadger_issuer_org' );
			$content  .= 'Diese Auszeichung wird verliehen an:';
			$content  .= '<ul style="font-weight:bold; margin-left:190px">';
			$content  .= '	<li>Name des/der Empfänger/s</li>';
			$content  .= '</ul>';
			$content  .= '<div style="clear:both;"><hr style="margin-top:25px" /></div>';
			$content  .= 'Folgende Leistungen wurden bei der Verleihung des Badges berücksichtigt:';
			$content  .= '<ul>';
			$content  .= '	<li>Erfolgreicher Einrichtung eines ...</li>';
			$content  .= '	<li>Erarbeitung eines ....</li>';
			$content  .= '	<li><a href="#">Webseite/Blogartikel/Video/Dokumentation</a>: <strong>Titel des Werkes</strong></li>';
			$content  .= '</ul>';	
			$content  .= '<div style="clear:both">&nbsp;</div>';
			$content  .= 'Ausgezeichnet durch: '.$issuer_name .' ('. $issuer_org .')';
			
		}
		return $content;
	}
	
	
    // General Filters and Actions

	/**
	 * Add rewrite tags
	 *
	 * @since 1.2
	 */
    function add_rewrite_tags()
    {
		add_rewrite_tag( '%%accept%%', '([1]{1,})' );
		add_rewrite_tag( '%%json%%', '([1]{1,})' );
		add_rewrite_tag( '%%reject%%', '([1]{1,})' );
    }

    function ajax()
    {
        ob_end_clean();
		$award_id = intval( $_POST[ 'award_id' ] );
        $award = get_post( $award_id );
        if (is_null( $award ) || $award->post_type != $this->get_post_type_name())
            die();

        $admin_email = get_settings( 'admin_email' );

        //header( 'Content-Type: text/html' );

        # Only actions are valid on awards that haven't been accepted or
        # rejected yet
        $award_status = get_post_meta( $award_id, 'openbadger-award-status', true );
        if ($award_status != 'Awarded')
        {
            ?>
            <div class="openbadger-award-error">
                <p><?_e('This award has already been claimed.','rpibadger')?></p>
                <p><?_e('If you believe this was done in error, please contact the','rpibadger')?> 
                <a href="mailto:<?php esc_attr_e( $admin_email ) ?>"><?_e('site administrator','rpibadger')?></a>.</p>
				
            </div>
            <?php
            die();
        }

        switch ($_POST[ 'award_action' ])
        {
        case 'accept':
            update_post_meta( $award_id, 'openbadger-award-status', 'Accepted' );
			// If WP Super Cache Plugin installed, delete cache files for award post
            
			//if (function_exists( 'wp_cache_post_change' )) wp_cache_post_change( $award_id );
			
            ?>
            <div class="openbadger-award-updated">
                <p><? echo __( 'You have successfully accepted to add your award to your backpack.', 'rpibadger' );?></p>
            </div>
            <?php
            break;

        case 'reject':
            update_post_meta( $award_id, 'openbadger-award-status', 'Rejected' );
            // If WP Super Cache Plugin installed, delete cache files for award post
            //if (function_exists( 'wp_cache_post_change' )) wp_cache_post_change( $award_id );
            ?>
            <div class="openbadger-award-updated">
                <p><? echo __( 'You have successfully declined to add your award to your backpack.', 'rpibadger' );?></p>
            </div>
            <?php
            break;
        }

       die();
    }

    function content_filter( $content )
    {
        
		
		if (get_post_type() != $this->get_post_type_name())
            return $content;

        $post_id = get_the_ID();

		$content = $this->get_custom_content($content, $post_id);
		
		
        $award_status = get_post_meta( $post_id, 'openbadger-award-status', true );

        if ($award_status == 'Awarded' && isset($_GET['awarded']))
        {
            $badge_title = esc_html( get_the_title( get_post_meta( $post_id, 'openbadger-award-choose-badge', true ) ) );

			$message_congratulation	= sprintf( __( 'Congratulations! The "%1$s" badge has been awarded to you.', 'rpibadger' ),$badge_title);
			$message_choose_start	= __( 'Please choose to', 'rpibadger' );
			$message_choose_accept	= __( 'accept', 'rpibadger' );
			$message_choose_decline = __( 'decline', 'rpibadger' );
			$message_choose_or		= __( 'or', 'rpibadger' );
			$message_choose_end		= __( ' the award', 'rpibadger' );
			$message_ie_issue		= __( 'Microsoft Internet Explorer is not supported at this time. Please use Firefox or Chrome to retrieve your award.', 'rpibadger' );
			$message_error			= __( 'An error occured while adding this badge to your backpack.', 'rpibadger' );
			
			//$message_choose = "$message_choose_start <a href='#' class='acceptBadge'>$message_choose_accept</a> $message_choose_or <a href='#' class='rejectBadge'>$message_choose_decline</a> $message_choose_end.";
			$message_choose = "$message_choose_start <b><a href='#' class='acceptBadge'>$message_choose_accept</a></b> $message_choose_end.";
			
            $content = <<<EOHTML
                <div id="openbadger-award-actions-wrap">
                <div id="openbadger-award-actions" class="openbadger-award-notice">
                    <p>$message_congratulation $message_choose.</p>
                </div>
                <div id="openbadger-award-browser-support" class="openbadger-award-error">
                    <p>$message_ie_issue</p>
                </div>
                <div id="openbadger-award-actions-errors" class="openbadger-award-error">
                    <p>$message_error</p>
                </div>
                </div>
                {$content}
EOHTML;
        }
        elseif ($award_status == 'Rejected')
        {
            $message_declined		= __( 'This award has been declined.', 'rpibadger' );
			return $content .'<div style="clear:both; height:20px"></div><div class="openbadger-award-notice"><p>'.$message_declined.'</p></div>';
        }

        return $content;
    }

	/**
	 * Generates custom rewrite rules
	 *
	 * @since 1.2
	 */
    function generate_rewrite_rules( $wp_rewrite )
    {
		$rules = array(
			// Create rewrite rules for each action
			'awards/([^/]+)/?$' =>
				'index.php?post_type=' . $this->get_post_type_name() . '&name=' . $wp_rewrite->preg_index( 1 ),
			'awards/([^/]+)/accept/?$' =>
				'index.php?post_type=' . $this->get_post_type_name() . '&name=' . $wp_rewrite->preg_index( 1 ) . '&accept=1',
			'awards/([^/]+)/json/?$' =>
				'index.php?post_type=' . $this->get_post_type_name() . '&name=' . $wp_rewrite->preg_index( 1 ) . '&json=1',
			'awards/([^/]+)/reject/?$' =>
				'index.php?post_type=' . $this->get_post_type_name() . '&name=' . $wp_rewrite->preg_index( 1 ) . '&reject=1',
		);

		// Merge new rewrite rules with existing
		$wp_rewrite->rules = array_merge( $rules, $wp_rewrite->rules );

		return $wp_rewrite;
    }

    /**
     * Initialize the custom post type. This registers what we need to
     * support the Award type.
     */
    function init()
    {
        $this->set_post_type_name();
        $this->set_post_capability_type();

		$labels = array(
			'name'                  => __( 'Awards', 'rpibadger' ),
			'singular_name'         => __( 'Award', 'rpibadger' ),
			'add_new'               => __( 'Add New', 'rpibadger' ),
			'add_new_item'          => __( 'Add New Award', 'rpibadger' ),
			'edit_item'             => __( 'Edit Award', 'rpibadger' ),
			'new_item'              => __( 'New Award', 'rpibadger' ),
			'all_items'             => __( 'All Awards', 'rpibadger' ),
			'view_item'             => __( 'View Award', 'rpibadger' ),
			'search_items'          => __( 'Search Awards', 'rpibadger' ),
			'not_found'             => __( 'No awards found', 'rpibadger' ),
			'not_found_in_trash'    => __( 'No award found in Trash', 'rpibadger' ),
			'parent_item_colon'     => '',
			'menu_name'             => __( 'Awards', 'rpibadger' )
		);

		$args = array(
			'labels'                => $labels,
            'public'                => true,
            'exclude_from_search'   => true,
			'query_var'             => true,
			'rewrite' => array(
				'slug'              => 'awards',
				'with_front'        => false
			),
			'capability_type'       => $this->get_post_capability_type(),
			'has_archive'           => false,
			'hierarchical'          => false,
			'supports'              => array( 'editor' )
		);

		register_post_type( $this->get_post_type_name(), $args );

		$this->add_rewrite_tags();
        add_filter( 'manage_' . $this->get_post_type_name() . '_posts_columns', array( $this, 'manage_posts_columns' ), 10);  
        add_action( 'manage_' . $this->get_post_type_name() . '_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 10, 2);  
	}

    /**
     * Limit it so that a user can't request a listing of award posts,
     * unless they happen to be an administrator.
     */
    function parse_request( &$arg )
    {
        $arg_post_type  = $arg->query_vars[ 'post_type' ];
        $arg_name       = $arg->query_vars[ 'name' ];
        $idx            = false;

        # Only restrict listings of the award post_type
        if (!isset( $arg->query_vars[ 'post_type' ] ))
            return;
        if (is_array( $arg_post_type ))
        {
            $idx = array_search( $this->get_post_type_name(), $arg_post_type );
            if ($idx === false)
                return;
        }
        else
        {
            if ($arg_post_type != $this->get_post_type_name())
                return;
        }

        # Don't restrict the listing if a user is logged in and has permission
        # to edit_posts
        $post_type = get_post_type_object( $this->get_post_type_name() );
        if (current_user_can( $post_type->cap->edit_posts ))
            return;

        # Allow only if we're querying by a single name
        if (is_array( $arg_name ))
        {
            $first = reset( $arg_name );
            if (count( $arg_name ) == 1 && !empty( $first ))
                return;
        }
        else
        {
            if (!empty( $arg_name ))
                return;
        }

        # If we reach this point then it's an unpriviledged user querying
        # all the awards. Don't allow this
        if (is_array( $arg_post_type ))
            unset( $arg->query_vars[ 'post_type' ][ $idx ] );
        else
            unset( $arg->query_vars[ 'post_type' ] );
    }

    /**
     * Let admins search awards based on the email address.
     */
    function posts_search( $search, &$query )
    {
        # Only add the metadata in a search
        if (!$query->is_search)
            return $search;
        # Only check for posts that are awards, or might return awards
        $post_type = $query->query_vars[ 'post_type' ];
        if (is_array( $post_type ))
        {
            if (count( array_intersect( array( 'any', $this->get_post_type_name() ), $post_type ) ) == 0)
                return $search;
        }
        else
        {
            if ($post_type != 'any' && $post_type != $this->get_post_type_name())
                return $search;
        }

        if (is_email( $query->query_vars[ 's' ] ))
        {
            # If it is an email then only search on the email address. Clear
            # out the other calculated search
            $query->meta_query->queries[] = array(
                'key'   => 'openbadger-award-email-address',
                'value' => $query->query_vars[ 's' ]
            );
            return '';
        }
        else
            return $search;
    }

    /**
     * Use the JSON template for assertions.
     */
    function template_include()
    {
        global $template;

        if (get_post_type() != $this->get_post_type_name())
            return $template;

        $json = get_query_var( 'json' );

        if ($json)
            return dirname( __FILE__ ) . '/awards_json.php';

        return $template;
    }

    function wp_enqueue_scripts()
    {
        if (get_post_type() != $this->get_post_type_name())
            return;

        if (is_single())
        {
            wp_enqueue_script( 'openbadges', 'https://backpack.openbadges.org/issuer.js', array( 'jquery' ), null );

            wp_enqueue_script( 'openbadger-awards', plugins_url( 'js/awards.js', dirname( __FILE__ ) ), array( 'jquery' ) );
            wp_localize_script( 'openbadger-awards', 'WPBadger_Awards', array(
                'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                'assertion_url' => add_query_arg( 'json', '1', get_permalink() ),
                'award_id'      => get_the_ID()
            ) );
        }
    }


    // Admin Filters and Actions

    function _generate_title( $badge_id )
    {
        return sprintf( __( 'Badge Awarded: %1$s', 'rpibadger' ), get_the_title( $badge_id ) );
    }

    // Generate the award slug. Shared by interface to award single badges, as well as bulk
    function _generate_slug()
    {
        $slug = '';
        if (function_exists( 'openssl_random_pseudo_bytes' ))
        {
            $data = openssl_random_pseudo_bytes( 16 );
            if ($data !== false)
                $slug = bin2hex( $data );
        }

        if (!$slug)
            $slug = rand( 100000000000000, 999999999999999 );

        return $slug;
    }

    /**
     * Display admin notices about invalid posts.
     */
    function admin_notices()
    {
        global $pagenow, $post;

        if ($pagenow != 'post.php')
            return;
        if (get_post_type() != $this->get_post_type_name())
            return;
        if (get_post_status() != 'publish')
            return;

        $valid = $this->check_valid( $post->ID, $post );

        if (!$valid[ 'evidence' ])
            echo '<div class="error"><p>'.__("You must specify award evidence.", 'rpibadger' ).'</p></div>';
        if (!$valid[ 'badge' ])
            echo '<div class="error"><p>'.__("You must choose a badge.", 'rpibadger' ).'</p></div>';
        if (!$valid[ 'email' ])
            echo '<div class="error"><p>'.__("You must enter an email address for the award.", 'rpibadger' ).'</p></div>';
    }

    function bulk_award()
    {
        $badge_id           = intval( $_POST[ 'openbadger-award-choose-badge' ] );
        $email_addresses    = $_POST[ 'openbadger-award-email-addresses' ];
        $evidence           = $_POST[ 'content' ]?$_POST[ 'content' ]:$this->set_content('',null,true);
        $expires            = $_POST[ 'openbadger-award-expires' ];

        if ($_POST[ 'publish' ])
        {
            check_admin_referer( 'openbadger_bulk_award_badges' );

            $errors = array();

            if (empty( $badge_id ))
                $errors[] = __( 'You must choose a badge to award.', 'rpibadger' );

            $emails = array();
            foreach (preg_split( '/[\n,]/', $email_addresses, -1, PREG_SPLIT_NO_EMPTY ) as $email)
            {
                $email = trim( $email );
                if (!empty( $email ))
                    $emails[] = $email;
            }
                
            if (count( $emails ) == 0)
                $errors[] = __( 'You must specify at least one email address.', 'rpibadger' );
            else
            {
                foreach ($emails as $email)
                {
                    if (!is_email( $email ))
                        $errors[] = sprintf( __( 'The email address "%1$s" is not valid.', 'rpibadger' ), $email );
                }
            }

            $tmp = trim( strip_tags( $evidence ) );
            if (empty( $tmp ))
                $errors[] = __( 'You must enter some evidence for the awards.', 'rpibadger' );

            if (count( $errors ) > 0)
            {
                foreach ($errors as $error)
                {
                    ?>
                    <div class='error'><p><?php esc_html_e( $error ) ?></p></div>
                    <?php
                }
            }
            else
            {
                foreach ($emails as $email)
                {
                    // Usually this hook gets run for metaboxes only; add it here and we'll
                    // get most of the metadata we need automatically
                    add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
                    $_POST[ 'openbadger-award-email-address' ] = $email;

                    // Insert a new post for each award
                    $post = array(
                        'post_content'  => $evidence,
                        'post_status'   => 'publish',
                        'post_type'     => $this->get_post_type_name(),
                        'post_name'     => $this->_generate_slug()
                    );

                    $post_id = wp_insert_post( $post, $wp_error );
                }

                ?>
                <div class="updated">
                    <p><?=__( 'Badges were awarded successfully. You can view a list of', 'rpibadger' )?>
                    <a href="<?php esc_attr_e( admin_url( 'edit.php?post_type=' . $this->get_post_type_name() ) ) ?>"><?=__( 'all awards', 'rpibadger' )?></a>.
                    </p>
                </div>
                <?php

                $badge_id           = 0;
                $email_addresses    = '';
                $evidence           = '';
                $expires            = '';
            }
        }

        ?>
        <h2><?=__('Award Badges in Bulk',  'rpibadger')?></h2>

        <div class="wrap">
        <form method="POST" action="" name="openbadger_bulk_award_badges">
            <?php wp_nonce_field( 'openbadger_bulk_award_badges' ); ?>
            <?php wp_nonce_field( basename( __FILE__ ), 'openbadger_award_nonce' ); ?>

            <table class="form-table">
                <tr valign="top">
                <th scope="row"><label for="openbadger_award_choose_badge"><?=__('Badge',  'rpibadger')?></label></th>
                <td>
                    <select name="openbadger-award-choose-badge" id="openbadger_award_choose_badge">

                    <?php 	
                    $query = new WP_Query( array(
                        'post_type'     => 'badge',
                        'post_status'   => 'publish',
                        'nopaging'      => true,
                        'meta_query' => array(
                            array(
                                'key'   => 'openbadger-badge-valid',
                                'value' => true
                            )
                        )
                    ) );

                    while ($query->next_post())
                    {
                        $title_version = esc_html( get_the_title( $query->post->ID ) . " (" . get_post_meta( $query->post->ID, 'openbadger-badge-version', true ) . ")" );

                        $selected = '';
                        if ($badge_id == $query->post->ID)
                            $selected = ' selected="selected"';

                        echo "<option value='{$query->post->ID}'{$selected}>{$title_version}</option>";
                    }
                    ?>

                    </select>
                </td>
                </tr>

                <tr valign="top">
                <th scope="row"><label for="openbadger_award_email_addresses"><?=__('Email Address',  'rpibadger')?></label></th>
                <td>
                <textarea name="openbadger-award-email-addresses" id="openbadger_award_email_addresses" rows="5" cols="45"><?php echo esc_textarea( $email_addresses ) ?></textarea>
                    <br />
                    <?=__('Separate multiple email addresses with commas, or put one per line.',  'rpibadger')?>
                </td>
                </tr>

                <tr valign="top">
                <th scope="row"><label for="content"><?=__('Evidence',  'rpibadger')?></label></th>
                <td><?php wp_editor( $evidence, 'content' ) ?></td>
                </tr>

                <tr valign="top">
                <th scope="row"><label for="openbadger_award_expires"><?=__('Expiration Date',  'rpibadger')?></label></th>
                <td>
                <input type="text" name="openbadger-award-expires" id="openbadger_award_expires" value="<?php esc_attr_e( $expires ) ?>" />
                    <br />
                    <?=__('Optional. Enter as "YY-MM-DD"',  'rpibadger')?>.</td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button-primary" name="publish" value="<?php _e('Publish') ?>" />
            </p>

        </form>
        </div>
        <?php
    }

    /**
     * Checks that an award post is valid. Returns an array with the parts checked, and
     * an overall results. Array keys:
     *
     * - evidence
     * - email
     * - badge
     * - status
     * - all
     *
     * @return array
     */
    function check_valid( $post_id, $post = null )
    {
        if (is_null( $post ))
            $post = get_post( $post_id );

        $rv = array(
            'evidence'      => false,
            'email'         => false,
            'badge'         => false,
            'status'        => false
        );

        # Check that the evidence is not empty. We're going to
        # strip the tags and spaces just to make sure that it isn't
        # empty
        $evidence = trim( strip_tags( $post->post_content ) );
        if (!empty( $evidence ))
            $rv[ 'evidence' ] = true;

        $email = get_post_meta( $post_id, 'openbadger-award-email-address', true );
        if (!empty( $email ) && is_email( $email ))
            $rv[ 'email' ] = true;

        $badge = get_post_meta( $post_id, 'openbadger-award-choose-badge', true );
        if (!empty( $badge ))
            $rv[ 'badge' ] = true;

        if ($post->post_status == 'publish')
            $rv[ 'status' ] = true;

        $rv[ 'all' ] = $rv[ 'evidence' ] && $rv[ 'email' ] && $rv[ 'badge' ] && $rv[ 'status' ];

        return $rv;
    }

    /**
     * If the award is invalid, add it to the list of post states.
     */
    function display_post_states( $post_states )
    {
        if (get_post_type() != $this->get_post_type_name())
            return $post_states;

        if (get_post_status() == 'publish')
        {
            $valid = get_post_meta( get_the_ID(), 'openbadger-award-valid', true );
            if (!$valid)
                $post_states[ 'openbadger-award-state' ] = '<span class="openbadger-award-state-invalid">'.__( "Invalid", 'rpibadger' ).'</span>';
        }

        return $post_states;
    }

    function manage_posts_columns( $defaults )
    {  
        $defaults[ 'award_email' ] = __( 'Issued To Email', 'rpibadger' );
        $defaults[ 'award_status' ] = __( 'Award Status', 'rpibadger' );

        return $defaults;  
    }  

    function manage_posts_custom_column( $column_name, $post_id )
    {  
        switch ($column_name)
        {
        case 'award_email':
            esc_html_e( get_post_meta( $post_id, 'openbadger-award-email-address', true ) );
            break;

        case 'award_status':
            esc_html_e( get_post_meta( $post_id, 'openbadger-award-status', true ) );
            break;
        }
    }

    // Create metaboxes for post editor
    function meta_boxes_add()
    {
        add_meta_box(
            'openbadger-award-information',
            esc_html__( 'Award Information', 'rpibadger' ),
            array( $this, 'meta_box_information' ),
            $this->get_post_type_name(),
            'side',
            'default'
        );
    }

    function meta_box_information( $object, $box )
    {
        global $openbadger_badge_schema;

        wp_nonce_field( basename( __FILE__ ), 'openbadger_award_nonce' );

        $is_published = ('publish' == $object->post_status || 'private' == $object->post_status);
        $award_badge_id = get_post_meta( $object->ID, 'openbadger-award-choose-badge', true );
        $award_email = get_post_meta( $object->ID, 'openbadger-award-email-address', true );
        $award_status = get_post_meta( $object->ID, 'openbadger-award-status', true );

        ?>
        <div id="openbadger-award-actions">
        <div class="openbadger-award-section openbadger-award-badge">
            <label for="openbadger-award-choose-badge">Badge: </label>
        <?php 	

        if (!$is_published || current_user_can( 'manage_options' ))
        {
            echo '<select name="openbadger-award-choose-badge" id="openbadger-award-choose-badge">';

            $query = new WP_Query( array( 'post_type' => 'badge', 'nopaging' => true ) );
            while ($query->next_post())
            {
                $badge_id = $query->post->ID;
                $badge_title_version = get_the_title( $badge_id ) . " (" . get_post_meta( $badge_id, 'openbadger-badge-version', true ) . ")";

                // As we iterate through the list of badges, if the chosen badge has the same ID then mark it as selected
                if ($award_badge_id == $badge_id)
                    $selected = ' selected="selected"';
                else
                    $selected = '';

                $valid = $openbadger_badge_schema->check_valid( $badge_id, $query->post );
                if ($valid[ 'all' ])
                    $disabled = '';
                else
                    $disabled = ' disabled="disabled"';

                echo "<option value='{$badge_id}'{$selected}{$disabled}>{$badge_title_version}</option>";
            }

            echo '</select>';
        }
        else
        {
            $badge_title_version = get_the_title( $award_badge_id ) . " (" . get_post_meta( $award_badge_id, 'openbadger-badge-version', true ) . ")";
            echo "<b>" . $badge_title_version . "</b>";
        }

        ?>
        </div>
        <div class="openbadger-award-section openbadger-award-email-address">
            <label for="openbadger-award-email-address">Email Address:</label><br />
        <?php

        if (!$is_published || current_user_can( 'manage_options' ))
            echo '<input type="text" name="openbadger-award-email-address" id="openbadger-award-email-address" value="' . esc_attr($award_email) . '" />';
        else
            echo '<b>' . esc_html( $award_email ) . '</b>';

        ?>
        </div>
        <?php
        
        if ($is_published)
        {
            ?>
            <div class="openbadger-award-section openbadger-award-status">
                Status: <b><?php echo esc_html( $award_status ) ?></b>
            </div>
            <?php
        }

        echo '</div>';
    }

    function meta_boxes_setup()
    {
        add_action( 'add_meta_boxes', array( $this, 'meta_boxes_add' ) );
        add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
    }

    function save_post( $post_id, $post )
    {
        if (!isset( $_POST['openbadger_award_nonce'] ) || !wp_verify_nonce( $_POST[ 'openbadger_award_nonce' ], basename( __FILE__ ) ))
            return $post_id;

        $post_type = get_post_type_object( $post->post_type );

        if (!current_user_can( $post_type->cap->edit_post, $post_id ))
            return $post_id;

        $meta_key = 'openbadger-award-choose-badge';
        $new_value = $_POST['openbadger-award-choose-badge'];
        $old_value = get_post_meta( $post_id, $meta_key, true );

        if ($new_value && empty( $old_value ))
            add_post_meta( $post_id, $meta_key, $new_value, true );
        elseif (current_user_can( 'manage_options' ))
        {
            if ($new_value && $new_value != $old_value)
                update_post_meta( $post_id, $meta_key, $new_value );
            elseif (empty( $new_value ))
                delete_post_meta( $post_id, $meta_key, $old_value );
        }

        $meta_key = 'openbadger-award-email-address';
        $new_value = $_POST['openbadger-award-email-address'];
        $old_value = get_post_meta( $post_id, $meta_key, true );

        if ($new_value && empty( $old_value ))
            add_post_meta( $post_id, $meta_key, $new_value, true );
        elseif (current_user_can( 'manage_options' ))
        {
            if ($new_value && $new_value != $old_value)
                update_post_meta( $post_id, $meta_key, $new_value );
            elseif (empty( $new_value ))
                delete_post_meta( $post_id, $meta_key, $old_value );	
        }

        $meta_key = 'openbadger-award-expires';
        $new_value = $_POST[ 'openbadger-award-expires' ];
        $old_value = get_post_meta( $post_id, $meta_key, true );

        if ($new_value && empty( $old_value ))
            add_post_meta( $post_id, $meta_key, $new_value, true );
        elseif (current_user_can( 'manage_options' ))
        {
            if ($new_value && $new_value != $old_value)
                update_post_meta( $post_id, $meta_key, $new_value );
            elseif (empty( $new_value ))
                delete_post_meta( $post_id, $meta_key, $old_value );	
        }

        if (get_post_meta( $post_id, 'openbadger-award-status', true ) == false)
            add_post_meta( $post_id, 'openbadger-award-status', 'Awarded' );

        // Add the salt only the first time, and do not update if already exists
        if (get_post_meta( $post_id, 'openbadger-award-salt', true ) == false)
        {
            $salt = substr( str_shuffle( str_repeat( "0123456789abcdefghijklmnopqrstuvwxyz", 8 ) ), 0, 8 );
            add_post_meta( $post_id, 'openbadger-award-salt', $salt );
        }
    }

    function save_post_validate( $post_id, $post )
    {
        if ($post->post_type != $this->get_post_type_name())
            return;

        $valid = $this->check_valid( $post_id, $post );

        update_post_meta( $post_id, 'openbadger-award-valid', $valid[ 'all' ] );
    }

    function save_slug( $slug )
    {
        if ($_REQUEST[ 'post_type' ] == $this->get_post_type_name())
            return $this->_generate_slug();		

        return $slug;
    }

    function save_title( $data, $postarr )
    {
        
		if ($postarr[ 'post_type' ] != $this->get_post_type_name())
            return $data;

		$data[ 'comment_status' ] = 'closed';
		$data[ 'ping_status' ] = 'closed';
		
        $data[ 'post_title' ] = $this->_generate_title( $_POST[ 'openbadger-award-choose-badge' ] );
        return $data;
    }

    function send_email( $post_id )
    {
        // Verify that post has been published, and is an award
        if (get_post_type( $post_id ) != $this->get_post_type_name())
            return;
        if (!get_post_meta( $post_id, 'openbadger-award-valid', true ))
            return;
        if (get_post_meta( $post_id, 'openbadger-award-status', true ) != 'Awarded')
            return;
        if (!openbadger_configured())
            return;

        $badge_id = (int)get_post_meta( $post_id, 'openbadger-award-choose-badge', true );
        if (!$badge_id)
            return;

        $email_address = get_post_meta( $post_id, 'openbadger-award-email-address', true );
        if (get_post_meta( $post_id, 'openbadger-award-email-sent', true ) == $email_address)
            return;

        $badge_title    = get_the_title( $badge_id );
        $badge_url      = get_permalink( $badge_id );
        $badge_image_id = get_post_thumbnail_id( $badge_id );
        $badge_image_url = wp_get_attachment_url( $badge_image_id );
        $badge_desc     = get_post_meta( $badge_id, 'openbadger-badge-description', true );

        $award          = get_post( $post_id );
        $award_title    = get_the_title( $post_id );
        $award_url      = get_permalink( $post_id );
        $award_evidence = $award->post_content;

        $subject = openbadger_template(
            get_option( 'openbadger_awarded_email_subject' ),
            array(
                'BADGE_TITLE'   => $badge_title,
                'AWARD_TITLE'   => $award_title
            )
        );
        $subject = apply_filters( 'openbadger_awarded_email_subject', $subject );

        $message = openbadger_template(
            get_option( 'openbadger_awarded_email_html' ),
            array(
                'BADGE_TITLE'       => esc_html( $badge_title ),
                'BADGE_URL'         => $badge_url,
                'BADGE_IMAGE_URL'   => $badge_image_url,
                'BADGE_DESCRIPTION' => esc_html( $badge_desc ),
                'AWARD_TITLE'       => esc_html( $award_title ),
                'AWARD_URL'         => $award_url,
                'AWARD_ACCEPT_URL'  => $award_url.'?awarded',
                'EVIDENCE'          => $award_evidence
            )
        );

        add_filter( 'openbadger_awarded_email_html', 'wptexturize'        );
        add_filter( 'openbadger_awarded_email_html', 'convert_chars'      );
        add_filter( 'openbadger_awarded_email_html', 'wpautop'            );
        $message = apply_filters( 'openbadger_awarded_email_html', $message );

        wp_mail( $email_address, $subject, $message, array( 'Content-Type: text/html' ) );
        update_post_meta( $post_id, 'openbadger-award-email-sent', $email_address );
    }
}

$GLOBALS[ 'openbadger_award_schema' ] = new OpenBadger_Award_Schema();

