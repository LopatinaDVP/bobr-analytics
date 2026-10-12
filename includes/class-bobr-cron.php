<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Cron {
	const HOOK = 'bobr_nightly';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$t = strtotime( wp_date( 'Y-m-d' ) . ' 03:30:00 ' . wp_timezone_string() );
			if ( $t < time() ) {
				$t += DAY_IN_SECONDS;
			}
			wp_schedule_event( $t, 'daily', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run() {
		for ( $i = 1; $i <= 3; $i++ ) {
			self::aggregate( wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) );
		}
		self::purge();
	}

	public static function aggregate( $day ) {
		global $wpdb;
		$v = Bobr_DB::t( 'visits' );
		$h = Bobr_DB::t( 'hits' );
		$b = Bobr_DB::t( 'bots' );
		$d = Bobr_DB::t( 'daily' );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day = %s', $d, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, nightly totals.

		$views = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE day = %s', $h, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, nightly totals.
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i (day,kind,k,visitors,visits,views,bounces,duration) SELECT day,'site','',COUNT(DISTINCT vhash),COUNT(*),%d,SUM(bounce),SUM(duration) FROM %i WHERE day = %s GROUP BY day", $d, $views, $v, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, nightly totals.
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i (day,kind,k,visitors,visits,views,bounces,duration) SELECT day,'entry',LEFT(entry,190),COUNT(DISTINCT vhash),COUNT(*),SUM(pages),SUM(bounce),SUM(duration) FROM %i WHERE day = %s GROUP BY day, LEFT(entry,190)", $d, $v, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, nightly totals.
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i (day,kind,k,visitors,visits,views,bounces,duration) SELECT day,'source',LEFT(CONCAT(src_type,':',src_name),190),COUNT(DISTINCT vhash),COUNT(*),SUM(pages),SUM(bounce),SUM(duration) FROM %i WHERE day = %s GROUP BY day, LEFT(CONCAT(src_type,':',src_name),190)", $d, $v, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, nightly totals.
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i (day,kind,k,visitors,visits,views,bounces,duration) SELECT day,'page',LEFT(path,190),0,COUNT(DISTINCT visit_id),COUNT(*),0,SUM(visible) FROM %i WHERE day = %s GROUP BY day, LEFT(path,190)", $d, $h, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, nightly totals.
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i (day,kind,k,visitors,visits,views,bounces,duration) SELECT day,'bot',LEFT(CONCAT(kind,':',bot),190),0,0,COUNT(*),0,0 FROM %i WHERE day = %s GROUP BY day, LEFT(CONCAT(kind,':',bot),190)", $d, $b, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, nightly totals.
	}

	public static function purge() {
		global $wpdb;
		$days = max( 30, (int) bobr_settings()['retention'] );
		$edge = wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		foreach ( array( 'hits', 'visits', 'events', 'bots' ) as $t ) {
			do {
				$n = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s LIMIT 5000', Bobr_DB::t( $t ), $edge ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own statistics tables, retention cleanup.
			} while ( $n >= 5000 );
		}
	}
}
