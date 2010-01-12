<?php
	
	require_once(EXTENSIONS . '/brightcove/lib/echove.php');
	
	class Extension_Brightcove extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Brightcove',
				'version'		=> '1.0.1',
				'release-date'	=> '2010-01-12',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				),
				'description'	=> 'Adds Bright Cove video upload support to upload fields.'
			);
		}
		
		public function uninstall() {
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_brightcove_status`");
		}
		
		public function install() {
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_brightcove_status` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `field_id` (`field_id`)
				)
			");
			
			return true;
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'UploadField_AppendFormattedElement',
					'callback'	=> 'appendFormattedElement'
				),
				array(
					'page'		=> '/publish/',
					'delegate'	=> 'UploadField_AppendMediaPreview',
					'callback'	=> 'appendMediaPreview'
				),
				array(
					'page'		=> '/publish/',
					'delegate'	=> 'UploadField_PostProccessFile',
					'callback'	=> 'postProccessFile'
				)
			);
		}
		
	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/
		
		public function getReadApiKey() {
			return '5Ri7QxyT2IKuywpUW0fb1jFveEG2R_kWRRU2ZJAdeg8.';
		}
		
		public function getWriteApiKey() {
			return 'EyceGZkNMBKgILJ4H1g3XnpgCsiIvEErFaYz5up-KqaI2IDbbQF6rA..';
		}
		
		public function getBackendPlayerId() {
			return '61274950001';
		}
		
		public function getFrontendPlayerId() {
			return '61274950001';
		}
		
	/*-------------------------------------------------------------------------
		Utilites:
	-------------------------------------------------------------------------*/
		
		protected $apppendPublishHeaders = false;
		protected $apppendSettingsHeaders = false;
		
		public function apppendPublishHeaders() {
			$admin = Administration::instance();
			
			if ($admin->Page and !$this->apppendPublishHeaders) {
				$admin->Page->addStylesheetToHead(URL . '/extensions/brightcove/assets/publish.css', 'screen', 7126310);
				$admin->Page->addScriptToHead('http://admin.brightcove.com/js/BrightcoveExperiences.js', 7126310);
				
				$this->apppendPublishHeaders = true;
			}
		}
		
		public function addSettingsHeaders() {
			$admin = Administration::instance();
			
			if ($admin->Page and !$this->apppendSettingsHeaders) {
				//$admin->Page->addStylesheetToHead(URL . '/extensions/brightcove/assets/publish.css', 'screen', 7126310);
				//$admin->Page->addScriptToHead('http://admin.brightcove.com/js/BrightcoveExperiences.js', 7126310);
				
				$this->apppendSettingsHeaders = true;
			}
		}
		
		public function getVideoStatus($entry_id) {
			$admin = Administration::instance();
			$db = $admin->Database;
			$data = $db->fetchRow(0, sprintf(
				"
					SELECT
						d.*
					FROM
						`tbl_brightcove` AS d
					WHERE
						d.entry_id = '%d'
					LIMIT 1
				",
				$entry_id
			));
			
			if (empty($data)) {
				return 'none';
			}
			
			if ($data['completed'] == 'yes') {
				return 'completed';
			}
			
			else if ($data['failed'] == 'yes') {
				return 'failed';
			}
			
			else if ($data['encoding'] == 'yes') {
				return 'encoding';
			}
			
			else if ($data['uploading'] == 'yes') {
				return 'uploading';
			}
			
			else {
				return 'queued';
			}
		}
		
		public function appendMediaPreview($context) {
			header('content-type: text/plain');
			
			$db = $context['parent']->Database;
			$data = $context['data'];
			$wrapper = $context['wrapper'];
			
			if (!preg_match('/^video\//i', $data['mimetype'])) return;
			
			$row = $db->fetchRow(0, sprintf(
				"
					SELECT
						d.*
					FROM
						`tbl_brightcove` AS d
					WHERE
						d.entry_id = '%d'
						AND d.field_id = '%d'
				",
				$context['entry_id'],
				$context['field_id']
			));
			
			// Show preview:
			if ($row['completed'] == 'yes') {
				$api = new Echove($this->getReadApiKey(), $this->getWriteApiKey());
				$code = $api->embed('video', $this->getBackendPlayerId(), $row['video_id'], array(
					'width'		=> 320,
					'height'	=> 240
				));
				
				$this->apppendPublishHeaders();
				
				$embed = new XMLElement('div');
				$embed->setAttribute('class', 'brightcove-video-embed');
				$embed->setValue($code);
				$wrapper->appendChild($embed);
			}
		}
		
		public function postProccessFile($context) {
			$db = $context['parent']->Database;
			$data = $context['data'];
			
			if (!preg_match('/^video\//i', $data['mimetype'])) return;
			
			$row = $db->fetchRow(0, sprintf(
				"
					SELECT
						d.id
					FROM
						`tbl_brightcove` AS d
					WHERE
						d.entry_id = '%d'
						AND d.field_id = '%d'
				",
				$context['entry_id'],
				$context['field_id']
			));
			
			$db->insert(
				array(
					'id'		=> @$row['id'],
					'entry_id'	=> $context['entry_id'],
					'field_id'	=> $context['field_id'],
					'video_id'	=> null,
					'file'		=> WORKSPACE . $data['file'],
					'uploading'	=> 'no',
					'encoding'	=> 'no',
					'completed'	=> 'no',
					'failed'	=> 'no'
				),
				'tbl_brightcove', true
			);
		}
	}
	
?>