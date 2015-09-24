<?php

define("__MerchantNumber__","123456");
define("__MerchantPass__","xxxx");


require_once 'CiviTest/CiviUnitTestCase.php';

class CRM_Core_Payment_NewebTest extends CiviUnitTestCase {
  public $DBResetRequired = FALSE;
  protected $_apiversion;
  protected $_processor;
  protected $_is_test;
  protected $_page_id;

  /**
   *  Constructor
   *
   *  Initialize configuration
   */
  function __construct() {
    // test if drupal bootstraped
    if(!defined('DRUPAL_ROOT')){
      die("You must exprot DRUPAL_ROOT for bootstrap drupal before test.");
    }
    if(!module_exists('civicrm_neweb')){
      die("You must enable civicrm_neweb module first before test.");
    }
    $payment_page = variable_get('civicrm_demo_payment_page', array('Payment_Neweb' => 1));
    $class_name = 'Payment_Neweb';
    if(isset($payment_page[$class_name])){
      $this->_page_id = $payment_page[$class_name];
    }
    parent::__construct();
  }

  function get_info() {
    return array(
     'name' => 'Neweb payment processor',
     'description' => 'Test Neweb payment processor.',
     'group' => 'Payment Processor Tests',
    );
  }

  function setUp() {
    parent::setUp();

    $this->_is_test = 1;

    // get processor
    $params = array(
      'version' => 3,
      'class_name' => 'Payment_Neweb',
      'is_test' => $this->_is_test,
    );
    $result = civicrm_api('PaymentProcessor', 'get', $params);
    $this->assertAPISuccess($result);
    if(empty($result['count'])){
      $payment_processors = array();
      $params = array(
        'version' => 3,
        'class_name' => 'Payment_Neweb',
      );
      $result = civicrm_api('PaymentProcessorType', 'get', $params);
      $this->assertAPISuccess($result);
      if(!empty($result['count'])){
        $domain_id = CRM_Core_Config::domainID();
        foreach($result['values'] as $type_id => $p){
          $payment_processor = array(
            'version' => 3,
            'domain_id' => $domain_id,
            'name' => 'AUTO payment '.$p['name'],
            'payment_processor_type_id' => $type_id,
            'payment_processor_type' => $p['name'],
            'is_active' => 1,
            'is_default' => 0,
            'is_test' => 0,
            'user_name' => !empty($p['user_name_label']) ? __MerchantNumber__ : NULL,
            'password' => !empty($p['password_label']) ? __MerchantNumber__ : NULL,
            'signature' => !empty($p['signature_label']) ? __MerchantPass__ : NULL,
            'subject' => !empty($p['subject_label']) ? __MerchantPass__ : NULL,
            'url_site' => !empty($p['url_site_default']) ? $p['url_site_default'] : NULL,
            'url_api' => !empty($p['url_api_default']) ? $p['url_api_default'] : NULL,
            'url_recur' => !empty($p['url_site_default']) ? $p['url_site_default'] : NULL,
            'class_name' => $p['class_name'],
            'billing_mode' => $p['billing_mode'],
            'is_recur' => $p['is_recur'],
            'payment_type' => $p['payment_type'],
          );
          $result = civicrm_api('PaymentProcessor', 'create', $payment_processor);
          $this->assertAPISuccess($result);
          if(is_numeric($result['id'])){
            $ftp = array();
            $ftp['ftp_host'] = '127.0.0.1';
            $ftp['ftp_user'] = 'user'; 
            $ftp['ftp_password'] = __MerchantPass__;
            variable_set("civicrm_neweb_ftp_".$result['id'], $ftp);
            $payment_processors[] = $result['id'];
          }

          $payment_processor['is_test'] = 1;
          $payment_processor['url_site'] = !empty($p['url_site_test_default']) ? $p['url_site_test_default'] : NULL;
          $payment_processor['url_api'] = !empty($p['url_api_test_default']) ? $p['url_api_test_default'] : NULL;
          $payment_processor['url_recur'] = !empty($p['url_site_test_default']) ? $p['url_site_test_default'] : NULL;
          $result = civicrm_api('PaymentProcessor', 'create', $payment_processor);
          if(is_numeric($result['id'])){
            variable_set("civicrm_neweb_ftp_test_".$result['id'], $ftp);
            $payment_processors[] = $result['id'];
          }
          $this->assertAPISuccess($result);
        }
      }
    }
    $params = array(
      'version' => 3,
      'class_name' => 'Payment_Neweb',
      'is_test' => $this->_is_test,
    );
    $result = civicrm_api('PaymentProcessor', 'get', $params);
    $this->assertAPISuccess($result);
    $pp = reset($result['values']);
    $this->_processor = $pp;

    // get cid
    $params = array(
      'version' => 3,
      'options' => array(
        'limit' => 1,
      ),
    );
    $result = civicrm_api('Contact', 'get', $params);
    $this->assertAPISuccess($result);
    if(!empty($result['count'])){
      $this->_cid = $result['id'];
    }

    // load drupal module file
    $loaded = module_load_include('inc', 'civicrm_neweb', 'civicrm_neweb.extern');
  }

