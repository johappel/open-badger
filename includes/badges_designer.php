<?php
/**
 * Support functions and hooks to add a badge image designer to the
 * 'Badge Image' metabox. Right now we embed OpenBadges.me, but the
 * hope is that in the future we can support multiple badge designer
 * APIs.
 *
 * @package openbadger
 */

/* Hooks to modify the thumbnail metabox HTML, and handle the AJAX callback */
add_filter( 'admin_post_thumbnail_html', 'openbadger_badgedesigner_admin_post_thumbnail_html', 10, 2 );
add_action( 'wp_ajax_openbadger_badgedesigner_publish', 'openbadger_ajax_badgedesigner_publish' );

/**
 * For badge pages, change the feature image content to add the designer. This
 * gets called as a filter by WP whenever the content inside the postimagediv
 * needs to be updated (even via AJAX).
 */
function openbadger_badgedesigner_admin_post_thumbnail_html( $content, $post_id )
{
    global $openbadger_badge_schema;

    if (get_post_type( $post_id ) != $openbadger_badge_schema->get_post_type_name())
        return $content;

    /* if we already have a thumbnail, don't modify the content, just return */
    if (has_post_thumbnail( $post_id ))
        return $content;

    /* Copied from wp-admin/includes/post.php */
    $upload_iframe_src = esc_url( get_upload_iframe_src( 'image', $post_id ) );
    $media_manager_link = sprintf( '<a title="%2$s" href="%1$s" id="set-post-thumbnail" class="thickbox">%3$s</a>',
        $upload_iframe_src,
        esc_attr__( 'Media Library' ),
        esc_html__( 'Media Library' )
    );

    if (!is_multisite())
    {
        $origin = network_site_url();
        $email = get_site_option( 'admin_email' );
    }
    else
    {
        $origin = site_url();
        $email = get_bloginfo( 'admin_email' );
    }

    $designer_link = sprintf( '<a href="https://www.openbadges.me/designer.html?format=json&amp;origin=%3$s&amp;email=%4$s&amp;uniqueID=%7$s" title="%1$s" id="openbadger-badge-designer" data-post-id="%5$d" data-nonce="%6$s" data-badge-source="openbadges.me">%2$s</a>',
        esc_attr__( 'Badge Designer', 'rpibadger' ),
        esc_html__( 'Badge Designer', 'rpibadger' ),
        urlencode( $origin ),
        urlencode( $email ),
        $post_id,
        esc_attr( wp_create_nonce( 'openbadger-badgedesigner' ) ),
        urlencode( get_current_user_id() )
    );
	
	return '<p class="hide-if-no-js">'. sprintf(__('Use the %s to upload or select an existing image. Or use the %s to create a new one', 'rpibadger'), $media_manager_link, $designer_link ).'.</p>';
    
    ;
}

/**
 * Deletes a temporary file as part of the shutdown of a request.
 */
function _openbadger_badgedesigner_ajax_cleanup( $file )
{
    @unlink( $file );
}

/**
 * Handle a media upload that came from AJAX, but was never present in $_FILES.
 * This is here because the original relies on move_upload_file(), and that won't
 * work for us.
 *
 * Copied from wp-admin/includes/media.php media_handle_upload.
 */
