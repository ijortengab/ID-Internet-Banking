<?php
/**
 * @file
 *   parseHTML.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/parseHTML
 */
class parseHTML {

  /**
   * String or NULL.
   * Data mentah keseluruhan dokumen html. Menjadi rujukan utama untuk
   * pencarian dan lain-lain. Jika tercipta object baru hasil eksekusi
   * method find(), maka property $raw dari object baru tersebut akan
   * sama dengan property $raw dari object ini.
   */
  private $raw;

  /**
   * Berisi array satu dimensi, dimana keys merupakan "position", yakni
   * jarak element dari awal string $raw (idem dengan strpos) dan value
   * merupakan "element". Element harus lengkap terdiri dari start tag,
   * content, dan end tag - kecuali element yang tidak memerlukan end tag.
   * Untuk referensi element tidak perlu end tag, dapat dilihat pada
   * method validate_tag_void_element().
   *
   * Untuk mengambil info properti $element ini, gunakan method getElements().
   * Jika $element merupakan empty array, maka itu berarti $raw digunakan
   * sebagai element dan nilai position-nya adalah 0.
   */
  private $elements = array();

  function __construct($raw = NULL, $elements = NULL) {
    if (isset($raw)) {
      $this->raw = $raw;
    }
    if (isset($elements)) {
      $this->elements = $elements;
    }
  }

  public function getRaw() {
    return $this->raw;
  }

  /**
   * Mendapatkan nilai property $elements.
   */
  public function getElements() {
    if (empty($this->elements)) {
      return array('0' => $this->raw);
    }
    return $this->elements;
  }

  public function find($selector) {
    // Raw = NULL terjadi jika find tidak menemukan element.
    if (is_null($this->raw)) {
      return new parseHTML;
    }
    $info_elements = $this->translate_selector($selector);
    // Bila selector tidak valid, maka return object kosong.
    if (empty($info_elements)) {
      return new parseHTML;
    }

    $result = $this;
    while ($info_element = array_shift($info_elements)) {
      $result = call_user_func_array(array($result, 'find_element'), array($info_element));
    }
    return $result;
  }

  public function html() {
    // Kita hanya toleransi pada element yang pertama.
    $elements = $this->getElements();
    if (isset($elements[0])) {
      // Tag Doctype itu tidak dianggap.
      $elements[0] = preg_replace('/^\s*\<\!DOCTYPE[^>]*\>/i', '', $elements[0]);
    }
    $element = array_shift($elements);
    return $element;
  }

  public function text() {
    return strip_tags($this->html());
  }

  public function attr($name) {
    $element = $this->html();
    $mask = '/^\<\w+\s*[^>]*\>/i';
    if (preg_match($mask, $element, $mathces)) {
      $start_tag = array_shift($mathces);
      $attributes = $this->extractAttributes($start_tag, TRUE);
      return isset($attributes[$name]) ? $attributes[$name] : NULL;
    }
  }

  public function prev($selector = NULL) {
    // Todo.
  }
  
  public function next($selector = NULL) {
    // Todo.
  }
  
  public function parent($selector = NULL) {
    // Todo.
  }
  
  public function parents($selector = NULL) {
    // Todo.
  }
  
  public function children($selector = NULL) {
    // Todo.
  }
  
  public function contents() {
    // Todo.
  }
  
  /**
   * Mencari element dengan attribute sebagai pencariannya.
   * Mengembalikan array dengan key merupakan posisi pointer
   * dan value merupakan start_tag.
   *
   * @param $attribute
   *   String attribute yang mau dicari, incasesensitive.
   *   Contoh: 'class', 'id',
   * @param $html
   *   String dengan format html.
   *
   * Return
   *   Contoh array yang dihasilkan dengan attribute yang dicari
   *   adalah class:
   *   array(
   *     '5' => '<body class="a">',
   *     '25' => '<img class="b">',
   *     '83' => '<div class="c">',
   *   );
   */
  public function getElementByAttribute($attribute, $html, $callback = NULL, $param_arr = NULL) {
    // Set storage.
    $storage = array();
    // Validate.
    if (!$this->validate_attribute_element($attribute) || strlen($html) === 0) {
      return $storage;
    }
    // Find string.
    $find = $attribute . '=';
    $length = strlen($find);
    $scoupe = $html;
    $offset = 0;
    while ($distance = stripos($scoupe, $find)) {
      $position = $distance + $offset;
      // Sebelum disimpan ke storage, maka validasi beberapa hal.
      // Karakter sebelumnya harus whitespace.
      $pch = (isset($html[$position - 1])) ? $html[$position - 1] : false;
      if ($pch && ctype_space($pch)) {
        // var_dump($position);
        // Cek apakah posisi pointer dari ditemukannya attribute itu
        // berada diantara start tag html.
        if ($this->validate_inside_start_tag($position, $html)) {
          // var_dump($position);
          // Cek apakah karakter pertama setelah
          // karakter < pada element adalah string valid.
          $prefix = substr($html, 0, $position);
          $suffix = substr($html, $position);
          $starttag_lt_position = strrpos($prefix, '<');
          $starttag_rt_position = $position + strpos($suffix, '>') + strlen('>');
          $start_tag = substr($html, $starttag_lt_position, $starttag_rt_position - $starttag_lt_position);
          if ($this->validate_start_tag($start_tag)) {
            // Validasi selesai, maka masukkan data ke $storage.
            // Tapi tunggu dulu, karena method ini juga dapat digunakan
            // oleh method lain, maka cek dulu apakah ada custom filter.
            if (is_callable($callback)) {
              // Insert our arguments.
              $args = $param_arr;
              is_array($args) or $args = array();
              $args[] = $start_tag;
              // Check.
              if ($action = call_user_func_array($callback, $args)) {
                // True then insert.
                $storage[$starttag_lt_position] = $start_tag;
                // What do you want after TRUE.
                if (isset($action['break']) && $action['break']) {
                  break;
                }
              }
            }
            // Gak ada custom filter bro, jadi masukin aja dah..
            else {
              $storage[$starttag_lt_position] = $start_tag;
            }
          }
        }
      }
      // Ubah offset dan scope.
      $offset += $distance + $length;
      $scoupe = substr($html, $offset);
    }
    return $storage;
  }

