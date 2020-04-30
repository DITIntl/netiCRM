<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
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

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

require_once 'CRM/Contribute/DAO/TaiwanACH.php';
class CRM_Contribute_BAO_TaiwanACH extends CRM_Contribute_DAO_TaiwanACH {

  public static $_editableFields = array('amount', 'installments', 'end_date', 'contribution_status_id', 'note_title', 'note_body');

  public static $_hideFields = array('invoice_id', 'trxn_id');

  /**
   * takes an associative array and creates a contribution object
   *
   * the function extract all the params it needs to initialize the create a
   * contribution object. the params array could contain additional unused name/value
   * pairs
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   * @param array $ids    the array that holds all the db ids
   *
   * @return object CRM_Contribute_BAO_Contribution object
   * @access public
   * @static
   */
  static function add(&$params) {

    // pre-processing hooks
    require_once 'CRM/Utils/Hook.php';
    if (CRM_Utils_Array::value('id', $params)) {
      CRM_Utils_Hook::pre('edit', 'TaiwanACH', $params['id'], $params);
    }
    else {
      CRM_Utils_Hook::pre('create', 'TaiwanACH', NULL, $params);
    }

    if (!empty($params['id'])) {
      $taiwanACH = new CRM_Contribute_DAO_TaiwanACH();
      $taiwanACH->id = $params['id'];
      $taiwanACH->find(TRUE);
    }
    else if (!empty($params['contribution_recur_id'])) {
      $taiwanACH = new CRM_Contribute_DAO_TaiwanACH();
      $taiwanACH->contribution_recur_id = $params['contribution_recur_id'];
      $taiwanACH->find(TRUE);
    }
    else {
      $taiwanACH = new CRM_Contribute_DAO_TaiwanACH();
    }
    $originData = unserialize($taiwanACH->data);
    if (empty($originData)) {
      $originData = array();
    }
    $paramsData = $params['data'];
    $mergedData = array_merge($originData, $paramsData);
    $taiwanACH->copyValues($params);

    $recurring = new CRM_Contribute_DAO_ContributionRecur();
    $recurParams = array();
    $recurringFields = $recurring->fields();
    foreach ($recurringFields as $field) {
      $fieldName = $field['name'];
      if (isset($params[$fieldName])) {
        $recurParams[$fieldName] = $params[$fieldName];
      }
    }
    $recurParams['create_date'] = date('YmdHis');
    $ids = array();
    if (!empty($taiwanACH->contribution_recur_id)) {
      $recurParams['id'] = $taiwanACH->contribution_recur_id;
    }
    $recurring = CRM_Contribute_BAO_ContributionRecur::add($recurParams, $ids);
    if (empty($taiwanACH->contribution_recur_id)) {
      $taiwanACH->contribution_recur_id = $recurring->id;
    }

    // set currency for CRM-1496
    if (!isset($taiwanACH->currency)) {
      $config = CRM_Core_Config::singleton();
      $taiwanACH->currency = $config->defaultCurrency;
    }

    if (isset($mergedData) && is_array($mergedData)) {
      $taiwanACH->data = serialize($mergedData);
    }

    $result = $taiwanACH->save();

    // create post-processing hooks
    if (CRM_Utils_Array::value('id', $params)) {
      CRM_Utils_Hook::post('edit', 'TaiwanACH', $taiwanACH->id, $taiwanACH);
    }
    else {
      CRM_Utils_Hook::post('create', 'TaiwanACH', $taiwanACH->id, $taiwanACH);
    }

    return $result;
  }

  static function addNote($taiwanACHId, $title, $body = NULL) {
    $session = CRM_Core_Session::singleton();
    $userId = $session->get('userID');
    if (empty($userId)) {
      $userId = "NULL";
    }
    $noteParams = array(
      'entity_table'  => 'civicrm_contribution_recur',
      'subject'       => $title,
      'note'          => $body,
      'entity_id'     => $taiwanACHId,
      'contact_id'    => $userId,
      'modified_date' => date('YmdHis'),
    );
    $note = CRM_Core_BAO_Note::add( $noteParams, NULL );
  }

  static function getValue($recurringId) {
    $output = array();

    $taiwanACH = new CRM_Contribute_DAO_TaiwanACH();
    $taiwanACH->contribution_recur_id = $recurringId;
    $taiwanACH->find(TRUE);
    $taiwanACH->data = unserialize($taiwanACH->data);
    $taiwanACHFields = $taiwanACH->fields();
    foreach ($taiwanACHFields as $field) {
      $fieldName = $field['name'];
      $output[$fieldName] = $taiwanACH->$fieldName;
    }

    $recurring = new CRM_Contribute_DAO_ContributionRecur();
    $recurring->id = $recurringId;
    $recurring->find(TRUE);
    $recurringFields = $recurring->fields();
    foreach ($recurringFields as $field) {
      $fieldName = $field['name'];
      if ($fieldName != 'id') {
        $output[$fieldName] = $recurring->$fieldName;
      }
    }

    return $output;
  }

