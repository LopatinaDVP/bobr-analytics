<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Collector {
	const VISIT_GAP = 1800;
	const RATE      = 300;

	private static $bot = null;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'init', array( __CLASS__, 'detect_bot' ), 1 );
	}

	private static function excluded_ip( $ip ) {
		$s = bobr_settings();
		return $ip && in_array( Bobr_Util::ip_hash( $ip ), (array) $s['exclude_ips'], true );
	}

	private static function excluded_user() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$roles = (array) wp_get_current_user()->roles;
		return (bool) array_intersect( $roles, (array) bobr_settings()['exclude_roles'] );
	}

	public static function enqueue() {
		if ( is_admin() || is_customize_preview() || self::excluded_user() ) {
			return;
		}
		wp_enqueue_script( 'bobr', BOBR_URL . 'assets/bobr.js', array(), BOBR_VER, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script(
			'bobr',
			'window.bobr=' . wp_json_encode(
				array(
					'u'  => esc_url_raw( rest_url( 'bobr/v1/hit' ) ),
					'nf' => is_404() ? 1 : 0,
				)
			) . ';',
			'before'
		);
	}

	public static function routes() {
		register_rest_route(
			'bobr/v1',
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'hit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function done() {
		$r = new WP_REST_Response( null, 204 );
		$r->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $r;
	}

	public static function hit( $req ) {
		$body = (string) $req->get_body();
		if ( '' === $body || strlen( $body ) > 4096 ) {
			return self::done();
		}
		$d = json_decode( $body, true );
		if ( ! is_array( $d ) || empty( $d['t'] ) ) {
			return self::done();
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : '';
		if ( Bobr_Util::bot( $ua ) ) {
			return self::done();
		}
		$ip = Bobr_Util::ip();
		if ( self::excluded_ip( $ip ) ) {
			return self::done();
		}
		$vh = Bobr_Util::visitor_hash( $ip, $ua );

		$rk = 'bobr_rl_' . $vh;
		$n  = (int) get_transient( $rk );
		if ( $n >= self::RATE ) {
			return self::done();
		}
		set_transient( $rk, $n + 1, 600 );

		$pid = preg_match( '~^[a-z0-9]{6,12}$~', (string) ( $d['id'] ?? '' ) ) ? (string) $d['id'] : '';
		switch ( (string) $d['t'] ) {
			case 'pv':
				self::pageview( $d, $vh, $ip, $ua, $pid );
				break;
			case 'lv':
				self::leave( $d, $vh, $pid );
				break;
			case 'ev':
				self::event( $d, $vh, $pid );
				break;
		}
		return self::done();
	}

	private static function now() {
		return current_time( 'mysql' );
	}

	private static function open_visit( $vh ) {
		global $wpdb;
		$since = wp_date( 'Y-m-d H:i:s', time() - self::VISIT_GAP );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, pages FROM ' . Bobr_DB::t( 'visits' ) . ' WHERE vhash = %s AND last_seen >= %s ORDER BY id DESC LIMIT 1', $vh, $since ) );
	}

	private static function pageview( $d, $vh, $ip, $ua, $pid ) {
		global $wpdb;
		$raw = (string) ( $d['p'] ?? '' );
		if ( '' === $raw || '/' !== $raw[0] || 0 === strpos( $raw, '//' ) ) {
			return;
		}
		$path  = Bobr_Util::clean_path( $raw );
		$title = Bobr_Util::str( $d['ti'] ?? '', 200 );
		$now   = self::now();
		$day   = substr( $now, 0, 10 );

		$utm = array();
		parse_str( (string) wp_parse_url( $raw, PHP_URL_QUERY ), $q );
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $k ) {
			$utm[ $k ] = isset( $q[ $k ] ) && is_string( $q[ $k ] ) ? Bobr_Util::str( $q[ $k ], 100 ) : '';
		}
		$ref      = (string) ( $d['r'] ?? '' );
		$ref_host = '';
		$ref_path = '';
		if ( '' !== $ref && preg_match( '~^https?://~i', $ref ) ) {
			$ref_host = Bobr_Util::str( strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) ), 120 );
			$ref_path = Bobr_Util::str( (string) wp_parse_url( $ref, PHP_URL_PATH ), 200 );
		}
		list( $src_type, $src_name ) = Bobr_Util::source( $ref_host, $ref_path, $utm );

		$visit = self::open_visit( $vh );
		if ( $visit && ! in_array( $src_type, array( 'internal', 'direct' ), true ) ) {
			$visit = null;
		}

		if ( $visit ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . Bobr_DB::t( 'visits' ) . ' SET last_seen = %s, exit_path = %s, pages = pages + 1, bounce = 0, duration = GREATEST(duration, TIMESTAMPDIFF(SECOND, started, %s)) WHERE id = %d', $now, $path, $now, $visit->id ) );
			$visit_id = (int) $visit->id;
		} else {
			list( $device, $os, $browser ) = Bobr_Util::ua( $ua );
			$tz = Bobr_Util::str( $d['tz'] ?? '', 40 );
			$geo = bobr_settings()['geo'] ? Bobr_Geo::locate( $ip, $tz ) : array( '', '', '' );
			$wpdb->insert(
				Bobr_DB::t( 'visits' ),
				array(
					'vhash'        => $vh,
					'day'          => $day,
					'started'      => $now,
					'last_seen'    => $now,
					'entry'        => $path,
					'exit_path'    => $path,
					'pages'        => 1,
					'duration'     => 0,
					'bounce'       => 1,
					'src_type'     => $src_type,
					'src_name'     => Bobr_Util::str( $src_name, 64 ),
					'ref_host'     => $ref_host,
					'ref_path'     => $ref_path,
					'utm_source'   => $utm['utm_source'],
					'utm_medium'   => $utm['utm_medium'],
					'utm_campaign' => $utm['utm_campaign'],
					'utm_term'     => $utm['utm_term'],
					'utm_content'  => $utm['utm_content'],
					'device'       => $device,
					'os'           => $os,
					'browser'      => $browser,
					'screen'       => Bobr_Util::screen( $d['w'] ?? 0 ),
					'lang'         => Bobr_Util::str( $d['l'] ?? '', 12 ),
					'tz'           => $tz,
					'country'      => Bobr_Util::str( $geo[0], 60 ),
					'region'       => Bobr_Util::str( $geo[1], 80 ),
					'city'         => Bobr_Util::str( $geo[2], 80 ),
				)
			);
			$visit_id = (int) $wpdb->insert_id;
		}
		$wpdb->insert( Bobr_DB::t( 'hits' ), array( 'visit_id' => $visit_id, 'pid' => $pid, 'ts' => $now, 'day' => $day, 'path' => $path, 'title' => $title ) );

		if ( ! empty( $d['nf'] ) ) {
			$from = '' !== $ref_host ? $ref_host . $ref_path : '(без реферера)';
			$wpdb->insert( Bobr_DB::t( 'events' ), array( 'visit_id' => $visit_id, 'ts' => $now, 'day' => $day, 'path' => $path, 'type' => 'nf', 'name' => Bobr_Util::str( $from, 250 ) ) );
		}
	}

	private static function hit_by_pid( $pid ) {
		global $wpdb;
		if ( '' === $pid ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, visit_id, path FROM ' . Bobr_DB::t( 'hits' ) . ' WHERE pid = %s AND day >= %s ORDER BY id DESC LIMIT 1', $pid, wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) ) );
	}

	private static function leave( $d, $vh, $pid ) {
		global $wpdb;
		$hit = self::hit_by_pid( $pid );
		if ( ! $hit ) {
			return;
		}
		$v = max( 0, min( 21600, (int) ( $d['v'] ?? 0 ) ) );
		$s = max( 0, min( 100, (int) ( $d['s'] ?? 0 ) ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Bobr_DB::t( 'hits' ) . ' SET visible = GREATEST(visible, %d), scroll = GREATEST(scroll, %d) WHERE id = %d', $v, $s, $hit->id ) );
		$now = self::now();
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Bobr_DB::t( 'visits' ) . ' SET last_seen = GREATEST(last_seen, %s), duration = GREATEST(duration, TIMESTAMPDIFF(SECOND, started, %s), %d) WHERE id = %d', $now, $now, $v, $hit->visit_id ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Bobr_DB::t( 'visits' ) . ' SET bounce = IF(pages <= 1 AND duration < 15, 1, 0) WHERE id = %d', $hit->visit_id ) );

		if ( ! empty( $d['m'] ) && is_array( $d['m'] ) ) {
			$has = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Bobr_DB::t( 'events' ) . " WHERE day = %s AND type IN ('ttfb','fcp','lcp','inp','cls') AND name = %s", substr( $now, 0, 10 ), 'pid:' . $pid ) );
			if ( ! $has ) {
				$lim = array( 'ttfb' => 60000, 'fcp' => 60000, 'lcp' => 60000, 'inp' => 60000, 'cls' => 10 );
				foreach ( $lim as $k => $max ) {
					if ( isset( $d['m'][ $k ] ) && is_numeric( $d['m'][ $k ] ) ) {
						$val = (float) $d['m'][ $k ];
						if ( $val >= 0 && $val <= $max ) {
							$wpdb->insert( Bobr_DB::t( 'events' ), array( 'visit_id' => $hit->visit_id, 'ts' => $now, 'day' => substr( $now, 0, 10 ), 'path' => $hit->path, 'type' => $k, 'name' => 'pid:' . $pid, 'val' => round( $val, 4 ) ) );
						}
					}
				}
			}
		}
	}

	private static function event( $d, $vh, $pid ) {
		global $wpdb;
		$types = array( 'out' => 'out', 'tel' => 'tel', 'mail' => 'mail', 'fs' => 'form_sent', 'fd' => 'form_drop' );
		$k     = (string) ( $d['k'] ?? '' );
		if ( ! isset( $types[ $k ] ) ) {
			return;
		}
		$hit = self::hit_by_pid( $pid );
		if ( ! $hit ) {
			return;
		}
		$name = '';
		if ( 'out' === $k ) {
			$u = (string) ( $d['n'] ?? '' );
			if ( ! preg_match( '~^https?://~i', $u ) ) {
				return;
			}
			$name = strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ) . (string) wp_parse_url( $u, PHP_URL_PATH );
		} elseif ( 'fs' === $k || 'fd' === $k ) {
			$name = Bobr_Util::str( $d['n'] ?? '', 120 );
		}
		$now = self::now();
		$wpdb->insert( Bobr_DB::t( 'events' ), array( 'visit_id' => $hit->visit_id, 'ts' => $now, 'day' => substr( $now, 0, 10 ), 'path' => $hit->path, 'type' => $types[ $k ], 'name' => Bobr_Util::str( $name, 250 ) ) );
	}

	public static function detect_bot() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! bobr_settings()['bots'] ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'rest_route=' ) ) {
			return;
		}
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		$bot = Bobr_Util::bot( $ua );
		if ( ! $bot ) {
			return;
		}
		self::$bot = array( $bot, Bobr_Util::clean_path( $uri ) );
		add_action( 'shutdown', array( __CLASS__, 'save_bot' ), 1 );
	}

	public static function save_bot() {
		global $wpdb;
		if ( ! self::$bot ) {
			return;
		}
		list( $bot, $path ) = self::$bot;
		$now    = current_time( 'mysql' );
		$status = (int) http_response_code();
		$wpdb->insert( Bobr_DB::t( 'bots' ), array( 'ts' => $now, 'day' => substr( $now, 0, 10 ), 'bot' => Bobr_Util::str( $bot[0], 40 ), 'kind' => $bot[1], 'path' => $path, 'status' => $status > 0 ? $status : 200 ) );
	}
}