  /**
   * Sama sepert method getElementByAttribute() namun dengan
   * fitur filter seperti query sql.
   *
   * @param $conditions string
   *   Syntax seperti sql, berupa kalimat logika untuk pencarian.
   *   Contoh:
   *    - $conditions = 'title equals Mari Kemari';
   *      Berarti mencari element dengan title sama dengan 'Mari Kemari'.
   *    - $conditions = 'title equals "Mari Kemari"';
   *      Berarti mencari element dengan title sama dengan 'Mari Kemari'.
   *    - $conditions = "title equals 'Mari Kemari'";
   *      Berarti mencari element dengan title sama dengan 'Mari Kemari'.
   *    - $conditions = "class contains 'first'";
   *      Berarti mencari element dengan attribute class mengandung
   *      kata 'first'.
   *    - $conditions = "id = 'form' OR method = GET";
   *      Berarti mencari element dengan attribute id sama dengan 'form' ATAU
   *      juga memiliki attribute method dengan nilai sama dengan 'GET'.
   *    - $conditions = "data-length > 500 AND data-length < 2000";
   *      Berarti mencari element dengan attribute data-length lebih dari 500
   *      DAN kurang dari 2000.
   *
   *   Operator yang tersedia:
   *    - 'AND', 'OR',
   *    - '=', 'equals', 'is',
   *    - '!=', 'is not',
   *    - '<', 'is less than',
   *    - '>', 'is greater than',
   *    - '<=', 'is less than or equals',
   *    - '>=', 'is greater than or equals',
   *    - 'contains',
   *    - 'does not contain',
   *
   * @param $html
   *   String dengan format html.
   *
   * Return
   *   Contoh array yang dihasilkan dengan condiitons yang dicari
   *   adalah 'class contains a OR class contains x':
   *
   *   array(
   *     '5' => '<body class="a b">',
   *     '25' => '<img class="x y">',
   *     '83' => '<div class="a x">',
   *   );
   */
  public function getElementByAttributes($conditions, $html) {
    // var_dump('WELCOME IN getElementByAttributes()');
    // echo 'var_dump($conditions): '; var_dump($conditions);
    // echo 'var_dump($html): '; var_dump($html);

    $elements = array();
    // Validate.
    $conditions = trim($conditions);
    if (empty($html) || empty($conditions)) {
      return $elements;
    }
    $attributes = $this->_get_attributes_parse_conditions($conditions);
    // echo 'var_dump($attributes): '; var_dump($attributes);
    foreach($attributes as $attribute) {
      $elements += $this->getElementByAttribute($attribute, $html);
    }
    // echo 'var_dump($elements): '; var_dump($elements);

    // Filtering.
    foreach($elements as $position => $element) {
      $attributes = $this->extractAttributes($element);
      if (!$this->_validate_attribute_conditions($attributes, $conditions)) {
        unset($elements[$position]);
      }
    }
    // echo 'var_dump($elements): '; var_dump($elements);
    // var_dump('GOODBYE FROM getElementByAttributes()');
    // echo "\r\n";
    // echo "\r\n";
    // echo "\r\n";
    return $elements;
  }

  /**
   * Jika pencarian hanya berdasarkan id, maka lebih baik gunakan
   * method ini dibandingkan harus mencari menggunakan method
   * getElementByAttributes() karena method ini telah reduce berbagai
   * looping.
   * Ilustrasi: daripada mencari element dengan cara seperti ini:
   *
   *   $this->getElementByAttributes('id = somevalue', $html)
   *
   * sebaiknya gunakan cara ini:
   *
   *   $this->getElementById('somevalue', $html);
   *
   *
   * @param $value string
   *   Value dari attribute id yang akan dicari
   * @param $html
   *   String dengan format html.
   *
   * Return
   *   Jika terdapat banyak element dengan value dari attribute id yang sama
   *   nilainya, maka kita hanya akan mengambil element yang pertama.
   *   Contoh:
   *
   *   array(
   *     '65' => '<div id="somevalue" class="a">',
   *   );
   *
   */
  public function getElementById($value, $html) {
    $callback = 'self::_getElementById';
    $param_arr = array($value);
    return $this->getElementByAttribute('id', $html, $callback, $param_arr);
  }

