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
			//$this->_Parent->Database->query("DROP TABLE `tbl_fields_brightcove_status`");
			//$this->_Parent->Database->query("DROP TABLE `tbl_brightcove`");
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
			
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_brightcove` (
					`id` int(11) NOT NULL auto_increment,
					`entry_id` int(11) NOT NULL,
					`field_id` int(11) NOT NULL,
					`video_id` varchar(32) default NULL,
					`attempts` int(11) NOT NULL default '0',
					`file` text,
					`uploading` enum('yes','no') NOT NULL default 'no',
					`encoding` enum('yes','no') NOT NULL default 'no',
					`completed` enum('yes','no') NOT NULL default 'no',
					`failed` enum('yes','no') NOT NULL default 'no',
					PRIMARY KEY  (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `field_id` (`field_id`),
					KEY `video_id` (`video_id`)
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
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'addCustomPreferenceFieldsets'
				)
			);
		}
		
	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/
		
		public function getPreferencesData() {
			$config = $this->_Parent->Configuration;
			$data = array(
				'read-api-key'			=> '',
				'write-api-key'			=> '',
				'backend-player-id'		=> '',
				'frontend-player-id'	=> ''
			);
			
			foreach ($data as $key => &$value) {
				$value = $config->get($key, 'brightcove');
			}
			
			return $data;
		}
		
		public function getReadApiKey() {
			$config = $this->_Parent->Configuration;
			
			return $config->get('read-api-key', 'brightcove');
		}
		
		public function getWriteApiKey() {
			$config = $this->_Parent->Configuration;
			
			return $config->get('write-api-key', 'brightcove');
		}
		
		public function getBackendPlayerId() {
			$config = $this->_Parent->Configuration;
			
			return $config->get('backend-player-id', 'brightcove');
		}
		
		public function getFrontendPlayerId() {
			$config = $this->_Parent->Configuration;
			
			return $config->get('frontend-player-id', 'brightcove');
		}
		
		public function addCustomPreferenceFieldsets($context) {
			$data = $this->getPreferencesData();
			$page = Administration::instance()->Page;
			$page->addStylesheetToHead(
				URL . '/extensions/neatify/assets/preferences.css', 'screen', 250
			);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Brightcove'));
			
			$this->buildPreferences(
				__('Interface'), $fieldset,
				array(
					array(
						'label'		=> 'Read API Key',
						'name'		=> 'read-api-key',
						'value'		=> $data['read-api-key']
					),
					array(
						'label'		=> 'Write API Key',
						'name'		=> 'write-api-key',
						'value'		=> $data['write-api-key']
					),
					array(
						'label'		=> 'Backend Player ID',
						'name'		=> 'backend-player-id',
						'value'		=> $data['backend-player-id']
					),
					array(
						'label'		=> 'Frontend Player ID',
						'name'		=> 'frontend-player-id',
						'value'		=> $data['frontend-player-id']
					)
				)
			);
			
			$context['wrapper']->appendChild($fieldset);
		}
		
		public function buildPreferences($title, $fieldset, $data) {
			$row = null;
			
			foreach ($data as $index => $item) {
				if ($index % 2 == 0) {
					if ($row) $fieldset->appendChild($row);
					
					$row = new XMLElement('div');
					$row->setAttribute('class', 'group');
				}
				
				$label = Widget::Label(__($item['label']));
				$name = 'settings[brightcove][' . $item['name'] . ']';
				
				$input = Widget::Input($name, $item['value']);
				
				$label->appendChild($input);
				$row->appendChild($label);
			}
			
			$fieldset->appendChild($row);
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
		
		public function appendFormattedElement($context) {
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
				
				$embed = new XMLElement('brightcove');
				$embed->setValue($code);
				$wrapper->appendChild($embed);
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