  function tearDown() {
    $this->_processor = NULL;
  }

  function testSinglePaymentNotify(){
    $now = time();
    $amount = 111;

    // create contribution
    $contrib = array(
      'trxn_id' => $contribution->id,
      'contact_id' => $this->_cid,
      'contribution_contact_id' => $this->_cid,
      'contribution_type_id' => 1,
      'contribution_page_id' => $this->_page_id,
      'payment_processor_id' => $this->_processor['id'],
      'payment_instrument_id' => 1,
      'created_date' => date('YmdHis', $now),
      'non_deductible_amount' => 0,
      'total_amount' => $amount,
      'currency' => 'TWD',
      'cancel_reason' => '0',
      'source' => 'AUTO: unit test',
      'contribution_source' => 'AUTO: unit test',
      'amount_level' => '',
      'is_test' => $this->_is_test,
      'is_pay_later' => 0,
      'contribution_status_id' => 2,
    );
    $contribution = CRM_Contribute_BAO_Contribution::create($contrib, CRM_Core_DAO::$_nullArray);
    $this->assertNotEmpty($contribution->id, "In line " . __LINE__);
    $params = array(
      'is_test' => $this->_is_test,
      'id' => $contribution->id,
    );
    $this->assertDBState('CRM_Contribute_DAO_Contribution', $contribution->id, $params);

    // manually trigger ipn
    
    $get = $post = $ids = array();
    $ids = CRM_Contribute_BAO_Contribution::buildIds($contribution->id);
    $query = CRM_Contribute_BAO_Contribution::makeNotifyUrl($ids, NULL, $return_query = TRUE);
    parse_str($query, $get);
    $_POST = array(
      'MerchantNumber'=>__MerchantNumber__,
      'OrderNumber'=>$contribution->id,
      'Amount'=>$amount,
      'CheckSum'=>md5(__MerchantNumber__.$contribution->id.'0'.'0'.__MerchantPass__.$amount),
      'PRC'=>'0',
      'SRC'=>'0',
      // 'ApproveCode'=>'ET7373',
      'BankResponseCode'=>'0/00',
      'BatchNumber'=>''
    );
    $_GET = array(
      "module"=>"contribute",
      "contact_id" => 1,
      "cid" => $contribution->id,
      );

    civicrm_neweb_ipn();

    // verify contribution status after trigger
    $this->assertDBCompareValue(
      'CRM_Contribute_DAO_Contribution',
      $searchValue = $contribution->id,
      $returnColumn = 'contribution_status_id',
      $searchColumn = 'id',
      $expectedValue = 1,
      "In line " . __LINE__
    );

    // verify data in drupal module
    $cid = db_result(db_query("SELECT id FROM {civicrm_contribution} WHERE id = $contribution->id"));
    $this->assertNotEmpty($cid, "In line " . __LINE__);
    
  }

}

