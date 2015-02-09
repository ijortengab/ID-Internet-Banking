<?php
/**
 * @file
 *   BNI.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */
class BNI extends browser {

  protected $finish = FALSE;

  protected $step = 'step_visit_home';

  protected $storage = array();

  // protected $autolooping = FALSE;
  protected $autolooping = TRUE;

  protected $step_end = '';//step_visit_link_informasi_saldo

  // CRUD of property $options.
  public function storage() {
    $args = func_get_args();
    return $this->propertyArray('storage', $args);
  }

  function __construct() {
    // Execute parent.
    $args = func_get_args();
    call_user_func_array('parent::__construct', $args);

    // Custom for BNI only.
    $this->setCwd(__DIR__ . DIRECTORY_SEPARATOR . 'BNI', TRUE);
  }

  function browse($url = NULL) {
    try {    
      $user_agent = $this->getState('bni_user_agent');
      if (empty($user_agent)) {
        $user_agent = $this->getUserAgent(array('mobile' => TRUE));
        $this->setState('bni_user_agent', $user_agent);
      }
      $this->headers('User-Agent', $user_agent)
           ->options('referer_retrieve', TRUE)
           ->options('referer_save', TRUE)
           ->options('cookie_retrieve', TRUE)
           ->options('cookie_save', TRUE)
           ->options('cache_save', TRUE)
           ->options('history_save', TRUE)
           ->options('follow_location', TRUE)
           ->curl(FALSE)
      ;
      // Execute parent.
      parent::browse($url);
      if ((isset($this->result) && $this->result->code == 200) == FALSE) {
        throw new Exception('Gagal Browsing.');
      }

      // $this->storage('bni_cache', $this->cache)
           // ->storage('bni_cache_expired', date('Y-m-d H:i:s O', strtotime('+5 minutes')))
      // ;
      $states = array(
        'bni_cache' => $this->cache,
        'bni_cache_expired' => date('Y-m-d H:i:s O', strtotime('+5 minutes')),
      );
      $this->setState($states);


      // exit;



      // Beri jeda setelah browsing agar tidak membebani server.
      usleep(500000);
      // Everything's OK, so send happy.
      return TRUE;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function execute() {
    // Limit execution to 20 seconds.
    $timer = new timer(28);
    $x = 0;
    do {
      $x++;
      // $debugname = 'x'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);
      $this->run_steps();
      // break disini.
      if ($this->finish) {
        break;
      }
      if (!$this->autolooping) {
        break;
      }
    }
    while ($timer->countdown() > 0);
  }

  function run_steps() {
    try {
      $is_delay = $this->getState('bni_delay_execute', FALSE);
      if ($is_delay) {
        $is_delay_expired = time() > strtotime($this->getState('bni_delay_expired'));
        if ($is_delay_expired) {
          $this->setState('bni_delay_execute', FALSE);
        }
        else {
          $this->finish = TRUE;
          throw new Exception('Sedang delay, tunggu beberapa saat lagi.');
        }
      }
      else {
        $this->run_step();
        // Cek apakah error terjadi.
        if (!empty($this->error)) {
          $this->finish();
        }
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function run_step() {
    // $step = $this->step;
    $step = $this->getState('bni_step', 'step_visit_home');
    // $debugname = 'step'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);
    $this->{$step}();
    if ($this->step_end == $step) {
      $this->finish();
    }
  }

  function reset_browser() {
    // Reset
    $this->post(NULL)
         // ->options(NULL)
         // ->post(NULL)
    ;
  }

  function step_visit_home() {
    $default_url = 'https://ibank.bni.co.id';
    $url = $this->getState('bni_url_home', $default_url);
    if ($this->browse($url)) {
      // $this->step = 'step_visit_link_entry';
      $this->setState('bni_step', 'step_visit_link_entry');
    }
  }

  function step_visit_link_entry() {
    try {
      $html = $this->get_cache();
      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }
      $url = $html->find('#RetailUser')->attr('href');
      if (!isset($url)) {
        throw new Exception('Url entry tidak ditemukan.');
      }
      if ($this->browse($url)) {
        $this->setState('bni_step', 'step_login');
        // $this->step = 'step_login';
        $this->reset_browser();
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function step_login() {
    try {
      $html = $this->get_cache();
      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }
      $url = $html->find('form')->attr('action');
      $fields = $html->find('form')->extractForm();
      $fields['__AUTHENTICATE__'] = 'Login';
      $fields['CorpId'] = internetBankingID::$username;
      $fields['PassWord'] = internetBankingID::$password;
      if ($this->post($fields)->browse($url)) {
        // $this->step = 'step_after_login';
        $this->post(NULL)->setState('bni_step', 'step_after_login');
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function step_after_login() {
    // Cek apakah username dan password itu tidak valid.
    try {
      $html = $this->get_cache();
      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }
      $test = $html->find('#Display_MConError');
      if ($test->length != 0) {
        // Berarti error.
        $text = $test->text();
        throw new Exception('Login gagal. Pesan dari bank:' . $text);
      }
      // $this->step = 'step_visit_link_rekening';
      $this->setState('bni_step', 'step_visit_link_rekening');
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function step_visit_link_rekening() {
    // Cek apakah username dan password itu tidak valid.
    try {
      $html = $this->get_cache();
      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }
      // Cari link ke rekening.
      $url = $html->find('td a')->eq(0)->attr('href');
      if (!isset($url)) {
        throw new Exception('Url rekening tidak ditemukan.');
      }
      if ($this->browse($url)) {
        // $this->step = 'step_visit_link_informasi_saldo';
        $this->setState('bni_step', 'step_visit_link_informasi_saldo');
        // $this->reset_browser();
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function step_visit_link_informasi_saldo() {
    try {
      $html = $this->get_cache();
      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }
      $url = $html->find('td a')->eq(0)->attr('href');
      if (!isset($url)) {
        throw new Exception('Url informasi saldo tidak ditemukan.');
      }
      if ($this->browse($url)) {
        // $this->step = 'step_select_tipe_rekening';
        $this->setState('bni_step', 'step_select_tipe_rekening');
        // $this->reset_browser();
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function step_select_tipe_rekening() {
    try {

      $html = $this->get_cache();
      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }
      $fields = $html->find('form')->extractForm();
      $submit = $html->find('form')->extractForm('input[type=submit]');
      $keep = 'AccountIDSelectRq';
      // Buang semua input submit kecuali $keep.
      unset($submit[$keep]);
      $fields = array_diff_assoc($fields, $submit);
      // Pilih pada Tabungan dan Giro dengan value = OPR.
      $fields['MAIN_ACCOUNT_TYPE'] = 'OPR';
      $url = $html->find('form')->attr('action');
      if ($this->post($fields)->browse($url)) {
        // $this->step = 'step_select_nomor_rekening';
        $this->post(NULL)->setState('bni_step', 'step_select_nomor_rekening');
        // $this->reset_browser();
      }

    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function step_select_nomor_rekening() {
    try {

      // $this->post(NULL);
      // $debugname = 'a'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);


      $html = $this->get_cache();

      // $debugname = 'html'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);

      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }

      $fields = $html->find('form')->extractForm();
      $submit = $html->find('form')->extractForm('input[type=submit]');
      // Buang semua input submit kecuali $keep.
      $keep = 'BalInqRq';
      unset($submit[$keep]);
      $fields = array_diff_assoc($fields, $submit);
      $url = $html->find('form')->attr('action');


      if ($this->post($fields)->browse($url)) {
        // $this->step = 'step_get_saldo';
        $this->post(NULL)->setState('bni_step', 'step_get_saldo');
        // $this->reset_browser();
      }
      // $this->finish();
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function step_get_saldo() {
    try {
      $html = $this->get_cache();
      if (!$html) {
        throw new Exception('Gagal mendapatkan cache.');
      }
      // $debugname = 'html'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);
      // exit;
      $table = $html->find('table[id~=BalanceDisplayTable]')->eq(1);
      // $debugname = 'table'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);

      $span = $table->find('tr#Row5_5 td#Row5_5_column2 div > span');
      // $debugname = 'span'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);

      $saldo = $span->text();
      // $debugname = 'saldo'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);

      // echo $saldo;
      $debugname = 'saldo'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);

      $this->finish(TRUE);
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function finish($reset = FALSE) {
    $this->finish = TRUE;
    $states = array(
      'bni_delay_execute' => TRUE,
      // 'bni_delay_expired' => date('Y-m-d H:i:s O', strtotime('+1 minutes')),
      // 'bni_delay_expired' => date('Y-m-d H:i:s O', strtotime('+10 seconds')),
      'bni_delay_expired' => date('Y-m-d H:i:s O', strtotime('+30 seconds')),
    );
    if ($reset) {
      $states['bni_step'] = NULL;
    }
    $this->setState($states);
  }

  function get_cache() {
    try {

      // $a = 'C:\\xampp\\htdocs\\ID-Internet-Banking\\classes\\BNI\\cache_6.html';
      // $this->storage('bni_cache', $a)
           // ->storage('bni_cache_expired', date('Y-m-d H:i:s O', strtotime('+5 minutes')))
      // ;

      // Pastikan file ada dan bisa kebaca karena ada kasus
      // dimana file ternyata tidak bisa kebaca karena settingan
      // otomatis oleh server.

      // $file_cache = $this->storage('bni_cache');
      // $cache_expired = strtotime($this->storage('bni_cache_expired'));
      $file_cache = $this->getState('bni_cache');
      $cache_expired = strtotime($this->getState('bni_cache_expired'));

      if (!file_exists($file_cache)) {
        throw new Exception('File cache tidak ditemukan.');
      }
      if (!is_readable($file_cache)) {
        throw new Exception('File cache tidak bisa terbaca.');
      }

      if (time() > $cache_expired) {
        throw new Exception('Cache expires.');
      }
      $contents = file_get_contents($file_cache);
      return new parseHTML($contents);
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }
}
