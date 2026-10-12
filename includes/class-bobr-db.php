<?php

defined( 'ABSPATH' ) || exit;

class Bobr_DB {
	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'bobr_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		dbDelta( 'CREATE TABLE ' . self::t( 'visits' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			vhash CHAR(16) NOT NULL,
			day DATE NOT NULL,
			started DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			entry VARCHAR(250) NOT NULL DEFAULT '',
			exit_path VARCHAR(250) NOT NULL DEFAULT '',
			pages SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			duration INT UNSIGNED NOT NULL DEFAULT 0,
			bounce TINYINT(1) NOT NULL DEFAULT 1,
			src_type VARCHAR(12) NOT NULL DEFAULT '',
			src_name VARCHAR(64) NOT NULL DEFAULT '',
			ref_host VARCHAR(120) NOT NULL DEFAULT '',
			ref_path VARCHAR(200) NOT NULL DEFAULT '',
			utm_source VARCHAR(100) NOT NULL DEFAULT '',
			utm_medium VARCHAR(100) NOT NULL DEFAULT '',
			utm_campaign VARCHAR(100) NOT NULL DEFAULT '',
			utm_term VARCHAR(100) NOT NULL DEFAULT '',
			utm_content VARCHAR(100) NOT NULL DEFAULT '',
			device VARCHAR(10) NOT NULL DEFAULT '',
			os VARCHAR(24) NOT NULL DEFAULT '',
			browser VARCHAR(24) NOT NULL DEFAULT '',
			screen VARCHAR(16) NOT NULL DEFAULT '',
			lang VARCHAR(12) NOT NULL DEFAULT '',
			tz VARCHAR(40) NOT NULL DEFAULT '',
			country VARCHAR(60) NOT NULL DEFAULT '',
			region VARCHAR(80) NOT NULL DEFAULT '',
			city VARCHAR(80) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY vhash_last (vhash,last_seen),
			KEY day (day),
			KEY last_seen (last_seen)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'hits' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visit_id BIGINT UNSIGNED NOT NULL,
			pid CHAR(12) NOT NULL DEFAULT '',
			ts DATETIME NOT NULL,
			day DATE NOT NULL,
			path VARCHAR(250) NOT NULL DEFAULT '',
			title VARCHAR(200) NOT NULL DEFAULT '',
			visible INT UNSIGNED NOT NULL DEFAULT 0,
			scroll TINYINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY visit_id (visit_id),
			KEY day (day),
			KEY pid (pid)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'events' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visit_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ts DATETIME NOT NULL,
			day DATE NOT NULL,
			path VARCHAR(250) NOT NULL DEFAULT '',
			type VARCHAR(12) NOT NULL DEFAULT '',
			name VARCHAR(250) NOT NULL DEFAULT '',
			val DOUBLE NULL,
			PRIMARY KEY  (id),
			KEY day_type (day,type)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'bots' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ts DATETIME NOT NULL,
			day DATE NOT NULL,
			bot VARCHAR(40) NOT NULL DEFAULT '',
			kind VARCHAR(10) NOT NULL DEFAULT '',
			path VARCHAR(250) NOT NULL DEFAULT '',
			status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY day (day),
			KEY bot (bot)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'daily' ) . " (
			day DATE NOT NULL,
			kind VARCHAR(10) NOT NULL,
			k VARCHAR(190) NOT NULL DEFAULT '',
			visitors INT UNSIGNED NOT NULL DEFAULT 0,
			visits INT UNSIGNED NOT NULL DEFAULT 0,
			views INT UNSIGNED NOT NULL DEFAULT 0,
			bounces INT UNSIGNED NOT NULL DEFAULT 0,
			duration BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,kind,k)
		) $c;" );

		update_option( 'bobr_db_ver', BOBR_DB_VER, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'bobr_db_ver' ) !== BOBR_DB_VER ) {
			self::install();
		}
	}
}