function _openbadger_badgedesigner_media_handle_upload( $_f, $post_id, $post_data = array(), $overrides = array( 'test_form' => false ) )
{
    $time = current_time( 'mysql' );
    if ($post = get_post( $post_id ))
    {
        if (substr( $post->post_date, 0, 4 ) > 0)
            $time = $post->post_date;
    }

    $name = $_f[ 'name' ];
    $file = _openbadger_badgedesigner_wp_handle_upload( $_f, $overrides, $time );

    if (isset( $file[ 'error' ] ))
        return new WP_Error( 'upload_error', $file[ 'error' ] );

    $name_parts = pathinfo( $name );
    $name = trim( substr( $name, 0, -(1 + strlen( $name_parts[ 'extension' ] )) ) );

    $url = $file[ 'url' ];
    $type = $file[ 'type' ];
    $file = $file[ 'file' ];
    $title = $name;
    $content = '';

    if ($image_meta = @wp_read_image_metadata( $file ))
    {
        if (trim( $image_meta[ 'title' ] ) && !is_numeric( sanitize_title( $image_meta[ 'title' ] ) ))
            $title = $image_meta[ 'title' ];
        if (trim( $image_meta[ 'caption' ] ))
            $content = $image_meta[ 'caption' ];
    }

    // Construct the attachment array
    $attachment = array_merge( array(
        'post_mime_type' => $type,
        'guid' => $url,
        'post_parent' => $post_id,
        'post_title' => $title,
        'post_content' => $content,
    ), $post_data );

    // This should never be set as it would then overwrite an existing attachment.
    if (isset( $attachment[ 'ID' ] ))
        unset( $attachment[ 'ID' ] );

    // Save the data
    $id = wp_insert_attachment( $attachment, $file, $post_id );
    if (!is_wp_error( $id ))
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );

    return $id;
}

/**
 * Handle errors inside of _openbadger_badgedesigner_wp_handle_upload. Just returns an array with the
 * error message.
 */
function _openbadger_badgedesigner_wp_handle_upload_error( &$file, $message )
{
    return array( 'error' => $message );
}

/**
 * Handle an upload that came from AJAX, but was never present in $_FILES.
 * This is here because the original relies on move_upload_file(), and that won't
 * work for us.
 *
 * Copied from wp-admin/includes/file.php wp_handle_upload.
 */
function _openbadger_badgedesigner_wp_handle_upload( &$file, $overrides = false, $time = null )
{
    $file = apply_filters( 'wp_handle_upload_prefilter', $file );

    // You may define your own function and pass the name in $overrides['upload_error_handler']
    $upload_error_handler = '_openbadger_badgedesigner_wp_handle_upload_error';

    // You may have had one or more 'wp_handle_upload_prefilter' functions error out the file. Handle that gracefully.
    if (isset( $file[ 'error' ] ) && !is_numeric( $file[ 'error' ] ) && $file[ 'error' ])
        return call_user_func( $upload_error_handler, $file, $file['error'] );

    // You may define your own function and pass the name in $overrides['unique_filename_callback']
    $unique_filename_callback = null;

    // $_POST['action'] must be set and its value must equal $overrides['action'] or this:
    $action = 'wp_handle_upload';

    // Courtesy of php.net, the strings that describe the error indicated in $_FILES[{form field}]['error'].
    $upload_error_strings = array( false,
        __( "The uploaded file exceeds the upload_max_filesize directive in php.ini." ),
        __( "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form." ),
        __( "The uploaded file was only partially uploaded." ),
        __( "No file was uploaded." ),
        '',
        __( "Missing a temporary folder." ),
        __( "Failed to write file to disk." ),
        __( "File upload stopped by extension." ));

    // All tests are on by default. Most can be turned off by $overrides[{test_name}] = false;
    $test_form = true;
    $test_size = true;
    $test_upload = true;

    // If you override this, you must provide $ext and $type!!!!
    $test_type = true;
    $mimes = false;

    // Install user overrides. Did we mention that this voids your warranty?
    if ( is_array( $overrides ) )
        extract( $overrides, EXTR_OVERWRITE );

    // A correct form post will pass this test.
    if ($test_form && (!isset( $_POST[ 'action' ] ) || ($_POST[ 'action' ] != $action)))
        return call_user_func( $upload_error_handler, $file, __( 'Invalid form submission.' ) );

    // A successful upload will pass this test. It makes no sense to override this one.
    if ($file[ 'error' ] > 0)
        return call_user_func( $upload_error_handler, $file, $upload_error_strings[ $file[ 'error' ] ] );

    // A non-empty file will pass this test.
    if ($test_size && !($file[ 'size' ] > 0 ))
    {
        if (is_multisite())
            $error_msg = __( 'File is empty. Please upload something more substantial.' );
        else
            $error_msg = __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.' );
        return call_user_func( $upload_error_handler, $file, $error_msg );
    }

    if ($test_type)
    {
        // A correct MIME type will pass this test. Override $mimes or use the upload_mimes filter.
        $wp_filetype = wp_check_filetype_and_ext( $file[ 'tmp_name' ], $file[ 'name' ], $mimes );
        extract( $wp_filetype );

        // Check to see if wp_check_filetype_and_ext() determined the filename was incorrect
        if ($proper_filename)
            $file[ 'name' ] = $proper_filename;

        if ((!$type || !$ext) && !current_user_can( 'unfiltered_upload' ))
            return call_user_func( $upload_error_handler, $file, __( 'Sorry, this file type is not permitted for security reasons.' ) );

        if (!$ext)
            $ext = ltrim( strrchr( $file[ 'name' ], '.' ), '.' );

        if (!$type)
            $type = $file[ 'type' ];
    }
    else
        $type = '';

    // A writable uploads dir will pass this test. Again, there's no point overriding this one.
    if (!(($uploads = wp_upload_dir( $time )) && false === $uploads[ 'error' ]))
        return call_user_func( $upload_error_handler, $file, $uploads[ 'error' ] );

    $filename = wp_unique_filename( $uploads[ 'path' ], $file[ 'name' ], $unique_filename_callback );

    // Move the file to the uploads dir
    $new_file = $uploads[ 'path' ] . "/$filename";
    if (false === @copy( $file[ 'tmp_name' ], $new_file ))
        return array( 'error' => sprintf( __('The uploaded file could not be moved to %s.' ), $uploads['path'] ) );

    // Set correct file permissions
    $stat = stat( dirname( $new_file ));
    $perms = $stat[ 'mode' ] & 0000666;
    @chmod( $new_file, $perms );

    // Compute the URL
    $url = $uploads[ 'url' ] . "/$filename";

    if (is_multisite())
        delete_transient( 'dirsize_cache' );

    return apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ), 'upload' );
}