  /**
   * Jika pencarian hanya berdasarkan class, maka lebih baik gunakan
   * method ini dibandingkan harus mencari menggunakan method
   * getElementByAttributes() karena method ini telah reduce berbagai
   * looping.
   * Ilustrasi: daripada mencari element dengan cara seperti ini:
   *
   *   $this->getElementByAttributes('class ~= somevalue', $html)
   *
   * sebaiknya gunakan cara ini:
   *
   *   $this->getElementByClass('somevalue', $html);
   *
   *
   * @param $value string
   *   Value dari attribute id yang akan dicari
   * @param $html
   *   String dengan format html.
   *
   * Return
   *   Jika terdapat banyak element dengan value dari attribute id yang sama
   *   nilainya, maka kita hanya akan mengambil element yang pertama.
   *   Contoh:
   *
   *   array(
   *     '65' => '<div class="somevalue anothervalue">',
   *   );
   *
   */
  public function getElementByClass($value, $html) {
    $callback = 'self::_getElementByClass';
    $param_arr = array($value);
    return $this->getElementByAttribute('class', $html, $callback, $param_arr);
  }

  public function getElementByTag($tag, $html, $callback = NULL, $param_arr = NULL) {
    $tag = trim($tag);
    // Set storage.
    $storage = array();
    // Validate.
    // if ($this->validate_tag_element($tag) === FALSE) {
      // return $storage;
    // }
    // Find string.
    $find = $tag;
    $length = strlen($find);
    $scoupe = $html;
    $offset = 0;
    $distance = stripos($scoupe, $find);
    while ($distance = stripos($scoupe, $find)) {
      $position = $distance + $offset;
      // Sebelum disimpan ke storage, maka validasi beberapa hal.
      // Karakter sebelumnya harus < dan karakter sesudahnya harus
      // whitespace atau >.
      $nch = (isset($html[$position + $length])) ? $html[$position + $length] : false;
      $pch = (isset($html[$position - 1])) ? $html[$position - 1] : false;
      if ($nch && (ctype_space($nch) || $nch == '>') && $pch && $pch == '<') {
        // Cek apakah posisi pointer dari ditemukannya attribute itu
        // berada diantara start tag html.
        if ($this->validate_inside_start_tag($position, $html)) {
          $prefix = substr($html, 0, $position);
          $suffix = substr($html, $position);
          $starttag_lt_position = strrpos($prefix, '<');
          $starttag_rt_position = $position + strpos($suffix, '>') + strlen('>');
          $start_tag = substr($html, $starttag_lt_position, $starttag_rt_position - $starttag_lt_position);
          // Validasi selesai, maka masukkan data ke $storage.
          // Tapi tunggu dulu, karena method ini juga dapat digunakan
          // oleh method lain, maka cek dulu apakah ada custom filter.
          if (is_callable($callback)) {
            // Insert our arguments.
            $args = $param_arr;
            is_array($args) or $args = array();
            $args[] = $start_tag;
            // Check.
            if ($action = call_user_func_array($callback, $args)) {
              // True then insert.
              $storage[$starttag_lt_position] = $start_tag;
              // What do you want after TRUE.
              if (isset($action['break']) && $action['break']) {
                break;
              }
            }
          }
          // Gak ada custom filter bro, jadi masukin aja dah..
          else {
            $storage[$starttag_lt_position] = $start_tag;
          }
        }
      }
      // Ubah offset dan scope.
      $offset += $distance + $length;
      $scoupe = substr($html, $offset);
    }
    return $storage;
  }

