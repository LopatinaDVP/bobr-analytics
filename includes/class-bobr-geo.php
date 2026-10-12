<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Geo {
	const DB_URL = 'https://download.db-ip.com/free/dbip-city-lite-%s.mmdb.gz';

	private static $reader = false;

	public static function db_file() {
		$up = wp_upload_dir( null, false );
		return trailingslashit( $up['basedir'] ) . 'bobr/dbip-city-lite.mmdb';
	}

	public static function has_db() {
		return file_exists( self::db_file() );
	}

	private static function reader() {
		if ( false === self::$reader ) {
			self::$reader = null;
			if ( self::has_db() ) {
				try {
					self::$reader = new Bobr_Mmdb( self::db_file() );
				} catch ( Exception $e ) {
					self::$reader = null;
				}
			}
		}
		return self::$reader;
	}

	private static function name( $node ) {
		if ( ! is_array( $node ) || empty( $node['names'] ) ) {
			return '';
		}
		$n = $node['names'];
		$lang = substr( get_locale(), 0, 2 );
		return (string) ( $n[ $lang ] ?? $n['en'] ?? reset( $n ) );
	}

	public static function locate( $ip, $tz ) {
		$r = self::reader();
		if ( $r && $ip ) {
			try {
				$rec = $r->get( $ip );
				if ( is_array( $rec ) ) {
					$country = self::name( $rec['country'] ?? null );
					$region  = self::name( $rec['subdivisions'][0] ?? null );
					$city    = self::name( $rec['city'] ?? null );
					if ( $country ) {
						return array( $country, $region, $city );
					}
				}
			} catch ( Exception $e ) {
			}
		}
		return self::by_tz( $tz );
	}

	public static function by_tz( $tz ) {
		$tz = (string) $tz;
		$ru = array(
			'Europe/Moscow' => __( 'Moscow area', 'bobr-analytics' ), 'Europe/Simferopol' => __( 'Moscow area', 'bobr-analytics' ), 'Europe/Kirov' => __( 'Moscow area', 'bobr-analytics' ), 'Europe/Volgograd' => __( 'Moscow area', 'bobr-analytics' ),
			'Europe/Kaliningrad' => __( 'Kaliningrad', 'bobr-analytics' ), 'Europe/Samara' => __( 'Samara area', 'bobr-analytics' ), 'Europe/Ulyanovsk' => __( 'Samara area', 'bobr-analytics' ), 'Europe/Saratov' => __( 'Samara area', 'bobr-analytics' ), 'Europe/Astrakhan' => __( 'Samara area', 'bobr-analytics' ),
			'Asia/Yekaterinburg' => __( 'Urals', 'bobr-analytics' ), 'Asia/Omsk' => __( 'Omsk', 'bobr-analytics' ), 'Asia/Novosibirsk' => __( 'Novosibirsk', 'bobr-analytics' ), 'Asia/Barnaul' => __( 'Novosibirsk', 'bobr-analytics' ), 'Asia/Tomsk' => __( 'Novosibirsk', 'bobr-analytics' ),
			'Asia/Novokuznetsk' => __( 'Krasnoyarsk', 'bobr-analytics' ), 'Asia/Krasnoyarsk' => __( 'Krasnoyarsk', 'bobr-analytics' ), 'Asia/Irkutsk' => __( 'Irkutsk', 'bobr-analytics' ), 'Asia/Chita' => __( 'Yakutsk', 'bobr-analytics' ), 'Asia/Yakutsk' => __( 'Yakutsk', 'bobr-analytics' ),
			'Asia/Vladivostok' => __( 'Vladivostok', 'bobr-analytics' ), 'Asia/Magadan' => __( 'Magadan', 'bobr-analytics' ), 'Asia/Sakhalin' => __( 'Magadan', 'bobr-analytics' ), 'Asia/Kamchatka' => __( 'Kamchatka', 'bobr-analytics' ), 'Asia/Anadyr' => __( 'Kamchatka', 'bobr-analytics' ),
		);
		if ( isset( $ru[ $tz ] ) ) {
			return array(
				__( 'Russia', 'bobr-analytics' ),
				/* translators: %s: region of Russia guessed from the browser time zone */
				sprintf( __( '%s (by time zone)', 'bobr-analytics' ), $ru[ $tz ] ),
				'',
			);
		}
		$c = array(
			'Europe/Minsk' => __( 'Belarus', 'bobr-analytics' ), 'Asia/Almaty' => __( 'Kazakhstan', 'bobr-analytics' ), 'Asia/Qostanay' => __( 'Kazakhstan', 'bobr-analytics' ), 'Asia/Aqtobe' => __( 'Kazakhstan', 'bobr-analytics' ), 'Asia/Tashkent' => __( 'Uzbekistan', 'bobr-analytics' ),
			'Asia/Bishkek' => __( 'Kyrgyzstan', 'bobr-analytics' ), 'Asia/Yerevan' => __( 'Armenia', 'bobr-analytics' ), 'Asia/Tbilisi' => __( 'Georgia', 'bobr-analytics' ), 'Asia/Baku' => __( 'Azerbaijan', 'bobr-analytics' ), 'Europe/Kiev' => __( 'Ukraine', 'bobr-analytics' ), 'Europe/Kyiv' => __( 'Ukraine', 'bobr-analytics' ),
			'Europe/Chisinau' => __( 'Moldova', 'bobr-analytics' ), 'Asia/Dushanbe' => __( 'Tajikistan', 'bobr-analytics' ), 'Asia/Ashgabat' => __( 'Turkmenistan', 'bobr-analytics' ), 'Europe/Istanbul' => __( 'Turkey', 'bobr-analytics' ), 'Asia/Dubai' => __( 'UAE', 'bobr-analytics' ),
			'Europe/Berlin' => __( 'Germany', 'bobr-analytics' ), 'Europe/London' => __( 'United Kingdom', 'bobr-analytics' ), 'Europe/Riga' => __( 'Latvia', 'bobr-analytics' ), 'Europe/Vilnius' => __( 'Lithuania', 'bobr-analytics' ), 'Europe/Tallinn' => __( 'Estonia', 'bobr-analytics' ),
			'Asia/Bangkok' => __( 'Thailand', 'bobr-analytics' ), 'Asia/Ho_Chi_Minh' => __( 'Vietnam', 'bobr-analytics' ), 'Asia/Jerusalem' => __( 'Israel', 'bobr-analytics' ), 'Europe/Belgrade' => __( 'Serbia', 'bobr-analytics' ), 'Asia/Makassar' => __( 'Indonesia', 'bobr-analytics' ),
		);
		if ( isset( $c[ $tz ] ) ) {
			return array( $c[ $tz ], '', '' );
		}
		if ( '' === $tz ) {
			return array( __( 'Not detected', 'bobr-analytics' ), '', '' );
		}
		/* translators: %s: browser time zone, for example America/New_York */
		return array( sprintf( __( 'Other (%s)', 'bobr-analytics' ), substr( $tz, 0, 30 ) ), '', '' );
	}

	public static function download() {
		$dir = dirname( self::db_file() );
		if ( ! wp_mkdir_p( $dir ) ) {
			/* translators: %s: folder path on the server */
			return new WP_Error( 'dir', sprintf( __( 'Could not create the folder %s', 'bobr-analytics' ), $dir ) );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny file in our own uploads folder, WP_Filesystem may ask for FTP credentials.
			file_put_contents( $dir . '/index.php', "<?php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny file in our own uploads folder, WP_Filesystem may ask for FTP credentials.
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$url = sprintf( self::DB_URL, wp_date( 'Y-m' ) );
		$tmp = download_url( $url, 600 );
		if ( is_wp_error( $tmp ) ) {
			$url = sprintf( self::DB_URL, wp_date( 'Y-m', strtotime( '-1 month' ) ) );
			$tmp = download_url( $url, 600 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
		}
		$out = self::db_file() . '.new';
		$in  = gzopen( $tmp, 'rb' );
		$fo  = fopen( $out, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming unpack of a 130 MB file, WP_Filesystem has no streaming write.
		if ( ! $in || ! $fo ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'gz', __( 'Could not unpack the database', 'bobr-analytics' ) );
		}
		while ( ! gzeof( $in ) ) {
			fwrite( $fo, gzread( $in, 1048576 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming unpack of a 130 MB file, WP_Filesystem has no streaming write.
		}
		gzclose( $in );
		fclose( $fo ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the stream opened above.
		wp_delete_file( $tmp );
		try {
			$test = new Bobr_Mmdb( $out );
			unset( $test );
		} catch ( Exception $e ) {
			wp_delete_file( $out );
			/* translators: %s: technical error message */
			return new WP_Error( 'mmdb', sprintf( __( 'The downloaded file cannot be read: %s', 'bobr-analytics' ), $e->getMessage() ) );
		}
		rename( $out, self::db_file() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic swap of the database file inside our own uploads folder.
		update_option( 'bobr_geo_updated', time(), false );
		return true;
	}
}
