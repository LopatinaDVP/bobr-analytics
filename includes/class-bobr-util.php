<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Util {
	public static function bots() {
		return array(
			'ChatGPT-User'        => array( 'ChatGPT-User', 'ai' ),
			'OAI-SearchBot'       => array( 'OAI-SearchBot', 'ai' ),
			'GPTBot'              => array( 'GPTBot', 'ai' ),
			'Claude-User'         => array( 'Claude-User', 'ai' ),
			'Claude-SearchBot'    => array( 'Claude-SearchBot', 'ai' ),
			'ClaudeBot'           => array( 'ClaudeBot', 'ai' ),
			'anthropic-ai'        => array( 'anthropic-ai', 'ai' ),
			'Perplexity-User'     => array( 'Perplexity-User', 'ai' ),
			'PerplexityBot'       => array( 'PerplexityBot', 'ai' ),
			'GoogleOther'         => array( 'GoogleOther', 'ai' ),
			'Google-Extended'     => array( 'Google-Extended', 'ai' ),
			'Google-CloudVertexBot' => array( 'Google-CloudVertexBot', 'ai' ),
			'Bytespider'          => array( 'Bytespider', 'ai' ),
			'CCBot'               => array( 'CCBot', 'ai' ),
			'Amazonbot'           => array( 'Amazonbot', 'ai' ),
			'meta-externalagent'  => array( 'meta-externalagent', 'ai' ),
			'meta-externalfetcher' => array( 'meta-externalfetcher', 'ai' ),
			'Applebot-Extended'   => array( 'Applebot-Extended', 'ai' ),
			'DeepSeekBot'         => array( 'DeepSeekBot', 'ai' ),
			'cohere-ai'           => array( 'cohere-ai', 'ai' ),
			'Diffbot'             => array( 'Diffbot', 'ai' ),
			'YouBot'              => array( 'YouBot', 'ai' ),
			'MistralAI-User'      => array( 'MistralAI-User', 'ai' ),
			'TimpiBot'            => array( 'TimpiBot', 'ai' ),
			'YandexAdditional'    => array( __( 'YandexAdditional (Alice)', 'bobr-analytics' ), 'ai' ),
			'GigaBot'             => array( 'GigaBot', 'ai' ),
			'YandexBot'           => array( 'YandexBot', 'search' ),
			'YandexMobileBot'     => array( 'YandexMobileBot', 'search' ),
			'YandexImages'        => array( 'YandexImages', 'search' ),
			'YandexRenderResourcesBot' => array( 'YandexRenderResources', 'search' ),
			'YandexMetrika'       => array( 'YandexMetrika', 'search' ),
			'YandexWebmaster'     => array( 'YandexWebmaster', 'search' ),
			'YandexFavicons'      => array( 'YandexFavicons', 'search' ),
			'Yandex'              => array( __( 'Yandex (other)', 'bobr-analytics' ), 'search' ),
			'Googlebot-Image'     => array( 'Googlebot-Image', 'search' ),
			'Googlebot'           => array( 'Googlebot', 'search' ),
			'Google-InspectionTool' => array( 'Google-InspectionTool', 'search' ),
			'AdsBot-Google'       => array( 'AdsBot-Google', 'search' ),
			'Storebot-Google'     => array( 'Storebot-Google', 'search' ),
			'bingbot'             => array( 'bingbot', 'search' ),
			'DuckDuckBot'         => array( 'DuckDuckBot', 'search' ),
			'Mail.RU_Bot'         => array( 'Mail.RU_Bot', 'search' ),
			'Applebot'            => array( 'Applebot', 'search' ),
			'Baiduspider'         => array( 'Baiduspider', 'search' ),
			'SeznamBot'           => array( 'SeznamBot', 'search' ),
			'PetalBot'            => array( 'PetalBot', 'search' ),
			'AhrefsBot'           => array( 'AhrefsBot', 'seo' ),
			'AhrefsSiteAudit'     => array( 'AhrefsSiteAudit', 'seo' ),
			'SemrushBot'          => array( 'SemrushBot', 'seo' ),
			'MJ12bot'             => array( 'MJ12bot', 'seo' ),
			'DotBot'              => array( 'DotBot', 'seo' ),
			'BLEXBot'             => array( 'BLEXBot', 'seo' ),
			'serpstatbot'         => array( 'serpstatbot', 'seo' ),
			'DataForSeoBot'       => array( 'DataForSeoBot', 'seo' ),
			'Screaming Frog'      => array( 'Screaming Frog', 'seo' ),
			'SiteAuditBot'        => array( 'SiteAuditBot', 'seo' ),
			'linkdexbot'          => array( 'linkdexbot', 'seo' ),
			'TelegramBot'         => array( 'TelegramBot', 'other' ),
			'vkShare'             => array( 'vkShare', 'other' ),
			'facebookexternalhit' => array( 'facebookexternalhit', 'other' ),
			'WhatsApp'            => array( 'WhatsApp', 'other' ),
			'Twitterbot'          => array( 'Twitterbot', 'other' ),
			'Slackbot'            => array( 'Slackbot', 'other' ),
			'UptimeRobot'         => array( 'UptimeRobot', 'other' ),
		);
	}

	public static function labels() {
		return array(
			'search'   => __( 'Search engines', 'bobr-analytics' ),
			'ai'       => __( 'AI assistants', 'bobr-analytics' ),
			'seo'      => __( 'SEO tools', 'bobr-analytics' ),
			'other'    => __( 'Other', 'bobr-analytics' ),
			'unknown'  => __( 'Unknown', 'bobr-analytics' ),
			'social'   => __( 'Social networks', 'bobr-analytics' ),
			'ref'      => __( 'Websites', 'bobr-analytics' ),
			'direct'   => __( 'Direct', 'bobr-analytics' ),
			'internal' => __( 'Internal', 'bobr-analytics' ),
			'utm'      => __( 'UTM campaigns', 'bobr-analytics' ),
			'desktop'  => __( 'Desktop', 'bobr-analytics' ),
			'mobile'   => __( 'Mobile', 'bobr-analytics' ),
			'tablet'   => __( 'Tablet', 'bobr-analytics' ),
		);
	}

	public static function label( $k ) {
		$l = self::labels();
		return isset( $l[ $k ] ) ? $l[ $k ] : $k;
	}

	public static function bot( $ua ) {
		$ua = (string) $ua;
		if ( '' === trim( $ua ) ) {
			return array( __( 'Empty user agent', 'bobr-analytics' ), 'unknown' );
		}
		foreach ( self::bots() as $needle => $info ) {
			if ( false !== stripos( $ua, $needle ) ) {
				return $info;
			}
		}
		if ( preg_match( '~(bot|crawl|spider|slurp|fetch|scan|monitor|curl/|wget/|python-|python/|go-http-client|java/|okhttp|axios/|node-fetch|headless|phantomjs|libwww|httpclient|guzzle|scrapy|httrack)~i', $ua, $m ) ) {
			$name = __( 'Unknown bot', 'bobr-analytics' );
			if ( preg_match( '~([A-Za-z][\w\-\.]*(?:bot|crawler|spider)[\w\-]*)~i', $ua, $n ) ) {
				$name = $n[1];
			} elseif ( preg_match( '~(curl|wget|python-requests|python-urllib|go-http-client|okhttp|axios|node-fetch|java|HeadlessChrome|Guzzle|Scrapy)~i', $ua, $n ) ) {
				$name = $n[1];
			}
			return array( substr( $name, 0, 40 ), 'unknown' );
		}
		return null;
	}

	public static function ua( $ua ) {
		$ua = (string) $ua;
		$device = 'desktop';
		if ( preg_match( '~iPad|Tablet|Tab\b|SM-T\d|Kindle|Silk|PlayBook~i', $ua ) || ( false !== stripos( $ua, 'Android' ) && false === stripos( $ua, 'Mobile' ) ) ) {
			$device = 'tablet';
		} elseif ( preg_match( '~Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|Opera Mini~i', $ua ) ) {
			$device = 'mobile';
		}

		$os = __( 'Other OS', 'bobr-analytics' );
		$map = array(
			'~Windows NT 10~'      => 'Windows 10/11',
			'~Windows NT 6\.[23]~' => 'Windows 8',
			'~Windows NT 6\.1~'    => 'Windows 7',
			'~Windows~'            => 'Windows',
			'~iPhone|iPad|iPod~'   => 'iOS',
			'~HarmonyOS~'          => 'HarmonyOS',
			'~Android~'            => 'Android',
			'~Mac OS X|Macintosh~' => 'macOS',
			'~CrOS~'               => 'ChromeOS',
			'~Linux~'              => 'Linux',
		);
		foreach ( $map as $re => $name ) {
			if ( preg_match( $re, $ua ) ) {
				$os = $name;
				break;
			}
		}

		$browser = __( 'Other browser', 'bobr-analytics' );
		$bmap = array(
			'~YaBrowser|YaSearchBrowser~' => __( 'Yandex Browser', 'bobr-analytics' ),
			'~Edg/|Edge/~'                => 'Edge',
			'~OPR/|Opera~'                => 'Opera',
			'~SamsungBrowser~'            => 'Samsung',
			'~MiuiBrowser|XiaoMi~'        => 'Mi Browser',
			'~Atom/~'                     => 'Atom',
			'~Firefox|FxiOS~'             => 'Firefox',
			'~CriOS|Chrome/~'             => 'Chrome',
			'~Safari/~'                   => 'Safari',
		);
		foreach ( $bmap as $re => $name ) {
			if ( preg_match( $re, $ua ) ) {
				$browser = $name;
				break;
			}
		}
		return array( $device, $os, $browser );
	}

	public static function screen( $w ) {
		$w = (int) $w;
		if ( $w <= 0 ) {
			return '';
		}
		foreach ( array( 360, 400, 480, 768, 1024, 1280, 1366, 1440, 1600, 1920, 2560 ) as $b ) {
			if ( $w <= $b ) {
				/* translators: %d: screen width in pixels */
				return sprintf( __( 'up to %d', 'bobr-analytics' ), $b );
			}
		}
		/* translators: %d: screen width in pixels */
		return sprintf( __( 'over %d', 'bobr-analytics' ), 2560 );
	}

	public static function source( $ref_host, $ref_path, $utm ) {
		$own = self::own_host();
		$h   = strtolower( preg_replace( '~^www\.~', '', (string) $ref_host ) );
		$src = strtolower( (string) ( $utm['utm_source'] ?? '' ) );

		$ai = array(
			'chatgpt.com' => 'ChatGPT', 'chat.openai.com' => 'ChatGPT', 'openai.com' => 'ChatGPT',
			'perplexity.ai' => 'Perplexity', 'chat.deepseek.com' => 'DeepSeek', 'deepseek.com' => 'DeepSeek',
			'gemini.google.com' => 'Gemini', 'bard.google.com' => 'Gemini', 'copilot.microsoft.com' => 'Copilot',
			'alice.yandex.ru' => __( 'Alice', 'bobr-analytics' ), 'ya.ru/alice' => __( 'Alice', 'bobr-analytics' ), 'giga.chat' => 'GigaChat', 'claude.ai' => 'Claude',
			'you.com' => 'You.com', 'poe.com' => 'Poe', 'phind.com' => 'Phind', 'grok.com' => 'Grok', 'chat.mistral.ai' => 'Mistral',
		);
		$ai_utm = array( 'chatgpt' => 'ChatGPT', 'openai' => 'ChatGPT', 'perplexity' => 'Perplexity', 'deepseek' => 'DeepSeek', 'gemini' => 'Gemini', 'copilot' => 'Copilot', 'gigachat' => 'GigaChat', 'giga.chat' => 'GigaChat', 'claude' => 'Claude', 'grok' => 'Grok', 'phind' => 'Phind', 'alice' => __( 'Alice', 'bobr-analytics' ) );
		foreach ( $ai_utm as $needle => $name ) {
			if ( '' !== $src && false !== strpos( $src, $needle ) ) {
				return array( 'ai', $name );
			}
		}
		foreach ( $ai as $dom => $name ) {
			$d = strtok( $dom, '/' );
			if ( '' !== $h && ( $h === $d || self::ends( $h, '.' . $d ) ) ) {
				if ( 'ya.ru' === $d && false === strpos( (string) $ref_path, 'alice' ) ) {
					continue;
				}
				return array( 'ai', $name );
			}
		}
		if ( '' !== $src || ! empty( $utm['utm_campaign'] ) ) {
			return array( 'utm', '' !== $src ? substr( $src, 0, 60 ) : __( 'no utm_source', 'bobr-analytics' ) );
		}
		if ( '' === $h ) {
			return array( 'direct', __( 'Direct', 'bobr-analytics' ) );
		}
		if ( $h === $own || self::ends( $h, '.' . $own ) ) {
			return array( 'internal', __( 'Internal navigation', 'bobr-analytics' ) );
		}
		$search = array(
			'yandex' => __( 'Yandex', 'bobr-analytics' ), 'ya.ru' => __( 'Yandex', 'bobr-analytics' ), 'google' => 'Google', 'bing.com' => 'Bing', 'go.mail.ru' => 'Mail.ru',
			'search.mail.ru' => 'Mail.ru', 'rambler.ru' => __( 'Rambler', 'bobr-analytics' ), 'duckduckgo.com' => 'DuckDuckGo', 'yahoo.com' => 'Yahoo',
			'ecosia.org' => 'Ecosia', 'search.brave.com' => 'Brave',
		);
		foreach ( $search as $dom => $name ) {
			if ( false !== strpos( $h, $dom ) ) {
				if ( preg_match( '~^(mail\.|e\.mail|dzen\.|zen\.|market\.|music\.|disk\.|passport\.|translate\.)~', $h ) ) {
					break;
				}
				return array( 'search', $name );
			}
		}
		$social = array(
			'vk.com' => __( 'VK', 'bobr-analytics' ), 'vk.ru' => __( 'VK', 'bobr-analytics' ), 'away.vk.com' => __( 'VK', 'bobr-analytics' ), 'ok.ru' => __( 'Odnoklassniki', 'bobr-analytics' ),
			't.me' => __( 'Telegram', 'bobr-analytics' ), 'telegram.org' => __( 'Telegram', 'bobr-analytics' ), 'web.telegram.org' => __( 'Telegram', 'bobr-analytics' ), 'dzen.ru' => __( 'Dzen', 'bobr-analytics' ),
			'zen.yandex.ru' => __( 'Dzen', 'bobr-analytics' ), 'youtube.com' => __( 'YouTube', 'bobr-analytics' ), 'youtu.be' => __( 'YouTube', 'bobr-analytics' ), 'pinterest' => __( 'Pinterest', 'bobr-analytics' ),
			'instagram.com' => 'Instagram', 'facebook.com' => 'Facebook', 'tiktok.com' => 'TikTok', 'rutube.ru' => 'Rutube',
			'twitter.com' => 'X', 'x.com' => 'X', 'max.ru' => 'MAX', 'whatsapp' => 'WhatsApp', 'linkedin.com' => 'LinkedIn',
		);
		foreach ( $social as $dom => $name ) {
			if ( $h === $dom || self::ends( $h, '.' . $dom ) || ( false === strpos( $dom, '.' ) && false !== strpos( $h, $dom ) ) ) {
				return array( 'social', $name );
			}
		}
		return array( 'ref', substr( $h, 0, 60 ) );
	}

	public static function ends( $s, $end ) {
		return '' !== $end && substr( $s, -strlen( $end ) ) === $end;
	}

	public static function own_host() {
		return strtolower( preg_replace( '~^www\.~', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	}

	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$priv = ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		if ( $priv ) {
			foreach ( array( 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP' ) as $k ) {
				if ( ! empty( $_SERVER[ $k ] ) ) {
					$cand = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) ) )[0] );
					if ( filter_var( $cand, FILTER_VALIDATE_IP ) ) {
						return $cand;
					}
				}
			}
		}
		return $ip;
	}

	public static function salt() {
		$day = wp_date( 'Y-m-d' );
		$s   = get_option( 'bobr_salt' );
		if ( ! is_array( $s ) || ( $s['day'] ?? '' ) !== $day || empty( $s['salt'] ) ) {
			$s = array( 'day' => $day, 'salt' => wp_generate_password( 40, true, true ) );
			update_option( 'bobr_salt', $s, true );
		}
		return $s['salt'];
	}

	public static function visitor_hash( $ip, $ua ) {
		return substr( hash( 'sha256', $ip . '|' . $ua . '|' . self::own_host() . '|' . self::salt() ), 0, 16 );
	}

	public static function ip_hash( $ip ) {
		return hash_hmac( 'sha256', trim( (string) $ip ), wp_salt( 'auth' ) );
	}

	public static function clean_path( $p ) {
		$p = (string) $p;
		$path = (string) wp_parse_url( $p, PHP_URL_PATH );
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . ltrim( $path, '/' );
		}
		$path = rawurldecode( $path );
		$path = preg_replace( '~[\x00-\x1F\x7F]~u', '', $path );
		return mb_substr( $path, 0, 250 );
	}

	public static function str( $s, $len ) {
		$s = preg_replace( '~[\x00-\x1F\x7F]~u', '', wp_strip_all_tags( (string) $s ) );
		return mb_substr( trim( $s ), 0, $len );
	}
}
