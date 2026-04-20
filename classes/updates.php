class TML_Classic_Updater {
	private $plugin_slug;
	private $plugin_file;
	private $update_url;

	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( $plugin_file );
		$this->update_url  = 'https://webguyio.github.io/tml-classic/updates.json';
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
	}

	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$remote_info = $this->get_remote_version_info();
		if ( !$remote_info || version_compare( $this->get_current_version(), $remote_info['version'], '>=' ) ) {
			return $transient;
		}
		$transient->response[$this->plugin_slug] = (object) array(
			'slug'         => dirname( $this->plugin_slug ),
			'plugin'       => $this->plugin_slug,
			'new_version'  => $remote_info['version'],
			'url'          => $remote_info['homepage'] ?? 'https://webguyio.github.io/tml-classic/',
			'package'      => $remote_info['package'] ?? 'https://webguyio.github.io/tml-classic/tml-classic.zip',
			'icons'        => $remote_info['icons'] ?? array(),
			'tested'       => $this->normalize_version( $remote_info['tested'] ?? '' ),
			'requires'     => $remote_info['requires'] ?? '',
			'requires_php' => $remote_info['requires_php'] ?? '',
		);
		return $transient;
	}

	public function get_plugin_info( $response, $action, $args ) {
		if ( $action !== 'plugin_information' || $args->slug !== dirname( $this->plugin_slug ) ) {
			return $response;
		}
		$remote_info = $this->get_remote_version_info();
		if ( $remote_info ) {
			$response = (object) array(
				'name'          => $remote_info['name'] ?? 'TML Classic',
				'slug'          => dirname( $this->plugin_slug ),
				'version'       => $remote_info['version'],
				'author'        => $remote_info['author'] ?? 'Web Guy',
				'homepage'      => $remote_info['homepage'] ?? 'https://webguyio.github.io/tml-classic/',
				'requires'      => $remote_info['requires'] ?? '5.0',
				'requires_php'  => $remote_info['requires_php'] ?? '8.0',
				'tested'        => $this->normalize_version( $remote_info['tested'] ?? '' ),
				'last_updated'  => $remote_info['last_updated'] ?? current_time( 'mysql' ),
				'sections'      => array(
					'description' => $remote_info['description'] ?? 'Display themed WordPress login, registration, and lost password forms on any front-end page using a shortcode.',
					'changelog'   => $remote_info['changelog'] ?? '',
				),
				'download_link' => $remote_info['package'] ?? 'https://webguyio.github.io/tml-classic/tml-classic.zip',
				'icons'         => $remote_info['icons'] ?? array(),
			);
		}
		return $response;
	}

	private function get_remote_version_info() {
		$cache_key = 'tml_classic_update_info';
		$cached = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}
		$response = wp_remote_get( $this->update_url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( $data ) {
			set_transient( $cache_key, $data, 3 * HOUR_IN_SECONDS );
		}
		return $data ? $data : false;
	}

	private function get_current_version() {
		$plugin_data = get_plugin_data( $this->plugin_file );
		return $plugin_data['Version'];
	}

	private function normalize_version( $version ) {
		global $wp_version;
		if ( empty( $version ) ) {
			return $wp_version;
		}
		$version_parts = explode( '.', $version );
		$wp_parts = explode( '.', $wp_version );
		if ( count( $version_parts ) === 2 && count( $wp_parts ) >= 3 ) {
			if ( $version_parts[0] === $wp_parts[0] && $version_parts[1] === $wp_parts[1] ) {
				return $wp_parts[0] . '.' . $wp_parts[1] . '.' . $wp_parts[2];
			}
		}
		return $version;
	}
}