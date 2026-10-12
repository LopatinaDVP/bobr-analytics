<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Admin {
	const CAP = 'manage_options';

	private static $r;

	public static function sections() {
		return array(
			'summary'  => 'Сводка',
			'entry'    => 'Страницы входа',
			'pages'    => 'Все страницы и выходы',
			'sources'  => 'Источники',
			'ai'       => 'Нейросети',
			'bots'     => 'Роботы',
			'geo'      => 'География',
			'tech'     => 'Технологии',
			'time'     => 'Время',
			'behavior' => 'Поведение',
			'events'   => 'События',
			'nf'       => 'Ошибки 404',
			'speed'    => 'Скорость',
			'now'      => 'Сейчас на сайте',
			'settings' => 'Настройки',
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
		add_menu_page( 'Бобр аналитика', 'Бобр аналитика', self::CAP, 'bobr', array( __CLASS__, 'page' ), 'dashicons-chart-area', 3 );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=bobr&sec=settings' ) ) . '">Настройки</a>' );
		return $links;
	}

	public static function row_meta( $links, $file ) {
		if ( plugin_basename( BOBR_FILE ) === $file ) {
			$links[] = '<a href="' . esc_url( BOBR_GITHUB . '/issues' ) . '" target="_blank" rel="noopener">Обратная связь</a>';
		}
		return $links;
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_bobr' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'bobr-admin', BOBR_URL . 'assets/admin.css', array(), BOBR_VER );
		wp_enqueue_script( 'bobr-admin', BOBR_URL . 'assets/admin.js', array(), BOBR_VER, true );
	}

	private static function url( $args = array() ) {
		$keep = array();
		foreach ( array( 'sec', 'period', 'from', 'to', 'cmp', 'f_src', 'f_dev', 'f_city', 'f_page', 'who', 'unit' ) as $k ) {
			if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) {
				$keep[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) );
			}
		}
		$q = array_merge( array( 'page' => 'bobr' ), $keep, $args );
		$q = array_filter( $q, function ( $v ) { return null !== $v && '' !== $v; } );
		return add_query_arg( array_map( 'rawurlencode', $q ), admin_url( 'admin.php' ) );
	}

	private static function g( $k, $def = '' ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $def;
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Нет прав.' );
		}
		$sec = self::g( 'sec', 'summary' );
		if ( ! isset( self::sections()[ $sec ] ) ) {
			$sec = 'summary';
		}
		self::$r = new Bobr_Report( $_GET );
		Bobr_Charts::$unit = self::$r->unit;
		echo '<div class="wrap bobr"><div class="bobr-top"><h1><span class="bobr-logo">🦫</span> Бобр аналитика</h1>';
		echo '<span class="bobr-note">Своя статистика сайта: без куки, данные хранятся только в базе этого сайта.</span></div>';
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
		$t = array( 'saved' => 'Сохранено.', 'geo' => 'База городов скачана и подключена.', 'rebuilt' => 'Суммы по дням пересчитаны.' );
		if ( isset( $t[ $m ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $t[ $m ] ) . '</p></div>';
		}
		if ( 'geoerr' === $m ) {
			echo '<div class="notice notice-error"><p>База городов не скачалась: ' . esc_html( (string) get_transient( 'bobr_geo_err' ) ) . '</p></div>';
		}
	}

	private static function toolbar( $sec ) {
		$r = self::$r;
		$p = self::g( 'period', '7d' );
		echo '<form class="bobr-bar" method="get"><input type="hidden" name="page" value="bobr"><input type="hidden" name="sec" value="' . esc_attr( $sec ) . '">';
		echo '<div class="bobr-periods">';
		foreach ( array( 'today' => 'Сегодня', 'yesterday' => 'Вчера', '7d' => '7 дней', '30d' => '30 дней', 'month' => 'Месяц' ) as $k => $l ) {
			echo '<a class="' . ( $p === $k ? 'is-on' : '' ) . '" href="' . esc_url( self::url( array( 'period' => $k, 'from' => null, 'to' => null ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '<span class="bobr-custom"><input type="date" name="from" value="' . esc_attr( $r->from ) . '"> - <input type="date" name="to" value="' . esc_attr( $r->to ) . '"><input type="hidden" name="period" value="custom"></span>';
		echo '<label class="bobr-cmp"><input type="checkbox" name="cmp" value="1" ' . checked( self::g( 'cmp' ), '1', false ) . '> сравнить с прошлым периодом</label>';
		echo '<span class="bobr-units">Графики: ';
		foreach ( array( 'day' => 'по дням', 'week' => 'по неделям', 'month' => 'по месяцам' ) as $k => $l ) {
			echo '<a class="' . ( $r->unit === $k ? 'is-on' : '' ) . '" href="' . esc_url( self::url( array( 'unit' => 'day' === $k ? null : $k ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</span>' . ( 'day' !== $r->unit ? '<input type="hidden" name="unit" value="' . esc_attr( $r->unit ) . '">' : '' ) . '</div>';

		$src = array( '' => 'Все источники', 'search' => 'Поисковики', 'ai' => 'Нейросети', 'social' => 'Соцсети', 'ref' => 'Сайты', 'utm' => 'Рекламные метки', 'direct' => 'Прямые заходы', 'internal' => 'Внутренние' );
		$dev = array( '' => 'Все устройства', 'desktop' => 'Компьютер', 'mobile' => 'Телефон', 'tablet' => 'Планшет' );
		echo '<div class="bobr-filters">' . self::select( 'f_src', $src, $r->f['src'] ) . self::select( 'f_dev', $dev, $r->f['dev'] );
		echo '<input type="search" name="f_city" placeholder="Город или регион" value="' . esc_attr( $r->f['city'] ) . '">';
		echo '<input type="search" name="f_page" placeholder="Страница содержит" value="' . esc_attr( $r->f['page'] ) . '">';
		if ( 'summary' === $sec ) {
			echo self::select( 'who', array( '' => 'Люди', 'bots' => 'Роботы' ), self::g( 'who' ) );
		}
		echo '<button class="button button-primary">Показать</button>';
		if ( $r->has_filters() ) {
			echo ' <a class="button-link" href="' . esc_url( self::url( array( 'f_src' => null, 'f_dev' => null, 'f_city' => null, 'f_page' => null ) ) ) . '">Сбросить фильтры</a>';
		}
		echo '</div><div class="bobr-range">' . esc_html( Bobr_Charts::day_label( $r->from, true ) . ' - ' . Bobr_Charts::day_label( $r->to, true ) ) . ( self::g( 'cmp' ) ? ' · сравнение с ' . esc_html( Bobr_Charts::day_label( $r->pfrom ) . ' - ' . Bobr_Charts::day_label( $r->pto ) ) : '' ) . '</div></form>';
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
			return $s . ' с';
		}
		return floor( $s / 60 ) . ' мин ' . str_pad( (string) ( $s % 60 ), 2, '0', STR_PAD_LEFT ) . ' с';
	}

	private static function delta( $cur, $prev, $less_better = false ) {
		if ( ! self::g( 'cmp' ) ) {
			return '';
		}
		if ( ! $prev ) {
			return $cur ? '<span class="d up">новое</span>' : '';
		}
		$p    = ( $cur - $prev ) / $prev * 100;
		$good = $less_better ? $p < 0 : $p > 0;
		return '<span class="d ' . ( abs( $p ) < 0.5 ? '' : ( $good ? 'up' : 'down' ) ) . '">' . ( $p > 0 ? '+' : '' ) . number_format_i18n( $p, 1 ) . '%</span>';
	}

	private static function card( $label, $val, $delta = '', $hint = '' ) {
		echo '<div class="bobr-card"><div class="l">' . esc_html( $label ) . ( $hint ? ' <i title="' . esc_attr( $hint ) . '">?</i>' : '' ) . '</div><div class="v">' . esc_html( $val ) . '</div>' . $delta . '</div>';
	}

	private static function table( $title, $cols, $rows, $file = 'bobr' ) {
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>' . esc_html( $title ) . '</h2>';
		echo '<div class="bobr-tools"><input type="search" class="bobr-q" placeholder="Поиск"><button type="button" class="button bobr-csv" data-file="' . esc_attr( $file ) . '">CSV</button></div></div>';
		if ( ! $rows ) {
			echo '<p class="bobr-empty">Пока нет данных за этот период.</p></section>';
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
						echo '<td class="n" data-v="' . esc_attr( (float) $v ) . '">' . $txt . '</td>';
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
						echo '<td class="n ' . esc_attr( $r[ $k . '_c' ] ?? '' ) . '" data-v="' . esc_attr( null === $v ? -1 : (float) $v ) . '">' . esc_html( null === $v ? '-' : ( 'cls' === $k ? number_format_i18n( (float) $v, 3 ) : number_format_i18n( (float) $v ) . ' мс' ) ) . '</td>';
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
			echo '<p class="bobr-empty">Пока нет данных за этот период.</p></section>';
			return;
		}
		echo ( 'stack' === $kind ? Bobr_Charts::stack( $labels, $series ) : Bobr_Charts::line( $labels, $series, 'half' === $kind ? 600 : 1000 ) ) . '</section>';
	}

	private static function named( $split, $empty = 'Не определено' ) {
		$out = array();
		foreach ( $split as $k => $vals ) {
			$out[] = array( '' === (string) $k ? $empty : Bobr_Util::label( (string) $k ), $vals );
		}
		return $out;
	}

	private static function per() {
		$t = array( 'day' => 'по дням', 'week' => 'по неделям', 'month' => 'по месяцам' );
		return $t[ self::$r->unit ];
	}

	private static function prev_series( $name, $vals, $n ) {
		return self::g( 'cmp' ) ? array( array( $name . ' (прошлый период)', array_slice( array_values( $vals ), 0, $n ), true ) ) : array();
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
		self::card( 'Посетители', number_format_i18n( $t['visitors'] ), self::delta( $t['visitors'], $p['visitors'] ), 'Без куки посетитель узнаётся только в пределах суток: за несколько дней это сумма посетителей по дням.' );
		self::card( 'Визиты', number_format_i18n( $t['visits'] ), self::delta( $t['visits'], $p['visits'] ) );
		self::card( 'Просмотры', number_format_i18n( $t['views'] ), self::delta( $t['views'], $p['views'] ) );
		self::card( 'Отказы', number_format_i18n( $t['bounce_rate'], 1 ) . '%', self::delta( $t['bounce_rate'], $p['bounce_rate'], true ), 'Одна страница и меньше 15 секунд.' );
		self::card( 'Глубина', number_format_i18n( $t['depth'], 2 ), self::delta( $t['depth'], $p['depth'] ), 'Страниц за визит.' );
		self::card( 'Время на сайте', self::dur( $t['avg_time'] ), self::delta( $t['avg_time'], $p['avg_time'] ) );
		echo '</div>';
		$rs     = $r->roll( $s );
		$labels = array_keys( $rs );
		$series = array(
			array( 'Визиты', array_column( $rs, 'visits' ) ),
			array( 'Посетители', array_column( $rs, 'visitors' ) ),
			array( 'Просмотры', array_column( $rs, 'views' ) ),
		);
		if ( $ps ) {
			$series = array_merge( $series, self::prev_series( 'Визиты', array_column( $r->roll( $ps ), 'visits' ), count( $labels ) ) );
		}
		self::chart( 'Посещаемость ' . self::per(), $labels, $series );
		self::chart( 'Визиты по типам источников ' . self::per(), $labels, self::named( $r->split( 'src_type', '', 8 ) ) );

		$bounce = array();
		$time   = array();
		foreach ( $rs as $x ) {
			$bounce[] = $x['visits'] ? round( $x['bounces'] / $x['visits'] * 100, 1 ) : null;
			$time[]   = $x['visits'] ? round( $x['duration'] / $x['visits'] ) : null;
		}
		echo '<div class="bobr-2">';
		self::chart( 'Отказы, %', $labels, array( array( 'Отказы, %', $bounce ) ), 'half' );
		self::chart( 'Время на сайте, секунд', $labels, array( array( 'Время на сайте, с', $time ) ), 'half' );
		echo '</div>';

		echo '<div class="bobr-2">';
		$rows = array();
		foreach ( $r->group( "CONCAT(src_type,':',src_name)", '', 8 ) as $x ) {
			list( $ty, $nm ) = array_pad( explode( ':', $x->k, 2 ), 2, '' );
			$rows[] = array( 'src' => Bobr_Util::label( $ty ) . ( $nm && $nm !== Bobr_Util::label( $ty ) ? ': ' . $nm : '' ), 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( 'Источники', array( 'src' => array( 'Источник' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ) ), $rows, 'istochniki' );
		$rows = array();
		foreach ( $r->group( 'entry', '', 8 ) as $x ) {
			$rows[] = array( 'p' => $x->k, 'p_url' => self::url( array( 'sec' => 'entry', 'path' => $x->k ) ), 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( 'Страницы входа', array( 'p' => array( 'Страница', 'link' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ) ), $rows, 'vhody' );
		echo '</div>';
	}

	private static function sec_entry() {
		global $wpdb;
		$r    = self::$r;
		$path = self::g( 'path' );
		if ( '' !== $path ) {
			echo '<p><a href="' . esc_url( self::url( array( 'path' => null ) ) ) . '">← Все страницы входа</a></p>';
			$extra = $wpdb->prepare( 'AND v.entry = %s', $path );
			$d     = $r->daily_visits( $extra );
			self::chart( 'Визиты со входом на ' . $path, array_keys( $d ), array( array( 'Визиты', array_values( $d ) ) ) );
			$rows = array();
			foreach ( $r->group( "CONCAT(src_type,':',src_name)", $extra ) as $x ) {
				list( $ty, $nm ) = array_pad( explode( ':', $x->k, 2 ), 2, '' );
				$rows[] = array( 'type' => Bobr_Util::label( $ty ), 'name' => $nm, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur, 'depth' => $x->depth );
			}
			self::table( 'Откуда приходят на эту страницу', array( 'type' => array( 'Тип' ), 'name' => array( 'Источник' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'dur' => array( 'Время', 'sec' ), 'depth' => array( 'Глубина', 'float' ) ), $rows, 'vhod-istochniki' );
			return;
		}
		$v    = Bobr_DB::t( 'visits' );
		$tops = array();
		foreach ( $wpdb->get_results( "SELECT entry, src_type, COUNT(*) n FROM $v v WHERE " . $r->where() . ' GROUP BY entry, src_type ORDER BY n DESC' ) as $x ) {
			$tops[ $x->entry ][] = Bobr_Util::label( $x->src_type ) . ' ' . $x->n;
		}
		$rows = array();
		foreach ( $r->group( 'entry' ) as $x ) {
			$rows[] = array( 'p' => $x->k, 'p_url' => self::url( array( 'path' => $x->k ) ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur, 'depth' => $x->depth, 'src' => implode( ', ', array_slice( $tops[ $x->k ] ?? array(), 0, 3 ) ) );
		}
		self::table( 'Страницы входа', array( 'p' => array( 'Страница', 'link' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'dur' => array( 'Время', 'sec' ), 'depth' => array( 'Глубина', 'float' ), 'src' => array( 'Источники' ) ), $rows, 'stranicy-vhoda' );
	}

	private static function sec_pages() {
		global $wpdb;
		$r    = self::$r;
		$h    = Bobr_DB::t( 'hits' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, MAX(title) title, COUNT(*) views, COUNT(DISTINCT visit_id) visits, AVG(NULLIF(visible,0)) vis, AVG(NULLIF(scroll,0)) sc FROM $h h WHERE " . $r->hits_where() . ' GROUP BY path ORDER BY views DESC LIMIT 1000' ) as $x ) {
			$rows[] = array( 'p' => $x->path, 't' => $x->title, 'views' => $x->views, 'visits' => $x->visits, 'vis' => (float) $x->vis, 'sc' => (float) $x->sc );
		}
		self::table( 'Все страницы', array( 'p' => array( 'Страница' ), 't' => array( 'Заголовок' ), 'views' => array( 'Просмотры', 'bar' ), 'visits' => array( 'Визиты', 'num' ), 'vis' => array( 'Время на странице', 'sec' ), 'sc' => array( 'Прокрутка', 'pct' ) ), $rows, 'vse-stranicy' );
		$rows = array();
		foreach ( $r->group( 'exit_path' ) as $x ) {
			$rows[] = array( 'p' => $x->k, 'visits' => $x->visits, 'depth' => $x->depth, 'dur' => $x->dur );
		}
		self::table( 'Страницы выхода', array( 'p' => array( 'Страница' ), 'visits' => array( 'Визиты закончились здесь', 'bar' ), 'depth' => array( 'Глубина', 'float' ), 'dur' => array( 'Время визита', 'sec' ) ), $rows, 'stranicy-vyhoda' );
	}

	private static function sec_sources() {
		global $wpdb;
		$r    = self::$r;
		$path = self::g( 'path' );
		if ( '' !== $path ) {
			list( $ty, $nm ) = array_pad( explode( ':', $path, 2 ), 2, '' );
			echo '<p><a href="' . esc_url( self::url( array( 'path' => null ) ) ) . '">← Все источники</a></p>';
			$extra = $wpdb->prepare( 'AND v.src_type = %s AND v.src_name = %s', $ty, $nm );
			$d     = $r->daily_visits( $extra );
			self::chart( 'Визиты: ' . Bobr_Util::label( $ty ) . ( $nm ? ', ' . $nm : '' ), array_keys( $d ), array( array( 'Визиты', array_values( $d ) ) ) );
			$rows = array();
			foreach ( $r->group( 'entry', $extra ) as $x ) {
				$rows[] = array( 'p' => $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
			}
			self::table( 'Страницы входа из этого источника', array( 'p' => array( 'Страница' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'dur' => array( 'Время', 'sec' ) ), $rows, 'istochnik-vhody' );
			return;
		}
		$labels = $r->buckets();
		self::chart( 'Типы источников ' . self::per(), $labels, self::named( $r->split( 'src_type', '', 8 ) ) );
		echo '<div class="bobr-2">';
		self::chart( 'Поисковики ' . self::per(), $labels, self::named( $r->split( 'src_name', "AND v.src_type = 'search'" ) ), 'half' );
		self::chart( 'Соцсети ' . self::per(), $labels, self::named( $r->split( 'src_name', "AND v.src_type = 'social'" ) ), 'half' );
		echo '</div>';
		echo '<div class="bobr-2">';
		$rows = array();
		foreach ( $r->group( 'src_type' ) as $x ) {
			$rows[] = array( 'type' => Bobr_Util::label( $x->k ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
		}
		self::table( 'Типы источников', array( 'type' => array( 'Тип' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'dur' => array( 'Время', 'sec' ) ), $rows, 'tipy-istochnikov' );
		$rows = array();
		foreach ( $r->group( 'src_name', "AND v.src_type = 'search'" ) as $x ) {
			$rows[] = array( 'name' => $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( 'Поисковики', array( 'name' => array( 'Поисковик' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ) ), $rows, 'poiskoviki' );
		echo '</div>';
		$rows = array();
		foreach ( $r->group( "CONCAT(src_type,':',src_name)" ) as $x ) {
			list( $ty, $nm ) = array_pad( explode( ':', $x->k, 2 ), 2, '' );
			$rows[] = array( 'type' => Bobr_Util::label( $ty ), 'name' => $nm, 'name_url' => self::url( array( 'path' => $x->k ) ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur, 'depth' => $x->depth );
		}
		self::table( 'Все источники (нажмите, чтобы увидеть страницы входа)', array( 'type' => array( 'Тип' ), 'name' => array( 'Источник', 'link' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'dur' => array( 'Время', 'sec' ), 'depth' => array( 'Глубина', 'float' ) ), $rows, 'istochniki' );
		$rows = array();
		foreach ( $r->group( "CONCAT_WS(' | ',utm_source,utm_medium,utm_campaign,utm_term,utm_content)", "AND (v.utm_source <> '' OR v.utm_campaign <> '')" ) as $x ) {
			$p      = array_pad( explode( ' | ', $x->k ), 5, '' );
			$rows[] = array( 's' => $p[0], 'm' => $p[1], 'c' => $p[2], 't' => $p[3], 'ct' => $p[4], 'visits' => $x->visits, 'bounce' => $x->bounce );
		}
		self::table( 'utm-метки', array( 's' => array( 'utm_source' ), 'm' => array( 'utm_medium' ), 'c' => array( 'utm_campaign' ), 't' => array( 'utm_term' ), 'ct' => array( 'utm_content' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ) ), $rows, 'utm' );
	}

	private static function sec_ai() {
		$r     = self::$r;
		$extra = "AND v.src_type = 'ai'";
		$d     = $r->daily_visits( $extra );
		self::chart( 'Переходы людей из нейросетей ' . self::per(), array_keys( $d ), array( array( 'Визиты из нейросетей', array_values( $d ) ) ) );
		echo '<div class="bobr-2">';
		$rows = array();
		foreach ( $r->group( 'src_name', $extra ) as $x ) {
			$rows[] = array( 'name' => $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
		}
		self::table( 'Нейросети', array( 'name' => array( 'Нейросеть' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'dur' => array( 'Время', 'sec' ) ), $rows, 'neiroseti' );
		$rows = array();
		foreach ( $r->group( "CONCAT(entry,'  ←  ',src_name)", $extra ) as $x ) {
			list( $p, $n ) = array_pad( explode( '  ←  ', $x->k, 2 ), 2, '' );
			$rows[] = array( 'p' => $p, 'n' => $n, 'visits' => $x->visits );
		}
		self::table( 'На какие страницы ведут нейросети', array( 'p' => array( 'Страница' ), 'n' => array( 'Нейросеть' ), 'visits' => array( 'Визиты', 'bar' ) ), $rows, 'neiroseti-stranicy' );
		echo '</div><p class="bobr-hint">Нейросеть определяется по адресу, с которого пришёл человек, и по метке utm_source (ChatGPT добавляет её сам). Часть переходов из приложений нейросетей приходит без адреса и попадает в прямые заходы.</p>';
	}

	private static function sec_bots() {
		global $wpdb;
		$r    = self::$r;
		$b    = Bobr_DB::t( 'bots' );
		$w    = $r->day_where();
		$rows = $wpdb->get_results( 'SELECT ' . $r->bucket_sql() . " d, kind, COUNT(*) n FROM $b WHERE $w GROUP BY d, kind" );
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
			echo '<p class="bobr-hint">Учёт роботов выключен в настройках.</p>';
		}
		self::chart( 'Запросы роботов ' . self::per(), $labels, $series ? $series : array( array( 'Роботы', array_fill( 0, count( $labels ), 0 ) ) ) );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT bot, kind, COUNT(*) n, COUNT(DISTINCT path) pages, SUM(status >= 400) errs, MAX(ts) last FROM $b WHERE $w GROUP BY bot, kind ORDER BY n DESC LIMIT 500" ) as $x ) {
			$rows[] = array( 'bot' => $x->bot, 'kind' => Bobr_Util::label( $x->kind ), 'n' => $x->n, 'pages' => $x->pages, 'errs' => $x->errs, 'last' => mysql2date( 'd.m H:i', $x->last ) );
		}
		self::table( 'Роботы', array( 'bot' => array( 'Робот' ), 'kind' => array( 'Тип' ), 'n' => array( 'Запросы', 'bar' ), 'pages' => array( 'Разных страниц', 'num' ), 'errs' => array( 'Ошибки 4xx-5xx', 'num' ), 'last' => array( 'Последний заход' ) ), $rows, 'roboty' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT bot, path, COUNT(*) n, MAX(ts) last FROM $b WHERE $w AND kind = 'ai' GROUP BY bot, path ORDER BY n DESC LIMIT 500" ) as $x ) {
			$rows[] = array( 'bot' => $x->bot, 'p' => $x->path, 'n' => $x->n, 'last' => mysql2date( 'd.m H:i', $x->last ) );
		}
		self::table( 'Нейросетевые агенты: что читали', array( 'bot' => array( 'Агент' ), 'p' => array( 'Страница' ), 'n' => array( 'Запросы', 'bar' ), 'last' => array( 'Последний раз' ) ), $rows, 'ai-agenty' );
		$grid = array();
		foreach ( $wpdb->get_results( "SELECT WEEKDAY(ts)+1 wd, HOUR(ts) hh, COUNT(*) n FROM $b WHERE $w GROUP BY wd, hh" ) as $x ) {
			$grid[ (int) $x->wd ][ (int) $x->hh ] = (int) $x->n;
		}
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>Когда приходят роботы: дни недели и часы</h2></div>' . Bobr_Charts::heat( $grid ) . '</section>';
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, COUNT(*) n, COUNT(DISTINCT bot) bots, SUM(status = 404) nf FROM $b WHERE $w GROUP BY path ORDER BY n DESC LIMIT 500" ) as $x ) {
			$rows[] = array( 'p' => $x->path, 'n' => $x->n, 'bots' => $x->bots, 'nf' => $x->nf );
		}
		self::table( 'Какие страницы смотрят роботы', array( 'p' => array( 'Страница' ), 'n' => array( 'Запросы', 'bar' ), 'bots' => array( 'Разных роботов', 'num' ), 'nf' => array( 'Ответов 404', 'num' ) ), $rows, 'roboty-stranicy' );
	}

	private static function sec_geo() {
		$r = self::$r;
		if ( ! Bobr_Geo::has_db() ) {
			echo '<p class="bobr-hint">База городов не подключена: страна и пояс определяются по часовому поясу браузера. Подключить базу можно в Настройках.</p>';
		}
		self::chart( 'Пять главных городов ' . self::per(), $r->buckets(), self::named( $r->split( 'city' ) ) );
		echo '<div class="bobr-2">';
		foreach ( array( 'country' => 'Страны', 'region' => 'Регионы' ) as $col => $title ) {
			$rows = array();
			foreach ( $r->group( $col ) as $x ) {
				$rows[] = array( 'k' => '' === $x->k ? 'Не определено' : $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce );
			}
			self::table( $title, array( 'k' => array( $title ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ) ), $rows, $col );
		}
		echo '</div>';
		$rows = array();
		foreach ( $r->group( "CONCAT_WS(', ',NULLIF(city,''),NULLIF(region,''),NULLIF(country,''))" ) as $x ) {
			$rows[] = array( 'k' => '' === (string) $x->k ? 'Не определено' : $x->k, 'visits' => $x->visits, 'bounce' => $x->bounce, 'dur' => $x->dur );
		}
		self::table( 'Города', array( 'k' => array( 'Город' ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'dur' => array( 'Время', 'sec' ) ), $rows, 'goroda' );
		echo '<p class="bobr-hint">По мобильному интернету город часто определяется неточно: операторы выходят в интернет через крупные города, чаще всего через Москву.</p>';
	}

	private static function sec_tech() {
		$r    = self::$r;
		$cols = array( 'device' => 'Устройства', 'os' => 'Операционные системы', 'browser' => 'Браузеры', 'screen' => 'Ширина экрана' );
		$all  = array();
		foreach ( $cols as $col => $title ) {
			foreach ( $r->group( $col ) as $x ) {
				$all[ $col ][] = array( 'k' => 'device' === $col ? Bobr_Util::label( $x->k ) : ( '' === $x->k ? 'Не определено' : $x->k ), 'visits' => $x->visits, 'bounce' => $x->bounce, 'depth' => $x->depth );
			}
		}
		self::chart( 'Телефоны и компьютеры: доли визитов ' . self::per(), $r->buckets(), self::named( $r->split( 'device' ) ), 'stack' );
		echo '<section class="bobr-box"><div class="bobr-donuts">';
		foreach ( array( 'device', 'os', 'browser' ) as $col ) {
			echo '<div><h3>' . esc_html( $cols[ $col ] ) . '</h3>' . Bobr_Charts::donut( array_column( $all[ $col ] ?? array(), 'visits', 'k' ) ) . '</div>';
		}
		echo '</div></section><div class="bobr-2">';
		foreach ( $cols as $col => $title ) {
			self::table( $title, array( 'k' => array( $title ), 'visits' => array( 'Визиты', 'bar' ), 'bounce' => array( 'Отказы', 'pct' ), 'depth' => array( 'Глубина', 'float' ) ), $all[ $col ] ?? array(), $col );
		}
		echo '</div>';
	}

	private static function sec_time() {
		global $wpdb;
		$r    = self::$r;
		$v    = Bobr_DB::t( 'visits' );
		$grid = array();
		foreach ( $wpdb->get_results( "SELECT WEEKDAY(started)+1 wd, HOUR(started) hh, COUNT(*) n FROM $v v WHERE " . $r->where() . ' GROUP BY wd, hh' ) as $x ) {
			$grid[ (int) $x->wd ][ (int) $x->hh ] = (int) $x->n;
		}
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>Визиты по дням недели и часам</h2></div>' . Bobr_Charts::heat( $grid ) . '<p class="bobr-hint">Время по часовому поясу сайта.</p></section>';
		$hours = array_fill( 0, 24, 0 );
		$wdays = array_fill( 0, 7, 0 );
		foreach ( $grid as $wd => $row ) {
			foreach ( $row as $hh => $n ) {
				$hours[ $hh ]    += $n;
				$wdays[ $wd - 1 ] += $n;
			}
		}
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>Визиты по часам суток</h2></div>' . Bobr_Charts::bars( range( 0, 23 ), $hours, 'визиты' ) . '</section>';
		echo '<section class="bobr-box"><div class="bobr-box-h"><h2>Визиты по дням недели</h2></div>' . Bobr_Charts::bars( array( 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс' ), $wdays, 'визиты' ) . '</section>';
	}

	private static function sec_behavior() {
		global $wpdb;
		$r    = self::$r;
		$h    = Bobr_DB::t( 'hits' );
		$by   = $wpdb->get_results( 'SELECT ' . $r->bucket_sql( 'h' ) . " d, AVG(NULLIF(visible,0)) vis, AVG(NULLIF(scroll,0)) sc FROM $h h WHERE " . $r->hits_where() . ' GROUP BY d', OBJECT_K );
		$vis  = array();
		$sc   = array();
		foreach ( $r->buckets() as $d ) {
			$vis[] = isset( $by[ $d ]->vis ) ? round( (float) $by[ $d ]->vis ) : null;
			$sc[]  = isset( $by[ $d ]->sc ) ? round( (float) $by[ $d ]->sc, 1 ) : null;
		}
		echo '<div class="bobr-2">';
		self::chart( 'Время на странице, секунд', $r->buckets(), array( array( 'Время на виду, с', $vis ) ), 'half' );
		self::chart( 'Прокрутка страницы, %', $r->buckets(), array( array( 'Средняя прокрутка, %', $sc ) ), 'half' );
		echo '</div>';
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, COUNT(*) views, AVG(NULLIF(visible,0)) vis, AVG(NULLIF(scroll,0)) sc, ROUND(SUM(scroll >= 75)/NULLIF(SUM(scroll > 0),0)*100,1) deep, ROUND(SUM(visible > 0 AND visible < 10)/NULLIF(SUM(visible > 0),0)*100,1) quick FROM $h h WHERE " . $r->hits_where() . ' GROUP BY path ORDER BY views DESC LIMIT 1000' ) as $x ) {
			$rows[] = array( 'p' => $x->path, 'views' => $x->views, 'vis' => (float) $x->vis, 'sc' => (float) $x->sc, 'deep' => (float) $x->deep, 'quick' => (float) $x->quick );
		}
		self::table( 'Время и прокрутка по страницам', array( 'p' => array( 'Страница' ), 'views' => array( 'Просмотры', 'bar' ), 'vis' => array( 'Время на виду', 'sec' ), 'sc' => array( 'Средняя прокрутка', 'pct' ), 'deep' => array( 'Дочитали до 75%', 'pct' ), 'quick' => array( 'Ушли за 10 с', 'pct' ) ), $rows, 'povedenie' );
		echo '<p class="bobr-hint">Время на виду считается только пока вкладка открыта перед глазами: свёрнутая вкладка не считается.</p>';
	}

	private static function sec_events() {
		global $wpdb;
		$r = self::$r;
		$e = Bobr_DB::t( 'events' );
		$w = $r->day_where( 'e' );
		$types = array( 'tel' => 'Нажали на телефон', 'mail' => 'Нажали на почту', 'out' => 'Ушли по внешним ссылкам', 'form_sent' => 'Отправили формы', 'form_drop' => 'Бросили формы' );
		echo '<div class="bobr-cards">';
		foreach ( $types as $t => $l ) {
			self::card( $l, number_format_i18n( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $e e WHERE $w AND type = %s", $t ) ) ) );
		}
		echo '</div>';
		$by = array();
		foreach ( $wpdb->get_results( 'SELECT ' . $r->bucket_sql( 'e' ) . " d, type, COUNT(*) n FROM $e e WHERE $w AND type IN ('tel','mail','out','form_sent','form_drop') GROUP BY d, type" ) as $x ) {
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
		self::chart( 'Действия посетителей ' . self::per(), $r->buckets(), $series );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT name, COUNT(*) n, COUNT(DISTINCT path) pages FROM $e e WHERE $w AND type = 'out' GROUP BY name ORDER BY n DESC LIMIT 1000" ) as $x ) {
			$rows[] = array( 'u' => $x->name, 'n' => $x->n, 'pages' => $x->pages );
		}
		self::table( 'Уходы по внешним ссылкам', array( 'u' => array( 'Куда' ), 'n' => array( 'Переходы', 'bar' ), 'pages' => array( 'С разных страниц', 'num' ) ), $rows, 'vneshnie-ssylki' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT name, path, SUM(type = 'form_sent') s, SUM(type = 'form_drop') dr FROM $e e WHERE $w AND type IN ('form_sent','form_drop') GROUP BY name, path ORDER BY s DESC, dr DESC LIMIT 1000" ) as $x ) {
			$rows[] = array( 'f' => $x->name, 'p' => $x->path, 's' => $x->s, 'dr' => $x->dr );
		}
		self::table( 'Формы', array( 'f' => array( 'Форма' ), 'p' => array( 'Страница' ), 's' => array( 'Отправлены', 'bar' ), 'dr' => array( 'Начаты и брошены', 'num' ) ), $rows, 'formy' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, SUM(type = 'tel') t, SUM(type = 'mail') m FROM $e e WHERE $w AND type IN ('tel','mail') GROUP BY path ORDER BY t DESC LIMIT 500" ) as $x ) {
			$rows[] = array( 'p' => $x->path, 't' => $x->t, 'm' => $x->m );
		}
		self::table( 'Где нажимают на телефон и почту', array( 'p' => array( 'Страница' ), 't' => array( 'Телефон', 'bar' ), 'm' => array( 'Почта', 'num' ) ), $rows, 'telefon-pochta' );
		echo '<p class="bobr-hint">Номера телефонов, адреса почты и значения полей форм не записываются: только сам факт нажатия и название формы.</p>';
	}

	private static function sec_nf() {
		global $wpdb;
		$r    = self::$r;
		$e    = Bobr_DB::t( 'events' );
		$rows = array();
		foreach ( $wpdb->get_results( "SELECT path, name, COUNT(*) n, MAX(ts) last FROM $e e WHERE " . $r->day_where( 'e' ) . " AND type = 'nf' GROUP BY path, name ORDER BY n DESC LIMIT 1000" ) as $x ) {
			$rows[] = array( 'p' => $x->path, 'from' => $x->name, 'n' => $x->n, 'last' => mysql2date( 'd.m H:i', $x->last ) );
		}
		self::table( 'Страницы 404 у людей: откуда пришли', array( 'p' => array( 'Адрес, которого нет' ), 'from' => array( 'Откуда пришли' ), 'n' => array( 'Раз', 'bar' ), 'last' => array( 'Последний раз' ) ), $rows, 'oshibki-404' );
		echo '<p class="bobr-hint">Ошибки 404 у роботов смотрите в разделе Роботы.</p>';
	}

	private static function sec_speed() {
		global $wpdb;
		$r    = self::$r;
		$e    = Bobr_DB::t( 'events' );
		$data = array();
		$by   = array();
		foreach ( $wpdb->get_results( 'SELECT ' . $r->bucket_sql( 'e' ) . " d, path, type, val FROM $e e WHERE " . $r->day_where( 'e' ) . " AND type IN ('ttfb','fcp','lcp','inp','cls') ORDER BY id DESC LIMIT 60000" ) as $x ) {
			$data[ $x->path ][ $x->type ][] = (float) $x->val;
			$by[ $x->type ][ $x->d ][]      = (float) $x->val;
		}
		$series = array();
		foreach ( array( 'ttfb' => 'Ответ сервера', 'fcp' => 'Первая отрисовка', 'lcp' => 'Главный блок (LCP)', 'inp' => 'Отклик (INP)' ) as $k => $l ) {
			if ( ! empty( $by[ $k ] ) ) {
				$vals = array();
				foreach ( $r->buckets() as $d ) {
					$vals[] = Bobr_Report::p75( $by[ $k ][ $d ] ?? array() );
				}
				$series[] = array( $l, $vals );
			}
		}
		self::chart( 'Скорость ' . self::per() . ', мс (75-й процентиль)', $r->buckets(), $series );
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
		self::table( 'Скорость по страницам: 75-й процентиль', array( 'p' => array( 'Страница' ), 'n' => array( 'Замеров', 'num' ), 'ttfb' => array( 'Ответ сервера', 'ms' ), 'fcp' => array( 'Первая отрисовка', 'ms' ), 'lcp' => array( 'Главный блок (LCP)', 'ms' ), 'inp' => array( 'Отклик (INP)', 'ms' ), 'cls' => array( 'Сдвиги (CLS)', 'ms' ) ), $rows, 'skorost' );
		echo '<p class="bobr-hint">Зелёный - хорошо, жёлтый - терпимо, красный - плохо (пороги Google Core Web Vitals). 75-й процентиль: у трёх из четырёх посетителей было так или быстрее.</p>';
	}

	private static function sec_now() {
		global $wpdb;
		$v     = Bobr_DB::t( 'visits' );
		$since = wp_date( 'Y-m-d H:i:s', time() - 1800 );
		$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $v WHERE last_seen >= %s ORDER BY last_seen DESC LIMIT 300", $since ) );
		$five  = 0;
		foreach ( $list as $x ) {
			if ( strtotime( $x->last_seen ) >= strtotime( wp_date( 'Y-m-d H:i:s' ) ) - 300 ) {
				$five++;
			}
		}
		echo '<div class="bobr-cards">';
		self::card( 'Визитов за 30 минут', number_format_i18n( count( $list ) ) );
		self::card( 'Активны последние 5 минут', number_format_i18n( $five ) );
		echo '</div>';
		$rows = array();
		foreach ( $list as $x ) {
			$rows[] = array( 'when' => mysql2date( 'H:i', $x->last_seen ), 'entry' => $x->entry, 'exit' => $x->exit_path, 'pages' => $x->pages, 'src' => Bobr_Util::label( $x->src_type ) . ( $x->src_name && $x->src_name !== Bobr_Util::label( $x->src_type ) ? ': ' . $x->src_name : '' ), 'dev' => Bobr_Util::label( $x->device ), 'geo' => implode( ', ', array_filter( array( $x->city, $x->country ) ) ) );
		}
		self::table( 'Сейчас на сайте', array( 'when' => array( 'Активность' ), 'entry' => array( 'Вход' ), 'exit' => array( 'Сейчас на странице' ), 'pages' => array( 'Страниц', 'num' ), 'src' => array( 'Источник' ), 'dev' => array( 'Устройство' ), 'geo' => array( 'Город' ) ), $rows, 'seichas' );
	}

	private static function sec_settings() {
		$s     = bobr_settings();
		$roles = wp_roles()->get_names();
		$ip    = Bobr_Util::ip();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bobr-box bobr-set"><input type="hidden" name="action" value="bobr_settings">';
		wp_nonce_field( 'bobr_settings' );
		echo '<h2>Кого не считать</h2><div class="bobr-roles">';
		foreach ( $roles as $k => $name ) {
			echo '<label><input type="checkbox" name="exclude_roles[]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, (array) $s['exclude_roles'], true ), true, false ) . '> ' . esc_html( translate_user_role( $name ) ) . '</label>';
		}
		echo '</div><p class="bobr-hint">Вошедшие на сайт пользователи с отмеченными ролями не попадают в статистику.</p>';
		echo '<h3>Свои айпи-адреса</h3><p>Исключено адресов: <b>' . count( (array) $s['exclude_ips'] ) . '</b>. Адреса хранятся только в виде хешей, прочитать их обратно нельзя.</p>';
		echo '<textarea name="add_ips" rows="3" placeholder="Добавить айпи, по одному в строке"></textarea>';
		echo '<p><label><input type="checkbox" name="add_my_ip" value="1"> Добавить мой текущий айпи (' . esc_html( $ip ) . ')</label> &nbsp; <label><input type="checkbox" name="clear_ips" value="1"> Очистить список</label></p>';
		echo '<h2>Хранение и сбор</h2>';
		echo '<p><label>Подробные записи хранить, дней: <input type="number" name="retention" min="30" max="1095" value="' . esc_attr( $s['retention'] ) . '"></label></p><p class="bobr-hint">Потом удаляются по ночам. Суммы по дням без данных о людях хранятся бессрочно.</p>';
		echo '<p><label><input type="checkbox" name="geo" value="1" ' . checked( $s['geo'], 1, false ) . '> Определять город по айпи (айпи после определения отбрасывается)</label></p>';
		echo '<p><label><input type="checkbox" name="bots" value="1" ' . checked( $s['bots'], 1, false ) . '> Записывать роботов</label></p>';
		submit_button( 'Сохранить настройки' );
		echo '</form>';

		echo '<section class="bobr-box bobr-set"><h2>База городов</h2>';
		if ( Bobr_Geo::has_db() ) {
			$up = (int) get_option( 'bobr_geo_updated' );
			echo '<p>Подключена: ' . esc_html( size_format( filesize( Bobr_Geo::db_file() ) ) ) . ( $up ? ', обновлена ' . esc_html( wp_date( 'd.m.Y', $up ) ) : '' ) . '.</p>';
		} else {
			echo '<p>Не подключена. Пока её нет, страна и пояс определяются по часовому поясу браузера.</p>';
		}
		echo '<p class="bobr-hint">Файл DB-IP IP to City Lite с сайта db-ip.com: архив около 60 МБ, после распаковки около 130 МБ на диске сайта. Бесплатно, без регистрации, обновляется раз в месяц. Кнопка делает один запрос к db-ip.com, данные посетителей никуда не отправляются.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Скачать базу городов с db-ip.com (около 60 МБ)?\');"><input type="hidden" name="action" value="bobr_geo">';
		wp_nonce_field( 'bobr_geo' );
		submit_button( Bobr_Geo::has_db() ? 'Обновить базу городов' : 'Скачать базу городов', 'secondary', 'submit', false );
		echo '</form><p class="bobr-attr"><a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> (лицензия CC BY 4.0)</p></section>';

		echo '<section class="bobr-box bobr-set"><h2>Служебное</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bobr_rebuild">';
		wp_nonce_field( 'bobr_rebuild' );
		echo '<p>Суммы по дням пересчитываются каждую ночь. Пересчитать вручную за последние 30 дней: ';
		submit_button( 'Пересчитать', 'secondary', 'submit', false );
		echo '</p></form><p class="bobr-hint">Кэша страниц на сайте ' . ( defined( 'WP_CACHE' ) && WP_CACHE ? '<b>есть</b>: роботы на закэшированных страницах не учитываются, люди учитываются полностью.' : 'нет: роботы учитываются полностью.' ) . '</p></section>';

		echo '<section class="bobr-box bobr-set"><h2>О плагине</h2>';
		echo '<p>Бобр аналитика ' . esc_html( BOBR_VER ) . ', бесплатный плагин с открытым кодом. Автор: <a href="https://lop-pa.dev" target="_blank" rel="noopener">Ekaterina Lopatina</a>.</p>';
		echo '<p><a class="button" href="' . esc_url( BOBR_GITHUB ) . '" target="_blank" rel="noopener">Код на GitHub</a> ';
		echo '<a class="button" href="' . esc_url( BOBR_GITHUB . '/issues' ) . '" target="_blank" rel="noopener">Сообщить об ошибке или предложить идею</a> ';
		echo '<a class="button" href="https://lop-pa.dev" target="_blank" rel="noopener">lop-pa.dev</a></p></section>';
	}

	private static function check( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Нет прав.' );
		}
		check_admin_referer( $action );
	}

	private static function back( $msg ) {
		wp_safe_redirect( admin_url( 'admin.php?page=bobr&sec=settings&bobr_msg=' . $msg ) );
		exit;
	}

	public static function save_settings() {
		self::check( 'bobr_settings' );
		$s     = bobr_settings();
		$roles = array_keys( wp_roles()->get_names() );
		$s['exclude_roles'] = array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['exclude_roles'] ?? array() ) ), $roles ) );
		$ips = ! empty( $_POST['clear_ips'] ) ? array() : (array) $s['exclude_ips'];
		foreach ( preg_split( '~\s+~', (string) wp_unslash( $_POST['add_ips'] ?? '' ) ) as $ip ) {
			if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
				$ips[] = Bobr_Util::ip_hash( $ip );
			}
		}
		if ( ! empty( $_POST['add_my_ip'] ) ) {
			$ips[] = Bobr_Util::ip_hash( Bobr_Util::ip() );
		}
		$s['exclude_ips'] = array_values( array_unique( $ips ) );
		$s['retention']   = max( 30, min( 1095, (int) ( $_POST['retention'] ?? 180 ) ) );
		$s['geo']         = empty( $_POST['geo'] ) ? 0 : 1;
		$s['bots']        = empty( $_POST['bots'] ) ? 0 : 1;
		update_option( 'bobr_settings', $s, false );
		self::back( 'saved' );
	}

	public static function geo_download() {
		self::check( 'bobr_geo' );
		@set_time_limit( 900 );
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
