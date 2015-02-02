<?php
/**
 * @file
 *   parseINFO.php
 *
 * @author
 *   Drupal, IjorTengab
 *
 * @homepage
 *   https://drupal.org
 *   https://github.com/ijortengab/classes
 */
class parseINFO {

  // Hasil dari parsing.
  public $result;

  // Fungsi awal langsung bersifat toggle.
	// Jika input diberikan merupakan string, artinya
	// ubah dari string ke array
	// sebaliknya jika input diberikan merupakan array atau object
	// maka ubah menjadi string.
	function __construct($input = NULL) {
		if (is_array($input) || is_object($input)) {
			return $this->result = $this->encode($input);
		}
		elseif (is_string($input)) {
			return $this->result = $this->decode($input);
		}
	}

  // Fungsi mengubah dari array atau object menjadi string.
	public function encode($array_or_object) {
		if(is_array($array_or_object) || is_object($array_or_object)) {
			$result = array();
			$this->_encode_recursive($result, $array_or_object);
			$result = implode("\r\n", $result);
			return $result;
		}
	}

  // Fungsi internal, dibutuhkan oleh method encode untuk
	// melakukan proses secara recursive terkait struktur array.
	protected function _encode_recursive(&$result, $value, &$key = null) {
		if(is_array($value) || is_object($value)) {
      // Jadikan array, jikapun sebuah object.
      $value = (array) $value;
			foreach($value as $_key => $_value) {
				if(is_array($_value) || is_object($_value)) {
					// Jadikan array, jikapun sebuah object.
          $_value = (array) $_value;
					foreach($_value as $__key => $__value) {
						$buildkey = $_key . '[' . $__key . ']';
						if (!is_null($key)) {
							$buildkey = $key . '[' . $_key . '][' . $__key . ']';
						}
						$this->_encode_recursive($result, $__value, $buildkey);
					}
				}
				else{
					$buildkey = $_key;
					if (!is_null($key)) {
						$buildkey = $key . '[' . $_key . ']';
					}
					$result[] = $buildkey . ' = ' . $this->_encode_modify($_value);
				}
			}
		}
		else{
			$result[] = $key . ' = ' . $this->_encode_modify($value);
		}
	}

  // Fungsi internal, dibutuhkan oleh method _encode_recursive untuk
  // memodifikasi value.
	protected function _encode_modify($value) {
		if (is_string($value) && preg_match('/["\'\r\n\t]/',$value)) {
			$value = json_encode($value);
		}
		elseif (is_string($value) && empty($value)) {
			// Perlu diberi single quote, jika tidak nanti akan
			// ada error saat di decode oleh regex drupal.
			$value = "''";
		}
		elseif (is_bool($value)) {
			$value = $value ? 'TRUE' : 'FALSE';
		}
		elseif (is_null($value)) {
			$value = 'NULL';
		}
		return $value;
	}

  // Fungsi bersumber dari fungsi drupal 7 yakni
	// drupal_parse_info_format($data) dengan sedikit modifikasi.
	public function decode($data) {
		$info = array();
		if (preg_match_all('
			@^\s*                           # Start at the beginning of a line, ignoring leading whitespace
			((?:
				[^=;\[\]]|                    # Key names cannot contain equal signs, semi-colons or square brackets,
				\[[^\[\]]*\]                  # unless they are balanced and not nested
			)+?)
			\s*=\s*                         # Key/value pairs are separated by equal signs (ignoring white-space)
			(?:
				("(?:[^"]|(?<=\\\\)")*")|     # Double-quoted string, which may contain slash-escaped quotes/slashes
				(\'(?:[^\']|(?<=\\\\)\')*\')| # Single-quoted string, which may contain slash-escaped quotes/slashes
				([^\r\n]*?)                   # Non-quoted string
			)\s*$                           # Stop at the next end of a line, ignoring trailing whitespace
			@msx', $data, $matches, PREG_SET_ORDER)) {
			// dpm($matches,'$matches');
			foreach ($matches as $match) {
				// Fetch the key and value string.
				$i = 0;
				foreach (array('key', 'value1', 'value2', 'value3') as $var) {
					$$var = isset($match[++$i]) ? $match[$i] : '';
				}
				// Tambahan dari Roji start
				foreach (array('value1', 'value2', 'value3') as $var) {
					$$var = $this->_decode_modify_before($$var);
					// Jika array, maka sudahi.
					if (is_array($$var)) {
						$parent = &$info;
						$parent[$key] = $$var;
						continue 2;
					}
				}
				// Tambahan dari Roji finish.
				$value = stripslashes(substr($value1, 1, -1)) . stripslashes(substr($value2, 1, -1)) . $value3;

				// Parse array syntax.
				$keys = preg_split('/\]?\[/', rtrim($key, ']'));
				$last = array_pop($keys);
				$parent = &$info;

				// Create nested arrays.
				foreach ($keys as $key) {
					if ($key == '') {
						$key = count($parent);
					}
					if (!isset($parent[$key]) || !is_array($parent[$key])) {
						$parent[$key] = array();
					}
					$parent = &$parent[$key];
				}

				// Insert actual value.
				if ($last == '') {
					$last = count($parent);
				}
				// Update dari Roji start
				$parent[$last] = $this->_decode_modify_after($value);
				// Update dari Roji finish
			}
		}
		return $info;

	}
	// Memodifikasi value.
	protected function _decode_modify_before($value) {
		// Jika huruf awal dari value adalah double quote, maka pasti value tersebut
		// hasil json encode, maka perlu kita kembalikan ke format awal
		if (substr($value, 0, 1) == '"') {
			$value = json_decode($value);
			// namun perlu diberi tanda double quote lagi, karena regex decode mengenalinya sebagai
			// match dari hasil double quote, karena oleh code drupal akan di hilangkan double
			// qoutenya dengan cara substr(lihat pada fungsi decode)
			$value = '"' . $value . '"';
		}
		if (substr($value, 0, 1) == '{' || substr($value, 0, 1) == '[') {
			$value = json_decode($value, TRUE);
		}
		return $value;
	}

  protected function _decode_modify_after($value) {
		if ($value === 'TRUE') {
			$value = (bool) TRUE;
		}
		elseif ($value === 'FALSE') {
			$value = (bool) FALSE;
		}
		elseif ($value === 'NULL') {
			$value = NULL;
		}
		return $value;
	}

}
