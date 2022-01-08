<?php
declare(strict_types=1);

namespace Http\Client;

use Exception;

/**
 * Class HttpParse
 * @package BeReborn\Http
 */
class HttpParse
{
	/**
	 * @param mixed ...$object
	 * @return string
	 */
	private static function getKey(...$object): string
	{
		$first = '';
		$tp = [];
		foreach ($object as $key => $value) {
			if ($value === null) {
				continue;
			}
			if (is_array($value)) {
				$value = key($value);
			}
			if ($first === '') {
				$first = $value;
			} else {
				$tp[] = $value;
			}
		}
		$key = $first . '[' . implode('][', $tp) . ']';
		if (count($tp) < 1) {
			$key = $first;
		}
		return $key;
	}

	/**
	 * @param $data
	 * @return string
	 * @throws Exception
	 */
	public static function parse($data): string
	{
		$tmp = [];
		if (is_string($data)) {
			return $data;
		}
		foreach ($data as $key => $datum) {
			if ($datum === null) {
				continue;
			}
			$tmp[] = static::ifElse($key, $datum);
		}
		return implode('&', $tmp);
	}

	/**
	 * @param $t
	 * @param $qt
	 * @return string
	 * @throws Exception
	 */
	private static function ifElse($t, $qt): string
	{
		if (is_numeric($qt)) {
			return $t . '=' . $qt;
		}
		if (is_string($qt)) {
			$string = $t . '=' . urlencode($qt);
		} else {
			$string = static::encode($t, $qt);
		}
		return $string;
	}

	/**
	 * @param mixed ...$object
	 * @return string
	 * @throws Exception
	 */
	private static function encode(...$object): string
	{
		$ret = [];

		$data = $object[count($object) - 1];
		$key = static::getKey(...$object);
		foreach ($data as $s => $datum) {
			if (is_array($datum)) {
				$object[count($object) - 1] = $s;
				$object[] = $datum;
				$string = static::encode(...$object);
			} else {
				if (is_object($datum)) {
					throw new Exception('Http body con\'t object.');
				}
				$string = $key . '=' . urlencode($datum);
			}
			$ret[] = $string;
		}
		return implode('&', $ret);
	}


}
