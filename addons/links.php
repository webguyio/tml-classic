<?php
/*
 * Addon Name: Links
 * Description: Adds the ability to show custom dashboard links in the TML Classic widget.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Custom_User_Links' ) ) :

class TML_Classic_Custom_User_Links extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_links';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		global $wp_roles;
		if ( empty( $wp_roles ) )
			$wp_roles = new WP_Roles();
		$options = array();
		foreach ( $wp_roles->get_names() as $role => $role_name ) {
			if ( 'pending' !== $role ) {
				$options[ $role ] = array(
					array(
						'title' => 'Dashboard',
						'url'   => admin_url(),
					),
					array(
						'title' => 'Settings',
						'url'   => admin_url( 'profile.php' ),
					),
				);
			}
		}
		return $options;
	}

	protected function load() {
		add_filter( 'tml_user_links', array( $this, 'get_user_links' ) );
		if ( is_admin() ) {
			add_action( 'tml_uninstall_links.php', array( $this, 'uninstall' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'wp_ajax_tml-add-user-link',    array( $this, 'ajax_add_link'    ) );
			add_action( 'wp_ajax_tml-delete-user-link', array( $this, 'ajax_delete_link' ) );
		}
	}

	public function get_user_links( $links = array() ) {
		if ( !is_user_logged_in() )
			return $links;
		$current_user = wp_get_current_user();
		if ( is_multisite() && empty( $current_user->roles ) )
			$current_user->roles = array( 'subscriber' );
		foreach ( (array) $current_user->roles as $role ) {
			if ( $links = $this->get_option( $role ) )
				break;
		}
		$replacements = apply_filters( 'tml_custom_user_links_variables', array(
			'%user_id%'  => $current_user->ID,
			'%username%' => $current_user->user_nicename,
		) );
		foreach ( (array) $links as $key => $link ) {
			$links[ $key ]['url'] = TML_Classic_Common::replace_vars( $link['url'], $current_user->ID, $replacements );
		}
		return $links;
	}

	public function uninstall() {
		delete_option( $this->options_key );
	}

	public function admin_menu() {
		$hook = add_submenu_page(
			'tml_classic',
			__( 'TML Classic Links Settings', 'tml-classic' ),
			__( 'Links', 'tml-classic' ),
			'manage_options',
			$this->options_key,
			array( $this, 'settings_page' )
		);
		add_action( 'load-' . $hook, array( $this, 'load_settings_page' ) );
	}

	public function admin_init() {
		register_setting( $this->options_key, $this->options_key, array( $this, 'save_settings' ) );
	}

	public function load_settings_page() {
		global $wp_roles;
		$screen = get_current_screen();
		wp_enqueue_script( 'postbox' );
		foreach ( $wp_roles->get_names() as $role => $role_name ) {
			if ( 'pending' !== $role )
				add_meta_box( $role, translate_user_role( $role_name ), array( $this, 'user_links_meta_box' ), $screen->id, 'normal' );
		}
		$css = '.user-links{border-collapse:collapse;width:100%}.user-links th, .user-links td{padding:6px 8px}.user-links .drag-handle{cursor:grab;color:#999;padding-right:8px}.user-links tr.dragging{opacity:.5}.user-links tr.drag-over{border-top:2px solid #2271b1}.new-link td{padding:4px 8px}';
		wp_add_inline_style( 'wp-admin', $css );
		$data = 'var tmlUserLinks=' . wp_json_encode( array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'addNonce' => wp_create_nonce( 'tml-add-user-link' ),
		) ) . ';';
		wp_register_script( 'tml-user-links-admin', false, array(), TML_CLASSIC_VERSION, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
		wp_enqueue_script( 'tml-user-links-admin' );
		wp_add_inline_script( 'tml-user-links-admin', $data );
		wp_add_inline_script( 'tml-user-links-admin', '(function(){document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".tml-add-link").forEach(function(btn){btn.addEventListener("click",function(){var role=btn.dataset.role;var row=btn.closest("tr");var title=row.querySelector("input[name*=\'[title]\']").value.trim();var url=row.querySelector("input[name*=\'[url]\']").value.trim();if(!title||!url)return;fetch(tmlUserLinks.ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"tml-add-user-link",nonce:tmlUserLinks.addNonce,role:role,title:title,url:url})}).then(function(r){return r.json();}).then(function(data){if(!data.success)return;var tbody=document.getElementById(role+"-link-list");if(tbody){tbody.insertAdjacentHTML("beforeend",data.data.html);tbody.closest("table").style.display="";initDrag(tbody.closest("table"));bindDeleteBtn(tbody.lastElementChild.querySelector(".tml-delete-link"));}row.querySelector("input[name*=\'[title]\']").value="";row.querySelector("input[name*=\'[url]\']").value="";});});});document.querySelectorAll(".tml-delete-link").forEach(bindDeleteBtn);document.querySelectorAll(".user-links").forEach(initDrag);});function bindDeleteBtn(btn){if(!btn)return;btn.addEventListener("click",function(){var role=btn.dataset.role;var id=btn.dataset.id;fetch(tmlUserLinks.ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"tml-delete-user-link",_ajax_nonce:btn.dataset.nonce,role:role,id:id})}).then(function(r){return r.text();}).then(function(resp){if(resp.trim()==="1"){var tr=btn.closest("tr");var tbody=tr.parentNode;tr.remove();if(!tbody.querySelector("tr")){tbody.closest("table").style.display="none";}}});});}function initDrag(table){var dragging=null;table.querySelectorAll("tbody tr").forEach(function(tr){tr.setAttribute("draggable","true");tr.addEventListener("dragstart",function(e){dragging=tr;tr.classList.add("dragging");e.dataTransfer.effectAllowed="move";});tr.addEventListener("dragend",function(){tr.classList.remove("dragging");table.querySelectorAll("tr").forEach(function(r){r.classList.remove("drag-over");});dragging=null;renumberInputs(table);});tr.addEventListener("dragover",function(e){e.preventDefault();if(tr===dragging)return;table.querySelectorAll("tr").forEach(function(r){r.classList.remove("drag-over");});tr.classList.add("drag-over");var tbody=tr.parentNode;var rows=Array.from(tbody.querySelectorAll("tr"));var from=rows.indexOf(dragging);var to=rows.indexOf(tr);if(from<to)tbody.insertBefore(dragging,tr.nextSibling);else tbody.insertBefore(dragging,tr);});tr.addEventListener("drop",function(e){e.preventDefault();});});}function renumberInputs(table){table.querySelectorAll("tbody tr").forEach(function(tr,idx){tr.querySelectorAll("input[name]").forEach(function(input){input.name=input.name.replace(/\]\[\d+\]\[/,"["+idx+"][");});});}})();' );
	}

	public function settings_page() {
		$screen = get_current_screen();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TML Classic Links Settings', 'tml-classic' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" id="tml-user-links-form">
				<?php
				settings_fields( $this->options_key );
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
				wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
				?>
				<div id="<?php echo esc_attr( $this->options_key ); ?>" class="metabox-holder">
					<?php do_meta_boxes( $screen->id, 'normal', null ); ?>
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function user_links_meta_box( $object, $box ) {
		$role  = $box['id'];
		$links = $this->get_option( $role, array() );
		?>
		<table id="<?php echo esc_attr( $role ); ?>-link-table"<?php echo empty( $links ) ? ' style="display:none"' : ''; ?> class="user-links widefat">
			<thead>
				<tr>
					<th></th>
					<th><?php esc_html_e( 'Title', 'tml-classic' ); ?></th>
					<th><?php esc_html_e( 'URL', 'tml-classic' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="<?php echo esc_attr( $role ); ?>-link-list">
				<?php foreach ( $links as $key => $link ) : ?>
					<?php echo $this->get_link_row( $role, $key, $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML generated internally by get_link_row() with all values escaped ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<table class="new-link widefat">
			<tbody>
				<tr>
					<td><input type="text" name="new_user_link[<?php echo esc_attr( $role ); ?>][title]" placeholder="<?php esc_attr_e( 'Title', 'tml-classic' ); ?>" class="regular-text"></td>
					<td><input type="text" name="new_user_link[<?php echo esc_attr( $role ); ?>][url]" placeholder="<?php esc_attr_e( 'URL', 'tml-classic' ); ?>" class="regular-text"></td>
					<td><button type="button" class="button tml-add-link" data-role="<?php echo esc_attr( $role ); ?>"><?php esc_html_e( 'Add Link', 'tml-classic' ); ?></button></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private function get_link_row( $role, $id, $link ) {
		$del_nonce = wp_create_nonce( 'tml-delete-user-link_' . $id );
		ob_start();
		?>
		<tr id="<?php echo esc_attr( $role ); ?>-link-<?php echo (int) $id; ?>" draggable="true">
			<td class="drag-handle" aria-hidden="true">&#8597;</td>
			<td><input type="text" name="<?php echo esc_attr( $this->options_key ); ?>[<?php echo esc_attr( $role ); ?>][<?php echo (int) $id; ?>][title]" value="<?php echo esc_attr( $link['title'] ); ?>" class="regular-text"></td>
			<td><input type="text" name="<?php echo esc_attr( $this->options_key ); ?>[<?php echo esc_attr( $role ); ?>][<?php echo (int) $id; ?>][url]" value="<?php echo esc_attr( $link['url'] ); ?>" class="regular-text"></td>
			<td><button type="button" class="button tml-delete-link" data-role="<?php echo esc_attr( $role ); ?>" data-id="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr( $del_nonce ); ?>"><?php esc_html_e( 'Delete', 'tml-classic' ); ?></button></td>
		</tr>
		<?php
		return ob_get_clean();
	}

	public function save_settings( $settings ) {
		global $wp_roles;
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			return $settings;
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- nonce verified by options.php before sanitize callback runs
		foreach ( $wp_roles->get_names() as $role => $role_name ) {
			if ( 'pending' === $role )
				continue;
			$existing          = !empty( $settings[ $role ] ) ? (array) $settings[ $role ] : array();
			$settings[ $role ] = array();
			foreach ( $existing as $link ) {
				$clean_title = wp_kses( $link['title'], array() );
				$clean_url   = esc_url_raw( $link['url'] );
				if ( !empty( $clean_title ) && !empty( $clean_url ) )
					$settings[ $role ][] = array( 'title' => $clean_title, 'url' => $clean_url );
			}
			if ( !empty( $_POST['new_user_link'][ $role ] ) ) {
				$clean_title = wp_kses( $_POST['new_user_link'][ $role ]['title'], array() );
				$clean_url   = esc_url_raw( $_POST['new_user_link'][ $role ]['url'] );
				if ( !empty( $clean_title ) && !empty( $clean_url ) )
					$settings[ $role ][] = array( 'title' => $clean_title, 'url' => $clean_url );
			}
		}
		return $settings;
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
	}

	public function ajax_add_link() {
		if ( !current_user_can( 'manage_options' ) )
			wp_die( -1 );
		check_ajax_referer( 'tml-add-user-link', 'nonce' );
		$role  = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
		$title = isset( $_POST['title'] ) ? wp_kses( wp_unslash( $_POST['title'] ), array() ) : '';
		$url   = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		if ( empty( $role ) || empty( $title ) || empty( $url ) )
			wp_die( -1 );
		$links   = (array) $this->get_option( $role, array() );
		$links[] = array( 'title' => $title, 'url' => $url );
		$this->set_option( $role, $links );
		$this->save_options();
		$id  = array_key_last( $links );
		$row = $this->get_link_row( $role, $id, array( 'title' => $title, 'url' => $url ) );
		wp_send_json_success( array( 'html' => $row, 'role' => $role ) );
	}

	public function ajax_delete_link() {
		if ( !current_user_can( 'manage_options' ) )
			wp_die( -1 );
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : -1;
		$role = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
		check_ajax_referer( 'tml-delete-user-link_' . $id );
		if ( empty( $role ) || $id < 0 )
			wp_die( -1 );
		if ( $this->get_option( array( $role, $id ) ) ) {
			$this->delete_option( array( $role, $id ) );
			$this->save_options();
			wp_die( 1 );
		}
		wp_die( 0 );
	}
}

TML_Classic_Custom_User_Links::get_object();

endif;