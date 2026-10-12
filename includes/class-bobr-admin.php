<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Admin {
	const CAP = 'manage_options';

	const FORM_TAGS = array(
		'select' => array( 'name' => true ),
		'option' => array( 'value' => true, 'selected' => true ),
	);

	private static $r;

	public static function sections() {
		return array(
			'summary'  => esc_html__( 'Summary', 'bobr-analytics' ),
			'entry'    => esc_html__( 'Entry pages', 'bobr-analytics' ),
			'pages'    => esc_html__( 'All pages and exits', 'bobr-analytics' ),
			'sources'  => esc_html__( 'Sources', 'bobr-analytics' ),
			'ai'       => esc_html__( 'AI assistants', 'bobr-analytics' ),
			'bots'     => esc_html__( 'Bots', 'bobr-analytics' ),
			'geo'      => esc_html__( 'Geography', 'bobr-analytics' ),
			'tech'     => esc_html__( 'Technology', 'bobr-analytics' ),
			'time'     => esc_html__( 'Time', 'bobr-analytics' ),
			'behavior' => esc_html__( 'Behavior', 'bobr-analytics' ),
			'events'   => esc_html__( 'Events', 'bobr-analytics' ),
			'nf'       => esc_html__( '404 errors', 'bobr-analytics' ),
			'speed'    => esc_html__( 'Speed', 'bobr-analytics' ),
			'now'      => esc_html__( 'Live now', 'bobr-analytics' ),
			'settings' => esc_html__( 'Settings', 'bobr-analytics' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_bobr_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_bobr_geo', array( __CLASS__, 'geo_download' ) );
		add_action( 'admin_post_bobr_rebuild', array( __CLASS__, 'rebuild' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BOBR_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
	}

	public static function menu() {
		add_menu_page( esc_html__( 'Bobr Analytics', 'bobr-analytics' ), esc_html__( 'Bobr Analytics', 'bobr-analytics' ), self::CAP, 'bobr', array( __CLASS__, 'page' ), 'dashicons-chart-area', 3 );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=bobr&sec=settings' ) ) . '">' . esc_html__( 'Settings', 'bobr-analytics' ) . '</a>' );
		return $links;
	}

	public static function row_meta( $links, $file ) {
		if ( plugin_basename( BOBR_FILE ) === $file ) {
			$links[] = '<a href="' . esc_url( BOBR_GITHUB . '/issues' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Feedback', 'bobr-analytics' ) . '</a>';
		}
		return $links;
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_bobr' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'bobr-admin', BOBR_URL . 'assets/admin.css', array(), BOBR_VER );
		wp_enqueue_script( 'bobr-admin', BOBR_URL . 'assets/admin.js', array(), BOBR_VER, true );
		wp_localize_script(
			'bobr-admin',
			'bobrL10n',
			array(
				/* translators: %d: number of table rows */
				'rows' => esc_html__( 'Rows: %d', 'bobr-analytics' ),
				'none' => esc_html__( 'Nothing found', 'bobr-analytics' ),
				/* translators: 1: first row, 2: last row, 3: total rows */
				'page' => esc_html__( '%1$d-%2$d of %3$d', 'bobr-analytics' ),
			)
		);
	}

	private static function url( $args = array() ) {
		$keep = array();
		foreach ( array( 'sec', 'period', 'from', 'to', 'cmp', 'f_src', 'f_dev', 'f_city', 'f_page', 'who', 'unit' ) as $k ) {
			if ( '' !== self::g( $k ) ) {
				$keep[ $k ] = self::g( $k );
			}
		}
		$q = array_merge( array( 'page' => 'bobr' ), $keep, $args );
		$q = array_filter( $q, function ( $v ) { return null !== $v && '' !== $v; } );
		return add_query_arg( array_map( 'rawurlencode', $q ), admin_url( 'admin.php' ) );
	}

	private static function g( $k, $def = '' ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $def; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only dashboard filters, nothing is changed.
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'bobr-analytics' ) );
		}
		$sec = self::g( 'sec', 'summary' );
		if ( ! isset( self::sections()[ $sec ] ) ) {
			$sec = 'summary';
		}
		self::$r = new Bobr_Report( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only filters, every value is unslashed and sanitized in Bobr_Report.
		Bobr_Charts::$unit = self::$r->unit;
		echo '<div class="wrap bobr"><div class="bobr-top"><h1><span class="bobr-logo">🦫</span> ' . esc_html__( 'Bobr Analytics', 'bobr-analytics' ) . '</h1>';
		echo '<span class="bobr-note">' . esc_html__( 'Your own website statistics: no cookies, data stays in this site\'s database.', 'bobr-analytics' ) . '</span></div>';
		self::notices();
		echo '<div class="bobr-layout"><nav class="bobr-nav">';
		foreach ( self::sections() as $k => $label ) {
			echo '<a href="' . esc_url( self::url( array( 'sec' => $k, 'path' => null ) ) ) . '" class="' . ( $k === $sec ? 'is-on' : '' ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav><main class="bobr-main">';
		if ( 'settings' !== $sec ) {
			self::toolbar( $sec );
		}
		call_user_func( array( __CLASS__, 'sec_' . $sec ) );
		echo '</main></div></div>';
	}

	private static function notices() {
		$m = self::g( 'bobr_msg' );
		$t = array( 'saved' => esc_html__( 'Saved.', 'bobr-analytics' ), 'geo' => esc_html__( 'The city database has been downloaded and connected.', 'bobr-analytics' ), 'rebuilt' => esc_html__( 'Daily totals have been recalculated.', 'bobr-analytics' ) );
		if ( isset( $t[ $m ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $t[ $m ] ) . '</p></div>';
		}
		if ( 'geoerr' === $m ) {
			/* translators: %s: technical error message */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( esc_html__( 'The city database could not be downloaded: %s', 'bobr-analytics' ), (string) get_transient( 'bobr_geo_err' ) ) ) . '</p></div>';
		}
	}

	private static function toolbar( $sec ) {
		$r = self::$r;
		$p = self::g( 'period', '7d' );
		echo '<form class="bobr-bar" method="get"><input type="hidden" name="page" value="bobr"><input type="hidden" name="sec" value="' . esc_attr( $sec ) . '">';
		echo '<div class="bobr-periods">';
		foreach ( array( 'today' => esc_html__( 'Today', 'bobr-analytics' ), 'yesterday' => esc_html__( 'Yesterday', 'bobr-analytics' ), '7d' => esc_html__( '7 days', 'bobr-analytics' ), '30d' => esc_html__( '30 days', 'bobr-analytics' ), 'month' => esc_html__( 'This month', 'bobr-analytics' ) ) as $k => $l ) {
			echo '<a class="' . ( $p === $k ? 'is-on' : '' ) . '" href="' . esc_url( self::url( array( 'period' => $k, 'from' => null, 'to' => null ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '<span class="bobr-custom"><input type="date" name="from" value="' . esc_attr( $r->from ) . '"> - <input type="date" name="to" value="' . esc_attr( $r->to ) . '"><input type="hidden" name="period" value="custom"></span>';
		echo '<label class="bobr-cmp"><input type="checkbox" name="cmp" value="1" ' . checked( self::g( 'cmp' ), '1', false ) . '> ' . esc_html__( 'compare with the previous period', 'bobr-analytics' ) . '</label>';
		echo '<span class="bobr-units">' . esc_html__( 'Charts:', 'bobr-analytics' ) . ' ';
		foreach ( array( 'day' => esc_html__( 'by day', 'bobr-analytics' ), 'week' => esc_html__( 'by week', 'bobr-analytics' ), 'month' => esc_html__( 'by month', 'bobr-analytics' ) ) as $k => $l ) {
			echo '<a class="' . ( $r->unit === $k ? 'is-on' : '' ) . '" href="' . esc_url( self::url( array( 'unit' => 'day' === $k ? null : $k ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</span>' . ( 'day' !== $r->unit ? '<input type="hidden" name="unit" value="' . esc_attr( $r->unit ) . '">' : '' ) . '</div>';

		$src = array( '' => esc_html__( 'All sources', 'bobr-analytics' ), 'search' => esc_html__( 'Search engines', 'bobr-analytics' ), 'ai' => esc_html__( 'AI assistants', 'bobr-analytics' ), 'social' => esc_html__( 'Social networks', 'bobr-analytics' ), 'ref' => esc_html__( 'Websites', 'bobr-analytics' ), 'utm' => esc_html__( 'UTM campaigns', 'bobr-analytics' ), 'direct' => esc_html__( 'Direct', 'bobr-analytics' ), 'internal' => esc_html__( 'Internal', 'bobr-analytics' ) );
		$dev = array( '' => esc_html__( 'All devices', 'bobr-analytics' ), 'desktop' => esc_html__( 'Desktop', 'bobr-analytics' ), 'mobile' => esc_html__( 'Mobile', 'bobr-analytics' ), 'tablet' => esc_html__( 'Tablet', 'bobr-analytics' ) );
		echo '<div class="bobr-filters">' . wp_kses( self::select( 'f_src', $src, $r->f['src'] ) . self::select( 'f_dev', $dev, $r->f['dev'] ), self::FORM_TAGS );
		echo '<input type="search" name="f_city" placeholder="' . esc_attr__( 'City or region', 'bobr-analytics' ) . '" value="' . esc_attr( $r->f['city'] ) . '">';
		echo '<input type="search" name="f_page" placeholder="' . esc_attr__( 'Page contains', 'bobr-analytics' ) . '" value="' . esc_attr( $r->f['page'] ) . '">';
		if ( 'summary' === $sec ) {
			echo wp_kses( self::select( 'who', array( '' => esc_html__( 'People', 'bobr-analytics' ), 'bots' => esc_html__( 'Bots', 'bobr-analytics' ) ), self::g( 'who' ) ), self::FORM_TAGS );
		}
		echo '<button class="button button-primary">' . esc_html__( 'Apply', 'bobr-analytics' ) . '</button>';
		if ( $r->has_filters() ) {
			echo ' <a class="button-link" href="' . esc_url( self::url( array( 'f_src' => null, 'f_dev' => null, 'f_city' => null, 'f_page' => null ) ) ) . '">' . esc_html__( 'Reset filters', 'bobr-analytics' ) . '</a>';
		}
		/* translators: %s: date range of the previous period, for example 1 May - 31 May */
		echo '</div><div class="bobr-range">' . esc_html( Bobr_Charts::day_label( $r->from, true ) . ' - ' . Bobr_Charts::day_label( $r->to, true ) ) . ( self::g( 'cmp' ) ? ' · ' . esc_html( sprintf( esc_html__( 'compared with %s', 'bobr-analytics' ), Bobr_Charts::day_label( $r->pfrom ) . ' - ' . Bobr_Charts::day_label( $r->pto ) ) ) : '' ) . '</div></form>';
	}

	private static function select( $name, $opts, $val ) {
		$o = '<select name="' . esc_attr( $name ) . '">';
		foreach ( $opts as $k => $l ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $val, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $o . '</select>';
	}

	private static function dur( $s ) {
		$s = (int) round( $s );
		if ( $s < 60 ) {
			/* translators: %d: number of seconds */
			return sprintf( esc_html__( '%d s', 'bobr-analytics' ), $s );
		}
		/* translators: 1: minutes; 2: seconds, always two digits */
		return sprintf( esc_html__( '%1$d min %2$s s', 'bobr-analytics' ), floor( $s / 60 ), str_pad( (string) ( $s % 60 ), 2, '0', STR_PAD_LEFT ) );
	}

	private static function delta( $cur, $prev, $less_better = false ) {
		if ( ! self::g( 'cmp' ) ) {
			return '';
		}
		if ( ! $prev ) {
			return $cur ? '<span class="d up">' . esc_html__( 'new', 'bobr-analytics' ) . '</span>' : '';
		}
		$p    = ( $cur - $prev ) / $prev * 100;
		$good = $less_better ? $p < 0 : $p > 0;
		return '<span class="d ' . ( abs( $p ) < 0.5 ? '' : ( $good ? 'up' : 'down' ) ) . '">' . ( $p > 0 ? '+' : '' ) . number_format_i18n( $p, 1 ) . '%</span>';
	}

	private static function card( $label, $val, $delta = '', $hint = '' ) {
		echo '<div class="bobr-card"><div class="l">' . esc_html( $label ) . ( $hint ? ' <i title="' . esc_attr( $hint ) . '">?</i>' : '' ) . '</div><div class="v">' . esc_html( $val ) . '</div>' . wp_kses( $delta, array( 'span' => array( 'class' => true ) ) ) . '</div>';
	}

	private static function table( $title, $cols, $rows, $file = 'bobr' ) {
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>' . esc_html( $title ) . '</h2>';
		echo '<div class="bobr-tools"><input type="search" class="bobr-q" placeholder="' . esc_attr__( 'Search', 'bobr-analytics' ) . '"><button type="button" class="button bobr-csv" data-file="' . esc_attr( $file ) . '">CSV</button></div></div>';
		if ( ! $rows ) {
			echo '<p class="bobr-empty">' . esc_html__( 'No data for this period yet.', 'bobr-analytics' ) . '</p></section>';
			return;
		}
		$max = array();
		foreach ( $cols as $k => $c ) {
			if ( 'bar' === ( $c[1] ?? '' ) ) {
				$max[ $k ] = max( 1, max( array_map( function ( $r ) use ( $k ) { return (float) ( $r[ $k ] ?? 0 ); }, $rows ) ) );
			}
		}
		echo '<div class="bobr-tw"><table class="bobr-t" data-page="25"><thead><tr>';
		foreach ( $cols as $k => $c ) {
			$num = in_array( $c[1] ?? 'text', array( 'num', 'pct', 'sec', 'ms', 'bar', 'float' ), true );
			echo '<th class="' . ( $num ? 'n' : '' ) . '" data-type="' . ( $num ? 'n' : 't' ) . '">' . esc_html( $c[0] ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			foreach ( $cols as $k => $c ) {
				$v    = array_key_exists( $k, $r ) ? $r[ $k ] : '';
				$type = $c[1] ?? 'text';
				switch ( $type ) {
					case 'num':
					case 'bar':
						$txt = number_format_i18n( (float) $v );
						if ( 'bar' === $type ) {
							$txt = '<span class="bar" style="width:' . round( (float) $v / $max[ $k ] * 100, 1 ) . '%"></span><b>' . $txt . '</b>';
						}
						echo '<td class="n" data-v="' . esc_attr( (float) $v ) . '">' . wp_kses( $txt, array( 'span' => array( 'class' => true, 'style' => true ), 'b' => array() ) ) . '</td>';
						break;
					case 'float':
						echo '<td class="n" data-v="' . esc_attr( (float) $v ) . '">' . esc_html( number_format_i18n( (float) $v, 2 ) ) . '</td>';
						break;
					case 'pct':
						echo '<td class="n" data-v="' . esc_attr( (float) $v ) . '">' . esc_html( number_format_i18n( (float) $v, 1 ) ) . '%</td>';
						break;
					case 'sec':
						echo '<td class="n" data-v="' . esc_attr( (float) $v ) . '">' . esc_html( self::dur( $v ) ) . '</td>';
						break;
					case 'ms':
						/* translators: %s: time in milliseconds */
						echo '<td class="n ' . esc_attr( $r[ $k . '_c' ] ?? '' ) . '" data-v="' . esc_attr( null === $v ? -1 : (float) $v ) . '">' . esc_html( null === $v ? '-' : ( 'cls' === $k ? number_format_i18n( (float) $v, 3 ) : sprintf( esc_html__( '%s ms', 'bobr-analytics' ), number_format_i18n( (float) $v ) ) ) ) . '</td>';
						break;
					case 'link':
						echo '<td><a href="' . esc_url( $r[ $k . '_url' ] ) . '">' . esc_html( $v ) . '</a></td>';
						break;
					default:
						echo '<td>' . esc_html( $v ) . '</td>';
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table></div></section>';
	}

	private static function chart( $title, $labels, $series, $kind = 'line' ) {
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>' . esc_html( $title ) . '</h2></div>';
		if ( ! $series ) {
			echo '<p class="bobr-empty">' . esc_html__( 'No data for this period yet.', 'bobr-analytics' ) . '</p></section>';
			return;
		}
		echo wp_kses( 'stack' === $kind ? Bobr_Charts::stack( $labels, $series ) : Bobr_Charts::line( $labels, $series, 'half' === $kind ? 600 : 1000 ), Bobr_Charts::allowed() ) . '</section>';
	}

	private static function named( $split, $empty = null ) {
		$empty = $empty ?? esc_html__( 'Not detected', 'bobr-analytics' );
		$out   = array();
		foreach ( $split as $k => $vals ) {
			$out[] = array( '' === (string) $k ? $empty : Bobr_Util::label( (string) $k ), $vals );
		}
		return $out;
	}

	private static function per() {
		$t = array( 'day' => esc_html__( 'by day', 'bobr-analytics' ), 'week' => esc_html__( 'by week', 'bobr-analytics' ), 'month' => esc_html__( 'by month', 'bobr-analytics' ) );
		return $t[ self::$r->unit ];
	}

	private static function prev_series( $name, $vals, $n ) {
		/* translators: %s: chart line name, for example Visits */
		return self::g( 'cmp' ) ? array( array( sprintf( esc_html__( '%s (previous period)', 'bobr-analytics' ), $name ), array_slice( array_values( $vals ), 0, $n ), true ) ) : array();
	}

	private static function sec_summary() {
		global $wpdb;
		$r = self::$r;
		if ( 'bots' === self::g( 'who' ) ) {
			self::sec_bots();
			return;
		}
		$s  = $r->series();
		$t  = Bobr_Report::totals( $s );
		$ps = self::g( 'cmp' ) ? $r->series( true ) : array();
		$p  = $ps ? Bobr_Report::totals( $ps ) : array( 'visitors' => 0, 'visits' => 0, 'views' => 0, 'bounce_rate' => 0, 'depth' => 0, 'avg_time' => 0 );
		echo '<div class="bobr-cards">';
		self::card( esc_html__( 'Visitors', 'bobr-analytics' ), number_format_i18n( $t['visitors'] ), self::delta( $t['visitors'], $p['visitors'] ), esc_html__( 'Without cookies a visitor is recognized only within one day: over several days this is the sum of daily visitors.', 'bobr-analytics' ) );
		self::card( esc_html__( 'Visits', 'bobr-analytics' ), number_format_i18n( $t['visits'] ), self::delta( $t['visits'], $p['visits'] ) );
		self::card( esc_html__( 'Pageviews', 'bobr-analytics' ), number_format_i18n( $t['views'] ), self::delta( $t['views'], $p['views'] ) );
		self::card( esc_html__( 'Bounce rate', 'bobr-analytics' ), number_format_i18n( $t['bounce_rate'], 1 ) . '%', self::delta( $t['bounce_rate'], $p['bounce_rate'], true ), esc_html__( 'One page and less than 15 seconds.', 'bobr-analytics' ) );
		self::card( esc_html__( 'Depth', 'bobr-analytics' ), number_format_i18n( $t['depth'], 2 ), self::delta( $t['depth'], $p['depth'] ), esc_html__( 'Pages per visit.', 'bobr-analytics' ) );
		self::card( esc_html__( 'Time on site', 'bobr-analytics' ), self::dur( $t['avg_time'] ), self::delta( $t['avg_time'], $p['avg_time'] ) );
		echo '</div>';
		$rs     = $r->roll( $s );
		$labels = array_keys( $rs );
		$series = array(
			array( esc_html__( 'Visits', 'bobr-analytics' ), array_column( $rs, 'visits' ) ),
			array( esc_html__( 'Visitors', 'bobr-analytics' ), array_column( $rs, 'visitors' ) ),
			array( esc_html__( 'Pageviews', 'bobr-analytics' ), array_column( $rs, 'views' ) ),
		);
		if ( $ps ) {
			$series = array_merge( $series, self::prev_series( esc_html__( 'Visits', 'bobr-analytics' ), array_column( $r->roll( $ps ), 'visits' ), count( $labels ) ) );
		}
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Traffic %s', 'bobr-analytics' ), self::per() ), $labels, $series );
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Visits by source type %s', 'bobr-analytics' ), self::per() ), $labels, self::named( $r->split( 'src_type', '', 8 ) ) );

		$bounce = array();
		$time   = array();
		foreach ( $rs as $x ) {
			$bounce[] = $x['visits'] ? round( $x['bounces'] / $x['visits'] * 100, 1 ) : null;
			$time[]   = $x['visits'] ? round( $x['duration'] / $x['visits'] ) : null;
		}
		echo '<div class="bobr-2">';
		self::chart( esc_html__( 'Bounce rate, %', 'bobr-analytics' ), $labels, array( array( esc_html__( 'Bounce rate, %', 'bobr-analytics' ), $bounce ) ), 'half' );
		self::chart( esc_html__( 'Time on site, seconds', 'bobr-analytics' ), $labels, array( array( esc_html__( 'Time on site, s', 'bobr-analytics' ), $time ) ), 'half' );
		echo '</div>';

		echo '<div class="bobr-2">';
		$rows = array();
		foreach ( $r->group( "CONCAT(src_type,':',src_name)", '', 8 ) as $x ) {
			list( $ty, $nm ) = array_pad( explode( ':', $x->k, 2 ), 2, '' );
			$rows[] = array( 'src' => Bobr_Util::label( $ty ) . ( $nm && $nm !== Bobr_Util::label( $ty ) ? ': ' . $nm : '' ), 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( esc_html__( 'Sources', 'bobr-analytics' ), array( 'src' => array( esc_html__( 'Source', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ) ), $rows, 'istochniki' );
		$rows = array();
		foreach ( $r->group( 'entry', '', 8 ) as $x ) {
			$rows[] = array( 'p' => $x->k, 'p_url' => self::url( array( 'sec' => 'entry', 'path' => $x->k ) ), 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( esc_html__( 'Entry pages', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ), 'link' ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ) ), $rows, 'vhody' );
		echo '</div>';
	}

	private static function sec_entry() {
		global $wpdb;
		$r    = self::$r;
		$path = self::g( 'path' );
		if ( '' !== $path ) {
			echo '<p><a href="' . esc_url( self::url( array( 'path' => null ) ) ) . '">← ' . esc_html__( 'All entry pages', 'bobr-analytics' ) . '</a></p>';
			$extra = $wpdb->prepare( 'AND v.entry = %s', $path );
			$d     = $r->daily_visits( $extra );
			/* translators: %s: page address */
			self::chart( sprintf( esc_html__( 'Visits that started on %s', 'bobr-analytics' ), $path ), array_keys( $d ), array( array( esc_html__( 'Visits', 'bobr-analytics' ), array_values( $d ) ) ) );
			$rows = array();
			foreach ( $r->group( "CONCAT(src_type,':',src_name)", $extra ) as $x ) {
				list( $ty, $nm ) = array_pad( explode( ':', $x->k, 2 ), 2, '' );
				$rows[] = array( 'type' => Bobr_Util::label( $ty ), 'name' => $nm, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur, 'depth' => $x->depth );
			}
			self::table( esc_html__( 'Where visitors of this page come from', 'bobr-analytics' ), array( 'type' => array( esc_html__( 'Type', 'bobr-analytics' ) ), 'name' => array( esc_html__( 'Source', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'dur' => array( esc_html__( 'Time', 'bobr-analytics' ), 'sec' ), 'depth' => array( esc_html__( 'Depth', 'bobr-analytics' ), 'float' ) ), $rows, 'vhod-istochniki' );
			return;
		}
		$v    = Bobr_DB::t( 'visits' );
		$tops = array();
		foreach ( $wpdb->get_results( "SELECT entry, src_type, COUNT(*) n FROM $v v WHERE " . $r->where() . ' GROUP BY entry, src_type ORDER BY n DESC' ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$tops[ $x->entry ][] = Bobr_Util::label( $x->src_type ) . ' ' . $x->n;
		}
		$rows = array();
		foreach ( $r->group( 'entry' ) as $x ) {
			$rows[] = array( 'p' => $x->k, 'p_url' => self::url( array( 'path' => $x->k ) ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur, 'depth' => $x->depth, 'src' => implode( ', ', array_slice( $tops[ $x->k ] ?? array(), 0, 3 ) ) );
		}
		self::table( esc_html__( 'Entry pages', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ), 'link' ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'dur' => array( esc_html__( 'Time', 'bobr-analytics' ), 'sec' ), 'depth' => array( esc_html__( 'Depth', 'bobr-analytics' ), 'float' ), 'src' => array( esc_html__( 'Sources', 'bobr-analytics' ) ) ), $rows, 'stranicy-vhoda' );
	}

	private static function sec_pages() {
		global $wpdb;
		$r    = self::$r;
		$h    = Bobr_DB::t( 'hits' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, MAX(title) title, COUNT(*) views, COUNT(DISTINCT visit_id) visits, AVG(NULLIF(visible,0)) vis, AVG(NULLIF(scroll,0)) sc FROM $h h WHERE " . $r->hits_where() . ' GROUP BY path ORDER BY views DESC LIMIT 1000' ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'p' => $x->path, 't' => $x->title, 'views' => $x->views, 'visits' => $x->visits, 'vis' => (float) $x->vis, 'sc' => (float) $x->sc );
		}
		self::table( esc_html__( 'All pages', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 't' => array( esc_html__( 'Title', 'bobr-analytics' ) ), 'views' => array( esc_html__( 'Pageviews', 'bobr-analytics' ), 'bar' ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'num' ), 'vis' => array( esc_html__( 'Time on page', 'bobr-analytics' ), 'sec' ), 'sc' => array( esc_html__( 'Scroll depth', 'bobr-analytics' ), 'pct' ) ), $rows, 'vse-stranicy' );
		$rows = array();
		foreach ( $r->group( 'exit_path' ) as $x ) {
			$rows[] = array( 'p' => $x->k, 'visits' => $x->visits, 'depth' => $x->depth, 'dur' => $x->dur );
		}
		self::table( esc_html__( 'Exit pages', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits ended here', 'bobr-analytics' ), 'bar' ), 'depth' => array( esc_html__( 'Depth', 'bobr-analytics' ), 'float' ), 'dur' => array( esc_html__( 'Visit duration', 'bobr-analytics' ), 'sec' ) ), $rows, 'stranicy-vyhoda' );
	}

	private static function sec_sources() {
		global $wpdb;
		$r    = self::$r;
		$path = self::g( 'path' );
		if ( '' !== $path ) {
			list( $ty, $nm ) = array_pad( explode( ':', $path, 2 ), 2, '' );
			echo '<p><a href="' . esc_url( self::url( array( 'path' => null ) ) ) . '">← ' . esc_html__( 'All sources', 'bobr-analytics' ) . '</a></p>';
			$extra = $wpdb->prepare( 'AND v.src_type = %s AND v.src_name = %s', $ty, $nm );
			$d     = $r->daily_visits( $extra );
			/* translators: %s: traffic source, for example Search, Google */
			self::chart( sprintf( esc_html__( 'Visits: %s', 'bobr-analytics' ), Bobr_Util::label( $ty ) . ( $nm ? ', ' . $nm : '' ) ), array_keys( $d ), array( array( esc_html__( 'Visits', 'bobr-analytics' ), array_values( $d ) ) ) );
			$rows = array();
			foreach ( $r->group( 'entry', $extra ) as $x ) {
				$rows[] = array( 'p' => $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
			}
			self::table( esc_html__( 'Entry pages from this source', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'dur' => array( esc_html__( 'Time', 'bobr-analytics' ), 'sec' ) ), $rows, 'istochnik-vhody' );
			return;
		}
		$labels = $r->buckets();
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Source types %s', 'bobr-analytics' ), self::per() ), $labels, self::named( $r->split( 'src_type', '', 8 ) ) );
		echo '<div class="bobr-2">';
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Search engines %s', 'bobr-analytics' ), self::per() ), $labels, self::named( $r->split( 'src_name', "AND v.src_type = 'search'" ) ), 'half' );
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Social networks %s', 'bobr-analytics' ), self::per() ), $labels, self::named( $r->split( 'src_name', "AND v.src_type = 'social'" ) ), 'half' );
		echo '</div>';
		echo '<div class="bobr-2">';
		$rows = array();
		foreach ( $r->group( 'src_type' ) as $x ) {
			$rows[] = array( 'type' => Bobr_Util::label( $x->k ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
		}
		self::table( esc_html__( 'Source types', 'bobr-analytics' ), array( 'type' => array( esc_html__( 'Type', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'dur' => array( esc_html__( 'Time', 'bobr-analytics' ), 'sec' ) ), $rows, 'tipy-istochnikov' );
		$rows = array();
		foreach ( $r->group( 'src_name', "AND v.src_type = 'search'" ) as $x ) {
			$rows[] = array( 'name' => $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( esc_html__( 'Search engines', 'bobr-analytics' ), array( 'name' => array( esc_html__( 'Search engine', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ) ), $rows, 'poiskoviki' );
		echo '</div>';
		$rows = array();
		foreach ( $r->group( "CONCAT(src_type,':',src_name)" ) as $x ) {
			list( $ty, $nm ) = array_pad( explode( ':', $x->k, 2 ), 2, '' );
			$rows[] = array( 'type' => Bobr_Util::label( $ty ), 'name' => $nm, 'name_url' => self::url( array( 'path' => $x->k ) ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur, 'depth' => $x->depth );
		}
		self::table( esc_html__( 'All sources (click to see entry pages)', 'bobr-analytics' ), array( 'type' => array( esc_html__( 'Type', 'bobr-analytics' ) ), 'name' => array( esc_html__( 'Source', 'bobr-analytics' ), 'link' ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'dur' => array( esc_html__( 'Time', 'bobr-analytics' ), 'sec' ), 'depth' => array( esc_html__( 'Depth', 'bobr-analytics' ), 'float' ) ), $rows, 'istochniki' );
		$rows = array();
		foreach ( $r->group( "CONCAT_WS(' | ',utm_source,utm_medium,utm_campaign,utm_term,utm_content)", "AND (v.utm_source <> '' OR v.utm_campaign <> '')" ) as $x ) {
			$p      = array_pad( explode( ' | ', $x->k ), 5, '' );
			$rows[] = array( 's' => $p[0], 'm' => $p[1], 'c' => $p[2], 't' => $p[3], 'ct' => $p[4], 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( esc_html__( 'UTM tags', 'bobr-analytics' ), array( 's' => array( 'utm_source' ), 'm' => array( 'utm_medium' ), 'c' => array( 'utm_campaign' ), 't' => array( 'utm_term' ), 'ct' => array( 'utm_content' ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ) ), $rows, 'utm' );
	}

	private static function sec_ai() {
		$r     = self::$r;
		$extra = "AND v.src_type = 'ai'";
		$d     = $r->daily_visits( $extra );
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Visits from AI assistants %s', 'bobr-analytics' ), self::per() ), array_keys( $d ), array( array( esc_html__( 'Visits from AI assistants', 'bobr-analytics' ), array_values( $d ) ) ) );
		echo '<div class="bobr-2">';
		$rows = array();
		foreach ( $r->group( 'src_name', $extra ) as $x ) {
			$rows[] = array( 'name' => $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
		}
		self::table( esc_html__( 'AI assistants', 'bobr-analytics' ), array( 'name' => array( esc_html__( 'AI assistant', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'dur' => array( esc_html__( 'Time', 'bobr-analytics' ), 'sec' ) ), $rows, 'neiroseti' );
		$rows = array();
		foreach ( $r->group( "CONCAT(entry,'  ←  ',src_name)", $extra ) as $x ) {
			list( $p, $n ) = array_pad( explode( '  ←  ', $x->k, 2 ), 2, '' );
			$rows[] = array( 'p' => $p, 'n' => $n, 'visits' => $x->visits );
		}
		self::table( esc_html__( 'Pages that AI assistants link to', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 'n' => array( esc_html__( 'AI assistant', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ) ), $rows, 'neiroseti-stranicy' );
		echo '</div><p class="bobr-hint">' . esc_html__( 'The AI assistant is detected by the referring address and by the utm_source tag (ChatGPT adds it automatically). Some visits from AI apps arrive without a referrer and are counted as direct.', 'bobr-analytics' ) . '</p>';
	}

	private static function sec_bots() {
		global $wpdb;
		$r    = self::$r;
		$b    = Bobr_DB::t( 'bots' );
		$w    = $r->day_where();
		$rows = $wpdb->get_results( 'SELECT ' . $r->bucket_sql() . " d, kind, COUNT(*) n FROM $b WHERE $w GROUP BY d, kind" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		$by   = array();
		foreach ( $rows as $x ) {
			$by[ $x->kind ][ $x->d ] = (int) $x->n;
		}
		$labels = $r->buckets();
		$series = array();
		foreach ( array( 'search', 'ai', 'seo', 'other', 'unknown' ) as $k ) {
			if ( ! empty( $by[ $k ] ) ) {
				$vals = array();
				foreach ( $labels as $d ) {
					$vals[] = $by[ $k ][ $d ] ?? 0;
				}
				$series[] = array( Bobr_Util::label( $k ), $vals );
			}
		}
		if ( ! bobr_settings()['bots'] ) {
			echo '<p class="bobr-hint">' . esc_html__( 'Bot tracking is turned off in the settings.', 'bobr-analytics' ) . '</p>';
		}
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Bot requests %s', 'bobr-analytics' ), self::per() ), $labels, $series ? $series : array( array( esc_html__( 'Bots', 'bobr-analytics' ), array_fill( 0, count( $labels ), 0 ) ) ) );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT bot, kind, COUNT(*) n, COUNT(DISTINCT path) pages, SUM(status >= 400) errs, MAX(ts) last FROM $b WHERE $w GROUP BY bot, kind ORDER BY n DESC LIMIT 500" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'bot' => $x->bot, 'kind' => Bobr_Util::label( $x->kind ), 'n' => $x->n, 'pages' => $x->pages, 'errs' => $x->errs, 'last' => mysql2date( 'd.m H:i', $x->last ) );
		}
		self::table( esc_html__( 'Bots', 'bobr-analytics' ), array( 'bot' => array( esc_html__( 'Bot', 'bobr-analytics' ) ), 'kind' => array( esc_html__( 'Type', 'bobr-analytics' ) ), 'n' => array( esc_html__( 'Requests', 'bobr-analytics' ), 'bar' ), 'pages' => array( esc_html__( 'Distinct pages', 'bobr-analytics' ), 'num' ), 'errs' => array( esc_html__( '4xx-5xx errors', 'bobr-analytics' ), 'num' ), 'last' => array( esc_html__( 'Last visit', 'bobr-analytics' ) ) ), $rows, 'roboty' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT bot, path, COUNT(*) n, MAX(ts) last FROM $b WHERE $w AND kind = 'ai' GROUP BY bot, path ORDER BY n DESC LIMIT 500" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'bot' => $x->bot, 'p' => $x->path, 'n' => $x->n, 'last' => mysql2date( 'd.m H:i', $x->last ) );
		}
		self::table( esc_html__( 'AI agents: what they read', 'bobr-analytics' ), array( 'bot' => array( esc_html__( 'Agent', 'bobr-analytics' ) ), 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 'n' => array( esc_html__( 'Requests', 'bobr-analytics' ), 'bar' ), 'last' => array( esc_html__( 'Last time', 'bobr-analytics' ) ) ), $rows, 'ai-agenty' );
		$grid = array();
		foreach ( $wpdb->get_results( "SELECT WEEKDAY(ts)+1 wd, HOUR(ts) hh, COUNT(*) n FROM $b WHERE $w GROUP BY wd, hh" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$grid[ (int) $x->wd ][ (int) $x->hh ] = (int) $x->n;
		}
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>' . esc_html__( 'When bots come: days of the week and hours', 'bobr-analytics' ) . '</h2></div>' . wp_kses( Bobr_Charts::heat( $grid ), Bobr_Charts::allowed() ) . '</section>';
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, COUNT(*) n, COUNT(DISTINCT bot) bots, SUM(status = 404) nf FROM $b WHERE $w GROUP BY path ORDER BY n DESC LIMIT 500" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'p' => $x->path, 'n' => $x->n, 'bots' => $x->bots, 'nf' => $x->nf );
		}
		self::table( esc_html__( 'Pages that bots request', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 'n' => array( esc_html__( 'Requests', 'bobr-analytics' ), 'bar' ), 'bots' => array( esc_html__( 'Distinct bots', 'bobr-analytics' ), 'num' ), 'nf' => array( esc_html__( '404 responses', 'bobr-analytics' ), 'num' ) ), $rows, 'roboty-stranicy' );
	}

	private static function sec_geo() {
		$r = self::$r;
		if ( ! Bobr_Geo::has_db() ) {
			echo '<p class="bobr-hint">' . esc_html__( 'The city database is not connected: country and region are estimated from the browser time zone. You can connect the database in Settings.', 'bobr-analytics' ) . '</p>';
		}
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Top five cities %s', 'bobr-analytics' ), self::per() ), $r->buckets(), self::named( $r->split( 'city' ) ) );
		echo '<div class="bobr-2">';
		foreach ( array( 'country' => esc_html__( 'Countries', 'bobr-analytics' ), 'region' => esc_html__( 'Regions', 'bobr-analytics' ) ) as $col => $title ) {
			$rows = array();
			foreach ( $r->group( $col ) as $x ) {
				$rows[] = array( 'k' => '' === $x->k ? esc_html__( 'Not detected', 'bobr-analytics' ) : $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce );
			}
			self::table( $title, array( 'k' => array( $title ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ) ), $rows, $col );
		}
		echo '</div>';
		$rows = array();
		foreach ( $r->group( "CONCAT_WS(', ',NULLIF(city,''),NULLIF(region,''),NULLIF(country,''))" ) as $x ) {
			$rows[] = array( 'k' => '' === (string) $x->k ? esc_html__( 'Not detected', 'bobr-analytics' ) : $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
		}
		self::table( esc_html__( 'Cities', 'bobr-analytics' ), array( 'k' => array( esc_html__( 'City', 'bobr-analytics' ) ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'dur' => array( esc_html__( 'Time', 'bobr-analytics' ), 'sec' ) ), $rows, 'goroda' );
		echo '<p class="bobr-hint">' . esc_html__( 'On mobile internet the city is often inaccurate: carriers route traffic through large cities.', 'bobr-analytics' ) . '</p>';
		if ( Bobr_Geo::has_db() ) {
			echo '<p class="bobr-attr"><a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a></p>';
		}
	}

	private static function sec_tech() {
		$r    = self::$r;
		$cols = array( 'device' => esc_html__( 'Devices', 'bobr-analytics' ), 'os' => esc_html__( 'Operating systems', 'bobr-analytics' ), 'browser' => esc_html__( 'Browsers', 'bobr-analytics' ), 'screen' => esc_html__( 'Screen width', 'bobr-analytics' ) );
		$all  = array();
		foreach ( $cols as $col => $title ) {
			foreach ( $r->group( $col ) as $x ) {
				$all[ $col ][] = array( 'k' => 'device' === $col ? Bobr_Util::label( $x->k ) : ( '' === $x->k ? esc_html__( 'Not detected', 'bobr-analytics' ) : $x->k ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'depth' => $x->depth );
			}
		}
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Mobile and desktop: share of visits %s', 'bobr-analytics' ), self::per() ), $r->buckets(), self::named( $r->split( 'device' ) ), 'stack' );
		echo '<section class="bobr-box"><div class="bobr-donuts">';
		foreach ( array( 'device', 'os', 'browser' ) as $col ) {
			echo '<div><h3>' . esc_html( $cols[ $col ] ) . '</h3>' . wp_kses( Bobr_Charts::donut( array_column( $all[ $col ] ?? array(), 'visits', 'k' ) ), Bobr_Charts::allowed() ) . '</div>';
		}
		echo '</div></section><div class="bobr-2">';
		foreach ( $cols as $col => $title ) {
			self::table( $title, array( 'k' => array( $title ), 'visits' => array( esc_html__( 'Visits', 'bobr-analytics' ), 'bar' ), 'bounce' => array( esc_html__( 'Bounce rate', 'bobr-analytics' ), 'pct' ), 'depth' => array( esc_html__( 'Depth', 'bobr-analytics' ), 'float' ) ), $all[ $col ] ?? array(), $col );
		}
		echo '</div>';
	}

	private static function sec_time() {
		global $wpdb;
		$r    = self::$r;
		$v    = Bobr_DB::t( 'visits' );
		$grid = array();
		foreach ( $wpdb->get_results( "SELECT WEEKDAY(started)+1 wd, HOUR(started) hh, COUNT(*) n FROM $v v WHERE " . $r->where() . ' GROUP BY wd, hh' ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$grid[ (int) $x->wd ][ (int) $x->hh ] = (int) $x->n;
		}
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>' . esc_html__( 'Visits by day of the week and hour', 'bobr-analytics' ) . '</h2></div>' . wp_kses( Bobr_Charts::heat( $grid ), Bobr_Charts::allowed() ) . '<p class="bobr-hint">' . esc_html__( 'Times are in the site time zone.', 'bobr-analytics' ) . '</p></section>';
		$hours = array_fill( 0, 24, 0 );
		$wdays = array_fill( 0, 7, 0 );
		foreach ( $grid as $wd => $row ) {
			foreach ( $row as $hh => $n ) {
				$hours[ $hh ]    += $n;
				$wdays[ $wd - 1 ] += $n;
			}
		}
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>' . esc_html__( 'Visits by hour of the day', 'bobr-analytics' ) . '</h2></div>' . wp_kses( Bobr_Charts::bars( range( 0, 23 ), $hours, esc_html__( 'visits', 'bobr-analytics' ) ), Bobr_Charts::allowed() ) . '</section>';
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>' . esc_html__( 'Visits by day of the week', 'bobr-analytics' ) . '</h2></div>' . wp_kses( Bobr_Charts::bars( Bobr_Charts::weekdays(), $wdays, esc_html__( 'visits', 'bobr-analytics' ) ), Bobr_Charts::allowed() ) . '</section>';
	}

	private static function sec_behavior() {
		global $wpdb;
		$r    = self::$r;
		$h    = Bobr_DB::t( 'hits' );
		$by   = $wpdb->get_results( 'SELECT ' . $r->bucket_sql( 'h' ) . " d, AVG(NULLIF(visible,0)) vis, AVG(NULLIF(scroll,0)) sc FROM $h h WHERE " . $r->hits_where() . ' GROUP BY d', OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		$vis  = array();
		$sc   = array();
		foreach ( $r->buckets() as $d ) {
			$vis[] = isset( $by[ $d ]->vis ) ? round( (float) $by[ $d ]->vis ) : null;
			$sc[]  = isset( $by[ $d ]->sc ) ? round( (float) $by[ $d ]->sc, 1 ) : null;
		}
		echo '<div class="bobr-2">';
		self::chart( esc_html__( 'Time on page, seconds', 'bobr-analytics' ), $r->buckets(), array( array( esc_html__( 'Visible time, s', 'bobr-analytics' ), $vis ) ), 'half' );
		self::chart( esc_html__( 'Scroll depth, %', 'bobr-analytics' ), $r->buckets(), array( array( esc_html__( 'Average scroll depth, %', 'bobr-analytics' ), $sc ) ), 'half' );
		echo '</div>';
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, COUNT(*) views, AVG(NULLIF(visible,0)) vis, AVG(NULLIF(scroll,0)) sc, ROUND(SUM(scroll >= 75)/NULLIF(SUM(scroll > 0),0)*100,1) deep, ROUND(SUM(visible > 0 AND visible < 10)/NULLIF(SUM(visible > 0),0)*100,1) quick FROM $h h WHERE " . $r->hits_where() . ' GROUP BY path ORDER BY views DESC LIMIT 1000' ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'p' => $x->path, 'views' => $x->views, 'vis' => (float) $x->vis, 'sc' => (float) $x->sc, 'deep' => (float) $x->deep, 'quick' => (float) $x->quick );
		}
		self::table( esc_html__( 'Time and scroll depth by page', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 'views' => array( esc_html__( 'Pageviews', 'bobr-analytics' ), 'bar' ), 'vis' => array( esc_html__( 'Visible time', 'bobr-analytics' ), 'sec' ), 'sc' => array( esc_html__( 'Average scroll depth', 'bobr-analytics' ), 'pct' ), 'deep' => array( esc_html__( 'Read to 75%', 'bobr-analytics' ), 'pct' ), 'quick' => array( esc_html__( 'Left within 10 s', 'bobr-analytics' ), 'pct' ) ), $rows, 'povedenie' );
		echo '<p class="bobr-hint">' . esc_html__( 'Visible time counts only while the tab is in front of the visitor: a hidden tab is not counted.', 'bobr-analytics' ) . '</p>';
	}

	private static function sec_events() {
		global $wpdb;
		$r = self::$r;
		$e = Bobr_DB::t( 'events' );
		$w = $r->day_where( 'e' );
		$types = array( 'tel' => esc_html__( 'Phone clicks', 'bobr-analytics' ), 'mail' => esc_html__( 'Email clicks', 'bobr-analytics' ), 'out' => esc_html__( 'Outbound link clicks', 'bobr-analytics' ), 'form_sent' => esc_html__( 'Forms submitted', 'bobr-analytics' ), 'form_drop' => esc_html__( 'Forms abandoned', 'bobr-analytics' ) );
		echo '<div class="bobr-cards">';
		foreach ( $types as $t => $l ) {
			self::card( $l, number_format_i18n( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $e e WHERE $w AND type = %s", $t ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		}
		echo '</div>';
		$by = array();
		foreach ( $wpdb->get_results( 'SELECT ' . $r->bucket_sql( 'e' ) . " d, type, COUNT(*) n FROM $e e WHERE $w AND type IN ('tel','mail','out','form_sent','form_drop') GROUP BY d, type" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$by[ $x->type ][ $x->d ] = (int) $x->n;
		}
		$series = array();
		foreach ( $types as $t => $l ) {
			if ( ! empty( $by[ $t ] ) ) {
				$vals = array();
				foreach ( $r->buckets() as $d ) {
					$vals[] = $by[ $t ][ $d ] ?? 0;
				}
				$series[] = array( $l, $vals );
			}
		}
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Visitor actions %s', 'bobr-analytics' ), self::per() ), $r->buckets(), $series );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT name, COUNT(*) n, COUNT(DISTINCT path) pages FROM $e e WHERE $w AND type = 'out' GROUP BY name ORDER BY n DESC LIMIT 1000" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'u' => $x->name, 'n' => $x->n, 'pages' => $x->pages );
		}
		self::table( esc_html__( 'Outbound links', 'bobr-analytics' ), array( 'u' => array( esc_html__( 'Destination', 'bobr-analytics' ) ), 'n' => array( esc_html__( 'Clicks', 'bobr-analytics' ), 'bar' ), 'pages' => array( esc_html__( 'From distinct pages', 'bobr-analytics' ), 'num' ) ), $rows, 'vneshnie-ssylki' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT name, path, SUM(type = 'form_sent') s, SUM(type = 'form_drop') dr FROM $e e WHERE $w AND type IN ('form_sent','form_drop') GROUP BY name, path ORDER BY s DESC, dr DESC LIMIT 1000" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'f' => $x->name, 'p' => $x->path, 's' => $x->s, 'dr' => $x->dr );
		}
		self::table( esc_html__( 'Forms', 'bobr-analytics' ), array( 'f' => array( esc_html__( 'Form', 'bobr-analytics' ) ), 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 's' => array( esc_html__( 'Submitted', 'bobr-analytics' ), 'bar' ), 'dr' => array( esc_html__( 'Started and abandoned', 'bobr-analytics' ), 'num' ) ), $rows, 'formy' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, SUM(type = 'tel') t, SUM(type = 'mail') m FROM $e e WHERE $w AND type IN ('tel','mail') GROUP BY path ORDER BY t DESC LIMIT 500" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'p' => $x->path, 't' => $x->t, 'm' => $x->m );
		}
		self::table( esc_html__( 'Where visitors click phone and email links', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 't' => array( esc_html__( 'Calls', 'bobr-analytics' ), 'bar' ), 'm' => array( esc_html__( 'Email', 'bobr-analytics' ), 'num' ) ), $rows, 'telefon-pochta' );
		echo '<p class="bobr-hint">' . esc_html__( 'Phone numbers, email addresses and form field values are never recorded: only the click itself and the form name.', 'bobr-analytics' ) . '</p>';
	}

	private static function sec_nf() {
		global $wpdb;
		$r    = self::$r;
		$e    = Bobr_DB::t( 'events' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, name, COUNT(*) n, MAX(ts) last FROM $e e WHERE " . $r->day_where( 'e' ) . " AND type = 'nf' GROUP BY path, name ORDER BY n DESC LIMIT 1000" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$rows[] = array( 'p' => $x->path, 'from' => $x->name, 'n' => $x->n, 'last' => mysql2date( 'd.m H:i', $x->last ) );
		}
		self::table( esc_html__( '404 pages seen by people: where they came from', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Missing address', 'bobr-analytics' ) ), 'from' => array( esc_html__( 'Came from', 'bobr-analytics' ) ), 'n' => array( esc_html__( 'Times', 'bobr-analytics' ), 'bar' ), 'last' => array( esc_html__( 'Last time', 'bobr-analytics' ) ) ), $rows, 'oshibki-404' );
		echo '<p class="bobr-hint">' . esc_html__( '404 errors seen by bots are in the Bots section.', 'bobr-analytics' ) . '</p>';
	}

	private static function sec_speed() {
		global $wpdb;
		$r    = self::$r;
		$e    = Bobr_DB::t( 'events' );
		$data = array();
		$by   = array();
		foreach ( $wpdb->get_results( 'SELECT ' . $r->bucket_sql( 'e' ) . " d, path, type, val FROM $e e WHERE " . $r->day_where( 'e' ) . " AND type IN ('ttfb','fcp','lcp','inp','cls') ORDER BY id DESC LIMIT 60000" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$data[ $x->path ][ $x->type ][] = (float) $x->val;
			$by[ $x->type ][ $x->d ][]      = (float) $x->val;
		}
		$series = array();
		foreach ( array( 'ttfb' => esc_html__( 'Server response', 'bobr-analytics' ), 'fcp' => esc_html__( 'First paint', 'bobr-analytics' ), 'lcp' => esc_html__( 'Main content (LCP)', 'bobr-analytics' ), 'inp' => esc_html__( 'Responsiveness (INP)', 'bobr-analytics' ) ) as $k => $l ) {
			if ( ! empty( $by[ $k ] ) ) {
				$vals = array();
				foreach ( $r->buckets() as $d ) {
					$vals[] = Bobr_Report::p75( $by[ $k ][ $d ] ?? array() );
				}
				$series[] = array( $l, $vals );
			}
		}
		/* translators: %s: chart step: by day, by week or by month */
		self::chart( sprintf( esc_html__( 'Speed %s, ms (75th percentile)', 'bobr-analytics' ), self::per() ), $r->buckets(), $series );
		$lim  = array( 'ttfb' => array( 800, 1800 ), 'fcp' => array( 1800, 3000 ), 'lcp' => array( 2500, 4000 ), 'inp' => array( 200, 500 ), 'cls' => array( 0.1, 0.25 ) );
		$rows = array();
		foreach ( $data as $p => $m ) {
			$row = array( 'p' => $p, 'n' => count( $m['ttfb'] ?? $m['fcp'] ?? $m['lcp'] ?? array() ) );
			foreach ( $lim as $k => $l ) {
				$v           = Bobr_Report::p75( $m[ $k ] ?? array() );
				$row[ $k ]   = $v;
				$row[ $k . '_c' ] = null === $v ? '' : ( $v <= $l[0] ? 'good' : ( $v <= $l[1] ? 'mid' : 'bad' ) );
			}
			$rows[] = $row;
		}
		usort( $rows, function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
		self::table( esc_html__( 'Speed by page: 75th percentile', 'bobr-analytics' ), array( 'p' => array( esc_html__( 'Page', 'bobr-analytics' ) ), 'n' => array( esc_html__( 'Samples', 'bobr-analytics' ), 'num' ), 'ttfb' => array( esc_html__( 'Server response', 'bobr-analytics' ), 'ms' ), 'fcp' => array( esc_html__( 'First paint', 'bobr-analytics' ), 'ms' ), 'lcp' => array( esc_html__( 'Main content (LCP)', 'bobr-analytics' ), 'ms' ), 'inp' => array( esc_html__( 'Responsiveness (INP)', 'bobr-analytics' ), 'ms' ), 'cls' => array( esc_html__( 'Layout shifts (CLS)', 'bobr-analytics' ), 'ms' ) ), $rows, 'skorost' );
		echo '<p class="bobr-hint">' . esc_html__( 'Green is good, yellow needs improvement, red is poor (Google Core Web Vitals thresholds). 75th percentile: three out of four visitors got this result or better.', 'bobr-analytics' ) . '</p>';
	}

	private static function sec_now() {
		global $wpdb;
		$v     = Bobr_DB::t( 'visits' );
		$since = wp_date( 'Y-m-d H:i:s', time() - 1800 );
		$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $v WHERE last_seen >= %s ORDER BY last_seen DESC LIMIT 300", $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		$five  = 0;
		foreach ( $list as $x ) {
			if ( strtotime( $x->last_seen ) >= strtotime( wp_date( 'Y-m-d H:i:s' ) ) - 300 ) {
				$five++;
			}
		}
		echo '<div class="bobr-cards">';
		self::card( esc_html__( 'Visits in the last 30 minutes', 'bobr-analytics' ), number_format_i18n( count( $list ) ) );
		self::card( esc_html__( 'Active in the last 5 minutes', 'bobr-analytics' ), number_format_i18n( $five ) );
		echo '</div>';
		$rows = array();
		foreach ( $list as $x ) {
			$rows[] = array( 'when' => mysql2date( 'H:i', $x->last_seen ), 'entry' => $x->entry, 'exit' => $x->exit_path, 'pages' => $x->pages, 'src' => Bobr_Util::label( $x->src_type ) . ( $x->src_name && $x->src_name !== Bobr_Util::label( $x->src_type ) ? ': ' . $x->src_name : '' ), 'dev' => Bobr_Util::label( $x->device ), 'geo' => implode( ', ', array_filter( array( $x->city, $x->country ) ) ) );
		}
		self::table( esc_html__( 'Live now', 'bobr-analytics' ), array( 'when' => array( esc_html__( 'Last activity', 'bobr-analytics' ) ), 'entry' => array( esc_html__( 'Entry', 'bobr-analytics' ) ), 'exit' => array( esc_html__( 'Current page', 'bobr-analytics' ) ), 'pages' => array( esc_html__( 'Pages', 'bobr-analytics' ), 'num' ), 'src' => array( esc_html__( 'Source', 'bobr-analytics' ) ), 'dev' => array( esc_html__( 'Device', 'bobr-analytics' ) ), 'geo' => array( esc_html__( 'City', 'bobr-analytics' ) ) ), $rows, 'seichas' );
	}

	private static function sec_settings() {
		$s     = bobr_settings();
		$roles = wp_roles()->get_names();
		$ip    = Bobr_Util::ip();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bobr-box bobr-set"><input type="hidden" name="action" value="bobr_settings">';
		wp_nonce_field( 'bobr_settings' );
		echo '<h2>' . esc_html__( 'Who not to count', 'bobr-analytics' ) . '</h2><div class="bobr-roles">';
		foreach ( $roles as $k => $name ) {
			echo '<label><input type="checkbox" name="exclude_roles[]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, (array) $s['exclude_roles'], true ), true, false ) . '> ' . esc_html( translate_user_role( $name ) ) . '</label>';
		}
		echo '</div><p class="bobr-hint">' . esc_html__( 'Logged-in users with the selected roles are not counted.', 'bobr-analytics' ) . '</p>';
		/* translators: %d: number of excluded IP addresses */
		echo '<h3>' . esc_html__( 'Your own IP addresses', 'bobr-analytics' ) . '</h3><p>' . esc_html( sprintf( esc_html__( 'Excluded addresses: %d. They are stored only as hashes and cannot be read back.', 'bobr-analytics' ), count( (array) $s['exclude_ips'] ) ) ) . '</p>';
		echo '<textarea name="add_ips" rows="3" placeholder="' . esc_attr__( 'Add IP addresses, one per line', 'bobr-analytics' ) . '"></textarea>';
		/* translators: %s: IP address of the current user */
		echo '<p><label><input type="checkbox" name="add_my_ip" value="1"> ' . esc_html( sprintf( esc_html__( 'Add my current IP (%s)', 'bobr-analytics' ), $ip ) ) . '</label> &nbsp; <label><input type="checkbox" name="clear_ips" value="1"> ' . esc_html__( 'Clear the list', 'bobr-analytics' ) . '</label></p>';
		echo '<h2>' . esc_html__( 'Storage and collection', 'bobr-analytics' ) . '</h2>';
		echo '<p><label>' . esc_html__( 'Keep detailed records, days:', 'bobr-analytics' ) . ' <input type="number" name="retention" min="30" max="1095" value="' . esc_attr( $s['retention'] ) . '"></label></p><p class="bobr-hint">' . esc_html__( 'Older records are deleted at night. Daily totals without personal data are kept forever.', 'bobr-analytics' ) . '</p>';
		echo '<p><label><input type="checkbox" name="geo" value="1" ' . checked( $s['geo'], 1, false ) . '> ' . esc_html__( 'Detect the city by IP (the IP is discarded right after that)', 'bobr-analytics' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="bots" value="1" ' . checked( $s['bots'], 1, false ) . '> ' . esc_html__( 'Record bots', 'bobr-analytics' ) . '</label></p>';
		submit_button( esc_html__( 'Save settings', 'bobr-analytics' ) );
		echo '</form>';

		echo '<section class="bobr-box bobr-set"><h2>' . esc_html__( 'City database', 'bobr-analytics' ) . '</h2>';
		if ( Bobr_Geo::has_db() ) {
			$up   = (int) get_option( 'bobr_geo_updated' );
			$size = size_format( filesize( Bobr_Geo::db_file() ) );
			if ( $up ) {
				/* translators: 1: database file size, for example 130 MB; 2: date of the last update */
				$msg = sprintf( esc_html__( 'Connected: %1$s, updated %2$s.', 'bobr-analytics' ), $size, wp_date( get_option( 'date_format' ), $up ) );
			} else {
				/* translators: %s: database file size, for example 130 MB */
				$msg = sprintf( esc_html__( 'Connected: %s.', 'bobr-analytics' ), $size );
			}
			echo '<p>' . esc_html( $msg ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Not connected. Until it is, country and region are estimated from the browser time zone.', 'bobr-analytics' ) . '</p>';
		}
		echo '<p class="bobr-hint">' . esc_html__( 'The DB-IP IP to City Lite file from db-ip.com: about 60 MB to download, about 130 MB on disk after unpacking. Free, no registration, updated monthly. The button makes a single request to db-ip.com, visitor data is never sent anywhere.', 'bobr-analytics' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( esc_html__( 'Download the city database from db-ip.com (about 60 MB)?', 'bobr-analytics' ) ) . '\');"><input type="hidden" name="action" value="bobr_geo">';
		wp_nonce_field( 'bobr_geo' );
		submit_button( Bobr_Geo::has_db() ? esc_html__( 'Update the city database', 'bobr-analytics' ) : esc_html__( 'Download the city database', 'bobr-analytics' ), 'secondary', 'submit', false );
		echo '</form><p class="bobr-attr"><a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> ' . esc_html__( '(CC BY 4.0 license)', 'bobr-analytics' ) . '</p></section>';

		echo '<section class="bobr-box bobr-set"><h2>' . esc_html__( 'Maintenance', 'bobr-analytics' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bobr_rebuild">';
		wp_nonce_field( 'bobr_rebuild' );
		echo '<p>' . esc_html__( 'Daily totals are recalculated every night. Recalculate the last 30 days now:', 'bobr-analytics' ) . ' ';
		submit_button( esc_html__( 'Recalculate', 'bobr-analytics' ), 'secondary', 'submit', false );
		echo '</p></form><p class="bobr-hint">' . ( defined( 'WP_CACHE' ) && WP_CACHE ? esc_html__( 'This site uses a page cache: bots on cached pages are not counted, people are counted in full.', 'bobr-analytics' ) : esc_html__( 'This site has no page cache: bots are counted in full.', 'bobr-analytics' ) ) . '</p></section>';

		echo '<section class="bobr-box bobr-set"><h2>' . esc_html__( 'About', 'bobr-analytics' ) . '</h2>';
		/* translators: %s: plugin version number */
		echo '<p>' . esc_html( sprintf( esc_html__( 'Bobr Analytics %s, a free open source plugin.', 'bobr-analytics' ), BOBR_VER ) ) . ' ' . esc_html__( 'Author:', 'bobr-analytics' ) . ' <a href="https://lop-pa.dev" target="_blank" rel="noopener">Ekaterina Lopatina</a>.</p>';
		echo '<p><a class="button" href="' . esc_url( BOBR_GITHUB ) . '" target="_blank" rel="noopener">' . esc_html__( 'Source code on GitHub', 'bobr-analytics' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( BOBR_GITHUB . '/issues' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Report a bug or suggest an idea', 'bobr-analytics' ) . '</a> ';
		echo '<a class="button" href="https://lop-pa.dev" target="_blank" rel="noopener">lop-pa.dev</a></p></section>';
	}

	private static function check( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'bobr-analytics' ) );
		}
		check_admin_referer( $action );
	}

	private static function back( $msg ) {
		wp_safe_redirect( admin_url( 'admin.php?page=bobr&sec=settings&bobr_msg=' . $msg ) );
		exit;
	}

	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'bobr-analytics' ) );
		}
		check_admin_referer( 'bobr_settings' );
		$s     = bobr_settings();
		$roles = array_keys( wp_roles()->get_names() );
		$asked = isset( $_POST['exclude_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['exclude_roles'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every role is passed through sanitize_key() on this line.
		$s['exclude_roles'] = array_values( array_intersect( $asked, $roles ) );
		$ips = ! empty( $_POST['clear_ips'] ) ? array() : (array) $s['exclude_ips'];
		foreach ( preg_split( '~\s+~', sanitize_textarea_field( wp_unslash( $_POST['add_ips'] ?? '' ) ) ) as $ip ) {
			if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
				$ips[] = Bobr_Util::ip_hash( $ip );
			}
		}
		if ( ! empty( $_POST['add_my_ip'] ) ) {
			$ips[] = Bobr_Util::ip_hash( Bobr_Util::ip() );
		}
		$s['exclude_ips'] = array_values( array_unique( $ips ) );
		$s['retention']   = max( 30, min( 1095, absint( wp_unslash( $_POST['retention'] ?? 180 ) ) ) );
		$s['geo']         = empty( $_POST['geo'] ) ? 0 : 1;
		$s['bots']        = empty( $_POST['bots'] ) ? 0 : 1;
		update_option( 'bobr_settings', $s, false );
		self::back( 'saved' );
	}

	public static function geo_download() {
		self::check( 'bobr_geo' );
		set_time_limit( 900 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- the city database is about 60 MB, the download needs more time than the default limit.
		$res = Bobr_Geo::download();
		if ( is_wp_error( $res ) ) {
			set_transient( 'bobr_geo_err', $res->get_error_message(), 300 );
			self::back( 'geoerr' );
		}
		self::back( 'geo' );
	}

	public static function rebuild() {
		self::check( 'bobr_rebuild' );
		for ( $i = 0; $i <= 30; $i++ ) {
			Bobr_Cron::aggregate( wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) );
		}
		self::back( 'rebuilt' );
	}
}
