<?php


class Steem_Post_Updates {
	var $defaults;

	const ADMIN_PAGE = 'steem_post_updates';
	const OPTION_GROUP = 'steem_post_updates';
	const OPTION = 'steem_post_updates';

	static function init() {
		static $instance = null;

		if ( $instance )
			return $instance;

		$class = __CLASS__;
		$instance = new $class;
		return $instance;
	}

	function __construct() {
		$this->defaults = apply_filters( 'steem_post_updates_default_options', array(
			'enable'     => 1,
			'users'      => array(),
			'userinfo'     => array( get_option( 'admin_email' ) ),
			'post_types' => array( 'post', 'page' ),
			'drafts'     => 0,
		) );


		$options = $this->get_options();

		if ( $options['enable'] ) {
			//add_action( 'save_post', array( $this, 'post_saved' ), 10, 3 );
			//add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );
			//add_action( 'epc_new_bbpress_item', array( $this, 'post_updated' ), 10, 3 );  // Support for bbPress 2
			// register script
			add_action('admin_enqueue_scripts', array($this, 'register_scripts'));

		}

		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 115 );
		}
	}
	
	function register_scripts($page) {
		global $post; 
	    if ( $page == 'post-new.php' || $page == 'post.php' ) {
	    		error_log("Post type ". $post->post_type);
	        if ( 'post' === $post->post_type && isset($_GET['message']) ) { 
				$message_id = absint( $_GET['message'] );
				$options = $this->get_options();
				$the_title = $post->post_title;
				$content = apply_filters('the_content', $post->post_content);
				$content = str_replace(']]>', ']]&gt;', $content);

				wp_register_script( 'steem.min', 'https://cdn.steemjs.com/lib/latest/steem.min.js' );
				wp_enqueue_script('test', plugins_url('/js/steem-post.js', __FILE__), array( 'jquery', 'steem.min' ), true);
				$data = array( 'ID' => $options['userinfo'][0],
							'Token' => $options['userinfo'][1],
							'Tags' => $options['userinfo'][2],
							'Title' => $the_title,
							'Content' => $content,
							'Message' => $message_id,
							'Post_ID' => $post->ID,
							'Slug' => $post->post_name
				);
				wp_localize_script( 'test', 'wpsePost', $data );
	        }
	    }
	}


	function get_post_types() {
		$post_types = get_post_types( array( 'public' => true ) );
		$_post_types = array();

		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'revisions' ) )
				$_post_types[] = $post_type;
		}

		return $_post_types;
	}

	function get_options( $just_defaults = false ) {
		if ( $just_defaults )
			return $this->defaults;

		$options = (array) get_option( 'steem_post_updates' );

		return wp_parse_args( $options, $this->defaults );
	}

    // when post is saved, it works.
    function post_saved($post_id, $post, $update)
    {
		error_log("post_saved ". $post_id);
    	}

	// The meat of the plugin
	function post_updated( $post_id, $post_after, $post_before ) {
		error_log("post_updated ". $post_id);
	}


	function get_post_type_label( $post_type ) {
		// 2.9
		if ( !function_exists( 'get_post_type_object' ) )
			return ucwords( str_replace( '_', ' ', $post_type ) );

		// 3.0
		$post_type_object = get_post_type_object( $post_type );
		if ( empty( $post_type_object->label ) )
			return ucwords( str_replace( '_', ' ', $post_type ) );
		return $post_type_object->label;
	}

	/* Admin */
	function admin_menu() {
		register_setting( self::OPTION_GROUP, self::OPTION, array( $this, 'validate_options' ) );

		add_settings_section( self::ADMIN_PAGE, __( 'WarpSteem settings' ), array( $this, 'settings_section' ), self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_enable', __( 'Enable' ), array( $this, 'enable_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_users', __( 'Users' ), array( $this, 'users_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_userinfo', __( 'Posting Settings' ), array( $this, 'userinfo_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_post_types', __( 'Post Types' ), array( $this, 'post_types_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_drafts', __( 'Drafts' ), array( $this, 'drafts_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );

		$hook = add_options_page( __( 'WarpSteem settings' ), __( 'WarpSteem settings' ), 'manage_options', self::ADMIN_PAGE, array( $this, 'admin_page' ) );
		add_action( "admin_head-$hook", array( $this, 'admin_page_head' ) );
	}

	// Used in validate_options to array_walk the list of email addresses
	function trim_email( &$email, $key ) {
		$email = trim( $email );
	}

	function validate_options( $options ) {
		if ( !$options || !is_array( $options ) )
			return $this->defaults;

		$return = array();

		$return['enable'] = ( empty( $options['enable'] ) ) ? 0 : 1;

		if ( empty( $options['users'] ) || !is_array( $options ) ) {
			$return['users'] = $this->defaults['users'];
		} else {
			$return['users'] = $options['users'];
		}

		if ( empty( $options['userinfo'] ) ) {
			if ( count( $return['users'] ) )
				$return['userinfo'] = array();
			else
				$return['userinfo'] = $this->defaults['userinfo'];
		} else {
			$_userinfo = is_string( $options['userinfo'] ) ? preg_split( '(\n|\r)', $options['userinfo'], -1, PREG_SPLIT_NO_EMPTY ) : array();
			$_userinfo = array_unique( $_userinfo );
			array_walk( $_userinfo, array( 'Steem_Post_Updates', 'trim_email' ) );
			$userinfo = array_filter( $_userinfo, 'is_email' );

			$invalid_userinfo = array_diff( $_userinfo, $userinfo );
			if ( $invalid_userinfo )
				$return['userinfo'] = $invalid_userinfo;

			// Don't store a huge list of invalid userinfo addresses in the option
			if ( isset( $return['invalid_userinfo'] ) && count( $return['invalid_userinfo'] ) > 200 ) {
				$return['invalid_userinfo'] = array_slice( $return['invalid_userinfo'], 0, 200 );
				$return['invalid_userinfo'][] = __( 'and many more not listed here' );
			}

			// Cap to at max 200 email addresses
			if ( count( $return['userinfo'] ) > 200 ) {
				$return['userinfo'] = array_slice( $return['userinfo'], 0, 200 );
			}
		}

		if ( empty( $options['post_types'] ) || !is_array( $options ) ) {
			$return['post_types'] = $this->defaults['post_types'];
		} else {
			$post_types = array_intersect( $options['post_types'], $this->get_post_types() );
			$return['post_types'] = $post_types ? $post_types : $this->defaults['post_types'];
		}

		$return['drafts'] = ( empty( $options['drafts'] ) ) ? 0 : 1;

		do_action( 'steem_post_updates_validate_options', $this->get_options(), $return );

		return $return;
	}

	function admin_page_head() {
?>
<style>
.epc-registered-user-selection {
	overflow: auto;
	max-height: 300px;
	max-width: 40em;
	border: 1px solid #ccc;
	background-color: #fafafa;
	padding: 12px;
	box-sizing: border-box;
}
.epc-registered-user-selection ul {
	margin: 0;
	padding: 0;
}
.epc-additional-userinfo {
	width: 40em;
}
</style>
<?php
	}

	function admin_page() {
		$options = $this->get_options();
?>

<div class="wrap">
	<h2><?php _e( 'WarpSteem settings' ); ?></h2>
<?php	if ( !empty( $options['invalid_userinfo'] ) && $_GET['settings-updated'] ) : ?>
	<div class="error">
		<p><?php printf( _n( 'Invalid Email: %s', 'Invalid userinfo: %s', count( $options['invalid_userinfo'] ) ), '<kbd>' . join( '</kbd>, <kbd>', array_map( 'esc_html', $options['invalid_userinfo'] ) ) ); ?></p>
	</div>
<?php	endif; ?>

	<form action="options.php" method="post">
		<?php settings_fields( self::OPTION_GROUP ); ?>
		<?php do_settings_sections( self::ADMIN_PAGE ); ?>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
		</p>
	</form>
</div>
<?php
	}

	function settings_section() {} // stub

	function enable_setting() {
		$options = $this->get_options();
?>
		<p><label><input type="checkbox" name="steem_post_updates[enable]" value="1"<?php checked( $options['enable'], 1 ); ?> /> <?php _e( 'send post to steemit when a post updates.' ); ?></label></p>
<?php
	}

	function users_setting() {
		$options = $this->get_options();
?>
		<div class="epc-registered-user-selection">
			<ul>
<?php		$users = get_users();
		usort( $users, array( $this, 'sort_users_by_display_name' ) );

		foreach ( $users as $user ) : ?>
				<li><label><input type="checkbox" name="steem_post_updates[users][]" value="<?php echo (int) $user->ID; ?>"<?php checked( in_array( $user->ID, $options['users'] ) ); ?> /> <?php echo esc_html( $user->display_name ); ?> ( <?php echo esc_html( $user->user_login ); ?> - <?php echo esc_html( $user->user_email ); ?> )</label></li>

<?php		endforeach; ?>
			</ul>
		</div>
<?php
	}

	function sort_users_by_display_name( $a, $b ) {
		return strcmp( strtolower( $a->display_name ), strtolower( $b->display_name ) );
	}

	function userinfo_setting() {
		$options = $this->get_options();
?>
		<textarea class="epc-additional-userinfo" rows="4" cols="40" name="steem_post_updates[userinfo]"><?php echo esc_html( join( "\n", $options['userinfo'] ) ); ?></textarea>
		<p class="description"><?php _e( 'ex) ID, Posting Key, Tags' ); ?></p>
<?php
	}

	function post_types_setting() {
		$options = $this->get_options();
?>
		<ul>
<?php		foreach ( $this->get_post_types() as $post_type ) :
			$label = $this->get_post_type_label( $post_type );
?>
			<li><label><input type="checkbox" name="steem_post_updates[post_types][]" value="<?php echo esc_attr( $post_type ); ?>"<?php checked( in_array( $post_type, $options['post_types'] ) ); ?> /> <?php echo esc_html( $label ); ?></label></li>
<?php		endforeach; ?>
		</ul>
<?php
	}

	function drafts_setting() {
		$options = $this->get_options();
?>
		<p><label><input type="checkbox" name="steem_post_updates[drafts]" value="1"<?php checked( $options['drafts'], 1 ); ?> /> <?php _e( 'drafts is not just published.' ); ?></label></p>
<?php
	}
}

