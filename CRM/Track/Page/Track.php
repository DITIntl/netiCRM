<?php
class CRM_Track_Page_Track extends CRM_Core_Page {

  /**
   * all the fields that are listings related
   *
   * @var array
   * @access protected
   */
  protected $_fields;

  /**
   * run this page (figure out the action needed and perform it).
   *
   * @return void
   */
  function run() {
    $null = CRM_Core_DAO::$_nullObject;
    $params = array(
      'pageType' => CRM_Utils_Request::retrieve('ptype', 'String', $null),
      'pageId' => CRM_Utils_Request::retrieve('pid', 'Positive', $null),
      'state' => CRM_Utils_Request::retrieve('state', 'Integer', $null),
      'referrerType' => CRM_Utils_Request::retrieve('rtype', 'String', $null),
      'referrerNetwork' => CRM_Utils_Request::retrieve('rnetwork', 'String', $null),
      'entityId' => CRM_Utils_Request::retrieve('entity_id', 'String', $null),
      'utmSource' => CRM_Utils_Request::retrieve('utm_source', 'String', $null),
      'utmMedium' => CRM_Utils_Request::retrieve('utm_medium', 'String', $null),
      'utmCampaign' => CRM_Utils_Request::retrieve('utm_campaign', 'String', $null),
      'utmTerm' => CRM_Utils_Request::retrieve('utm_term', 'String', $null),
      'utmContent' => CRM_Utils_Request::retrieve('utm_content', 'String', $null),
    );
    if ($start = CRM_Utils_Request::retrieve('start', 'Date', $null)) {
      $params['visitDateStart'] = $start;
    }
    if ($end = CRM_Utils_Request::retrieve('end', 'Date', $null)) {
      $params['visitDateEnd'] = $end;
    }
    if ($params['pageType'] == 'civicrm_contribution_page' && $params['pageId']) {
      // breadcrumb starter
      $breadcrumbs = array(
        array('url' => CRM_Utils_System::url('civicrm/admin', 'reset=1'), 'title' => ts('Administer CiviCRM')),
        array('url' => CRM_Utils_System::url('civicrm/admin/contribute', 'reset=1'), 'title' => ts('Manage Contribution Pages')),
      );
      CRM_Utils_System::appendBreadCrumb($breadcrumbs);
    }
    else
    if ($params['pageType'] == 'civicrm_event' && $params['pageId']) {
      // breadcrumb starter
      $breadcrumbs = array(
        array('url' => CRM_Utils_System::url('civicrm/event', 'reset=1'), 'title' => ts('CiviEvent Dashboard')),
      );
      CRM_Utils_System::appendBreadCrumb($breadcrumbs);
    }
    $selector = new CRM_Track_Selector_Track($params, $this->_scope);
    $selector->filters($this);
    $selector->breadcrumbs($this);

    $controller = new CRM_Core_Selector_Controller(
      $selector,
      $this->get(CRM_Utils_Pager::PAGE_ID),
      $sortID,
      CRM_Core_Action::VIEW,
      $this,
      CRM_Core_Selector_Controller::TEMPLATE
    );

    $controller->setEmbedded(TRUE);
    $controller->run();

    // another statistics
    $stat = array();
    $statistics = new CRM_Track_Selector_Track($params);
    $dao = $statistics->getQuery("COUNT(id) as `count`, referrer_type, SUM(CASE WHEN entity_id > 0 THEN 1 ELSE 0 END) as goal, max(visit_date) as end, min(visit_date) as start", 'GROUP BY referrer_type');
    while($dao->fetch()){
      $type = !empty($dao->referrer_type) ? $dao->referrer_type : 'unknown';
      $total = $total+$dao->count;
      $stat[$type] = array(
        'name' => $type,
        'label' => empty($dao->referrer_type) ? ts("Unknown") : ts($dao->referrer_type),
        'count' => $dao->count,
        'count_goal' => $dao->goal,
      );
    }
    // sort by count
    uasort($stat, array('CRM_Core_BAO_Track', 'cmp'));
    foreach($stat as $type => $data) {
      $stat[$type]['percent'] = number_format(($data['count'] / $total) * 100 );
      $stat[$type]['percent_goal'] = number_format(($data['count_goal'] / $total) * 100 );
    }
    foreach($stat as &$st) {
      $st['display'] = '<div>'.ts("%1 achieved", array(1 => "{$st['percent_goal']}% ({$st['count_goal']}".ts('People').")"))."</div><div style='color:grey'>".ts("Total")." {$st['percent']}% ({$st['count']}".ts('People').")</div>";
    }
    $this->assign('summary', $stat);

    /*
    CRM_Utils_System::setTitle(ts('Configure Contribution Page')." - ".$page['title']);
    if (!$this->get('filters')) {
      $pageStatistics = CRM_Contribute_Page_DashBoard::getContributionPageStatistics($id);
      foreach($pageStatistics['track'] as &$track) {
        $track['display'] = '<div>'.ts("%1 achieved", array(1 => "{$track['percent_goal']}% ({$track['count_goal']}".ts('People').")"))."</div><div style='color:grey'>".ts("Total")." {$track['percent']}% ({$track['count']}".ts('People').")</div>";
      }
      if ($track['start'] && $track['end']) {
        $this->assign('period_start', CRM_Utils_Date::customFormat($track['start'], $config->dateformatFull));
        $this->assign('period_end', CRM_Utils_Date::customFormat($track['end'], $config->dateformatFull));
      }
      unset($pageStatistics['page']['title']);
      unset($pageStatistics['duration']);
      $this->assign('summary', $pageStatistics);
    }
    */

    CRM_Utils_System::setTitle($selector->getTitle());
    $this->assign('title', $selector->getTitle());
    self::chart($this, 'chart_track', $params, array('ratioClass' => 'ct-double-octave'));

    $sortID = NULL;
    if ($this->get(CRM_Utils_Sort::SORT_ID)) {
      $sortID = CRM_Utils_Sort::sortIDValue($this->get(CRM_Utils_Sort::SORT_ID),
        $this->get(CRM_Utils_Sort::SORT_DIRECTION)
      );
    }

    return parent::run();
  }

