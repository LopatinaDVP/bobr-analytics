<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Charts {
	const COLORS = array( '#c48a2c', '#3a7bd5', '#2e9e6b', '#9b59b6', '#d9534f', '#16a2b8', '#7f8c8d' );

	public static $unit = 'day';

	public static function line( $labels, $series, $w = 1000 ) {
		$h = 260;
		$pl = 46;
		$pr = 14;
		$pt = 14;
		$pb = 30;
		$n = count( $labels );
		$max = 0;
		foreach ( $series as $s ) {
			foreach ( $s[1] as $v ) {
				$max = max( $max, (float) $v );
			}
		}
		$max  = self::nice( $max );
		$k = 4;
		if ( $max >= 1 && fmod( $max, 4 ) ) {
			if ( fmod( $max, 5 ) ) {
				$max = ceil( $max / 4 ) * 4;
			} else {
				$k = 5;
			}
		}
		$iw   = $w - $pl - $pr;
		$ih   = $h - $pt - $pb;
		$step = $n > 1 ? $iw / ( $n - 1 ) : 0;
		$x    = function ( $i ) use ( $pl, $step, $n, $iw ) {
			return $n > 1 ? $pl + $i * $step : $pl + $iw / 2;
		};
		$y = function ( $v ) use ( $pt, $ih, $max ) {
			return $pt + $ih - ( $max ? $v / $max * $ih : 0 );
		};

		$o = '<svg class="bobr-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img">';
		for ( $g = 0; $g <= $k; $g++ ) {
			$gv = $max * $g / $k;
			$gy = $y( $gv );
			$o .= '<line x1="' . $pl . '" x2="' . ( $w - $pr ) . '" y1="' . round( $gy, 1 ) . '" y2="' . round( $gy, 1 ) . '" class="g"/>';
			$o .= '<text x="' . ( $pl - 8 ) . '" y="' . round( $gy + 4, 1 ) . '" class="yl">' . esc_html( self::short( $gv ) ) . '</text>';
		}
		$o   .= self::x_labels( $labels, $x, $w, $h );
		foreach ( array_values( $series ) as $si => $s ) {
			$col  = self::COLORS[ $si % count( self::COLORS ) ];
			$segs = array();
			$cur  = array();
			foreach ( array_values( $s[1] ) as $i => $v ) {
				if ( null === $v ) {
					$segs[] = $cur;
					$cur    = array();
					continue;
				}
				$cur[] = round( $x( $i ), 1 ) . ',' . round( $y( (float) $v ), 1 );
			}
			$segs = array_filter( array_merge( $segs, array( $cur ) ) );
			if ( ! $segs ) {
				continue;
			}
			$dash = ! empty( $s[2] ) ? ' stroke-dasharray="6 5" opacity=".55"' : '';
			if ( 0 === $si && empty( $s[2] ) && $n > 1 && 1 === count( $segs ) && count( $cur ) === $n ) {
				$o .= '<polygon points="' . $pl . ',' . ( $pt + $ih ) . ' ' . implode( ' ', $cur ) . ' ' . round( $x( $n - 1 ), 1 ) . ',' . ( $pt + $ih ) . '" fill="' . $col . '" opacity=".08"/>';
			}
			foreach ( $segs as $pts ) {
				$o .= '<polyline points="' . implode( ' ', $pts ) . '" fill="none" stroke="' . $col . '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"' . $dash . '/>';
			}
			if ( empty( $s[2] ) && $n <= 62 ) {
				foreach ( array_values( $s[1] ) as $i => $v ) {
					if ( null !== $v ) {
						$o .= '<circle cx="' . round( $x( $i ), 1 ) . '" cy="' . round( $y( (float) $v ), 1 ) . '" r="3.2" fill="' . $col . '"><title>' . esc_html( self::point_label( $labels[ $i ], true ) . ': ' . $s[0] . ' ' . self::num( $v ) ) . '</title></circle>';
					}
				}
			}
		}
		$o .= '</svg>' . self::legend( $series );
		return $o;
	}

	private static function x_labels( $labels, $x, $w, $h ) {
		$n     = count( $labels );
		$every = max( 1, (int) ceil( $n / ( $w / 85 ) ) );
		$o     = '';
		foreach ( array_values( $labels ) as $i => $l ) {
			if ( 0 === $i % $every || $i === $n - 1 ) {
				$o .= '<text x="' . round( $x( $i ), 1 ) . '" y="' . ( $h - 8 ) . '" class="xl">' . esc_html( self::point_label( $l ) ) . '</text>';
			}
		}
		return $o;
	}

	private static function legend( $series ) {
		$o = '<div class="bobr-legend">';
		foreach ( array_values( $series ) as $si => $s ) {
			$o .= '<span><i style="background:' . self::COLORS[ $si % count( self::COLORS ) ] . ( ! empty( $s[2] ) ? '80' : '' ) . '"></i>' . esc_html( $s[0] ) . '</span>';
		}
		return $o . '</div>';
	}

	public static function heat( $grid ) {
		$max = 0;
		foreach ( $grid as $row ) {
			foreach ( $row as $v ) {
				$max = max( $max, $v );
			}
		}
		$days = array_combine( range( 1, 7 ), self::weekdays() );
		$o    = '<div class="bobr-heat"><table><thead><tr><th></th>';
		for ( $hh = 0; $hh < 24; $hh++ ) {
			$o .= '<th>' . $hh . '</th>';
		}
		$o .= '</tr></thead><tbody>';
		foreach ( $days as $d => $dn ) {
			$o .= '<tr><th>' . $dn . '</th>';
			for ( $hh = 0; $hh < 24; $hh++ ) {
				$v = isset( $grid[ $d ][ $hh ] ) ? (int) $grid[ $d ][ $hh ] : 0;
				$a = $v && $max ? round( 0.08 + 0.92 * $v / $max, 2 ) : 0.04;
				$o .= '<td style="background:' . self::COLORS[0] . sprintf( '%02x', round( $a * 255 ) ) . '" title="' . esc_attr( $dn . ', ' . $hh . ':00 - ' . $v ) . '">' . ( $v ? (int) $v : '' ) . '</td>';
			}
			$o .= '</tr>';
		}
		return $o . '</tbody></table></div>';
	}

	public static function stack( $labels, $series ) {
		$w  = 1000;
		$h  = 240;
		$pl = 46;
		$pr = 14;
		$pt = 14;
		$pb = 30;
		$n  = count( $labels );
		$iw = $w - $pl - $pr;
		$ih = $h - $pt - $pb;
		$cw = $n ? $iw / $n : 0;
		$x  = function ( $i ) use ( $pl, $cw ) {
			return $pl + ( $i + 0.5 ) * $cw;
		};
		$o = '<svg class="bobr-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img">';
		for ( $g = 0; $g <= 4; $g++ ) {
			$gy = $pt + $ih - $ih * $g / 4;
			$o .= '<line x1="' . $pl . '" x2="' . ( $w - $pr ) . '" y1="' . $gy . '" y2="' . $gy . '" class="g"/><text x="' . ( $pl - 8 ) . '" y="' . ( $gy + 4 ) . '" class="yl">' . ( 25 * $g ) . '%</text>';
		}
		$o .= self::x_labels( $labels, $x, $w, $h );
		foreach ( array_values( $labels ) as $i => $l ) {
			$tot = 0;
			foreach ( $series as $s ) {
				$tot += (float) ( $s[1][ $i ] ?? 0 );
			}
			if ( ! $tot ) {
				continue;
			}
			$yy = $pt + $ih;
			foreach ( array_values( $series ) as $si => $s ) {
				$v = (float) ( $s[1][ $i ] ?? 0 );
				if ( ! $v ) {
					continue;
				}
				$bh  = $v / $tot * $ih;
				$yy -= $bh;
				$o  .= '<rect x="' . round( $x( $i ) - $cw * 0.36, 1 ) . '" y="' . round( $yy, 1 ) . '" width="' . round( $cw * 0.72, 1 ) . '" height="' . round( $bh, 1 ) . '" fill="' . self::COLORS[ $si % count( self::COLORS ) ] . '"><title>' . esc_html( self::point_label( $l, true ) . ': ' . $s[0] . ' ' . number_format_i18n( $v / $tot * 100, 1 ) . '% (' . self::num( $v ) . ')' ) . '</title></rect>';
			}
		}
		return $o . '</svg>' . self::legend( $series );
	}

	public static function bars( $labels, $values, $name, $w = 1000 ) {
		$h   = 220;
		$pl  = 10;
		$pr  = 10;
		$pt  = 22;
		$pb  = 28;
		$n   = count( $labels );
		$max = max( 1, max( array_map( 'floatval', $values ? $values : array( 0 ) ) ) );
		$iw  = $w - $pl - $pr;
		$ih  = $h - $pt - $pb;
		$cw  = $n ? $iw / $n : 0;
		$o   = '<svg class="bobr-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img"><line x1="' . $pl . '" x2="' . ( $w - $pr ) . '" y1="' . ( $pt + $ih ) . '" y2="' . ( $pt + $ih ) . '" class="g"/>';
		foreach ( array_values( $labels ) as $i => $l ) {
			$v  = (float) ( $values[ $i ] ?? 0 );
			$bh = $v / $max * $ih;
			$cx = $pl + ( $i + 0.5 ) * $cw;
			$o .= '<rect x="' . round( $cx - $cw * 0.35, 1 ) . '" y="' . round( $pt + $ih - $bh, 1 ) . '" width="' . round( $cw * 0.7, 1 ) . '" height="' . round( $bh, 1 ) . '" rx="3" fill="' . self::COLORS[0] . '" opacity="' . ( $v >= $max ? 1 : 0.75 ) . '"><title>' . esc_html( $l . ': ' . $name . ' ' . self::num( $v ) ) . '</title></rect>';
			if ( $v ) {
				$o .= '<text x="' . round( $cx, 1 ) . '" y="' . round( $pt + $ih - $bh - 6, 1 ) . '" class="bl">' . esc_html( self::short( $v ) ) . '</text>';
			}
			$o .= '<text x="' . round( $cx, 1 ) . '" y="' . ( $h - 8 ) . '" class="xl">' . esc_html( $l ) . '</text>';
		}
		return $o . '</svg>';
	}

	public static function donut( $items, $top = 5 ) {
		$items = array_filter( array_map( 'floatval', $items ) );
		arsort( $items );
		$tot = array_sum( $items );
		if ( ! $tot ) {
			return '<p class="bobr-empty">' . __( 'No data for this period yet.', 'bobr-analytics' ) . '</p>';
		}
		if ( count( $items ) > $top + 1 ) {
			$rest              = array_sum( array_slice( $items, $top, null, true ) );
			$items             = array_slice( $items, 0, $top, true );
			$items[__( 'Everything else', 'bobr-analytics' )] = $rest;
		}
		$o   = '<div class="bobr-donut"><svg viewBox="0 0 42 42" role="img"><circle cx="21" cy="21" r="15.915" fill="none" stroke="#f2f0eb" stroke-width="7"/>';
		$li  = '';
		$cum = 0;
		$i   = 0;
		foreach ( $items as $k => $v ) {
			$p   = $v / $tot * 100;
			$col = self::COLORS[ $i % count( self::COLORS ) ];
			$o  .= '<circle cx="21" cy="21" r="15.915" fill="none" stroke="' . $col . '" stroke-width="7" stroke-dasharray="' . round( $p, 3 ) . ' ' . round( 100 - $p, 3 ) . '" stroke-dashoffset="' . round( 25 - $cum, 3 ) . '"><title>' . esc_html( $k . ': ' . self::num( $v ) . ' (' . number_format_i18n( $p, 1 ) . '%)' ) . '</title></circle>';
			$li .= '<li><i style="background:' . $col . '"></i>' . esc_html( (string) $k ) . ' <b>' . esc_html( number_format_i18n( $p, 1 ) ) . '%</b></li>';
			$cum += $p;
			$i++;
		}
		return $o . '</svg><ul>' . $li . '</ul></div>';
	}

	public static function nice( $v ) {
		if ( $v <= 0 ) {
			return 4;
		}
		$p = pow( 10, floor( log10( $v ) ) );
		foreach ( array( 1, 2, 2.5, 5, 10 ) as $m ) {
			if ( $v <= $m * $p ) {
				return $m * $p;
			}
		}
		return 10 * $p;
	}

	public static function short( $v ) {
		if ( $v >= 1000000 ) {
			return round( $v / 1000000, 1 ) . _x( 'M', 'millions, short', 'bobr-analytics' );
		}
		if ( $v >= 1000 ) {
			return round( $v / 1000, 1 ) . _x( 'k', 'thousands, short', 'bobr-analytics' );
		}
		return (string) ( floor( (float) $v ) === (float) $v ? (int) $v : round( $v, 1 ) );
	}

	public static function num( $v ) {
		return number_format_i18n( (float) $v, floor( (float) $v ) === (float) $v ? 0 : 1 );
	}

	public static function day_label( $d, $full = false ) {
		$t = strtotime( $d );
		$m = self::months( 'day' );
		return (int) gmdate( 'j', $t ) . ' ' . $m[ (int) gmdate( 'n', $t ) ] . ( $full ? ' ' . gmdate( 'Y', $t ) : '' );
	}

	public static function point_label( $d, $full = false ) {
		if ( 'month' === self::$unit ) {
			$m = self::months( 'month' );
			$t = strtotime( $d );
			return $m[ (int) gmdate( 'n', $t ) ] . ' ' . gmdate( 'Y', $t );
		}
		if ( 'week' === self::$unit ) {
			/* translators: %s: first day of the week */
			return $full ? sprintf( __( 'week of %s', 'bobr-analytics' ), self::day_label( $d, true ) ) : self::day_label( $d );
		}
		return self::day_label( $d, $full );
	}

	public static function allowed() {
		$box = array( 'class' => true, 'style' => true, 'title' => true );
		return array(
			'svg'      => array( 'class' => true, 'viewbox' => true, 'role' => true ),
			'line'     => array( 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'class' => true ),
			'text'     => array( 'x' => true, 'y' => true, 'class' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linejoin' => true, 'stroke-linecap' => true, 'stroke-dasharray' => true, 'opacity' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'opacity' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-dasharray' => true, 'stroke-dashoffset' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true, 'opacity' => true ),
			'title'    => array(),
			'div'      => $box,
			'span'     => $box,
			'i'        => $box,
			'b'        => array(),
			'p'        => $box,
			'ul'       => array(),
			'li'       => array(),
			'table'    => array(),
			'thead'    => array(),
			'tbody'    => array(),
			'tr'       => array(),
			'th'       => array(),
			'td'       => $box,
		);
	}

	public static function weekdays() {
		global $wp_locale;
		$out = array();
		foreach ( array( 1, 2, 3, 4, 5, 6, 0 ) as $i ) {
			$out[] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $i ) );
		}
		return $out;
	}

	public static function months( $kind ) {
		if ( 'day' === $kind ) {
			return array( '', _x( 'Jan', 'month after a day number', 'bobr-analytics' ), _x( 'Feb', 'month after a day number', 'bobr-analytics' ), _x( 'Mar', 'month after a day number', 'bobr-analytics' ), _x( 'Apr', 'month after a day number', 'bobr-analytics' ), _x( 'May', 'month after a day number', 'bobr-analytics' ), _x( 'Jun', 'month after a day number', 'bobr-analytics' ), _x( 'Jul', 'month after a day number', 'bobr-analytics' ), _x( 'Aug', 'month after a day number', 'bobr-analytics' ), _x( 'Sep', 'month after a day number', 'bobr-analytics' ), _x( 'Oct', 'month after a day number', 'bobr-analytics' ), _x( 'Nov', 'month after a day number', 'bobr-analytics' ), _x( 'Dec', 'month after a day number', 'bobr-analytics' ) );
		}
		return array( '', _x( 'Jan', 'month with a year', 'bobr-analytics' ), _x( 'Feb', 'month with a year', 'bobr-analytics' ), _x( 'Mar', 'month with a year', 'bobr-analytics' ), _x( 'Apr', 'month with a year', 'bobr-analytics' ), _x( 'May', 'month with a year', 'bobr-analytics' ), _x( 'Jun', 'month with a year', 'bobr-analytics' ), _x( 'Jul', 'month with a year', 'bobr-analytics' ), _x( 'Aug', 'month with a year', 'bobr-analytics' ), _x( 'Sep', 'month with a year', 'bobr-analytics' ), _x( 'Oct', 'month with a year', 'bobr-analytics' ), _x( 'Nov', 'month with a year', 'bobr-analytics' ), _x( 'Dec', 'month with a year', 'bobr-analytics' ) );
	}
}