  static function getTaiwanACHDatas($recurringIds = array()) {
    $achDatas = array();
    foreach ($recurringIds as $recurringId) {
      $achDatas[$recurringId] = self::getValue($recurringId);
    }
    return $achDatas;
  }

  static function doExportVerification($recurringIds = array(), $params = array(), $officeType = 'bank', $type = 'txt') {
    // Assign params
    $fileName = $params['file_name'];
    // $table = $bodyTable = array();
    $table = array();
    $achDatas = self::getTaiwanACHDatas($recurringIds);


    $firstAch = reset($achDatas);
    $paymentProcessor = CRM_Core_BAO_PaymentProcessor::getPayment($firstAch['processor_id'], '');
    $params['paymentProcessor'] = $paymentProcessor;

    if ($type == 'txt') {
      // account = ['user_name']
      // sic_code = ['password']
      // bank code = ['signature']
      // post_account = ['subject']
      if (strstr($officeType, 'Bank')) {
        $table = self::getBankVerifyTable($achDatas, $params);
      }
      else if (strstr($officeType, 'Post')) {
        $table = self::getPostVerifyTable($achDatas, $params);
      }

      // Add civicrm_log file
      $log = new CRM_Core_DAO_Log();
      $log->entity_table = 'civicrm_contribution_taiwanach_verification';
      $log->entity_id = $params['date'];
      $log->data = implode(',', $recurringIds);
      $log->modified_date = date('Y-m-d H:i:s');
      $session = CRM_Core_Session::singleton();
      $log->modified_id = $session->get('userID');
      $log->save();

      // Export File
      self::doExportTXTFile($fileName, $table);
    }
    else {
      $table = $achDatas;
      self::doExportXSLFile($fileName, $table);
    }
  }

  static function doExportTransaction($recurringIds, $params = array(), $officeType = 'bank', $type = 'txt') {
    // Assign params
    $fileName = $params['file_name'];
    // $table = $bodyTable = array();
    $table = array();
    $achDatas = self::getTaiwanACHDatas($recurringIds);


    $firstAch = reset($achDatas);
    $paymentProcessor = CRM_Core_BAO_PaymentProcessor::getPayment($firstAch['processor_id'], '');
    $params['paymentProcessor'] = $paymentProcessor;

    if ($type == 'txt') {
      // account = ['user_name']
      // sic_code = ['password']
      // bank code = ['signature']
      // post_account = ['subject']
      if (strstr($officeType, 'Bank')) {
        $table = self::getBankTransactTable($achDatas, $params);
      }
      else if (strstr($officeType, 'Post')) {
        $table = self::getPostTransactTable($achDatas, $params);
      }

      // Export File
      self::doExportTXTFile($fileName, $table);
    }
    else {
      $table = $achDatas;
      self::doExportXSLFile($fileName, $table);
    }
  }

  static private function doExportTXTFile($fileName, $table) {
    // arrange txt
    $txt = '';
    foreach ($table as $row) {
      $lines[] = implode('',$row);
    }
    $txt = implode("\n", $lines);

    // export file
    $config = CRM_Core_Config::singleton();
    $tmpDir = empty($config->uploadDir) ? CIVICRM_TEMPLATE_COMPILEDIR : $config->uploadDir;
    $fileName .= '.txt';
    $fileName = CRM_Utils_File::makeFileName($fileName);
    $fileFullPath = $tmpDir.'/'.$fileName;
    file_put_contents($fileFullPath, $txt, FILE_APPEND);
    header('Content-type: text/plain');
    header('Content-Disposition: attachment; filename=' . $fileName);
    header('Pragma: no-cache');
    echo file_get_contents($fileFullPath);
    CRM_Utils_System::civiExit();
  }

  static private function doExportXSLFile($fileName, $table) {
    $fileName .= '.xlsx';

    $header = array_shift($table);
    CRM_Core_Report_Excel::writeExcelFile(
      $fileName,
      $header,
      $table
    );
    CRM_Utils_System::civiExit();
  }