  public static function translate_selector($selector) {
    $string = trim($selector);
    $string_length = strlen($string);
    $meta_characters = '!"#$%&\'()*+,./:;<=>?@[\\]^`{|}~';
    $last = substr($string, -1, 1);

    // 1st Validation.
    // Krakter terakhir tidak boleh meta karakter kecuali karakter ].
    if ($last != ']' && strpos($meta_characters, $last) !== FALSE) {
      return FALSE;
    }

    // Categorize charachter by type to easy us.
    $characters = array();
    for ($x = 0; $x < $string_length; $x++) {
      $char = $string[$x];
      $type = 'std';
      if ($char == '\\' && isset($string[$x + 1]) && strpos($meta_characters, $string[$x + 1]) !== FALSE) {
        $char = $string[++$x];
      }
      elseif (strpos($meta_characters, $char) !== FALSE) {
        $type = 'meta';
      }
      elseif (ctype_space($char) !== FALSE) {
        $type = 'space';
      }
      $characters[] = array(
        $type => $char,
      );
    }

    // Build flag.
    $step = 'init';
    $attribute_name = '';
    $attribute_operator = '';
    $attribute_value = '';
    $quote = '';
    $tag = '';
    $register_elements = FALSE;
    $register_element = FALSE;
    $is_last = FALSE;
    $elements = array();
    $element = $_element = array('tag' => array(), 'attributes' => array());
    $x = 0;
    $string_length = count($characters);

    // Walking.
    while ($character = array_shift($characters)) {
      ($x != $string_length - 1) or $is_last = TRUE;
      switch ($step) {
        case 'init':
          if (isset($character['std'])) {
            $tag .= $character['std'];
            $step = 'build tag';
            if ($is_last) {
              $register_element = TRUE;
              $register_elements = TRUE;
            }
          }
          elseif (isset($character['meta'])) {
            switch ($character['meta']) {
              case '#':
                $attribute_name = 'id';
                $attribute_operator = '=';
                $step = 'build value';
                break;

              case '.':
                $attribute_name = 'class';
                $attribute_operator = '~=';
                $step = 'build value';
                break;

              case '[':
                $step = 'brackets build name';
                break;
            }
          }
          break;

        case 'brackets build name':
          if (isset($character['std'])) {
            $attribute_name .= $character['std'];
          }
          elseif (isset($character['meta'])) {
            switch ($character['meta']) {
              case ']':
                $register_element = TRUE;
                if ($is_last) {
                  $register_elements = TRUE;
                }
                break;

              default:
                $attribute_operator = $character['meta'];
                $step = 'brackets build operator';
            }
          }
          break;

        case 'brackets build operator':
          if (isset($character['std'])) {
            $attribute_value .= $character['std'];
            $step = 'brackets build value';
          }
          elseif (isset($character['meta'])) {
            switch ($character['meta']) {
              case '"':
              case "'":
                $quote = $character['meta'];
                $step = 'brackets build value';
                break;

              case ']':
                $register_element = TRUE;
                if ($is_last) {
                  $register_elements = TRUE;
                }
                break;

              default:
                $attribute_operator .= $character['meta'];
            }
          }
          break;

        case 'brackets build value':
          if (isset($character['std'])) {
            $attribute_value .= $character['std'];
          }
          elseif (isset($character['meta']) && in_array($character['meta'], array('"', "'")) && $character['meta'] != $quote) {
            $attribute_value .= $character['meta'];
          }
          elseif (isset($character['meta']) && $character['meta'] == ']') {
            $register_element = TRUE;
            if ($is_last) {
              $register_elements = TRUE;
            }
          }
          break;

        case 'build value':
          if (isset($character['std'])) {
            $attribute_value .= $character['std'];
            if ($is_last) {
              $register_element = TRUE;
              $register_elements = TRUE;
            }
          }
          elseif (isset($character['space'])) {
              $register_element = TRUE;
              $register_elements = TRUE;
          }
          elseif (isset($character['meta'])) {
            // Khusus class, maka ada perlakuan khusus.
            if ($character['meta'] == '.' && $attribute_name == 'class') {
              $attribute_value .= ' ';
              $attribute_operator = '~~=';
            }
            else {
              $register_element = TRUE;
            }
          }
          break;

        case 'build tag':
          if (isset($character['std'])) {
            $tag .= $character['std'];
            if ($is_last) {
              $register_element = TRUE;
              $register_elements = TRUE;
            }
          }
          elseif (isset($character['space'])) {
            $register_element = TRUE;
            $register_elements = TRUE;
          }
          elseif (isset($character['meta'])) {
            switch ($character['meta']) {
              case '#':
                $attribute_name = 'id';
                $attribute_operator = '=';
                $step = 'build value';
                break;

              case '.':
                $attribute_name = 'class';
                $attribute_operator = '~=';
                $step = 'build value';
                break;

              case '[':
                $step = 'brackets build name';
                break;
            }
          }
          break;
      }
      if ($register_element) {
        empty($tag) or $element['tag'][] = $tag;
        if ((empty($attribute_name) && empty($attribute_operator) && empty($attribute_value)) == FALSE) {
          $element['attributes'][] = array(
            'name' => $attribute_name,
            'operator' => $attribute_operator,
            'value' => $attribute_value,
          );
        }
        $register_element = FALSE;
        $attribute_name = '';
        $attribute_operator = '';
        $attribute_value = '';
        $quote = '';
        $tag = '';
        if (isset($character['meta'])) {
          switch ($character['meta']) {
            case '#':
              $attribute_name = 'id';
              $attribute_operator = '=';
              $step = 'build value';
              break;

            case '.':
              $attribute_name = 'class';
              $attribute_operator = '~=';
              $step = 'build value';
              break;

            case ']':
              $step = 'init';
              break;

            case '[':
              $step = 'brackets build name';
              break;
          }
        }
      }
      if ($register_elements) {
        // 2nd Validation.
        // $('div[tempe~=bacem]div'); Selector valid oleh jquery.
        // $('div[tempe~=bacem]a'); Selector tidak error regex tapi hasilnya NULL.
        // Sehingga jika terdapat lebih dari satu tag, maka kita anggap selector
        // tidak valid.
        empty($element['tag']) or $element['tag'] = array_unique($element['tag']);
        if (is_array($element['tag']) && count($element['tag']) > 1) {
          return FALSE;
        }
        $elements[] = $element;
        $register_elements = FALSE;
        $element = $_element;
        $step = 'init';
      }
      $x++;
    }
    // 3rd Validation. Todo.
    // From: http://www.w3.org/TR/CSS21/syndata.html#value-def-identifier
    //  > they cannot start with a digit, two hyphens, or a hyphen
    //  > followed by a digit.
    // if (isset($string[$x + 1]) && preg_match('/(?:^\d|\-{2}|-\d)/', $char . $string[$x + 1])) {
      // $error = 2;
      // break 2;
    // }
    return $elements;
  }

