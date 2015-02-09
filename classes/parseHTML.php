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
 *
 * Definisi FAQ
 *
 *  1. Array Elements Starttag
 *
 *     Array yang sederhana satu dimensi, dimana pada key merupakan posisi
 *     dan value merupakan element starttag.
 *
 *     Key adalah posisi dari awal dokumen html (pada $this->raw) menuju
 *     element yang ditandai dengan karakter kurung siku left "<". Posisi ini
 *     idem dengan nilai yang didapat dari fungsi strpos.
 *
 *     Value merupakan Starttag.
 *
 *     Contoh:
 *
 *       array(
 *         '5' => '<body class="a">',
 *         '25' => '<img class="b">',
 *         '83' => '<div class="c">',
 *         '253' => '<div class>',
 *       );
 *
 *     Untuk mengubah starttag ini menjadi full element (termasuk contents dan
 *     endtag) gunakan method constructElements() atau constructElement().
 *
 *
 *  2. Array Elements Full
 *
 *     Idem dengan Array Elements Starttag, namun pada value adalah komponen
 *     lengkap element yakni terdiri dari starttag, contents, dan endtag -
 *     kecuali void element. Untuk referensi void element, dapat melihat
 *     method validate_tag_void_element().
 *
 *     Contoh:
 *
 *       array(
 *         '30' => '<meta keyword="abc">',
 *         '50' => '<body class="a">bla bla bla</body>',
 *         '250' => '<img class="b">',
 *         '838' => '<div class="c"><span></span></div>',
 *         '2530' => '<div class></div>',
 *       );
 *
 */
class parseHTML {

  /**
   * Data mentah keseluruhan dokumen html.
   *
   * Dapat bertipe string atau NULL. Properti ini menjadi rujukan utama untuk
   * pencarian dan lain-lain. Jika tercipta object baru hasil eksekusi
   * method find(), maka property $raw dari object baru tersebut akan
   * sama dengan property $raw dari object ini.
   */
  private $raw;

  /**
   * Merupakan "Array Elements Full", lihat pada Definisi FAQ.
   *
   * Untuk mengambil info properti $element ini, gunakan method getElements().
   * Jika $element merupakan empty array, maka itu berarti $raw digunakan
   * sebagai element dan nilai position-nya adalah 0.
   */
  private $elements = array();

  /**
   * Jumlah element yang dimiliki oleh object ini.
   *
   * Cara cepat untuk mendapatkan informasi jumlah element. Nilai pada
   * properti ini didefinisikan saat __construct().
   */
  public $length = 0;

  /**
   * Internal only. Properti ini digunakan oleh developer saat debugging.
   */
  public $debug = FALSE;

  /**
   * Internal only. Property tempat penampungan hasil build regex oleh method
   * parse_conditions().
   */
  public static $mask;

  /**
   * Construct object.
   *
   * Property $length didefinisikan disini.
   *
   * @param $raw string
   *   Data mentah html, lihat pada properti $raw.
   * @param $elements array
   *   Merupakan "Array Elements Full", lihat pada Definisi FAQ.
   */
  function __construct($raw = NULL, $elements = NULL) {
    if (isset($raw)) {
      $this->raw = $raw;
    }
    if (isset($elements)) {
      $this->elements = $elements;
    }
    // Ubah property $length.
    if (isset($this->raw) && empty($this->elements)) {
      $this->length = 1;
    }
    elseif (!empty($this->elements)) {
      $this->length = count($this->elements);
    }
  }

  /**
   * Mendapatkan nilai dari properti $raw.
   */
  public function getRaw() {
    return $this->raw;
  }

  /**
   * Mendapatkan nilai dari property $elements.
   */
  public function getElements() {
    if (empty($this->elements)) {
      return array('0' => $this->raw);
    }
    return $this->elements;
  }

  /**
   * Menemukan element-element dengan selector CSS.
   *
   * Membuat object baru dengan element-element
   * yang dicari berdasarkan selector CSS. Selector yang didukung terbatas,
   * untuk mengetahui selector yang didukung dapat melihat dokumentasi pada
   * homepage.
   *
   * Get the descendants of each element in the current set of matched elements,
   * filtered by a selector.
   * Reference:
   * http://api.jquery.com/find/
   *
   * @param $selector string
   *   Selector CSS untuk mendapatkan element.
   *
   * Return
   *   Mengembalikan object parseHTML, baik object yang terdapat element
   *   (bila selector valid dan ditemukan) atau object kosong tanpa element
   *   (bila selector tidak valid atau hasil tidak ditemukan).
   */
  public function find($selector) {
    // Raw dapat bernilai NULL, terjadi jika find tidak menemukan element.
    // Bila hasil pencarian kosong, atau selector tidak valid, maka
    // Kita perlu me-return Object kosong, agar mendukung thread method oleh
    // user, sehingga tidak error.
    // Contoh: $html->find('invalid selector')->attr('name');
    if (is_null($this->raw)) {
      return new parseHTML;
    }
    // Dapatkan element.
    $elements = $this->getElements();
    // Translate selector.
    $multi_selector = $this->translate_selector($selector);
    if (!$multi_selector) {
      return new parseHTML;
    }
    // Buat penyimpanan hasil.
    $storage = array();
    while ($search_elements = array_shift($multi_selector)) {
      // Search.
      $result = $this->find_elements($elements, $search_elements, 0);
      // Nilai dari $result dapat NULL atau empty array, merge
      // jika ada value.
      if ($result) {
        $storage += $result;
      }
    }
    if (!empty($storage)) {
      return new parseHTML($this->raw, $storage);
    }
    return new parseHTML;
  }