  static private function getBankVerifyTable($achDatas, $params) {
    $paymentProcessor = $params['paymentProcessor'];

    // Generate Header
    $date = $params['date'];
    $account = $paymentProcessor['user_name'];
    $table[] = array(
      'BOF',
      'ACHP02',
      $date,
      $account,
      'V10',
      str_repeat(' ', 193),
    );

    // Generate Body Table
    $i = 1;
    foreach ($achDatas as $achData) {
      $achData['invoice_id'] = $params['date'].'_'.$i;
      CRM_Contribute_BAO_TaiwanACH::add($achData);
      $bankAccount = str_pad($achData['bank_account'], 14, "0", STR_PAD_RIGHT);
      $identifier_number = str_pad($achData['identifier_number'], 10, " ", STR_PAD_LEFT);
      $table[] = array(
        str_pad($i, 6, '0', STR_PAD_LEFT),
        '530',
        $paymentProcessor['password'],
        $achData['bank_code'],
        $bankAccount,
        $identifier_number,
        $identifier_number,
        'A',
        $params['date'],
        $paymentProcessor['signature'],
        str_pad($achData['contribution_recur_id'], 20, " ", STR_PAD_LEFT),
        'N',
        ' ',
        str_repeat(' ', 8),
        ' ',
        str_repeat(' ', 8),
        str_repeat(' ', 20),
        str_repeat(' ', 53),
      );
      $i++;
    }

    // Generate Footer
    $total = str_pad(count($achData), 8, "0", STR_PAD_LEFT);
    $table[] = array(
      'EOF',
      $total,
      str_repeat(' ', 209),
    );

    return $table;
  }

  static private function getPostVerifyTable($achDatas, $params) {
    $paymentProcessor = $params['paymentProcessor'];

    // Generate Body Table
    $i = 1;
    foreach ($achDatas as $achData) {
      $bankAccount = str_pad($achData['bank_account'], 14, "0", STR_PAD_RIGHT);
      $identifier_number = str_pad($achData['identifier_number'], 10, " ", STR_PAD_LEFT);
      $table[] = array(
        1,
        $paymentProcessor['subject'],
        str_repeat(' ', 3),
        $params['date'],
        '001',
        str_pad($i, 6, "0", STR_PAD_LEFT),
        '1',
        ($achData['postoffice_acc_type'] == 1)? 'P' : 'G',
        $bankAccount,
        str_pad($achData['contribution_recur_id'], 20, " ", STR_PAD_LEFT),
        $identifier_number,
        str_repeat(' ', 2),
        ' ',
        str_repeat(' ', 26),
      );
      $i++;
    }

    // Generate Footer
    $total = str_pad(count($achData), 6, "0", STR_PAD_LEFT);
    $table[] = array(
      2,
      $paymentProcessor['subject'],
      str_repeat(' ', 4),
      $params['date'],
      '001',
      'B',
      $total,
      str_repeat(' ', 8),
      str_repeat('0', 6),
      str_repeat('0', 6),
      str_repeat(' ', 54),
    );

    return $table;
  }

  static private function getBankTransactTable($achDatas, $params) {
    $paymentProcessor = $params['paymentProcessor'];

    // Generate Header
    $date = $params['date'];
    $time = $params['time'];
    $account = $paymentProcessor['user_name'];
    $sendBankCode = str_pad($paymentProcessor['signature'], 7, "0", STR_PAD_LEFT);
    $table[] = array(
      'BOF',
      'ACHP01',
      $date,
      $time,
      $account,
      '9990250',
      'V10',
      str_repeat(' ', 210),
    );

    // Generate Body Table
    $i = 1;
    $totalAmount = 0;
    foreach ($achDatas as $achData) {
      $contribution = self::createContributionByACHData($achData);
      $bankAccount = str_pad($achData['bank_account'], 14, "0", STR_PAD_RIGHT);
      $identifier_number = str_pad($achData['identifier_number'], 10, " ", STR_PAD_RIGHT);
      $table[] = array(
        'N',
        'SD',
        str_pad($i, 8, '0', STR_PAD_LEFT),
        '530',
        $sendBankCode,
        $paymentProcessor['user_name'],
        $achData['bank_code'],
        $bankAccount,
        $achData['amount'],
        str_repeat(' ', 2),
        'B',
        $paymentProcessor['password'],
        $identifier_number,
        str_repeat(' ', 6),
        str_repeat(' ', 8),
        str_repeat(' ', 8),
        ' ',
        $identifier_number,
        str_pad($contribution->id, 20, " ", STR_PAD_RIGHT),
        str_repeat(' ', 10),
        str_repeat('0', 5),
        str_repeat(' ', 10),
        str_repeat(' ', 39),
      );
      $i++;
      $totalAmount += $achDatas['amount'];
    }

    // Generate Footer
    $total = str_pad(count($achData), 8, "0", STR_PAD_LEFT);
    $totalAmount = str_pad($totalAmount, 16, "0", STR_PAD_LEFT);
    $table[] = array(
      'EOF',
      'ACHP01',
      $params['transact_date'],
      $sendBankCode,
      '9990250',
      $total,
      $totalAmount,
      str_repeat(' ', 8),
      str_repeat(' ', 187),
    );

    return $table;
  }