  /**
   * Mengembalikan attribute dari start tag element
   * berupa array sederhana dimana key merupakan nama attribute
   * dan value merupakan nilai dari attribute. Nama attribute
   * yang di-return selalu lower case.
   *
   * Ketentuan dalam extract attribute yakni:
   *  1) nama attribute incase-sensitive
   *  2) Jika nama attribute lebih dari satu,
   *     maka yang akan dianggap adalah nama attribute yang pertama
   *
   *
   * @param $start_tag string
   *   Start tag, harus diawali dengan karakter < dan diakhir dengan >.
   *   Contoh:
   *
   *     <a title="mytitle" href="link">
   *
   * @param $validate bool
   *   If set TRUE, any invalid attribute name will be removed.
   *
   * Return
   * Mengembalikan associative array dengan key adalah nama attribute,
   * dan value adalah value attribute.
   * Contoh paling ekstrem, misalnya kita memiliki start tag sbb:
   *
   *   <a "mengapa" tempe  'agama'="" id="roji" 965="cintakita"
   *   duhai= class="anto" dengan cinta="kita" cinta="bisa gila" yoyo=ok>
   *
   * Hasil yang akan didapat adalah sbb:
   *
   *   array(
   *     '"mengapa"' => '',
   *     'tempe' => '',
   *     "'agama'" => '',
   *     'id' => 'roji',
   *     '965' => 'cintakita',
   *     'duhai' => 'class="anto"',
   *     'dengan' => '',
   *     'cinta' => 'kita',
   *     'yoyo' => 'ok',
   *   );
   *
   * Hasil yang akan didapat jika dilakukan validasi adalah sbb:
   *
   *   array(
   *     'tempe' => '',
   *     'id' => 'roji',
   *     '965' => 'cintakita',
   *     'duhai' => 'class="anto"',
   *     'dengan' => '',
   *     'cinta' => 'kita',
   *     'yoyo' => 'ok',
   *   );
   *
   */
  public static function extractAttributes($start_tag, $validate = FALSE) {
    $attributes = array();
    // Validasi start_tag.
    $mask = '/^<\w+\s*(?P<attributes>[^>]*)>$/';
    if (preg_match($mask, $start_tag, $matches)) {
      $string = rtrim($matches['attributes']);
      $string_length = strlen($string);
      $string_last = $string_length - 1;
      $step = 'init';
      $name = '';
      $value = '';
      $quote = '';
      $register = FALSE;

      // Walking.
      for ($x = 0; $x < $string_length; $x++) {
        $char = $string[$x];
        switch ($step) {
          case 'init':
            $name .= $char;
            $step = 'build_name';
            break;

          case 'build_name':
            if ($char == '=') {
              $step = 'check_quote';
            }
            elseif (ctype_space($char)) {
              $value = '';
              $register = TRUE;
            }
            else {
              $name .= $char;
            }
            break;

          case 'check_quote':
            if ($char == '"' || $char == "'") {
              $step = 'build_value';
              $quote = $char;
            }
            elseif (ctype_space($char)) {
              break;
            }
            else {
              $value = $char;
              $quote = '';
              $step = 'build_value';
            }
            break;

          case 'build_value':
            if (empty($quote) && ctype_space($char)) {
              $register = TRUE;
            }
            elseif (empty($quote) && $x === $string_last) {
              $value .= $char;
              $register = TRUE;
            }
            elseif ($char == $quote) {
              $register = TRUE;
            }
            else {
              $value .= $char;
            }
            break;
        }
        if ($register) {
          empty($name) or $name = strtolower($name);
          if (!empty($name) && !isset($attributes[$name])) {
            $attributes[$name] = $value;
          }
          $register = FALSE;
          $name = '';
          $value = '';
          $quote = '';
          $step = 'build_name';
        }
      }
    }
    if (!empty($attributes) && $validate) {
      $validates = self::validate_attribute_element(array_keys($attributes));
      foreach($validates as $validate) {
        list($key, $result) = $validate;
        if (!$result) {
          unset($attributes[$key]);
        }
      }
    }
    return $attributes;
  }

  /**
   * Validation of tag of void element.
   *
   *  > Void elements only have a start tag; end tags must not be specified
   *  > for void elements.
   *  >
   *  Reference: http://www.w3.org/TR/html-markup/syntax.html#void-element
   *
   */
  public static function validate_tag_void_element($tag) {
    $tags = '
      |area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|
      |source|track|wbr|
    ';
    return stristr($tags, '|' . $tag .'|') !== FALSE;
  }

  /**
   * Validation of tag of element.
   *
   *  > Tags are used to delimit the start and end of elements in markup.
   *  > Elements have a start tag to indicate where they begin.
   *  > Non-void elements have an end tag to indicate where they end.
   *  >
   *  > Tag names are used within element start tags and end tags to give
   *  > the element’s name. HTML elements all have names that only use
   *  > characters in the range 0–9, a–z, and A–Z.
   *  >
   *  Reference:
   *   - http://www.w3.org/TR/html-markup/syntax.html#tag-name
   *   - http://www.w3.org/TR/html-markup/elements.html
   *
   */
  public static function validate_tag_element($tag) {
    $tags = '
      |a|abbr|address|area|article|aside|audio|
      |b|base|bdi|bdo|blockquote|body|br|button|
      |canvas|caption|cite|code|col|colgroup|command|
      |datalist|dd|del|details|dfn|div|dl|dt|
      |em|embed|
      |fieldset|figcaption|figure|footer|form|
      |h1|h2|h3|h4|h5|h6|head|header|hgroup|hr|html|
      |i|iframe|img|input|ins|
      |kbd|keygen|
      |label|legend|li|link|
      |map|mark|menu|meta|meter|
      |nav|noscript|
      |object|ol|optgroup|option|output|
      |p|param|pre|progress|
      |q|
      |rp|rt|ruby|
      |s|samp|script|section|select|small|source|span|strong|style|
      |sub|summary|sup|
      |table|tbody|td|textarea|tfoot|th|thead|time|title|tr|track|
      |u|ul|
      |var|video|
      |wbr|
    ';
    return stristr($tags, '|' . $tag .'|') !== FALSE;
  }