function openbadger_badgedesigner_openbadges_pre_upload( $post_id, $raw_badge, &$post_data, &$stash )
{
    /* Next bits take the OpenBadges.me response and parse it. We do these
     * steps:
     *
     * 1. Decocde the JSON response
     * 2. Check that badge.image starts with 'data:'
     * 3. Get the mime-type and encoding from the 'data' string
     * 4. Decode the data
     * 5. Save it in a temporary file and construct and array just like $_FILES would
     */
    $badge = @json_decode( $raw_badge, true );
    if (!$badge)
        wp_send_json_error( array(
            'message'   => __( 'Error decoding the badge designer data.', 'rpibadger' ),
            'filename'  => 'badge.png',
        ) );
    /* save for later */
    $stash[ 'badge' ] = $badge;

    /* get the image header (mime and encoding) and the data */
    if (substr( $badge[ 'image' ], 0, 5 ) != 'data:')
        wp_send_json_error( array(
            'message'   => __('Error decoding the badge designer image data.', 'rpibadger' ),
            'filename'  => 'badge.png',
        ) );

    $pos = strpos( $badge[ 'image' ], ',', 5 );
    if (($pos === false) || ($pos <= 5))
        wp_send_json_error( array(
            'message'   => __('Error decoding the badge designer image data.', 'rpibadger' ),
            'filename'  => 'badge.png',
        ) );
    $image_hdr = explode( ';', substr( $badge[ 'image' ], 5, ($pos - 5) ) );
    $image_data = substr( $badge[ 'image' ], $pos + 1 );

    switch ($image_hdr[ 1 ])
    {
    case 'base64':
        $image_data = @base64_decode( $image_data );
        if ($image_data === false)
            wp_send_json_error( array(
                'message'   => __( 'Error decoding the badge designer image data: bad base64 data.', 'rpibadger' ),
                'filename'  => 'badge.png',
            ) );
        break;

    default:
        wp_send_json_error( array(
            'message'   => __( 'Error decoding the badge designer image data: unknown encoding.', 'rpibadger' ),
            'filename'  => 'badge.png',
        ) );
    }

    /* fake our file upload */
    $_f = array(
        'tmp_name'  => tempnam( sys_get_temp_dir(), 'openbadger-badgedesigner-' ),
        'type'      => $image_hdr[ 0 ],
        'error'     => 0,
        'size'      => strlen( $image_data ),
    );
    $_f[ 'name' ] = pathinfo( $_f[ 'tmp_name' ], PATHINFO_BASENAME ) . '.png';
    register_shutdown_function( '_openbadger_badgedesigner_ajax_cleanup', $_f[ 'tmp_name' ] );

    if (file_put_contents( $_f[ 'tmp_name' ], $image_data ) === false)
        wp_send_json_error( array(
            'message'   => __( 'Error saving the badge designer image.', 'rpibadger' ),
            'filename'  => $_f[ 'name' ],
        ) );

    /* try to build a title for this image from the badgeText, and if that isn't available
     * use the concat of just the 'text' lines.
     */
    $title = sanitize_title( $badge[ 'badgeText' ][ 'value' ] );
    if (empty( $title ) || is_numeric( $title ))
    {
        $title = sanitize_title( $badge[ 'text' ][ 'value' ] . ' ' . $badge[ 'text' ][ 'value2' ] );
        if (is_numeric( $title ))
            $title = '';
    }

    $post_data[ 'title' ] = $title;

    return $_f;
}