  static private function getPostTransactTable($achDatas, $params) {
    $paymentProcessor = $params['paymentProcessor'];

    // Generate Body Table
    $i = 1;
    $totalAmount = 0;
    foreach ($achDatas as $achData) {
      $contributionParams = array(
        'contact_id' => $achData['contact_id'],
        'total_amount' => $achData['amount'],
        'create_date' => date('Y-m-d H:i:s'),
        'contribution_recur_id' => $achData['contribution_recur_id'],
      );
      $ids = array();
      $contribution = CRM_Contribute_BAO_Contribution::add($contributionParams, $ids);
      $bankAccount = str_pad($achData['bank_account'], 14, "0", STR_PAD_RIGHT);
      $identifier_number = str_pad($achData['identifier_number'], 10, " ", STR_PAD_LEFT);
      $totalAmount += $achData['amount'];
      $table[] = array(
        1,
        ($achData['postoffice_acc_type'] == 1)? 'P' : 'G',
        $paymentProcessor['subject'],
        str_repeat(' ', 4),
        $params['date'],
        'S',
        str_repeat(' ', 2),
        $bankAccount,
        str_repeat(' ', 10),
        str_pad($achData['amount'], 9, "0", STR_PAD_LEFT).'00',
        str_pad($contribution->id, 20, " ", STR_PAD_LEFT),
        1,
        " ",
        " ",
        " ",
        str_repeat(' ', 2),
        $params['date'],
        str_repeat(' ', 5),
        str_repeat(' ', 20),
        str_repeat(' ', 10),
      );
      $i++;
    }

    // Generate Footer
    $total = str_pad(count($achData), 7, "0", STR_PAD_LEFT);
    $totalAmount = str_pad($totalAmount, 11, '0', STR_PAD_LEFT).'00';
    $table[] = array(
      2,
      ' ',
      $paymentProcessor['subject'],
      str_repeat(' ', 4),
      $params['date'],
      'S',
      str_repeat(' ', 2),
      $total,
      $totalAmount,
      str_repeat(' ', 16),
      str_repeat('0', 7),
      str_repeat('0', 13),
      str_repeat(' ', 45),
    );
    return $table;
  }

  static private function createContributionByACHData($achData) {
    $countContribOfThisRecur = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_contribution WHERE contribution_recur_id = %1", array(1 => array($achData['contribution_recur_id'], 'Integer')));
    $page = new CRM_Contribute_DAO_ContributionPage();
    $page->id = $achData['contribution_page_id'];
    $page->find(TRUE);
    $instrumentIds = CRM_Core_OptionGroup::values('payment_instrument', FALSE, FALSE, FALSE, "AND v.name = 'ACH Bank'", 'value');
    $instrumentId = reset($instrumentIds);
    foreach ($achData['data'] as $key => $value) {
      if (strstr($key, 'custom_')) {
        $customFieldID = CRM_Core_BAO_CustomField::getKeyID($key);
        if ($customFieldID) {
          CRM_Core_BAO_CustomField::formatCustomField($customFieldID, $customData, $value, 'contribution');
        }
      }
    }
    $contributionParams = array(
      'contact_id' => $achData['contact_id'],
      'total_amount' => $achData['amount'],
      'create_date' => date('Y-m-d H:i:s'),
      'contribution_recur_id' => $achData['contribution_recur_id'],
      'contribution_type_id' => $page->contribution_type_id,
      'contribution_status_id' => 2,
      'invoice_id' => $achData['invoice_id'].'_'.($countContribOfThisRecur+1),
      'payment_processor_id' => $achData['processor_id'],
      'is_test' => $achData['is_test'],
      'currency' => $achData['currency'],
      'payment_instrument_id' => $instrumentId,
      'custom' => $customData,
    );
    $ids = array();
    $contribution = CRM_Contribute_BAO_Contribution::create($contributionParams, $ids);
    if (!empty($contribution->id)) {
      $contribution->trxn_id = $contribution->id;
      $contribution->save();
    }

    return $contribution;

  }
}