  /**
   * Mendapatkan keseluruhan html element.
   *
   * Get the HTML contents of the first element in the set of matched elements.
   * Reference:
   * - http://api.jquery.com/html/
   */
  public function html() {
    // Kita hanya toleransi pada element yang pertama.
    $elements = $this->getElements();
    return array_shift($elements);
  }

  /**
   * Mendapatkan nilai text dari element tanpa tag html.
   *
   * Get the combined text contents of each element in the set of matched
   * elements, including their descendants.
   * Reference:
   * - http://api.jquery.com/text/
   */
  public function text() {
    return strip_tags($this->html());
  }

  /**
   * Mendapatkan informasi attribute dari element yang pertama.
   *
   * Get the value of an attribute for the first element in the set of
   * matched elements.
   * Reference:
   * - http://api.jquery.com/attr/
   *
   * param $name string
   *  Nama attribute yang ingin didapat value-nya.
   */
  public function attr($name) {
    $element = $this->html();
    $mask = '/^\<\w+\s*[^>]*\>/i';
    if (preg_match($mask, $element, $mathces)) {
      $starttag = array_shift($mathces);
      $attributes = $this->extractAttributes($starttag, TRUE);
      return isset($attributes[$name]) ? $attributes[$name] : NULL;
    }
  }

  /**
   *
   */
  public function prev($selector = NULL) {
    // Todo.
  }

  /**
   *
   */
  public function next($selector = NULL) {
    // Todo.
  }

  /**
   *
   */
  public function parent($selector = NULL) {
    // Todo.
  }

  /**
   *
   */
  public function parents($selector = NULL) {
    // Todo.
  }

  /**
   *
   */
  public function children($selector = NULL) {
    // Todo.
  }

  /**
   *
   */
  public function contents() {
    // Todo.
  }

  /**
   * Mereduksi element yang di-attach dari jamak menjadi satu.
   *
   * @param $index int
   *   Posisi dari element, dimana posisi pertama dimulai dari index 0.
   *
   * Reduce the set of matched elements to the one at the specified index.
   * Reference:
   * - http://api.jquery.com/eq/
   */
  public function eq($index) {
    // Todo: support for negative index.
    $elements = $this->getElements();
    $keys = array_keys($elements);
    if (isset($keys[$index]) && isset($elements[$keys[$index]])) {
      $position = $keys[$index];
      return new parseHTML($this->raw, array($position => $elements[$position]));
    }
    else {
      return new parseHTML;
    }
  }

  /**
   * Mengambil informasi element form.
   *
   * Reference:
   *  - http://www.w3schools.com/html/html_form_elements.asp
   *
   * Todo, support for HTML5 element: <datalist> <keygen> <output>
   *
   * @param $selector string
   *   Custom css selector, if NULL, the selector is
   *   input, textarea, select, button
   *
   * Return
   *   Simple array which key is value from attribute name
   *   and value is value from attribute value
   *   (string or array).
   */
  public function extractForm($selector = NULL) {
    if (is_null($selector)) {
      $selector = 'input, textarea, select, button';
    }
    $storage = array();
    $form_element = $this->find($selector)->getElements();
    ksort($form_element);
    foreach($form_element as $element) {
      // $debugname = 'element'; echo 'print_r(' . $debugname . '): '; print_r($$debugname); echo "\r\n";


      list($starttag, $contents, $endtag) = $this->parseElement($element);
      $attr = $this->extractAttributes($starttag);
      if (isset($attr['name'])) {
        // Handle checkboxes element.
        $name = $attr['name'];
        $value = isset($attr['value']) ? $attr['value'] : NULL;
        // Jika name merupakan checkbox multivalue, contoh: myinput[],
        // maka kita perlu memberi numeric agar dapat dimasukkan ke array,
        // sehingga menjadi myinput[0], myinput[1], dsb...
        if (substr($name, -2, 2) === '[]') {
          $prefix_name = substr($name, 0, -2);
          $name = $prefix_name . '[0]';
          if (array_key_exists($name, $storage)) {
            $counter = 0;
            do {
              $name = $prefix_name . '[' . $counter++ . ']';
            } while (array_key_exists($name, $storage));
          }
        }
        // Handle multi value.
        if (isset($storage[$name])) {
          if (is_string($storage[$name])) {
            $storage[$name] = (array) $storage[$name];
          }
          $storage[$name][] = $value;
        }
        else {
          $storage[$name] = $value;
        }
      }
    }
    return $storage;
  }

  /**
   * Mempersiapkan element-element form yang akan dipost,
   * dimana input type submit hanya diijinkan satu saja.
   * Sementara method extractForm() akan menyertakan semua
   * input type submit.
   *
   * Todo, support for HTML5 element: <datalist> <keygen> <output>
   *
   * @param $submit string
   *   Value dari attribute name pada input type submit yang akan dijadikan
   *   trigger untuk mengirim form.
   */
  public function preparePostForm($submit) {
    $fields = $this->extractForm();
    $submit = $this->extractForm('input[type=submit]');
    // Buang semua input submit kecuali 'BalInqRq'.
    unset($submit[$submit]);
    return array_diff_assoc($fields, $submit);
  }

