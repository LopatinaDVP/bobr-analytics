<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Report {
	public $from;
	public $to;
	public $pfrom;
	public $pto;
	public $f = array();
	public $unit = 'day';

	public function __construct( $q ) {
		$u          = isset( $q['unit'] ) ? sanitize_key( $q['unit'] ) : '';
		$this->unit = in_array( $u, array( 'week', 'month' ), true ) ? $u : 'day';
		$today  = wp_date( 'Y-m-d' );
		$period = isset( $q['period'] ) ? sanitize_key( $q['period'] ) : '7d';
		switch ( $period ) {
			case 'today':
				$this->from = $this->to = $today;
				break;
			case 'yesterday':
				$this->from = $this->to = wp_date( 'Y-m-d', strtotime( '-1 day' ) );
				break;
			case '30d':
				$this->from = wp_date( 'Y-m-d', strtotime( '-29 days' ) );
				$this->to   = $today;
				break;
			case 'month':
				$this->from = wp_date( 'Y-m-01' );
				$this->to   = $today;
				break;
			case 'custom':
				$f = isset( $q['from'] ) ? sanitize_text_field( $q['from'] ) : '';
				$t = isset( $q['to'] ) ? sanitize_text_field( $q['to'] ) : '';
				$this->from = preg_match( '~^\d{4}-\d{2}-\d{2}$~', $f ) ? $f : $today;
				$this->to   = preg_match( '~^\d{4}-\d{2}-\d{2}$~', $t ) ? $t : $today;
				if ( $this->from > $this->to ) {
					list( $this->from, $this->to ) = array( $this->to, $this->from );
				}
				break;
			default:
				$this->from = wp_date( 'Y-m-d', strtotime( '-6 days' ) );
				$this->to   = $today;
		}
		$len         = $this->days_between( $this->from, $this->to ) + 1;
		$this->pto   = gmdate( 'Y-m-d', strtotime( $this->from . ' -1 day' ) );
		$this->pfrom = gmdate( 'Y-m-d', strtotime( $this->pto . ' -' . ( $len - 1 ) . ' days' ) );

		foreach ( array( 'src', 'dev', 'city', 'page' ) as $k ) {
			$this->f[ $k ] = isset( $q[ 'f_' . $k ] ) ? sanitize_text_field( wp_unslash( $q[ 'f_' . $k ] ) ) : '';
		}
	}

	public function days_between( $a, $b ) {
		return (int) round( ( strtotime( $b ) - strtotime( $a ) ) / DAY_IN_SECONDS );
	}

	public function days( $from = null, $to = null ) {
		$from = $from ? $from : $this->from;
		$to   = $to ? $to : $this->to;
		$out  = array();
		for ( $t = strtotime( $from ); $t <= strtotime( $to ); $t += DAY_IN_SECONDS ) {
			$out[] = gmdate( 'Y-m-d', $t );
		}
		return $out;
	}

	public function bucket( $d ) {
		if ( 'week' === $this->unit ) {
			$t = strtotime( $d );
			return gmdate( 'Y-m-d', $t - ( (int) gmdate( 'N', $t ) - 1 ) * DAY_IN_SECONDS );
		}
		return 'month' === $this->unit ? substr( $d, 0, 8 ) . '01' : $d;
	}

	public function bucket_sql( $alias = '' ) {
		$c = ( $alias ? $alias . '.' : '' ) . 'day';
		if ( 'week' === $this->unit ) {
			return "DATE_SUB($c, INTERVAL WEEKDAY($c) DAY)";
		}
		return 'month' === $this->unit ? "DATE_FORMAT($c, '%Y-%m-01')" : $c;
	}

	public function buckets( $prev = false ) {
		$out = array();
		foreach ( $prev ? $this->days( $this->pfrom, $this->pto ) : $this->days() as $d ) {
			$out[ $this->bucket( $d ) ] = 1;
		}
		return array_keys( $out );
	}

	public function roll( $series ) {
		$out = array();
		foreach ( $series as $d => $row ) {
			$b = $this->bucket( $d );
			foreach ( $row as $k => $v ) {
				$out[ $b ][ $k ] = ( $out[ $b ][ $k ] ?? 0 ) + $v;
			}
		}
		return $out;
	}

	public function has_filters() {
		return (bool) array_filter( $this->f, 'strlen' );
	}

	public function where( $prev = false, $alias = 'v' ) {
		global $wpdb;
		$a = $alias ? $alias . '.' : '';
		$w = $wpdb->prepare( "{$a}day BETWEEN %s AND %s", $prev ? $this->pfrom : $this->from, $prev ? $this->pto : $this->to ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $a is a fixed table alias from code ('v.' or empty), values are bound with placeholders.
		if ( '' !== $this->f['src'] ) {
			$w .= $wpdb->prepare( " AND {$a}src_type = %s", $this->f['src'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $a is a fixed table alias from code ('v.' or empty), values are bound with placeholders.
		}
		if ( '' !== $this->f['dev'] ) {
			$w .= $wpdb->prepare( " AND {$a}device = %s", $this->f['dev'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $a is a fixed table alias from code ('v.' or empty), values are bound with placeholders.
		}
		if ( '' !== $this->f['city'] ) {
			$w .= $wpdb->prepare( " AND ({$a}city LIKE %s OR {$a}region LIKE %s OR {$a}country LIKE %s)", '%' . $wpdb->esc_like( $this->f['city'] ) . '%', '%' . $wpdb->esc_like( $this->f['city'] ) . '%', '%' . $wpdb->esc_like( $this->f['city'] ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $a is a fixed table alias from code ('v.' or empty), values are bound with placeholders.
		}
		if ( '' !== $this->f['page'] ) {
			$w .= $wpdb->prepare( " AND {$a}id IN (SELECT visit_id FROM %i WHERE path LIKE %s)", Bobr_DB::t( 'hits' ), '%' . $wpdb->esc_like( $this->f['page'] ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $a is a fixed table alias from code ('v.' or empty), values are bound with placeholders.
		}
		return $w;
	}

	public function series( $prev = false ) {
		global $wpdb;
		$v    = Bobr_DB::t( 'visits' );
		$rows = $wpdb->get_results( "SELECT day, COUNT(DISTINCT vhash) visitors, COUNT(*) visits, SUM(pages) views, SUM(bounce) bounces, SUM(duration) duration FROM $v v WHERE " . $this->where( $prev ) . ' GROUP BY day', OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		$out  = array();
		$days = $prev ? $this->days( $this->pfrom, $this->pto ) : $this->days();
		$agg  = array();
		if ( ! $this->has_filters() ) {
			$agg = $wpdb->get_results( $wpdb->prepare( "SELECT day, visitors, visits, views, bounces, duration FROM %i WHERE kind = 'site' AND day BETWEEN %s AND %s", Bobr_DB::t( 'daily' ), reset( $days ), end( $days ) ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		}
		foreach ( $days as $d ) {
			$r = isset( $rows[ $d ] ) ? $rows[ $d ] : ( isset( $agg[ $d ] ) ? $agg[ $d ] : null );
			$out[ $d ] = array(
				'visitors' => $r ? (int) $r->visitors : 0,
				'visits'   => $r ? (int) $r->visits : 0,
				'views'    => $r ? (int) $r->views : 0,
				'bounces'  => $r ? (int) $r->bounces : 0,
				'duration' => $r ? (int) $r->duration : 0,
			);
		}
		return $out;
	}

	public static function totals( $series ) {
		$t = array( 'visitors' => 0, 'visits' => 0, 'views' => 0, 'bounces' => 0, 'duration' => 0 );
		foreach ( $series as $r ) {
			foreach ( $t as $k => $x ) {
				$t[ $k ] += $r[ $k ];
			}
		}
		$t['bounce_rate'] = $t['visits'] ? $t['bounces'] / $t['visits'] * 100 : 0;
		$t['depth']       = $t['visits'] ? $t['views'] / $t['visits'] : 0;
		$t['avg_time']    = $t['visits'] ? $t['duration'] / $t['visits'] : 0;
		return $t;
	}

	public function group( $expr, $extra = '', $limit = 1000 ) {
		global $wpdb;
		$v = Bobr_DB::t( 'visits' );
		return $wpdb->get_results( "SELECT $expr AS k, COUNT(*) visits, COUNT(DISTINCT vhash, day) visitors, ROUND(AVG(bounce)*100,1) bounce, ROUND(AVG(duration)) dur, ROUND(AVG(pages),2) depth FROM $v v WHERE " . $this->where() . " $extra GROUP BY k ORDER BY visits DESC LIMIT " . (int) $limit ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
	}

	public function daily_visits( $extra = '' ) {
		global $wpdb;
		$v    = Bobr_DB::t( 'visits' );
		$rows = $wpdb->get_results( 'SELECT ' . $this->bucket_sql( 'v' ) . " d, COUNT(*) n FROM $v v WHERE " . $this->where() . " $extra GROUP BY d", OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		$out  = array();
		foreach ( $this->buckets() as $d ) {
			$out[ $d ] = isset( $rows[ $d ] ) ? (int) $rows[ $d ]->n : 0;
		}
		return $out;
	}

	public function split( $expr, $extra = '', $top = 5 ) {
		global $wpdb;
		$v    = Bobr_DB::t( 'visits' );
		$w    = $this->where() . " $extra";
		$keys = $wpdb->get_col( "SELECT $expr k FROM $v v WHERE $w GROUP BY k ORDER BY COUNT(*) DESC LIMIT " . (int) $top ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
		$by   = array();
		foreach ( $wpdb->get_results( 'SELECT ' . $this->bucket_sql( 'v' ) . " d, $expr k, COUNT(*) n FROM $v v WHERE $w GROUP BY d, k" ) as $x ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own statistics tables: table names come from $wpdb->prefix, columns and SQL expressions are fixed in code, user values are bound with $wpdb->prepare() in Bobr_Report::where().
			$by[ (string) $x->k ][ $x->d ] = (int) $x->n;
		}
		$out = array();
		foreach ( $keys as $k ) {
			foreach ( $this->buckets() as $d ) {
				$out[ (string) $k ][] = $by[ (string) $k ][ $d ] ?? 0;
			}
		}
		return $out;
	}

	public function hits_where() {
		return 'h.visit_id IN (SELECT id FROM ' . Bobr_DB::t( 'visits' ) . ' v WHERE ' . $this->where() . ')';
	}

	public function day_where( $alias = '' ) {
		global $wpdb;
		$a = $alias ? $alias . '.' : '';
		return $wpdb->prepare( "{$a}day BETWEEN %s AND %s", $this->from, $this->to ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $a is a fixed table alias from code ('v.' or empty), values are bound with placeholders.
	}

	public static function p75( $vals ) {
		if ( ! $vals ) {
			return null;
		}
		sort( $vals );
		$i = (int) ceil( 0.75 * count( $vals ) ) - 1;
		return $vals[ max( 0, $i ) ];
	}
}