function openbadger_badgedesigner_openbadges_post_upload( $post_id, $attachment_id, &$stash )
{
    $badge = $stash[ 'badge' ];

    # Remove the binary data from the badge info before saving it
    unset( $badge[ 'image' ] );
    add_post_meta( $attachment_id, 'openbadger-badgedesigner-openbadges.me-data', $badge, true );
}

/**
 * AJAX callback that handles a JSON response from OpenBadges.me, saves the image in
 * the media library, and then sets it as the post feature image.
 */
function openbadger_ajax_badgedesigner_publish()
{
    /* checks copied from wp-admin/async-upload.php */
    nocache_headers();

    /* copied from wp-admin/includes/ajax-actions.php wp_ajax_upload_attachment */
    check_ajax_referer( 'openbadger-badgedesigner', 'nonce' );

    if (!current_user_can( 'upload_files' ))
        wp_die();

    $post_id = null;
    if (isset( $_POST[ 'post_id' ] ))
    {
        $post_id = $_POST[ 'post_id' ];
        if (!current_user_can( 'edit_post', $post_id ))
            wp_die();
    }

    $badge_source = $_POST[ 'badge_source' ];
    if (empty( $badge_source ))
        $badge_source = 'openbadges.me';

    $post_data = array();
    $stash = array();

    switch ($badge_source)
    {
        case 'openbadges.me':
            $_f = openbadger_badgedesigner_openbadges_pre_upload(
                $post_id,
                stripslashes( $_POST[ 'badge' ] ),
                $post_data,
                $stash
            );
            break;
    }

    /* DO THE UPLOAD. HOORAY! */
    $attachment_id = _openbadger_badgedesigner_media_handle_upload(
        $_f,
        $post_id,
        $post_data,
        array(
            'test_form' => false,
            'mimes' => array( 'png' => 'image/png' )
        )
    );

    if (is_wp_error( $attachment_id ))
        wp_send_json_error( array(
            'message'  => $attachment_id->get_error_message(),
            'filename' => $_f[ 'name' ],
        ) );

    add_post_meta( $attachment_id, 'openbadger-badgedesigner-source', $badge_source, true );
    switch ($badge_source)
    {
        case 'openbadges.me':
            openbadger_badgedesigner_openbadges_post_upload( $post_id, $attachment_id, $stash );
            break;
    }

    if (!set_post_thumbnail( $post_id, $attachment_id ))
        wp_send_json_error( array(
            'message'   => __( 'Unable to set the badge as the featured image.', 'rpibadger' ),
            'filename'  => $_f[ 'name' ],
        ) );

    if (!($attachment = wp_prepare_attachment_for_js( $attachment_id )))
        wp_die();

    $attachment[ 'postimagediv' ] = _wp_post_thumbnail_html( $attachment_id, $post_id );
    wp_send_json_success( $attachment );
}