  public static function chart($page, $chartName, $selectorParams, $chartParams = NULL) {
    $referrerTypes = CRM_Core_PseudoConstant::referrerTypes();
    $label = $dates = array();
    $dummy = $data = $legend = array();
    $selector = new CRM_Track_Selector_Track($selectorParams);
    $dao = $selector->getQuery(
      "referrer_type, count(id) as `count`, DATE_FORMAT(visit_date,'%Y-%m-%d') visit_day",
      'GROUP BY visit_day, referrer_type',
      NULL,
      NULL,
      'visit_date ASC'
    );
    while($dao->fetch()){
      if(empty($dao->referrer_type)){
        continue;
      }
      $dates[$dao->visit_day] = 1;
      $dummy[$dao->referrer_type][$dao->visit_day] += (int)$dao->count;
    }
    
    // prepare period label for chartist
    $start = !empty($selectorParams['visitDateStart']) ? $selectorParams['visitDateStart'] : key($dates);
    end($dates);
    $end = !empty($selectorParams['visitDateEnd']) ? $selectorParams['visitDateEnd'] : key($dates);
    $endD = new DateTime($end);
    $endD->modify('+1 day');
    $period = new DatePeriod(
      new DateTime($start),
      new DateInterval('P1D'),
      $endD
    );
    foreach ($period as $key => $val) {
      $label[] = $val->format('Y-m-d');
    }
     
    // prepare series and label for chartist
    $seriesNum = 0; 
    foreach($dummy as $rtype => $d) {
      $legend[$seriesNum] = $referrerTypes[$rtype];
      $data[$seriesNum] = array();
      foreach($label as $idx => $date) {
        if (!empty($d[$date])) {
          $data[$seriesNum][$idx] = $d[$date];
        }
        else {
          $data[$seriesNum][$idx] = 0;
        }
      }
      $seriesNum++;
    }

    $chart = array(
      'id' => str_replace('_', '-', $chartName),
      'selector' => '#'.str_replace('_', '-', $chartName),
      'type' => 'Bar',
      'labels' => json_encode($label),
      'series' => json_encode($data),
      'seriesUnit' => ts("People"),
      'withToolTip' => true,
      'withVerticalHint' => true,
      'legends' => json_encode($legend),
      'stackBars' => true,
      'withLegend' => true,
      'autoDateLabel' => true,
    );
    if (is_array($chartParams)) {
      $chart += $chartParams;
    }
    $page->assign($chartName, $chart);
  }
}

