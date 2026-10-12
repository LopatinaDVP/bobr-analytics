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
		return (string) ( $n['ru'] ?? $n['en'] ?? reset( $n ) );
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
			'Europe/Moscow' => 'Пояс Москвы', 'Europe/Simferopol' => 'Пояс Москвы', 'Europe/Kirov' => 'Пояс Москвы', 'Europe/Volgograd' => 'Пояс Москвы',
			'Europe/Kaliningrad' => 'Калининград', 'Europe/Samara' => 'Пояс Самары', 'Europe/Ulyanovsk' => 'Пояс Самары', 'Europe/Saratov' => 'Пояс Самары', 'Europe/Astrakhan' => 'Пояс Самары',
			'Asia/Yekaterinburg' => 'Урал', 'Asia/Omsk' => 'Омск', 'Asia/Novosibirsk' => 'Новосибирск', 'Asia/Barnaul' => 'Новосибирск', 'Asia/Tomsk' => 'Новосибирск',
			'Asia/Novokuznetsk' => 'Красноярск', 'Asia/Krasnoyarsk' => 'Красноярск', 'Asia/Irkutsk' => 'Иркутск', 'Asia/Chita' => 'Якутск', 'Asia/Yakutsk' => 'Якутск',
			'Asia/Vladivostok' => 'Владивосток', 'Asia/Magadan' => 'Магадан', 'Asia/Sakhalin' => 'Магадан', 'Asia/Kamchatka' => 'Камчатка', 'Asia/Anadyr' => 'Камчатка',
		);
		if ( isset( $ru[ $tz ] ) ) {
			return array( 'Россия', $ru[ $tz ] . ' (по поясу)', '' );
		}
		$c = array(
			'Europe/Minsk' => 'Беларусь', 'Asia/Almaty' => 'Казахстан', 'Asia/Qostanay' => 'Казахстан', 'Asia/Aqtobe' => 'Казахстан', 'Asia/Tashkent' => 'Узбекистан',
			'Asia/Bishkek' => 'Киргизия', 'Asia/Yerevan' => 'Армения', 'Asia/Tbilisi' => 'Грузия', 'Asia/Baku' => 'Азербайджан', 'Europe/Kiev' => 'Украина', 'Europe/Kyiv' => 'Украина',
			'Europe/Chisinau' => 'Молдова', 'Asia/Dushanbe' => 'Таджикистан', 'Asia/Ashgabat' => 'Туркменистан', 'Europe/Istanbul' => 'Турция', 'Asia/Dubai' => 'ОАЭ',
			'Europe/Berlin' => 'Германия', 'Europe/London' => 'Великобритания', 'Europe/Riga' => 'Латвия', 'Europe/Vilnius' => 'Литва', 'Europe/Tallinn' => 'Эстония',
			'Asia/Bangkok' => 'Таиланд', 'Asia/Ho_Chi_Minh' => 'Вьетнам', 'Asia/Jerusalem' => 'Израиль', 'Europe/Belgrade' => 'Сербия', 'Asia/Makassar' => 'Индонезия',
		);
		if ( isset( $c[ $tz ] ) ) {
			return array( $c[ $tz ], '', '' );
		}
		return array( '' === $tz ? 'Не определено' : 'Другие (' . substr( $tz, 0, 30 ) . ')', '', '' );
	}

	public static function download() {
		$dir = dirname( self::db_file() );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'dir', 'Не удалось создать папку ' . $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
			file_put_contents( $dir . '/index.php', "<?php\n" );
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
		$fo  = fopen( $out, 'wb' );
		if ( ! $in || ! $fo ) {
			@unlink( $tmp );
			return new WP_Error( 'gz', 'Не удалось распаковать базу' );
		}
		while ( ! gzeof( $in ) ) {
			fwrite( $fo, gzread( $in, 1048576 ) );
		}
		gzclose( $in );
		fclose( $fo );
		@unlink( $tmp );
		try {
			$test = new Bobr_Mmdb( $out );
			unset( $test );
		} catch ( Exception $e ) {
			@unlink( $out );
			return new WP_Error( 'mmdb', 'Скачанный файл не читается: ' . $e->getMessage() );
		}
		rename( $out, self::db_file() );
		update_option( 'bobr_geo_updated', time(), false );
		return true;
	}
}