  /**
   * Validation of start tag of element.
   *
   * @param $start_tag
   *   Example: <div id="main-content">
   */
  public static function validate_start_tag($start_tag) {
    $mask = '/^<(?P<tag>\w+)\s*[^>]*>$/';
    if (preg_match($mask, $start_tag, $matches)) {
      return self::validate_tag_element($matches['tag']);
    }
    return FALSE;
  }

  /**
   * Validation of attribute name of element.
   *
   *  > Attribute names must consist of one or more characters
   *  > other than the space characters, U+0000 NULL,
   *  > """, "'", ">", "/", "=", the control characters,
   *  > and any characters that are not defined by Unicode.
   *  >
   *  Reference: http://www.w3.org/TR/html-markup/syntax.html#syntax-attributes
   *
   * @param $names mixed
   *   The name of attribute (string) or the index array contains
   *   name of attribute.
   *
   * Return
   *  If argument is string
   *
   *
   */
  public static function validate_attribute_element($names) {
    $string = is_string($names);
    // Todo: Add support for Unicode characters.
    $names = (array) $names;
    $forbidden = array('"', "'", '>', '/', '=');
    $preg_quote = function ($var) {
      return preg_quote($var, '/');
    };
    $forbidden = array_map($preg_quote, $forbidden);
    $forbidden[] = '\\s';
    $forbidden = implode('|', $forbidden);
    foreach($names as $key => &$name) {
      $name = array(
        $name,
        !preg_match('/' . $forbidden . '/', $name),
      );
    }
    return $string ? $names[0][1] : $names;
  }

  /**
   * Validasi apakah posisi pointer dalam dokumen html berada diantara
   * start tag.
   * Contoh: Misalnya karakter pipe "|" berarti posisi pointer.
   *
   *  1. ... title="bla bla"> My name is | Budi Anduk </a> ...
   *     Pada contoh diatas maka posisi pointer tidak berada di dalam
   *     start tag, sehingga method ini akan return FALSE.
   *  2. <div title="main" | class="red"> My name is Budi Anduk </div>
   *     Pada contoh diatas maka posisi pointer berada di dalam
   *     start tag, sehingga method ini akan return TRUE.
   *
   * @param $position
   *   Posisi pointer dari awal string. Biasanya didapat dari fungsi strpos().
   * @param $html
   *   String dengan format html.
   */
  public static function validate_inside_start_tag($position, $html) {
    $result = '';
    // Jika kita bergerak mundur dari $position, maka
    // karakter < harus lebih dahulu ditemukan daripada karakter >
    $walk = $position;
    $lt = FALSE;
    $gt = FALSE;
    $temp = $walk;
    while (isset($html[--$walk])) {
      if ($html[$walk] == '<') {
        $lt = $walk;
        break;
      }
    }
    $walk = $position;
    while (isset($html[--$walk])) {
      if ($html[$walk] == '>') {
        $gt = $walk;
        break;
      }
    }
    // Khusus tag yang berada pada AWAL string
    if ($lt === 0 && $gt === FALSE) {
      $result .= 1;
    }
    else {
      $result .= $gt < $lt ? '1' : '0';
    }

    // Jika kita bergerak maju dari $position, maka
    // karakter > harus lebih dahulu ditemukan daripada karakter <
    $walk = $position;
    $lt = FALSE;
    $gt = FALSE;
    while (isset($html[++$walk])) {
      if ($html[$walk] == '<') {
        $lt = $walk;
        break;
      }
    }
    $walk = $position;
    while (isset($html[++$walk])) {
      if ($html[$walk] == '>') {
        $gt = $walk;
        break;
      }
    }
    // Khusus tag yang berada pada AKHIR string.
    if ($lt === FALSE && is_int($gt)) {
      $result .= 1;
    }
    else {
      $result .= $gt < $lt ? '1' : '0';
    }
    return strpos($result, '0') === false;
  }

  protected function build_conditions($attributes) {
    $implode = function ($var) {
      return implode(' ', $var);
    };
    $attributes = array_map($implode, $attributes);
    return implode(' AND ', $attributes);
  }

  protected function find_element($info_element) {

    $storage = array();
    $elements = $this->getElements();

    // Cari tag.
    $tag = empty($info_element['tag']) ? '' : array_shift($info_element['tag']);
    // Cari attribute.
    $attributes = $info_element['attributes'];

    foreach ($elements as $position => $element) {
      if (!empty($tag)) {
        $callback = 'self::getElementByTag';
        $param_arr = array($tag, $element);
        if (!empty($attributes)) {
          $param_arr[] = 'self::getElementByAttributes';
          $param_arr[] = array($this->build_conditions($attributes));
        }
      }
      else {
        $callback = 'self::getElementByAttributes';
        if (count($attributes) == 1) {
          $attribute = array_shift($attributes);
          switch ($attribute['name']) {
            case 'id':
              $callback = 'self::getElementById';
              $param_arr = array($attribute['value'], $element);
              break;

            case 'class':
              $callback = 'self::getElementByClass';
              $attribute['value'] = str_replace(' ', ' AND ', $attribute['value']);
              $param_arr = array($attribute['value'], $element);
              break;

            default:
              $conditions = $this->build_conditions(array($attribute));
              $param_arr = array($conditions, $element);
          }
        }
        else {
          $conditions = $this->build_conditions($attributes);
          $param_arr = array($conditions, $element);
        }
      }
      $results = call_user_func_array($callback, $param_arr);
      if (!empty($results)) {
        // Perbaiki position.
        $this->addPosition($results, $position);
        // Expand element.
        $this->expandElement($results, $this->raw);
        $storage += $results;
      }
    }
    if (!empty($storage)) {
      return new parseHTML($this->raw, $storage);
    }
    return new parseHTML;
  }

