<?php
/**
* Roundcube-YubiKey-plugin
*
* This plugin enables YubiKey authentication within Roundcube webmail against 
* the YubiKey web service API.
*
* @author Danny Fullerton <northox@mantor.org>
* @license GPL2
*
* Acknowledgement: This code is based on work done by Oliver Martin which was
* using patches from dirkm.
*
*/

require_once('lib/Yubico.php');

class roundcube_yubikey_authentication extends rcube_plugin
{
  private function is_enabled()
  {
    $r = ($this->get('yubikey') === true);
    return $r;
  }
  
  private function is_required()
  {
    $r = ($this->get('yubikey_required') == 'on');
    return $r;
  }
 
  private function disallow_change()
  {
    $r = false;
    if ($this->get('yubikey_disallow_user_changes') === true) { 
      $r = ($this->is_required() && strlen($this->get('yubikey_id')) == 12);
    }
    
    return $r;
  }
  
  private function get($v)
  {
    return rcmail::get_instance()->config->get($v);
  }
 
  // TODO add error message
  private function fail()
  {
    rcmail::get_instance()->logout_actions();
    rcmail::get_instance()->kill_session();
  } 

  function init()
  {
    $this->load_config();

    // minimal configuration validation
    $id = $this->get('yubikey_api_id');
    $key = $this->get('yubikey_api_key');
    if ($this->is_enabled() && (empty($id) || empty($key))) 
      throw new Exception('yubikey_api_id and yubikey_api_key must be set');
    
    $this->add_texts('localization/', true);

    $this->add_hook('preferences_list', array($this, 'preferences_list'));
    $this->add_hook('preferences_save', array($this, 'preferences_save'));
    $this->add_hook('template_object_loginform', array($this, 'update_login_form'));
    $this->add_hook('login_after', array($this, 'login_after'));
  }

  function update_login_form($p)
  {
    if ($this->is_enabled())
      $this->include_script('yubikey.js');

    return $p;
  }

  function login_after($args)
  {
    if (!$this->is_enabled() || !$this->is_required()) return $args;
	
	$ip =  rcube_utils::remote_addr();
	if (strpos($ip, "192.168.") !== FALSE) {
		// !== is on purpose ("not identical" operator in php)
		// If the remote IP matches 192.168., don't require OTP
		return $args;
	}
 
    $otp = rcube_utils::get_input_value('_yubikey', rcube_utils::INPUT_POST);
    $id = $this->get('yubikey_id');
    $id2 = $this->get('yubikey_id2');
    $id3 = $this->get('yubikey_id3');
    $url = $this->get('yubikey_api_url');
    $https = true;
    if (!empty($url) && $_url = parse_url($url)) {
      if ($_url['scheme'] == "http") $https = false;
      $urlpart = $_url['host'];
      if (!empty($_url['port'])) $urlpart .= ':'.$_url['port'];
      $urlpart .= $_url['path'];
    }
 
    // make sure that there is a YubiKey ID in the user's prefs
    // and that it matches the first 12 characters of the OTP

    if (empty($id) && empty($id2) && empty($id3))
    {
      $this->fail();
    }
    if (substr($otp, 0, 12) !== $id && substr($otp, 0, 12) !== $id2 && substr($otp, 0, 12) !== $id3 )
    {
      $this->fail();
    }
    else
    {
      try
      {
        $yubi = new Auth_Yubico(
          $this->get('yubikey_api_id'), 
          $this->get('yubikey_api_key'), 
          $https,
          true
        );
        
        if (!empty($urlpart)) $yubi->addURLpart($urlpart);       
        $yubi->verify($otp);
      }
      catch(Exception $e)
      {
        $this->fail();
      }
    }

    return $args;
  }
 
  function preferences_list($args)
  {
    if ($args['section'] != 'server' || !$this->is_enabled()) return $args;
    
    $disabled = $this->disallow_change();
 
    // add checkbox to enable/disable YubiKey auth for the current user
    $chk_yubikey = new html_checkbox(
      array(
        'name'     => '_yubikey_required',
        'id'       => 'rcmfd_yubikey_required',
        'disabled' => $disabled
      )
    );
    $args['blocks']['main']['options']['yubikey_required'] = array(
      'title' => html::label(
        'rcmfd_yubikey_required', 
        rcube::Q($this->gettext('yubikeyrequired'))
      ), 
      'content' => $chk_yubikey->show(!$this->is_required()) // TODO this is weird
    );

    // add inputfield for the YubiKey id
    $input_yubikey_id = new html_inputfield(
      array(
        'name'     => '_yubikey_id', 
        'id'       => 'rcmfd_yubikey_id', 
        'size'     => 12,
        'disabled' => $disabled
      )
    );
    $args['blocks']['main']['options']['yubikey_id'] = array(
      'title' => html::label(
        'rcmfd_yubikey_id', 
        rcube::Q($this->gettext('yubikeyid'))
      ),
      'content' => $input_yubikey_id->show($this->get('yubikey_id'))
    );

    // add inputfield for the YubiKey id2
    $input_yubikey_id2 = new html_inputfield(
      array(
        'name'     => '_yubikey_id2', 
        'id'       => 'rcmfd_yubikey_id2', 
        'size'     => 12,
        'disabled' => $disabled
      )
    );
    $args['blocks']['main']['options']['yubikey_id2'] = array(
      'title' => html::label(
        'rcmfd_yubikey_id2', 
        rcube::Q($this->gettext('yubikeyid2'))
      ),
      'content' => $input_yubikey_id2->show($this->get('yubikey_id2'))
    );

        // add inputfield for the YubiKey id3
    $input_yubikey_id3 = new html_inputfield(
      array(
        'name'     => '_yubikey_id3', 
        'id'       => 'rcmfd_yubikey_id3', 
        'size'     => 12,
        'disabled' => $disabled
      )
    );
    $args['blocks']['main']['options']['yubikey_id3'] = array(
      'title' => html::label(
        'rcmfd_yubikey_id3', 
        rcube::Q($this->gettext('yubikeyid3'))
      ),
      'content' => $input_yubikey_id3->show($this->get('yubikey_id3'))
    );
 
    return $args;
  }

  function preferences_save($args)
  {
    if (!$this->is_enabled()) return $args;
    
    if ($this->disallow_change())
    {
      // use values already saved earlier
      $args['prefs']['yubikey_required'] = true;
      $args['prefs']['yubikey_id']       = $this->get('yubikey_id');
      $args['prefs']['yubikey_id2']       = $this->get('yubikey_id2');
      $args['prefs']['yubikey_id3']       = $this->get('yubikey_id3');
    }
    else {
      // use newly posted values
      $args['prefs']['yubikey_required'] = isset($_POST['_yubikey_required']);
      $args['prefs']['yubikey_id']       = substr($_POST['_yubikey_id'], 0, 12);
      $args['prefs']['yubikey_id2']       = substr($_POST['_yubikey_id2'], 0, 12);
      $args['prefs']['yubikey_id3']       = substr($_POST['_yubikey_id3'], 0, 12);
    }
    
    return $args;
  }
}
?>
