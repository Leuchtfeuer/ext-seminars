<?php
/***************************************************************
* Copyright notice
*
* (c) 2005-2007 Oliver Klee (typo3-coding@oliverklee.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Module 'Seminars' for the 'seminars' extension.
 *
 * @package		TYPO3
 * @subpackage	tx_seminars
 * @author		Oliver Klee <typo3-coding@oliverklee.de>
 */

unset($MCONF);
$MCONF = array();
require ('conf.php');
require ($BACK_PATH.'init.php');
require ($BACK_PATH.'template.php');
$LANG->includeLLFile('EXT:seminars/mod1/locallang.php');

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_page.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_registration.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_registrationbag.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_seminar.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_seminarbag.php');

// This checks permissions and exits if the users has no permission for entry.
$BE_USER->modAccess($MCONF, 1);

class tx_seminars_module1 extends t3lib_SCbase {
	var $pageinfo;

	/**
	 * @return	[type]		...
	 */
	function init() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

		$this->id = intval($this->id);

		return;
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	[type]		...
	 */
	function menuConfig() {
		global $LANG, $BE_USER;

		$functionMenu = array();

		// check whether the user has write access to the page before allowing
		// to update the statistics
		$sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		if ($BE_USER->doesUserHaveAccess($sys_page->getPage($this->id), 16)) {
			$functionMenu['updateStats'] = $LANG->getLL('menu_updateStats');
		}

		$functionMenu['seminarDetails'] = $LANG->getLL('menu_seminarDetails');

		$this->MOD_MENU = array(
			'function' => $functionMenu
		);

		parent::menuConfig();
	}