  protected function addPosition(&$start_tag, $add = 0 ) {
    if ($add === 0) {
      return;
    }
    $positions = array_keys($start_tag);
    foreach ($positions as &$position) {
      $position += $add;
    }
    $start_tag = array_combine($positions, $start_tag);
  }

  /**
   * Mengubah element yang awalnya hanya start tag menjadi
   * lebih lengkap dengan content dan end tag.
   */
  protected function expandElement(&$elements, $html) {
    // Validate.
    $mask = '/^<(?P<tag>\w+)\s*[^>]*>$/';
    foreach ($elements as $starttag_lt_position => &$element) {
      if (preg_match($mask, $element, $matches)) {
        // Kita hanya mencari tag yang tidak termasuk void element.
        if (!$this->validate_tag_void_element($matches['tag'])) {
          // Hati-hati dengan nested element.
          // Kita juga perlu tahu apakah ada nested element
          // dengan tag yang sama.
          // Contoh:
          // <div id="tahu">
          //   <div id="tempe">
          //     <div id="tahutempe">
          //       TEST
          //     </div>
          //   </div>
          // </div>
          $starttag = '<' . $matches['tag'];
          $starttag_length = strlen($starttag);
          $endtag = '</' . $matches['tag'] . '>';
          $endtag_length = strlen($endtag);
          $offset_starttag = $offset_endtag = $starttag_lt_position + $starttag_length;
          $endtag_rt_position = FALSE;
          do {
            $distance_starttag = stripos($html, $starttag, $offset_starttag);
            $distance_endtag = stripos($html, $endtag, $offset_endtag);
            // Jika endtag tidak ditemukan, maka berarti element ini
            // dianggap single tag.
            if ($distance_endtag === FALSE) {
              break;
            }
            // JIka jarak ke starttag lebih kecil, berarti benar ada element
            // nested dengan tag sama.
            $nested_exists = $distance_starttag !== FALSE && ($distance_starttag < $distance_endtag);
            if ($nested_exists) {
              // Perbaiki jarak offset.
              $offset_starttag = $distance_starttag + $starttag_length;
              $offset_endtag = $distance_endtag + $endtag_length;
            }
            else {
              $endtag_rt_position = $distance_endtag + $endtag_length;
              break;
            }
          } while ($nested_exists);
          if ($endtag_rt_position !== FALSE) {
            $element = substr($html, $starttag_lt_position, $endtag_rt_position - $starttag_lt_position);
          }
        }
      }
    }
  }
  