  /**
   * Mencari element dengan berdasarkan attribute.
   *
   * Reference:
   * - http://www.w3.org/TR/xhtml1/#h-4.5
   * - http://stackoverflow.com/questions/13159180/set-attribute-without-value
   *
   * @param $attribute
   *   String attribute yang mau dicari, incasesensitive.
   *   Contoh: 'class', 'id',
   * @param $html
   *   String dengan format html.
   * @param $callback callable
   *   String atau array yang dapat dipanggil untuk melakukan tambahan filter.
   * @param $param_arr array
   *   Array berisi argument yang akan di-passing ke $callback.
   *
   * Return
   *   Mengembalikan "Array Elements Starttag"
   *   lihat pada Definisi FAQ.
   */
  public static function getElementByAttribute($attribute, $html, $callback = NULL, $param_arr = NULL) {
    // Set storage.
    $storage = array();
    // Validate.
    if (!self::validate_attribute_element($attribute) || strlen($html) === 0) {
      return $storage;
    }
    // Find string.
    $find = $attribute;
    $length = strlen($find);
    $scoupe = $html;
    $offset = 0;
    $distance = stripos($scoupe, $find);

    while ($distance !== FALSE) {
      $position = $distance + $offset;
      // Sebelum disimpan ke storage, maka validasi beberapa hal.
      // Karakter sebelumnya harus whitespace.
      $pch = (isset($html[$position - 1])) ? $html[$position - 1] : false;
      if ($pch && ctype_space($pch)) {
        // Cek apakah posisi pointer dari ditemukannya attribute itu
        // berada diantara start tag html.
        if (self::validate_inside_start_tag($position, $html)) {
          // Cek apakah karakter pertama setelah
          // karakter < pada element adalah string valid.
          $prefix = substr($html, 0, $position);
          $suffix = substr($html, $position);
          $starttag_lt_position = strrpos($prefix, '<');
          $starttag_rt_position = $position + strpos($suffix, '>') + strlen('>');
          $start_tag = substr($html, $starttag_lt_position, $starttag_rt_position - $starttag_lt_position);
          if (self::validate_start_tag($start_tag)) {
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
      $distance = stripos($scoupe, $find);
    }
    return $storage;
  }

  /**
   * Mencari element dengan custom filter berdasarkan attribute dan kondisinya.
   *
   * Sama sepert method getElementByAttribute() namun dengan fitur filter
   * seperti query sql. Method ini terinspirasi dari method pada
   * class parseCSV dan dilakukan pengembangan agar mirip dengan kebutuhan
   * selector CSS.
   *
   * Mengembalikan array dengan key merupakan posisi pointer
   * dan value merupakan starttag. Attribute yang dicari
   * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
   *
   * Untuk mengubah starttag menjadi full element (termasuk content dan
   * endatag) gunakan method constructElements() atau constructElement().
   *
   * Reference:
   *  - https://github.com/parsecsv/parsecsv-for-php
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
   *   Operator yang tersedia dapat dilihat pada method
   *   parse_conditions().
   *
   * @param $html
   *   String dengan format html.
   *
   * Return
   *   Mengembalikan "Array Elements Starttag"
   *   lihat pada Definisi FAQ.
   *   Contoh array yang dihasilkan dengan conditons yang dicari
   *   adalah 'class contains word a OR class contains word x':
   *
   *     array(
   *       '5' => '<body class="a b">',
   *       '25' => '<img class="x y">',
   *       '83' => '<div class="a x">',
   *     );
   *
   */
  public static function getElementByAttributes($conditions, $html) {
    $elements = array();
    // Validate.
    $conditions = trim($conditions);
    if (empty($html) || empty($conditions)) {
      return $elements;
    }
    $attributes = self::parse_conditions($conditions);
    foreach($attributes as $attribute) {
      $elements += self::getElementByAttribute($attribute, $html);
    }
    // Filtering.
    foreach($elements as $position => $element) {
      $attributes = self::extractAttributes($element);
      if (!self::_validate_attribute_conditions($attributes, $conditions)) {
        unset($elements[$position]);
      }
    }
    return $elements;
  }

  /**
   * Mencari element berdasarkan value dari attribute id.
   *
   * Method ini dibuat untuk efisiensi pencarian element alih-alih
   * menggunakan method getElementByAttributes() karena method ini
   * me-reduce looping.
   *
   * Ilustrasi: daripada mencari element dengan cara seperti ini:
   *
   *   $this->getElementByAttributes('id = somevalue', $html)
   *
   * sebaiknya gunakan cara ini:
   *
   *   $this->getElementById('somevalue', $html);
   *
   *
   * Mengembalikan array dengan key merupakan posisi pointer
   * dan value merupakan starttag. Attribute yang dicari
   * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
   *
   * Untuk mengubah starttag menjadi full element (termasuk content dan
   * endatag) gunakan method constructElements() atau constructElement().
   *
   * @param $value string
   *   Value dari attribute id yang akan dicari
   * @param $html
   *   String dengan format html.
   *
   * Return
   *   Mengembalikan "Array Elements Starttag"
   *   lihat pada Definisi FAQ.
   *   Id seharusnya hanya ada satu tiap element pada satu dokumen html.
   *   Namun jika terdapat banyak element berattribute id dengan value
   *   yang sama, maka kita hanya akan mengambil element yang pertama.
   *   Contoh:
   *
   *     array(
   *       '65' => '<div id="somevalue" class="a">',
   *     );
   *
   */
  public static function getElementById($value, $html) {
    $callback = 'self::_getElementById';
    $param_arr = array($value);
    return self::getElementByAttribute('id', $html, $callback, $param_arr);
  }

  /**
   * Mencari element berdasarkan value dari attribute class.
   *
   * Method ini dibuat untuk efisiensi pencarian element alih-alih
   * menggunakan method getElementByAttributes() karena method ini
   * me-reduce looping.
   *
   * Ilustrasi: daripada mencari element dengan cara seperti ini:
   *
   *   $this->getElementByAttributes('class ~= somevalue', $html)
   *
   * sebaiknya gunakan cara ini:
   *
   *   $this->getElementByClass('somevalue', $html);
   *
   *
   * Mengembalikan array dengan key merupakan posisi pointer
   * dan value merupakan starttag. Attribute yang dicari
   * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
   *
   * Untuk mengubah starttag menjadi full element (termasuk content dan
   * endatag) gunakan method constructElements() atau constructElement().
   *
   * @param $value string
   *   Value dari attribute class yang akan dicari.
   *   Dapat menggunakan contitions. Contoh:
   *    - "cinta"
   *      Mencari class yang terdapat kata cinta.
   *    - "cinta AND love"
   *      Mencari class yang terdapat kata cinta DAN love.
   *    - "cinta OR love"
   *      Mencari class yang terdapat kata cinta ATAU love.
   *
   * @param $html
   *   String dengan format html.
   *
   * Return
   *   Mengembalikan "Array Elements Starttag"
   *   lihat pada Definisi FAQ.
   *   Contoh:
   *
   *     array(
   *       '65' => '<div id="primary" class="somevalue">',
   *       '230' => '<div id="secondary" class="somevalue">',
   *     );
   *
   */
  public static function getElementByClass($value, $html) {
    $callback = 'self::_getElementByClass';
    $param_arr = array($value);
    return self::getElementByAttribute('class', $html, $callback, $param_arr);
  }

  /**
   * Mencari element berdasarkan tagname.
   *
   * Mengembalikan array dengan key merupakan posisi pointer
   * dan value merupakan starttag. Attribute yang dicari
   * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
   *
   * Untuk mengubah starttag menjadi full element (termasuk content dan
   * endatag) gunakan method constructElements() atau constructElement().
   *
   * @param $tag
   *   String tag yang mau dicari, incasesensitive.
   *   Contoh: 'a', 'img',
   * @param $html
   *   String dengan format html.
   * @param $callback callable
   *   String atau array yang dapat dipanggil untuk melakukan tambahan filter.
   * @param $param_arr array
   *   Array berisi argument yang akan di-passing ke $callback.
   *
   * Return
   *   Mengembalikan "Array Elements Starttag"
   *   lihat pada Definisi FAQ.
   *   Contoh array yang dihasilkan dengan tag yang dicari
   *   adalah a:
   *   array(
   *     '5' => '<a class="a">',
   *     '25' => '<a class="b">',
   *     '83' => '<a class="c">',
   *     '253' => '<a class>',
   *   );
   */
  public static function getElementByTag($tag, $html, $callback = NULL, $param_arr = NULL) {
    $tag = trim($tag);
    // Set storage.
    $storage = array();
    // Validate.
    // if (self::validate_tag_element($tag) === FALSE) {
      // return $storage;
    // }
    // Find string.
    $find = '<' . $tag;
    $length = strlen($find);
    $scoupe = $html;
    $offset = 0;
    $distance = stripos($scoupe, $find);
    while ($distance !== FALSE) {
      $position = $distance + $offset;
      // Sebelum disimpan ke storage, maka validasi beberapa hal.
      // Karakter sebelumnya harus < dan karakter sesudahnya harus
      // whitespace atau >.
      $nch = (isset($html[$position + $length])) ? $html[$position + $length] : false;
      $pch = (isset($html[$position - 1])) ? $html[$position - 1] : false;
      if ($nch && (ctype_space($nch) || $nch == '>')) {
        // Cek apakah posisi pointer dari ditemukannya attribute itu
        // berada diantara start tag html.
        $_position = $position + 1;
        if ($_position && self::validate_inside_start_tag($_position, $html)) {
          $suffix = substr($html, $_position);
          $starttag_lt_position = $position;
          $starttag_rt_position = strpos($html, '>', $position) + strlen('>');
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
      $distance = stripos($scoupe, $find);
    }
    return $storage;
  }

  /**
   * Memecah element menjadi starttag, contents, dan endtag
   *
   * @param $element string
   *   Element html lengkap dengan starttag, contents, dan endtag (kecuali
   *   void element). Element harus sudah trim dari whitespace atau akan
   *   gagal.
   *
   * Return
   *   Mengembalikan array dengan key index, dimana:
   *    - key = 0, merupakan starttag, atau FALSE jika not found or failed,
   *    - key = 1, merupakan contents, atau FALSE jika not found or failed,
   *    - key = 2, merupakan endtag, atau FALSE jika not found or failed.
   *   Info element ini dapat dengan mudah diparsing dengan fungsi list().
   */
  public static function parseElement($element) {
    $starttag = $contents = $endtag = FALSE;
    // Dapatkan starttag dengan regex.
    $mask = '/^<(?P<tag>\w+)\s*[^>]*>/';
    preg_match($mask, $element, $matches);
    if (preg_match($mask, $element, $matches)) {
      // Dapatkan contents dan endtag dengan strpos, strlen, dan substr.
      $starttag = $matches[0];
      $tag = $matches['tag'];
      $_endtag = '</' . $tag . '>';
      if ($distance = strripos($element, $_endtag)) {
        if ($endtag = substr($element, $distance)) {
          $contents = substr($element, strlen($starttag), strlen($endtag) * -1);
        }
      }
    }
    return array($starttag, $contents, $endtag);
  }

  /**
   * Mengubah selector css menjadi array untuk proses filtering.
   *
   * Array yang dihasilkan akan menjadi susunan seperti ini.
   *
   * Multi selector
   *  - Selector
   *     - Elements descendent
   *        - Element
   *           - Direct
   *           - Tag
   *           - Attributes
   *              - Attribute 1
   *              - Attribute 2
   *              - Attribute 3
   *
   * Contoh paling ekstrem:
   *
   *   $selector = 'div.class1.class2 a, #someid.class3.class4 > img[title][href="\\/a"]';
   *
   *   array(
   *     // First Selector.
   *     0 => array(
   *       // Elements descendents.
   *       0 => array(
   *         'direct' => FALSE
   *         'tag' => array(
   *           0 => 'div'
   *         ),
   *         'attributes' => array(
   *           0 => array(
   *             'name' => 'class'
   *             'operator' => '~~='
   *             'value' => 'class1 class2'
   *           ),
   *         ),
   *       ),
   *       1 => array(
   *         'direct' => FALSE
   *         'tag' => array(
   *           0 => 'a'
   *         ),
   *         'attributes' => array(
   *         ),
   *       ),
   *     ),
   *
   *     // Second Selector.
   *     1 => array(
   *       // Elements descendents.
   *       0 => array(
   *         'direct' => FALSE
   *         'tag' => array(
   *         ),
   *         'attributes' => array(
   *           0 => array(
   *             'name' => 'id'
   *             'operator' => '='
   *             'value' => 'someid'
   *           ),
   *           1 => array(
   *             'name' => 'class'
   *             'operator' => '~~='
   *             'value' => 'class3 class4'
   *           ),
   *         ),
   *       ),
   *       1 => array(
   *         'direct' => TRUE
   *         'tag' => array(
   *           0 => 'img'
   *         ),
   *         'attributes' => array(
   *           0 => array(
   *             'name' => 'title'
   *             'operator' =>
   *             'value' =>
   *           ),
   *           1 => array(
   *             'name' => 'href'
   *             'operator' => '='
   *             'value' => '/a'
   *           ),
   *         ),
   *       ),
   *     ),
   *   )
   */
  public static function translate_selector($selector) {
    $string = trim($selector);
    $string_length = strlen($string);
    $meta_characters = '!"#$%&\'()*+,./:;<=>?@[\\]^`{|}~';
    $last = substr($string, -1, 1);
    $first = substr($string, 0, 1);

    // 1st Validation.
    // Krakter terakhir tidak boleh meta karakter kecuali karakter ].
    if ($last != ']' && strpos($meta_characters, $last) !== FALSE) {
      return FALSE;
    }
    // Karakter pertama jikapun meta character, hanya boleh antara . # [
    elseif (strpos($meta_characters, $first) !== FALSE && !in_array($first, array('#', '.', '['))) {
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
    $register_selector = FALSE;
    $register_elements = FALSE;
    $register_element = FALSE;
    $is_last = FALSE;
    $selector = $elements = $_elements = array();
    $element = $_element = array('direct' => FALSE, 'tag' => array(), 'attributes' => array());
    $x = 0;
    $string_length = count($characters);

    // Walking.
    while ($character = array_shift($characters)) {
      ($x != $string_length - 1) or $is_last = TRUE;
      switch ($step) {
        case 'init':
          // $debugname = 'character'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);

          if (isset($character['std'])) {
            $tag .= $character['std'];
            $step = 'build tag';
            if ($is_last) {
              $register_element = TRUE;
              $register_elements = TRUE;
              $register_selector = TRUE;
            }
          }
          elseif (isset($character['meta'])) {
            switch ($character['meta']) {
              case ',':
                $register_elements = TRUE;
                $register_selector = TRUE;
                break;

              case '>':
                $element['direct'] = TRUE;
                break;

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
          // elseif (isset($character['space'])) {
            // $register_elements = TRUE;
          // }
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
                  $register_selector = TRUE;
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
                  $register_selector = TRUE;
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
              $register_selector = TRUE;
            }
          }
          break;

        case 'build value':
          if (isset($character['std'])) {
            $attribute_value .= $character['std'];
            if ($is_last) {
              $register_element = TRUE;
              $register_elements = TRUE;
              $register_selector = TRUE;
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
            // Khusus class, maka ada perlakuan khusus.
            elseif ($character['meta'] == ',') {
              $register_element = TRUE;
              $register_elements = TRUE;
              $register_selector = TRUE;
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
              $register_selector = TRUE;
            }
          }
          elseif (isset($character['space'])) {
            $register_element = TRUE;
            $register_elements = TRUE;
          }
          elseif (isset($character['meta'])) {
            switch ($character['meta']) {
              case ',':
                $register_element = TRUE;
                $register_elements = TRUE;
                $register_selector = TRUE;
                break;

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
              // Jika karakter setelahnya adalah spasi, maka daftarkan ke
              // elements.
              $step = 'init';
              if (isset($string[$x + 1]) && ctype_space($string[$x + 1])) {
                $register_elements = TRUE;
              }
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
      if ($register_selector) {
        $selector[] = $elements;
        $elements = $_elements;
        $register_selector = FALSE;
        $step = 'init';
      }
      $x++;
    }
    return $selector;


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
   *     '"mengapa"' => NULL,
   *     'tempe' => NULL,
   *     "'agama'" => '',
   *     'id' => 'roji',
   *     '965' => 'cintakita',
   *     'duhai' => 'class="anto"',
   *     'dengan' => NULL,
   *     'cinta' => 'kita',
   *     'yoyo' => 'ok',
   *   );
   *
   * Hasil yang akan didapat jika dilakukan validasi adalah sbb:
   *
   *   array(
   *     'tempe' => NULL,
   *     'id' => 'roji',
   *     '965' => 'cintakita',
   *     'duhai' => 'class="anto"',
   *     'dengan' => NULL,
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
              $value = NULL;
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
   * Boolean. Validation of tag of element.
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
   * @param $tag string
   *   Tag Name yang akan divalidasi.
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
   * Boolean. Validation of start tag of element.
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
   *   Jika argument adalah string, maka akan mengembalikan boolean.
   *   Jika argument adalah array, maka tiap value array akan di-expand
   *   menjadi array dengan 2 value dimana key 0 adalah nama attribute, dan
   *   key 1 adalah hasil validasi.
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
   * Boolean. Validasi posisi pointer apakah berada didalam tag html.
   *
   * Posisi pointer biasanya didapat dari hasil fungsi strpos()
   * dalam dokumen html.
   *
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

  /**
   * Menemukan hasil element (satu atau jamak) dengan pencarian tertentu.
   *
   * @param $elements array
   *   Informasi element dimana key merupakan posisi pointer dan value
   *   merupakan element (lengkap dengan startag, contents, dan endtag
   *   kecuali void element).
   *   Contoh:
   *
   *     array(
   *       '241' => '<ul class="topnav"><li>Item A1</li></ul>',
   *       '618' => '<ul class="topnav"><li>Item B1</li></ul>',
   *     );
   *
   * @param $search_elements array
   *   Array yang berisi informasi pencarian per satu selector. Variable
   *   ini didapat dari hasil method "translate_selector()".
   * @param $looping int
   *   Internal only, untuk debugging. Method find() dan turunannya
   *   (yakni: find_elements(), find_element_each(), dan
   *   find_element_each_direct()) dapat terjadi recursive untuk
   *   mencapai hasil. Nilai variable ini akan bertambah ketika proses
   *   mengalami looping.
   *
   * Return
   *   Mengembalikan array seperti parameter $elements yang mana selector
   *   telah berhasil mendapatkan element yang diinginkan.
   */
  protected function find_elements($elements, $search_elements, $looping) {
    $storage = array();
    foreach ($elements as $position => $element) {
      $result = $this->find_element_each($position, $element, $search_elements, $looping);
      if ($result) {
        $storage += $result;
      }
    }
    return $storage;
  }

  /**
   * Menemukan hasil element (satu atau jamak) dengan pencarian tertentu.
   *
   * @param $position int
   *   jarak element dari awal string $raw (idem dengan strpos).
   * @param $element string
   *   Element html lengkap dengan startag, contents, dan endtag
   *   kecuali void element.
   * @param $search_elements array
   *   Variable pencarian per satu selector, merupakan hasil dari method
   *   translate_selector().
   * @param $looping int
   *   Internal only. Properti ini digunakan oleh developer saat debugging.
   */
  protected function find_element_each($position, $element, $search_elements, $looping) {
    // Lakukan modifikasi khusus pada kasus $element = $this->raw.
    // Jika terdapat tag ini pada awal html: <!DOCTYPE>
    // menyebabkan $contens = FALSE.
    if ($position === 0 && preg_match('/^\s*\<\!DOCTYPE[^>]*\>\s*/i', $this->raw, $matches)) {
      $position = strlen($matches[0]);
      $element = substr($this->raw, $position);
    }
    $storage = array();
    list($starttag, $contents, $endtag) = $this->parseElement($element);

    $starttag_length = strlen($starttag);
    // Mengambil satu pencarian element, dari kumpulan element yang akan dicari
    // secara descendet oleh variable $search_elements.
    if ($search_element = array_shift($search_elements)) {
      // Khusus selector direct seperti "ul > li", maka kita perlu
      // melakukan manipulasi element agar pencarian didapat.
      // Oleh karena itu kita mampir dulu ke method find_element_each_direct()
      // untuk nantinya akan kembali ke method ini.
      if ($search_element['direct']) {
        // Kembalikan lagi variable pencarian element ke
        // kumpulan pencarian element-element.
        array_unshift($search_elements, $search_element);
        // Oper ke method find_element_each_direct() untuk dilakukan
        // manipulasi.
        return $this->find_element_each_direct($position, $element, $search_elements, $looping);
      }

      // Mulai membedah dan mencari informasi pencarian element dengan tag
      // maupun dengan attribute.
      $tag = empty($search_element['tag']) ? '' : array_shift($search_element['tag']);
      $attributes = $search_element['attributes'];

      // Tiap informasi tag dan attribute yang didapat, akan ada method spesifik
      // yang akan digunakan.
      // Mulai mencari method yang tepat.
      if (!empty($tag)) {
        $callback = 'self::getElementByTag';
        $param_arr = array($tag, $contents);
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
              $param_arr = array($attribute['value'], $contents);
              break;

            case 'class':
              $callback = 'self::getElementByClass';
              $attribute['value'] = str_replace(' ', ' AND ', $attribute['value']);
              $param_arr = array($attribute['value'], $contents);
              break;

            default:
              if (empty($attribute['operator']) && empty($attribute['value'])) {
                $callback = 'self::getElementByAttribute';
                $param_arr = array($attribute['name'], $contents);
              }
              else {
                $conditions = $this->build_conditions(array($attribute));
                $param_arr = array($conditions, $contents);
              }
          }
        }
        else {
          $conditions = $this->build_conditions($attributes);
          $param_arr = array($conditions, $contents);
        }
      }

      // Method dan argument untuk dieksekusi telah didefinisikan,
      // dan siap dieksekusi.
      $results = call_user_func_array($callback, $param_arr);

      // Variable $result berisi informasi element-element berupa array
      // (atau array kosong jika tidak ditemukan) dimana
      // key merupakan posisi pointer relative terhadap contents element
      // dan value merupakan start tag.
      // Kita perlu menyesuaikan posisi pointer agar relative terhadap
      // keseluruhan dokumen html.
      $this->addPosition($results, $position + $starttag_length);
      // Kita juga perlu mengembangkan informasi element
      // dari awalnya hanya startag,
      // menjadi element lengkap yang terdiri dari starttag, contents,
      // endtag (kecuali void element).
      $this->constructElements($results, $this->raw);
      // Masukkan ke storage.
      $storage += $results;
    }

    // Informasi variable pencarian element-element pada
    // $search_elements kini telah berkurang satu.
    // Jika hasil pencarian ternyata element jamak, sementara variable
    // pencarian ($search_elements) secara descendent masih ada,
    // maka proses akan recursive dimana proses dimulai lagi
    // ke method find_elements() sampai variable pencarian habis.
    if ($search_elements) {
      return $this->find_elements($storage, $search_elements, ++$looping);
    }
    // Finish simpan ke storage.
    return $storage;
  }

  /**
   * Mengakomodir pencarian dengan selector direct children element.
   *
   * Method ini akan memanipulasi variable $element yang mungkin awalnya
   * terdiri dari banyak nested element menjadi hanya satu saja.
   *
   *
   */
  protected function find_element_each_direct($position, $element, $search_elements, $looping) {
    $storage = array();
    $childrens = $this->getElementChildren($position, $element);
    list($starttag, $contents, $endtag) = $this->parseElement($element);
    if ($childrens) {
      // Wajib mengganti direct menjadi FALSE,
      // atau unlimited looping.
      $search_elements[0]['direct'] = FALSE;
      // Mulai membuat pseudo element.
      foreach($childrens as $p => $children) {
        // Hitung jarak dari endtag parent ke starttag direct children.
        $space = $p - $position - strlen($starttag);
        $a = '';
        // Buat spasi sebagai pengganti jeda antara parent dan direct children.
        while($space-- > 0){
          $a .= ' ';
        }
        $pseudo_element = $starttag . $a . $children . $endtag;
        // Oper kembali ke method find_element_each().
        $result = $this->find_element_each($position, $pseudo_element, $search_elements, ++$looping);
        if ($result) {
          $storage += $result;
        }
      }
    }
    return $storage;
  }

  /**
   * Mendapatkan element children nested tepat satu level didalam.
   *
   * @param $position int
   *   Posisi dari parameter $element ke awal string $raw.
   *
   * @param $element string
   *   Element html yang terdiri dari starttag, content, dan endtag,
   *   kecuali void element yang hanya terdiri dari starttag.
   *
   * @param $auto_expand bool
   *   Jika TRUE, maka starttag yang didapat akan diexpand sehingga menjadi
   *   full element.
   *
   * Return
   *   Array satu dimensi, dimana keys merupakan "position", yakni
   *   jarak element dari awal string $raw (idem dengan strpos) dan value
   *   merupakan starttag atau element lengkap disesuaikan dengan parameter
   *   $auto_expand.
   *   Contoh:
   *
   *     $element =
   *       '<ul>
   *         <li><a>LINK 1</a></li>
   *         <li><a>LINK 2</a></li>
   *         <li><a>LINK 3</a></li>
   *       </ul>';
   *
   *   Hasil yang akan didapat adalah sebagai berikut:
   *
   *     $array = array(
   *       'x' => '<li>',
   *       'x' => '<li>',
   *       'x' => '<li>',
   *     );
   */
  protected function getElementChildren($position, $element, $auto_expand = FALSE) {
    // Parsing element.
    list($starttag, $contents, $endtag) = $this->parseElement($element);
    // Khusus void element, tidak diperlukan tree.
    if (!$starttag || !$contents) {
      return;
    }
    $offset = strlen($starttag);
    $storage = array();
    $find = '<';
    $length = strlen($find);
    $scoupe = $contents;
    $distance_lt = stripos($scoupe, $find);
    while ($distance_lt !== FALSE) {
      $child_starttag_lt_position = $distance_lt + $offset;
      if ($distance_rt = stripos($scoupe, '>')) {
        if ($distance_rt > $distance_lt) {
          $child_starttag_rt_position = $distance_rt + $offset;
          $child_starttag = substr($element, $child_starttag_lt_position, $child_starttag_rt_position + strlen('>') - $child_starttag_lt_position);
          $a = substr($child_starttag, 1, 1);
          if (substr($child_starttag, 1, 1) !== '/') {
            $this->constructElement($child_starttag_lt_position, $child_starttag, $element);
            $offset += $distance_lt + strlen($child_starttag);
            $scoupe = substr($element, $offset);
            if (!$auto_expand) {
              $this->destructElement($child_starttag);
            }
            $storage[$child_starttag_lt_position] = $child_starttag;
            $distance_lt = stripos($scoupe, $find);
            continue;
          }
        }
      }
      break;
    }
    $this->addPosition($storage, $position);
    return $storage;
  }

  /**
   * Mengubah informasi array attribute menjadi conditions.
   *
   * Array attribute didapat dari hasil translate selector css, sementara
   * conditions akan digunakan sebagai argument pada method
   * getElementByAttribute().
   */
  protected function build_conditions($attributes) {
    $implode = function ($var) {
      return implode(' ', $var);
    };
    $attributes = array_map($implode, $attributes);
    return implode(' AND ', $attributes);
  }

  /**
   * Menambah nilai informasi posisi yang berada pada key array.
   *
   * @param $elements array
   *   Merupakan "Array Elements Full" atau "Array Elements Starttag",
   *   lihat pada Definisi FAQ.
   * @param $add int
   *   Angka yang akan ditambah pada key dari parameter $elements.
   */
  protected function addPosition(&$elements, $add = 0 ) {
    if ($add === 0) {
      return;
    }
    $positions = array_keys($elements);
    foreach ($positions as &$position) {
      $position += $add;
    }
    $elements = array_combine($positions, $elements);
  }

  /**
   * Mengubah starttag menjadi full element.
   *
   * Mengubah element yang awalnya hanya start tag menjadi
   * lebih lengkap dengan content dan end tag. Untuk void element,
   * maka tidak akan ada perubahan.
   *
   * @param $starttags array
   *   Merupakan "Array Elements Starttag",
   *   lihat pada Definisi FAQ.
   * @param $html string
   *   String dengan format html.
   */
  protected function constructElements(&$starttags, $html) {
    // echo 'var_dump($starttag): '; var_dump($starttag);
    // return;
    // $mask = '/^<(?P<tag>\w+)\s*[^>]*>$/';
    foreach ($starttags as $starttag_lt_position => &$starttag) {
      // echo 'var_dump($starttag_lt_position): '; var_dump($starttag_lt_position);
      // echo 'BEFORE var_dump($starttag): '; var_dump($starttag);
      // echo 'BEFORE var_dump($starttag_lt_position): '; var_dump($starttag_lt_position);
      $this->constructElement($starttag_lt_position, $starttag, $html);
      // echo 'AFTER var_dump($starttag): '; var_dump($starttag);
    }
  }

  /**
   * Mengubah starttag menjadi full element.
   *
   * @param $starttag_lt_position int
   *   Jarak pointer ke awal string $html.
   * @param $starttag string
   *   Element starttag.
   * @param $html string
   *   String dengan format html.
   */
  protected function constructElement($starttag_lt_position, &$starttag, $html) {
    // echo 'var_dump($starttag_lt_position): '; var_dump($starttag_lt_position);
    // echo 'var_dump($starttag): '; var_dump($starttag);
    // Validate.
    $mask = '/^<(?P<tag>\w+)\s*[^>]*>$/';
    if (preg_match($mask, $starttag, $matches)) {
      $_starttag = '<' . $matches['tag'];
      $_starttag_length = strlen($_starttag);
      $_endtag = '</' . $matches['tag'] . '>';
      $_endtag_length = strlen($_endtag);
      $offset_starttag = $offset_endtag = $starttag_lt_position + $_starttag_length;
      $endtag_rt_position = FALSE;
      do {
        $distance_starttag = stripos($html, $_starttag, $offset_starttag);
        $distance_endtag = stripos($html, $_endtag, $offset_endtag);
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
          $offset_starttag = $distance_starttag + $_starttag_length;
          $offset_endtag = $distance_endtag + $_endtag_length;
        }
        else {
          $endtag_rt_position = $distance_endtag + $_endtag_length;
          break;
        }
      } while ($nested_exists);

      if ($endtag_rt_position !== FALSE) {
        $starttag = substr($html, $starttag_lt_position, $endtag_rt_position - $starttag_lt_position);
      }
    }
  }

  /**
   * Mengubah full element menjadi starttag.
   *
   * @param $elements array
   *   Merupakan "Array Elements Full",
   *   lihat pada Definisi FAQ.
   * @param $html string
   *   String dengan format html.
   */
  protected function destructElements(&$elements, $html) {
    foreach ($elements as $starttag_lt_position => &$element) {
      $this->destructElement($element);
    }
  }

  /**
   * Mengubah full element menjadi starttag.
   *
   * @param $element string
   *   Element full terdiri dari starttag, contents, dan endtag - kecuali
   *   void element.
   */
  protected function destructElement(&$element) {
    list($starttag, $contents, $endtag) = $this->parseElement($element);
    $element = $starttag;
  }

  /**
   * Memecah conditions dan dapatkan informasi attribute - attribute
   * yang ada dalam info conditions tersebut.
   */
  protected static function parse_conditions($conditions) {
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
    isset(self::$mask) or self::$mask = $mask;
    $fields = array();
    foreach($storage as $condition) {
      if (preg_match($mask, trim($condition), $capture)) {
        $fields[] = $capture[1];
      }
    }
    return $fields;
  }

  /**
   * Validasi seluruh attributes berdasarkan conditions.
   */
  protected static function _validate_attribute_conditions($row = array(), $conditions = null) {
    if (!empty($row)) {
      if (!empty($conditions)) {
        $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions);
        $or = '';
        foreach ($conditions as $key => $value) {
          if (strpos($value, ' AND ') !== false) {
            $value = explode(' AND ', $value);
            $and   = '';
            foreach ($value as $k => $v) {
              $and .= $a = self::_validate_attribute_condition($row, $v);
            }
            $or .= (strpos($and, '0') !== false) ? '0' : '1';
          }
          else {
            $or .= $a = self::_validate_attribute_condition($row, $value);
          }
        }
        return (strpos($or, '1') !== false) ? true : false;
      }
      return true;
    }
    return false;
  }

  /**
   * Validasi satu attributes per satu condition.
   */
  protected static function _validate_attribute_condition($row, $condition) {
    if (preg_match(self::$mask, trim($condition), $capture)) {
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

  /**
   * Filtering tambahan untuk attribute berdasarkan class.
   */
  private static function _getElementByClass($value, $start_tag) {
    $conditions = $value;
    $conditions = (strpos($conditions, ' OR ') !== FALSE) ? explode(' OR ', $conditions) : array($conditions);
    $attributes = self::extractAttributes($start_tag);
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

  /**
   * Filtering tambahan untuk attribute berdasarkan id.
   */
  private static function _getElementById($value, $start_tag) {
    $attributes = self::extractAttributes($start_tag);
    if ($attributes['id'] === $value) {
      return array(
        'break' => TRUE,
      );
    }
    return FALSE;
  }
}
