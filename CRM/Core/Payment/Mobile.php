<?php
/*
  +--------------------------------------------------------------------+
  | CiviCRM version 3.3                                                |
  +--------------------------------------------------------------------+
  | This file is a part of CiviCRM.                                    |
  |                                                                    |
  | CiviCRM is free software; you can copy, modify, and distribute it  |
  | under the terms of the GNU Affero General Public License           |
  | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
  |                                                                    |
  | CiviCRM is distributed in the hope that it will be useful, but     |
  | WITHOUT ANY WARRANTY; without even the implied warranty of         |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
  | See the GNU Affero General Public License for more details.        |
  |                                                                    |
  | You should have received a copy of the GNU Affero General Public   |
  | License and the CiviCRM Licensing Exception along                  |
  | with this program; if not, contact CiviCRM LLC                     |
  | at info[AT]civicrm[DOT]org. If you have questions about the        |
  | GNU Affero General Public License or the licensing of CiviCRM,     |
  | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
  +--------------------------------------------------------------------+
*/


/*
 * PxPay Functionality Copyright (C) 2008 Lucas Baker, Logistic Information Systems Limited (Logis)
 * PxAccess Functionality Copyright (C) 2008 Eileen McNaughton
 * Licensed to CiviCRM under the Academic Free License version 3.0.
 *
 * Grateful acknowledgements go to Donald Lobo for invaluable assistance
 * in creating this payment processor module
 */