/*
  function testRecurringPaymentNotify(){
    $now = time()+60;
    $trxn_id = 'ut'.substr($now, -5);
    $amount = 111;

    // create recurring
    $date = date('YmdHis', $now);
    $recur = array(
      'contact_id' => $this->_cid,
      'amount' => $amount,
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
      'is_test' => $this->_is_test,
      'start_date' => $date,
      'create_date' => $date,
      'modified_date' => $date,
      'invoice_id' => md5($now),
      'contribution_status_id' => 2,
      'trxn_id' => CRM_Utils_Array::value('trxn_id', $params),
    );
    $ids = array();
    $recurring = &CRM_Contribute_BAO_ContributionRecur::add($recur, $ids);
    $params = array(
      'is_test' => $this->_is_test,
      'id' => $recurring->id,
    );
    $this->assertDBState('CRM_Contribute_DAO_ContributionRecur', $recurring->id, $params);

    // create contribution (first recurring)
    $contrib = array(
      'trxn_id' => $trxn_id,
      'contact_id' => $this->_cid,
      'contribution_contact_id' => $this->_cid,
      'contribution_type_id' => 1,
      'contribution_page_id' => $this->_page_id,
      'payment_processor_id' => $this->_processor['id'],
      'payment_instrument_id' => 1,
      'created_date' => date('YmdHis', $now),
      'non_deductible_amount' => 0,
      'total_amount' => $amount,
      'currency' => 'TWD',
      'cancel_reason' => '0',
      'source' => 'AUTO: unit test',
      'contribution_source' => 'AUTO: unit test',
      'amount_level' => '',
      'is_test' => $this->_is_test,
      'is_pay_later' => 0,
      'contribution_status_id' => 2,
      'contribution_recur_id' => $recurring->id,
    );
    $contribution = CRM_Contribute_BAO_Contribution::create($contrib, CRM_Core_DAO::$_nullArray);
    $this->assertNotEmpty($contribution->id, "In line " . __LINE__);
    $params = array(
      'is_test' => $this->_is_test,
      'id' => $contribution->id,
    );
    $this->assertDBState('CRM_Contribute_DAO_Contribution', $contribution->id, $params);

    // manually trigger ipn
    $get = $post = $ids = array();
    $ids = CRM_Contribute_BAO_Contribution::buildIds($contribution->id);
    $query = CRM_Contribute_BAO_Contribution::makeNotifyUrl($ids, NULL, $return_query = TRUE);
    parse_str($query, $get);
    $post = array(
      'MerchantID' => '2000132',
      'MerchantTradeNo' => $trxn_id,
      'RtnCode' => '1',
      'RtnMsg' => 'success',
      'TradeNo' => '201203151740582564',
      'TradeAmt' => $amount,
      'PaymentDate' => date('Y-m-d H:i:s', $now),
      'PaymentType' => 'Credit',
      'PaymentTypeChargeFee' => '10',
      'TradeDate' => date('Y-m-d H:i:s', $now),
      'SimulatePaid' => '1',
        // 'final_result'=>'1',
      
    );
    civicrm_allpay_ipn('Credit', $post, $get);

    // verify contribution status after trigger
    $this->assertDBCompareValue(
      'CRM_Contribute_DAO_Contribution',
      $searchValue = $contribution->id,
      $returnColumn = 'contribution_status_id',
      $searchColumn = 'id',
      $expectedValue = 1,
      "In line " . __LINE__
    );

    // verify data in drupal module
    $cid = db_query("SELECT cid FROM {civicrm_contribution_allpay} WHERE cid = :cid", array(':cid' => $contribution->id))->fetchField();
    $this->assertNotEmpty($cid, "In line " . __LINE__);

    // second payment
    $now = time()+120;
    $gwsr1 = 99999;
    $get = $post = $ids = array();
    $ids = CRM_Contribute_BAO_Contribution::buildIds($contribution->id);
    $query = CRM_Contribute_BAO_Contribution::makeNotifyUrl($ids, NULL, $return_query = TRUE);
    parse_str($query, $get);
    $get['is_recur'] = 1;
    $post = array(
      'MerchantID' => '2000132',
      'MerchantTradeNo' => $trxn_id,
      'RtnCode' => '1',
      'RtnMsg' => 'success',
      'PeriodType' => 'M',
      'Frequency' => '1',
      'ExecTimes' => '12',
      'Amount' => $amount,
      'Gwsr' => $gwsr1,
      'ProcessDate' => date('Y-m-d H:i:s', $now),
      'AuthCode' => '777777',
      'FirstAuthAmount' => $amount,
      'TotalSuccessTimes' => 2,
      'SimulatePaid' => '1',
    );
    civicrm_allpay_ipn('Credit', $post, $get);

    // check second payment contribution exists
    $params = array(
      1 => array($recurring->id, 'Integer'),
    );
    $this->assertDBQuery(2, "SELECT count(*) FROM civicrm_contribution WHERE contribution_recur_id = %1", $params);
    $cid2 = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_contribution WHERE contribution_recur_id = %1 ORDER BY id DESC", $params);
    $cid2 = db_query("SELECT cid FROM {civicrm_contribution_allpay} WHERE cid = :cid", array(':cid' => $cid2))->fetchField();
    $this->assertNotEmpty($cid2, "In line " . __LINE__);

    // TODO: use civicrm_allpay_recur_check to insert ant third payment
  }
}
*/




/**
 * $signature 來自金流的設定
 * 但必須要知道金流機制的 ID 才行
 */

/*
function my_curl($url,$post)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  //curl_setopt($ch, CURLOPT_POST,1);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
  $result = curl_exec($ch);
  curl_close ($ch);
  return $result;
}

$signature = "abcd1234";

$data = array(
  // 'final_result'=>'1',
  'MerchantNumber'=>$_POST['MerchantNumber'],
  'OrderNumber'=>$_POST['OrderNumber'],
  'Amount'=>$_POST['Amount'],
  'CheckSum'=>md5($_POST['MerchantNumber'].$_POST['OrderNumber'].$_POST['PRC'].$_POST['SRC'].$signature.$_POST['Amount']),
  'PRC'=>'0',
  'SRC'=>'0',
  // 'ApproveCode'=>'ET7373',
  'BankResponseCode'=>'0/00',
  'BatchNumber'=>''
,);

// print_r($data);

$getBody = my_curl($_POST['OrderURL'],$data);
print($getBody);

*/