  protected function _get_attributes_parse_conditions($conditions) {
    $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions);
    $storage = array();
    foreach ($conditions as $key => $value) {
      if (strpos($value, ' AND ') !== false) {
        $value = explode(' AND ', $value);
        $storage = array_merge($storage, $value);
      }
      else {
       $storage = array_merge($storage, (array) $value);
      }
    }
    $operators = array(
      '=', 'equals', 'is',
      '!=', 'is not',
      '<', 'is less than',
      '>', 'is greater than',
      '<=', 'is less than or equals',
      '>=', 'is greater than or equals',
      '|=', 'contains prefix',
      '~=', 'contains word', 'contains any word',
      '~~=', 'contains all word',
      '!*=', 'does not contain',
      '*=', 'contains',
      '!^=', 'does not start with',
      '^=', 'starts with',
      '!$=', 'does not end with',
      '$=', 'ends with',
    );
    $operators_regex = array();
    foreach ($operators as $value) {
      $operators_regex[] = preg_quote($value, '/');
    }
    $operators_regex = implode('|', $operators_regex);
    $mask = '/^(.+)\s+('.$operators_regex.')\s+(.+)$/i';
    isset($this->mask) or $this->mask = $mask;
    $fields = array();
    foreach($storage as $condition) {
      if (preg_match($mask, trim($condition), $capture)) {
        // $this->capture[] = $capture;
        $fields[] = $capture[1];
      }
    }
    return $fields;
  }

  protected function _validate_attribute_conditions($row = array(), $conditions = null) {
    // print_r($conditions);
    // var_dump($conditions);
    if (!empty($row)) {
      if (!empty($conditions)) {
        // var_dump($conditions);
        $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions);
        // var_dump($conditions);
        $or = '';
        foreach ($conditions as $key => $value) {
          if (strpos($value, ' AND ') !== false) {
            // var_dump('ADA AND');
            $value = explode(' AND ', $value);
            $and   = '';
            // echo 'var_dump($row): '; var_dump($row);
            foreach ($value as $k => $v) {
              // echo 'var_dump($v): '; var_dump($v);
              $and .= $a = $this->_validate_attribute_condition($row, $v);
              // echo 'var_dump($a): '; var_dump($a);
              // echo "\r\n";
              // echo "\r\n";
              // echo "\r\n";
              // echo "\r\n";
              // echo "\r\n";
            }
            // var_dump($and);
            $or .= (strpos($and, '0') !== false) ? '0' : '1';
          }
          else {
            // var_dump('TIDAK ADA AND');
            $or .= $a = $this->_validate_attribute_condition($row, $value);
            // var_dump($or);
            // echo 'var_dump($a): '; var_dump($a);
            // echo "\r\n";
            // echo "\r\n";
            // echo "\r\n";
            // echo "\r\n";
            // echo "\r\n";
          }
        }
        // var_dump($or); //todo to do herar
        return (strpos($or, '1') !== false) ? true : false;
      }
      return true;
    }
    return false;
  }

  protected function _validate_attribute_condition($row, $condition) {
    if (preg_match($this->mask, trim($condition), $capture)) {
      $field = $capture[1];
      $op    = $capture[2];
      $value = $capture[3];
      if (preg_match('/^([\'\"]{1})(.*)([\'\"]{1})$/i', $value, $capture)) {
        if ($capture[1] == $capture[3]) {
          $value = $capture[2];
          $value = stripslashes($value);
        }
      }
      if (array_key_exists($field, $row)) {
        // Prepare.
        if ($op == '~=' || $op == 'contains word' || $op == 'contains any word' || $op == '~~=' || $op == 'contains all word') {
          $words = preg_split('/\s/', $row[$field]);
          $values = preg_split('/\s/', $value);
        }
        // Run logic.
        if (($op == '=' || $op == 'equals' || $op == 'is') && $row[$field] == $value) {
          return '1';
        }
        elseif (($op == '!=' || $op == 'is not') && $row[$field] != $value) {
          return '1';
        }
        elseif (($op == '<' || $op == 'is less than' ) && $row[$field] < $value) {
          return '1';
        }
        elseif (($op == '>' || $op == 'is greater than') && $row[$field] > $value) {
          return '1';
        }
        elseif (($op == '<=' || $op == 'is less than or equals' ) && $row[$field] <= $value) {
          return '1';
        }
        elseif (($op == '>=' || $op == 'is greater than or equals') && $row[$field] >= $value) {
          return '1';
        }
        elseif (($op == '|=' || $op == 'contains prefix') && preg_match('/(?:^' . preg_quote($value, '/') . '$|^' . preg_quote($value, '/') . '\-\w+)/', $row[$field])) {
          return '1';
        }
        elseif (($op == '~=' || $op == 'contains word' || $op == 'contains any word') && count(array_intersect($words, $values)) !== 0) {
          return '1';
        }
        elseif (($op == '~~=' || $op == 'contains all word') && count(array_intersect($words, $values)) == count($values)) {
          return '1';
        }
        elseif (($op == '!*=' || $op == 'does not contain') && !preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
          return '1';
        }
        elseif (($op == '*=' || $op == 'contains') && preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
          return '1';
        }
        elseif (($op == '!^=' || $op == 'does not start with') && !preg_match('/^'.preg_quote($value, '/').'/i', $row[$field])) {
          return '1';
        }
        elseif (($op == '^=' || $op == 'starts with') && preg_match('/^'.preg_quote($value, '/').'/i', $row[$field])) {
          return '1';
        }
        elseif (($op == '!$=' || $op == 'does not end with') && !preg_match('/'.preg_quote($value, '/').'$/i', $row[$field])) {
          return '1';
        }
        elseif (($op == '$=' || $op == 'ends with') && preg_match('/'.preg_quote($value, '/').'$/i', $row[$field])) {
          return '1';
        }
        else {
          return '0';
        }
      }
      // Jika attribute tidak ada, maka harus return FALSE.
      else {
        return '0';
      }
    }
    return '1';
  }

  protected function buildAttr() {
    $attributes = array();
    if (isset($this->start_tag)) {
      $attributes = $this->extractAttributes($this->start_tag);
    }
    $this->attributes = $attributes;
  }

  // $value is defined by us, and $start_tag is
  // defined by getElementByAttribute().
  private function _getElementByClass($value, $start_tag) {
    $conditions = $value;
    $conditions = (strpos($conditions, ' OR ') !== FALSE) ? explode(' OR ', $conditions) : array($conditions);
    $attributes = $this->extractAttributes($start_tag);
    $classes = preg_split('/\s/', $attributes['class']);
    $or = '';
    foreach ($conditions as $key => $value) {
      if (strpos($value, ' AND ') !== FALSE) {
        $value = explode(' AND ', $value);
        $and   = '';
        foreach ($value as $k => $v) {
          $and .= in_array($v, $classes) ? '1' : '0';
        }
        $or .= (strpos($and, '0') !== FALSE) ? '0' : '1';
      }
      else {
        $or .= in_array($value, $classes) ? '1' : '0';
      }
    }
    return (strpos($or, '1') !== FALSE) ? TRUE : FALSE;
  }

  // $value is defined by us, and $start_tag is
  // defined by getElementByAttribute().
  private function _getElementById($value, $start_tag) {
    $attributes = $this->extractAttributes($start_tag);
    if ($attributes['id'] === $value) {
      return array(
        'break' => TRUE,
      );
    }
    return FALSE;
  }

}
