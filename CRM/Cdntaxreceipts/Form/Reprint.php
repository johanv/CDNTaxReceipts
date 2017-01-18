<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Cdntaxreceipts_Form_Reprint extends CRM_Core_Form {
  public function buildQuickForm() {

    // add form elements
    $this->add(
      'text', // field type
      'year', // field name
      'Calendar Year', // field label
      NULL, // list of options
      TRUE // is required
    );
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    $year = $values['year'];

    $receiptsForPrinting = cdntaxreceipts_openCollectedPDF();

    // FIXME: Smelly code

    $sql = "SELECT * FROM cdntaxreceipts_log WHERE receipt_no LIKE %1 AND is_duplicate = 0";
    // We assume that the attest number starts with the calendar year.
    $params = [ 1 => [$year . '%', 'String']];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    while ($dao->fetch()) {
      $receipt = [
        'receipt_no' => $dao->receipt_no,
        'issued_on' => $dao->issued_on,
        'contact_id' => $dao->contact_id,
        'receipt_amount' => $dao->receipt_amount,
        'is_duplicate' => $dao->is_duplicate,
        'issue_type' => $dao->issue_type,
        'issue_method' => $dao->issue_method,
        'receive_date' => $year,
        'contributions' => [],
      ];
      $contSql = "SELECT * FROM cdntaxreceipts_log_contributions WHERE receipt_id = %1";
      $contParams = [1 => [$dao->id, 'Integer']];
      $contDao = CRM_Core_DAO::executeQuery($contSql, $contParams);
      while ($contDao->fetch()) {
        $receipt['contributions'][] = [
          'contribution_id' => $contDao->contribution_id,
          'contribution_amount' => $contDao->contribution_amount,
          'receipt_amount' => cdntaxreceipts_eligibleAmount($contDao->contribution_id),
          'receive_date' => $year,
        ];
      }
      $contDao->free();
      cdntaxreceipts_processTaxReceipt($receipt, $receiptsForPrinting, FALSE);
    }

    cdntaxreceipts_sendCollectedPDF($receiptsForPrinting, 'Receipts-To-Print-' . (int) $_SERVER['REQUEST_TIME'] . '.pdf');
    // EXITS. Ik denk dat onderstaande lijn er niet meer aan te pas komt.
    parent::postProcess();
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
