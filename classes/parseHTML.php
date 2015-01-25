<?php
/**
 * @file
 *   parseHTML.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/classes
 */
class parseHTML {


  var $html;
  
  var $file;
  
  // Menyimpan hasil regex untuk referensi.
  var $current_regex;

  function __construct($input = NULL) {
    if (!empty($input)) {
      // todo, pastikan dilakukan try and catch
      // saat reading data dll.
      if (is_readable($input)) {
        $this->file = $input;
        $this->html = file_get_contents($input);
      }
      elseif (is_string($input)) {
        $this->html = $input;
      }
      else {
        return FALSE;
      }
    }
  }
  /**
   * Retrieve the tags.
   *
   * @param $tag string
   *   The tag html, that you want to get.
   *   Example: "a", "form".
   *
   * @param $attributes mixed
   *   Simple 1 Dimentional array, with numeric key.
   *   The list value that you need to result in return.
   *   Example: array('title', 'id');
   *   or a string if you just want one attribute to result in return.
   *
   * @return
   *   The list of tag as an array.
   */
  function get_tag($tag, $attributes = NULL) {
    $html = $this->html;
    // Minimal perlu ada satu attributes.
    if(is_null($attributes)){
      $attributes = array('title');
    }
    if(is_string($attributes)){
      $attributes = (array) $attributes;
    }
    $mask = '';
    $_mask = array();
    foreach($attributes as $attribute){
      // Value bisa kosong "" atau ''.
      $_mask[] = $attribute . '=["\'](?P<' . $attribute . '>[^"\'<>]+|.?)["\']'; 
    }
    $_mask[] = '\w+=["\'][^"\'<>]+["\']';
    $mask .= '/<' . $tag . '(?:\s+(?:';
    $mask .= implode('|', $_mask);
    $mask .= '))+/ix';
    $this->current_regex = $mask;
    $result = preg_match_all($mask, $html, $match, PREG_SET_ORDER);
    return $match;
  }
  
  function getLinkById($id) {
    $tags = $this->get_tag('a', array('href', 'id'));
    foreach ($tags as $tag) {
      if (!empty($tag['href']) && $tag['id'] == $id) {
        return $tag['href'];
      }
    }
    // print_r($this->current_regex);
    // print_r($a);
  }

}