require_once 'CRM/Core/Payment.php';
class CRM_Core_Payment_Mobile extends CRM_Core_Payment {
  CONST CHARSET = 'iso-8859-1';
  static protected $_mode = NULL;
  static protected $_params = array();

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = NULL;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct($mode, &$paymentProcessor) {

    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('DPS Payment Express');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   *
   */
  static function &singleton($mode, &$paymentProcessor) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === NULL) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_Mobile($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  /**
   * Setter for the payment form that wants to use the processor
   *
   * @param obj $paymentForm
   *
   */
  function setForm(&$paymentForm) {
    parent::setForm($paymentForm);
    // event registration doesn't use this...
  }

  function checkConfig() {
    $config = CRM_Core_Config::singleton();

    $error = array();

    if (empty($this->_paymentProcessor['user_name']) && strlen($this->_paymentProcessor['user_name']) == 0) {
      $error[] = ts('UserID is not set in the Administer CiviCRM &raquo; Payment Processor.');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  function setExpressCheckOut(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  function getExpressCheckoutDetails($token) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  function doExpressCheckout(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  function doDirectPayment(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  /**
   * Main transaction function
   *
   * @param array $params  name value pair of contribution data
   *
   * @return void
   * @access public
   *
   */
  function doTransferCheckout(&$params, $component) {
    if(!empty($this->_paymentForm->_params['civicrm_instrument_id'])){
      // civicrm_instrument_by_id($params['civicrm_instrument_id'], 'name');
      $options = array(1 => array( $this->_paymentForm->_params['civicrm_instrument_id'], 'Integer'));
      $instrument_name = CRM_Core_DAO::singleValueQuery("SELECT v.name FROM civicrm_option_value v INNER JOIN civicrm_option_group g ON v.option_group_id = g.id WHERE g.name = 'payment_instrument' AND v.is_active = 1 AND v.value = %1;", $options);
      $this->_instrumentType = strtolower($instrument_name);
    }

    $cid = $params['contributionID'];
    $iid = $params['civicrm_instrument_id'];
    if($cid && $iid){
      $options = array(
        1 => array($iid, 'Integer'),
        2 => array($cid, 'Integer'),
      );
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET payment_instrument_id = %1 WHERE id = %2", $options);
    }

    if($this->_instrumentType == 'linepay'){
      $this->_mobilePayment = new CRM_Core_Payment_LinePay($this->_paymentForm->_params['payment_processor']);
      $this->_mobilePayment->doRequest($params);
      return;
    }

    $qfKey = $params['qfKey'];
    $paymentProcessor = $this->_paymentProcessor;

    $provider_name = $paymentProcessor['password'];
    $module_name = 'civicrm_'.strtolower($provider_name);
    if ($this->_instrumentType != 'linepay' && module_load_include('inc', $module_name, $module_name.'.checkout') === FALSE) {
      CRM_Core_Error::fatal('Module '.$module_name.' doesn\'t exists.');
    }

    if(!empty($params['eventID'])){
      $event = new CRM_Event_DAO_Event();
      $event->id = $params['eventID'];
      $event->find(1);
      $page_title = $event->title;
    }else{
      $contribution_pgae = new CRM_Contribute_DAO_ContributionPage();
      $contribution_pgae->id = $params['contributionPageID'];
      $contribution_pgae->find(1);
      $page_title = $contribution_pgae->title;
    }

    $description = !empty($params['amount_level']) ? $page_title . ' - ' . $params['amount_level'] : $page_title;

    if($this->_paymentForm->_mode == 'test'){
      $is_test = 1;
    }else{
      $is_test = 0;
    }

    if($this->_instrumentType == 'applepay'){
      $smarty = CRM_Core_Smarty::singleton();
      $smarty->assign('after_redirect', 0);
      $payment_params = array(
        'cid' => $params['contributionID'],
        'provider' => $provider_name,
        'description' => $description,
        'amount' => $params['amount'],
        'qfKey' => $qfKey,
        'is_test' => $is_test,
      );
      if(!empty($params['participantID'])){
        $payment_params['pid'] = $params['participantID'];
      }
      if(!empty($params['eventID'])){
        $payment_params['eid'] = $params['eventID'];
      }
      $smarty->assign('params', $payment_params );
      $page = $smarty->fetch('CRM/Core/Payment/ApplePay.tpl');
      print($page);
      CRM_Utils_System::civiExit();
    }
  }

  static function checkout(){
    if($_POST['instrument'] == 'ApplePay'){
      $domain = CRM_Core_BAO_Domain::getDomain();
      $smarty = CRM_Core_Smarty::singleton();
      $smarty->assign('after_redirect', 1);
      $smarty->assign('organization', $domain->name);
      foreach ($_POST as $key => $value) {
        $smarty->assign($key, $value );
      }
      $page = $smarty->fetch('CRM/Core/Payment/ApplePay.tpl');
      CRM_Utils_System::setTitle(ts('Contribute Now'));
      // CRM_Utils_Hook::alterContent($page, 'page', $pageTemplateFile, $this);
      CRM_Utils_System::theme('page', $page);
    }
  }

  static function validate(){

    $contribution = new CRM_Contribute_DAO_Contribution();
    $contribution->id = $_POST['cid'];
    $contribution->find(TRUE);

    $paymentProcessor = new CRM_Core_DAO_PaymentProcessor();
    $paymentProcessor->id = $contribution->payment_processor_id;
    $paymentProcessor->find(TRUE);

    if(arg(2) == 'applepay'){
      // Refs: Document: [Requesting an Apple Pay Payment Session] https://goo.gl/CJAe4M
      $data = array(
        'merchantIdentifier' => $paymentProcessor->signature,
        'displayName' => 'test',
        'initiative' => 'web',
        'initiativeContext' => $_POST['domain_name'],
      );
      dd($data);
      $url = $_POST['validationURL'];
      /*
      $ch = curl_init($url);
      $opt = array();

      $cert = '/var/www/html/cert/apple-pay-cert.pem';
      // $key =  '/var/www/html/cert/neticrm.tw.key';
      // $opt[CURLOPT_SSLKEY] = $key;
      $opt[CURLOPT_SSLCERT] = $cert;

      $opt[CURLOPT_HTTPHEADER] = array(
        'Content-Type: application/json',
      );
      $opt[CURLOPT_RETURNTRANSFER] = TRUE;
      $opt[CURLOPT_POST] = TRUE;
      $opt[CURLOPT_POSTFIELDS] = $data;
      curl_setopt_array($ch, $opt);
      */

      $cmd = 'curl --request POST --url "'.$url.'" --cert /var/www/html/cert/apple-pay-cert.pem -H "Content-Type: application/json" --data "'. json_encode($data).'"';
      dd($cmd);
      $result = exec($cmd);

      // $result = curl_exec($ch);
      dd($result);
    }

    echo $result;
    CRM_Utils_System::civiExit();
  }

  static function transact(){



    if($merchantPaymentProcessor->payment_processor_type == 'SPGATEWAY'){
      module_load_include('inc', 'civicrm_spgateway', 'civicrm_spgateway.checkout');
      $result = civicrm_spgateway_do_transfer_checkout(&$vars, &$component, &$merchantPaymentProcessor, $is_test);
      

    }

    $type = 'applepay';
    $contribution = new CRM_Contribute_DAO_Contribution();
    $contribution->id = $_POST['cid'];
    $contribution->find(TRUE);
    dd($contribution);

    $email = new CRM_Core_DAO_Email();
    $email->contact_id = $contribution->contact_id;
    $email->is_primary = true;
    $email->find(TRUE);

    $paymentProcessor = new CRM_Core_DAO_PaymentProcessor();
    $paymentProcessor->id = $contribution->payment_processor_id;
    $paymentProcessor->find(TRUE);

    $merchantPaymentProcessor = new CRM_Core_DAO_PaymentProcessor();
    $merchantPaymentProcessor->id = $paymentProcessor->user_name;
    $merchantPaymentProcessor->id = 8;
    $merchantPaymentProcessor->find(TRUE);

    if($merchantPaymentProcessor->payment_processor_type == 'SPGATEWAY'){
      // module_load_include('inc', 'civicrm_spgateway', 'civicrm_spgateway.checkout');
      // civicrm_spgateway_do_transfer_checkout(&$vars, &$component, &$merchantPaymentProcessor, $is_test);
      dd('SPGATEWAY');
      dd($_POST);

      $token = json_decode($_POST['token']);
      dd($token);

      $params = array(
        'TimeStamp' => time(),
        'Version' => '1.0',
        'MerchantOrderNo' => _civicrm_spgateway_trxn_id($is_test, $contribution->id).rand(0,10000),
        'Amt' => $contribution->total_amount,
        'ProdDesc' => $_POST['description'], 
        'PayerEmail' => $email->email,
        'CardNo' => '',
        'Exp' => '',
        'CVC' => '',
        'APPLEPAY' => urlencode($token->data),
        'APPLEPAYTYPE' => '02',
      );
      dd($params);

      $data = _civicrm_spgateway_recur_encrypt(http_build_query($params), get_object_vars($merchantPaymentProcessor));
      dd($merchantPaymentProcessor);

      $data = array(
        'MerchantID_' => $merchantPaymentProcessor->user_name,
        'PostData_' => $data,
        'Pos_' => 'JSON',
      );
      dd($data);
      $url = 'https://ccore.spgateway.com/API/CreditCard';

      $ch = curl_init($url);
      $opt = array();
      $opt[CURLOPT_RETURNTRANSFER] = TRUE;
      $opt[CURLOPT_POST] = TRUE;
      $opt[CURLOPT_POSTFIELDS] = $data;
      curl_setopt_array($ch, $opt);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($result === FALSE) {
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $curlError = array($errno => $err);
      }
      else{
        $curlError = array();
      }
      curl_close($ch);

      // $cmd = 'curl --request POST --url "'.$url.'" -H "Content-Type: application/json" --data "'. json_encode($data).'"';
      // dd($cmd);
      // $result = exec($cmd);
      dd(json_decode($result));
      echo $result;
      exit;
    }




    if(strtolower($paymentProcessor->password) == 'neweb'){
      $type = 'applepay';
      $contribution = new CRM_Contribute_DAO_Contribution();
      $contribution->id = $_POST['cid'];
      $contribution->find(TRUE);

      $paymentProcessor = new CRM_Core_DAO_PaymentProcessor();
      $paymentProcessor->id = 8;
      // $paymentProcessor->id = $contribution->payment_processor_id;
      $paymentProcessor->find(TRUE);

      $data = array(
        'userid' => $paymentProcessor->signature,
        'passwd' => $paymentProcessor->subject,
        'merchantnumber' => $paymentProcessor->user_name,
        'ordernumber' => $contribution->id,
        'applepay_token' => $_POST['applepay_token'],
        'depositflag' => 0,
        'consumerip' => CRM_Utils_System::ipAddress(),
      );

      if(empty($paymentProcessor->url_site)){
        module_load_include("inc", 'civicrm_neweb', 'civicrm_neweb.checkout');
        if(function_exists('_civicrm_neweb_get_mobile_params')){
          $payment_params = _civicrm_neweb_get_mobile_params();
        }else{
          $error = "Can't get params from module when transact: civicrm_neweb";
          CRM_Core_Error::debug_log_message($error);
          $note .= $error;
          CRM_Core_Payment_Mobile::addNote($note, $contribution);
        }

        $_test = $_POST['is_test']? '_test' : '';
        $url = $payment_params['transact_url'.$_test];
      }else{
        $url = preg_replace('/\/$/', '', trim($paymentProcessor->url_site)).'/ccaccept';
      }
      $cmd = 'curl --request POST --url "'.$url.'" -H "Content-Type: application/json" --data "'. json_encode($data).'"';

      $record = array(
        'cid' => $contribution->id,
        'post_data_transact' => $cmd,
      );
      drupal_write_record('civicrm_contribution_neweb', $record, 'cid');

      $result = exec($cmd);

      $record = array(
        'cid' => $contribution->id,
        'return_data' => $result,
      );
      $result = json_decode($result);

      $record['created'] = time();
      $record['prc'] = $result->prc;
      $record['src'] = $result->src;
      $record['bankrc'] = $result->bankresponsecode;
      $record['approvalcode'] = $result->approvalcode;
      drupal_write_record('civicrm_contribution_neweb', $record, 'cid');
      $is_success = ($result->prc == 0 && $result->src == 0);
      $participant_id = $_POST['pid'];
      $event_id = $_POST['eid'];
    }

    // ipn transact
    $ipn = new CRM_Core_Payment_BaseIPN();
    $input = $ids = $objects = array();
    if(!empty($participant_id) && !empty($event_id)){
      $input['component'] = 'event';
      $ids['participant'] = $participant_id;
      $ids['event'] = $event_id;
    }else{
      $input['component'] = 'contribute';
    }
    $ids['contribution'] = $contribution->id;
    $ids['contact'] = $contribution->contact_id;
    $validate_result = $ipn->validateData($input, $ids, $objects, FALSE);
    if($validate_result){
      $transaction = new CRM_Core_Transaction();
      if($is_success){
        $input['payment_instrument_id'] = $contribution->payment_instrument_id;
        $input['amount'] = $contribution->amount;
        $objects['contribution']->receive_date = date('YmdHis');
        $transaction_result = $ipn->completeTransaction($input, $ids, $objects, $transaction);

        $result = array('is_success' => 1);
      }else{
        $ipn->failed($objects, $transaction, $error);
        $note .= $error . "Prc: {$result_object->prc}, Src: {$result_object->src}";
        self::addNote($note, $contribution);
        $result = array('is_success' => 0);
      }
    }

    if($type == 'applepay'){
      echo json_encode($result);
      CRM_Utils_System::civiExit();
    }
  }

  static function addNote($note, &$contribution){
    require_once 'CRM/Core/BAO/Note.php';
    $note = date("Y/m/d H:i:s "). ts("Transaction record").": \n\n".$note."\n===============================\n";
    $note_exists = CRM_Core_BAO_Note::getNote( $contribution->id, 'civicrm_contribution' );
    if(count($note_exists)){
      $note_id = array( 'id' => reset(array_keys($note_exists)) );
      $note = $note . reset($note_exists);
    }
    else{
      $note_id = NULL;
    }
    $noteParams = array(
      'entity_table'  => 'civicrm_contribution',
      'note'          => $note,
      'entity_id'     => $contribution->id,
      'contact_id'    => $contribution->contact_id,
      'modified_date' => date('Ymd')
    );
    CRM_Core_BAO_Note::add( $noteParams, $note_id );
  }

  static function getAdminFields($ppDAO){
    $text = ts('If the provider needs server IP address, the IP address of this website is %1', array(1 => gethostbyname($_SERVER['SERVER_NAME'])));
    CRM_Core_Session::setStatus($text);
    return array(
      array('name' => 'user_name',
        'label' => $ppDAO->user_name_label,
      ),
      array('name' => 'password',
        'label' => $ppDAO->password_label,
      ),
      array('name' => 'signature',
        'label' => $ppDAO->signature_label,
      ),
      array('name' => 'subject',
        'label' => $ppDAO->subject_label,
      ),
      array('name' => 'url_site',
        'label' => ts('LinePay Channel ID'),
      ),
      array('name' => 'url_api',
        'label' => ts('LinePay Channel Secret Key'),
      ),
    );
  }
}