	// If you chose 'web' as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	/**
	 * Main function of the module. Writes the content to $this->content
	 *
	 * @return	[type]		...
	 */
	function main() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		// Define the Database-Tables used in this class.
		// They are defined also in the dbplugin class, but cannot be read from here.
		$this->tableSeminars = 'tx_seminars_seminars';
		$this->tableAttendances = 'tx_seminars_attendances';
		$this->tableUsers = 'fe_users';

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id, $this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		$dbResult = $GLOBALS['TYPO3_DB']->sql_query(
			'(SELECT COUNT(*) AS num FROM '.$this->tableSeminars
				.' WHERE deleted=0 AND pid='.$this->id.') UNION '
				.'(SELECT COUNT(*) AS num FROM '.$this->tableAttendances
				.' WHERE deleted=0 AND pid='.$this->id.')'
		);
		if ($dbResult) {
			$dbResultRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult);
			$numberOfRecordsOnCurrentPage = $dbResultRow['num'];
		} else {
			$numberOfRecordsOnCurrentPage = 0;
		}

		if ($this->id && ($access || $BE_USER->user['admin'])
			&& ($numberOfRecordsOnCurrentPage)) {
			// Draw the header.
			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="post">';

			// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL) {
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = '.intval($this->id).';
				</script>
			';

			$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br>'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_pre($this->pageinfo['_thePath'],50);

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function'])));
			$this->content.=$this->doc->divider(5);

			// Render content:
			$this->moduleContent();

			// ShortCut
			if ($BE_USER->mayMakeShortcut()) {
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
			// Either the user has no acces, the page ID is zero or there are no
			// seminar or attendance records on the current page.
			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}

		return;
	}

	/**
	 * Prints out the module HTML.
	 *
	 * @return	[type]		...
	 */
	function printContent() {
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content.
	 *
	 * @return	[type]		...
	 */
	function moduleContent() {
		global $LANG;

		switch ((string)$this->MOD_SETTINGS['function']) {
			case 'updateStats':
				$content = $this->updateStats();
				$this->content.=$this->doc->section($LANG->getLL('menu_updateStats'),$content,0,1);
			break;
			case 'seminarDetails':
				$content = $this->listSeminarDetails();
				$this->content.=$this->doc->section($LANG->getLL('menu_seminarDetails'),$content,0,1);
			break;
			case 'listSpeakers':
				$content='<div align=center><strong>List Speakers</strong></div>';
				$this->content.=$this->doc->section('Message #2:',$content,0,1);
			break;
			case 'listSites':
				$content='<div align=center><strong>List Sites</strong></div>';
				$this->content.=$this->doc->section('Message #3:',$content,0,1);
			break;
			case 'listSeminars':
				$content='<div align=center><strong>List Seminars</strong></div>';
				$this->content.=$this->doc->section('Message #3:',$content,0,1);
			break;
		}
	}

	/**
	 * Updates the seminar statistics (number of attendances, is full,
	 * has enough attendances etc.).
	 *
	 * @return	string		HTML code displaying the updated statistics
	 *
	 * @access	private
	 */
	function updateStats() {
		global $LANG;

		$tableSeminars = 'tx_seminars_seminars';
		$tableAttendances = 'tx_seminars_attendances';
		$tableUsers = 'fe_users';

		$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');

		$result = '';

		$seminarBagClassname = t3lib_div::makeInstanceClassName('tx_seminars_seminarbag');
		$seminarBag =& new $seminarBagClassname('pid='.intval($this->id), '', '', $tableSeminars.'.begin_date');

		$result .= '<h3>'.$LANG->getLL('message_updatingAttendanceNumbers').'</h3>'.chr(10);
		while ($currentSeminar =& $seminarBag->getCurrent()) {
			$currentSeminar->updateStatistics();

			$result .= '<h4>'.htmlspecialchars($currentSeminar->getTitleAndDate('-')).'</h4>'.chr(10);
			$result .= '<p>'.$LANG->getLL('label_all').$currentSeminar->getAttendances().'</p>';
			$result .= '<p>'.$LANG->getLL('label_paid').$currentSeminar->getAttendancesPaid().'</p>';
			$result .= '<p>'.$LANG->getLL('label_unpaid').$currentSeminar->getAttendancesNotPaid().'</p>';
			$result .= '<p>'.$LANG->getLL('label_vacancies').$currentSeminar->getVacancies().'</p>';
			$result .= '<p>'.$LANG->getLL('label_hasEnough').((integer) $currentSeminar->hasEnoughAttendances()).'</p>';
			$result .= '<p>'.$LANG->getLL('label_isFull').((integer) $currentSeminar->isFull()).'</p>';

			$seminarBag->getNext();
		}

		$result .= $seminarBag->checkConfiguration();

		$result .= '<h3>Titel der Anmeldungen werden aktualisiert</h3>';
		$dbResultAttendances = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			$this->tableAttendances,
			'1'.$pageSelect->enableFields($this->tableAttendances),
			'',
			'',
			''
		);
		if ($dbResultAttendances) {
			while ($currentAttendance = mysql_fetch_assoc($dbResultAttendances)) {
				$dbResultAttendee = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid,name,username',
					$this->tableUsers,
					'uid='.intval($currentAttendance['user'])
						.$pageSelect->enableFields($this->tableUsers),
					'',
					'',
					''
				);
				$dbResultSeminar = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'title, begin_date',
					$this->tableSeminars,
					'uid='.intval($currentAttendance['seminar'])
						.$pageSelect->enableFields($this->tableSeminars),
					'',
					'',
					''
				);

				if ($dbResultAttendee && $dbResultSeminar) {
					$attendeeName = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultAttendee);
					$seminarData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultSeminar);
					$newTitle = $attendeeName['name'].' / '.$seminarData['title'].' '.strftime('%d.%m.%Y', $seminarData['begin_date']);
					$displayTitle = $attendeeName['name'].' ['.$attendeeName['username'].':'.$attendeeName['uid'].'] / '.$seminarData['title'].' '.strftime('%d.%m.%Y', $seminarData['begin_date']);
					$result .= '<p>'.$displayTitle.'</p>';
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						$this->tableAttendances,
						'uid='.intval($currentAttendance['uid']),
						array('title' => $GLOBALS['TYPO3_DB']->quoteStr($newTitle, $this->tableAttendances))
					);
				}
			}
		}
		return $result;
	}

	/**
	 * Returns a list of the emailadresses of the registered attendees.
	 *
 	 * @return	string		HTML Output (content of the Module).
 	 *
	 * @access	private
	 */
	function listSeminarDetails() {
		// initialize the localization functionality
		global $LANG;

		$tableSeminars = 'tx_seminars_seminars';

		$result = '';

		$result .= '<h3>'.$LANG->getLL('title_getEmailAddressesForAttendances').'</h3>';

		$seminarBagClassname = t3lib_div::makeInstanceClassName('tx_seminars_seminarbag');
		$seminarBag =& new $seminarBagClassname('pid='.intval($this->id), '', '', $tableSeminars.'.begin_date');

		while ($currentSeminar =& $seminarBag->getCurrent()) {
			$result .= '<h4>'.$currentSeminar->getTitleAndDate().'</h4>';
			$seminarQuery = $this->tableAttendances.'.seminar='.$currentSeminar->getUid();

			$result .= $LANG->getLL('label_all').'<br />'.$this->generateEmailList($seminarQuery).'<hr />';
			$result .= $LANG->getLL('label_paid').'<br />'.$this->generateEmailList($seminarQuery.' AND (paid=1 OR datepaid!="")').'<hr />';
			$result .= $LANG->getLL('label_unpaid').'<br />'.$this->generateEmailList($seminarQuery.' AND (paid=0 AND datepaid=0)').'<hr />';

			$seminarBag->getNext();
		}

		$result .= $seminarBag->checkConfiguration();

		return $result;
	}

	/**
	 * Returns a comma separated list of names and e-mail addresses.
	 *
	 * @param	string		string that will be prepended to the WHERE clause
	 *						using AND, e.g. 'pid=42' (the AND and the enclosing
	 *						spaces are not necessary for this parameter)
 	 *
 	 * @return	string		a comma separated list of names and e-mail addresses or a localized messages if there are no registration records
 	 *
	 * @access	private
	 */
	 function generateEmailList($queryParameters) {
		// initialize the localization functionality
		global $LANG;

		$result = '';
		$emailList = '';
		$dividerInEmailList = ', ';

		$registrationBagClassname = t3lib_div::makeInstanceClassName('tx_seminars_registrationbag');
		$registrationBag =& new $registrationBagClassname($queryParameters, '', '', 'crdate');

		if ($registrationBag->getCurrent()) {
			while ($currentRegistration =& $registrationBag->getCurrent()) {
				$currentEmail = htmlspecialchars($currentRegistration->getUserNameAndEmail());
				if (empty($emailList)) {
					$emailList = $currentEmail;
				}	else	{
					$emailList .= $dividerInEmailList . ' ' . $currentEmail;
				}
				$registrationBag->getNext();
			}
			$result .= $emailList;
		} else {
			// Display a message if no attendances are found for this seminar.
			$result .= $LANG->getLL('msg_noAttendancesFound');
		}

		$result .= $registrationBag->checkConfiguration();

		return $result;
	}
} // END of class

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/mod1/index.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/mod1/index.php']);
}

// Make instance:
$SOBE = t3lib_div::makeInstance('tx_seminars_module1');
$SOBE->init();

// Include files?
foreach ($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();

?>
