<?php

defined( 'ABSPATH' ) || exit;

class Bobr_Mmdb {
	private $fh;
	private $meta;
	private $node_count;
	private $record_size;
	private $node_bytes;
	private $tree_size;
	private $ipv4_start = null;

	public function __construct( $file ) {
		$this->fh = @fopen( $file, 'rb' );
		if ( ! $this->fh ) {
			throw new Exception( 'mmdb: файл не открывается' );
		}
		$size  = filesize( $file );
		$chunk = min( $size, 131072 );
		fseek( $this->fh, $size - $chunk );
		$tail = fread( $this->fh, $chunk );
		$pos  = strrpos( $tail, "\xAB\xCD\xEFMaxMind.com" );
		if ( false === $pos ) {
			throw new Exception( 'mmdb: нет метаданных' );
		}
		$meta_start = $size - $chunk + $pos + 14;
		list( $this->meta ) = $this->decode( $meta_start, $meta_start );
		$this->node_count  = (int) $this->meta['node_count'];
		$this->record_size = (int) $this->meta['record_size'];
		$this->node_bytes  = $this->record_size / 4;
		$this->tree_size   = $this->node_count * $this->node_bytes;
	}

	public function __destruct() {
		if ( $this->fh ) {
			fclose( $this->fh );
		}
	}

	public function meta() {
		return $this->meta;
	}

	public function get( $ip ) {
		$bin = @inet_pton( $ip );
		if ( false === $bin ) {
			return null;
		}
		$bits = strlen( $bin ) * 8;
		$node = 0;
		if ( 4 === strlen( $bin ) && 6 === (int) $this->meta['ip_version'] ) {
			$node = $this->ipv4_start();
		}
		for ( $i = 0; $i < $bits && $node < $this->node_count; $i++ ) {
			$bit  = 1 & ( ord( $bin[ $i >> 3 ] ) >> ( 7 - ( $i % 8 ) ) );
			$node = $this->record( $node, $bit );
		}
		if ( $node === $this->node_count || $node < $this->node_count ) {
			return null;
		}
		$offset = $node - $this->node_count + $this->tree_size;
		list( $val ) = $this->decode( $offset, $this->tree_size + 16 );
		return $val;
	}

	private function ipv4_start() {
		if ( null === $this->ipv4_start ) {
			$node = 0;
			for ( $i = 0; $i < 96 && $node < $this->node_count; $i++ ) {
				$node = $this->record( $node, 0 );
			}
			$this->ipv4_start = $node;
		}
		return $this->ipv4_start;
	}

	private function read( $offset, $len ) {
		if ( $len <= 0 ) {
			return '';
		}
		fseek( $this->fh, $offset );
		$s = fread( $this->fh, $len );
		return false === $s ? '' : $s;
	}

	private function record( $node, $bit ) {
		$b = $this->read( $node * $this->node_bytes, $this->node_bytes );
		switch ( $this->record_size ) {
			case 24:
				$r = substr( $b, $bit * 3, 3 );
				return unpack( 'N', "\x00" . $r )[1];
			case 28:
				if ( 0 === $bit ) {
					$mid = ( ord( $b[3] ) & 0xF0 ) >> 4;
					return unpack( 'N', chr( $mid ) . substr( $b, 0, 3 ) )[1];
				}
				$mid = ord( $b[3] ) & 0x0F;
				return unpack( 'N', chr( $mid ) . substr( $b, 4, 3 ) )[1];
			case 32:
				return unpack( 'N', substr( $b, $bit * 4, 4 ) )[1];
		}
		throw new Exception( 'mmdb: неизвестный размер записи' );
	}

	private function decode( $offset, $base ) {
		$ctrl = ord( $this->read( $offset, 1 ) );
		$offset++;
		$type = $ctrl >> 5;
		if ( 1 === $type ) {
			$ss  = ( $ctrl >> 3 ) & 3;
			$vvv = $ctrl & 7;
			$b   = $this->read( $offset, $ss + 1 );
			switch ( $ss ) {
				case 0:
					$p = ( $vvv << 8 ) | ord( $b[0] );
					break;
				case 1:
					$p = ( ( $vvv << 16 ) | unpack( 'n', $b )[1] ) + 2048;
					break;
				case 2:
					$p = ( ( $vvv << 24 ) | unpack( 'N', "\x00" . $b )[1] ) + 526336;
					break;
				default:
					$p = unpack( 'N', $b )[1];
			}
			list( $val ) = $this->decode( $base + $p, $base );
			return array( $val, $offset + $ss + 1 );
		}
		if ( 0 === $type ) {
			$type = 7 + ord( $this->read( $offset, 1 ) );
			$offset++;
		}
		$size = $ctrl & 0x1F;
		if ( $size >= 29 ) {
			$n = $size - 28;
			$b = $this->read( $offset, $n );
			$offset += $n;
			if ( 29 === $size ) {
				$size = 29 + ord( $b[0] );
			} elseif ( 30 === $size ) {
				$size = 285 + unpack( 'n', $b )[1];
			} else {
				$size = 65821 + unpack( 'N', "\x00" . $b )[1];
			}
		}
		switch ( $type ) {
			case 2:
				return array( $this->read( $offset, $size ), $offset + $size );
			case 3:
				return array( unpack( 'E', $this->read( $offset, 8 ) )[1], $offset + 8 );
			case 4:
				return array( $this->read( $offset, $size ), $offset + $size );
			case 5:
			case 6:
			case 9:
			case 10:
			case 8:
				$v = 0;
				$b = $this->read( $offset, $size );
				for ( $i = 0; $i < $size; $i++ ) {
					$v = ( $v * 256 ) + ord( $b[ $i ] );
				}
				if ( 8 === $type && 4 === $size && $v >= 2147483648 ) {
					$v -= 4294967296;
				}
				return array( $v, $offset + $size );
			case 7:
				$m = array();
				for ( $i = 0; $i < $size; $i++ ) {
					list( $k, $offset ) = $this->decode( $offset, $base );
					list( $v, $offset ) = $this->decode( $offset, $base );
					$m[ $k ] = $v;
				}
				return array( $m, $offset );
			case 11:
				$a = array();
				for ( $i = 0; $i < $size; $i++ ) {
					list( $v, $offset ) = $this->decode( $offset, $base );
					$a[] = $v;
				}
				return array( $a, $offset );
			case 14:
				return array( 0 !== $size, $offset );
			case 15:
				return array( unpack( 'G', $this->read( $offset, 4 ) )[1], $offset + 4 );
		}
		return array( null, $offset + $size );
	}
}
