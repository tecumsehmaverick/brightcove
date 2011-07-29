<?php

	require_once(EXTENSIONS . '/brightcove/lib/echove.php');

	class Extension_Brightcove extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function about() {
			return array(
				'name'			=> 'Brightcove',
				'version'		=> '1.2',
				'release-date'	=> '2011-07-29',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				),
				'description'	=> 'Adds Bright Cove video upload support to Advanced Upload fields.'
			);
		}

		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_brightcove_status`");
			Symphony::Database()->query("DROP TABLE `tbl_brightcove`");
		}

		public function install() {
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_brightcove_status` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");

			Symphony::Database()->query("
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
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");

			return true;
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/extension/advanced_upload_field/',
					'delegate'	=> 'AppendFormattedElement',
					'callback'	=> 'appendFormattedElement'
				),
				array(
					'page'		=> '/extension/advanced_upload_field/',
					'delegate'	=> 'AppendMediaPreview',
					'callback'	=> 'appendMediaPreview'
				),
				array(
					'page'		=> '/extension/advanced_upload_field/',
					'delegate'	=> 'PostProcessFile',
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
			$config = Symphony::Configuration();
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
			$config = Symphony::Configuration();

			return $config->get('read-api-key', 'brightcove');
		}

		public function getWriteApiKey() {
			$config = Symphony::Configuration();

			return $config->get('write-api-key', 'brightcove');
		}

		public function getBackendPlayerId() {
			$config = Symphony::Configuration();

			return $config->get('backend-player-id', 'brightcove');
		}

		public function getFrontendPlayerId() {
			$config = Symphony::Configuration();

			return $config->get('frontend-player-id', 'brightcove');
		}

		public function addCustomPreferenceFieldsets($context) {
			$data = $this->getPreferencesData();
			$page = Symphony::Engine()->Page;
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
			if (Symphony::Engine()->Page && !$this->apppendSettingsHeaders) {
				$this->apppendSettingsHeaders = true;
			}
		}

		public function getAPI() {
			return new Echove($this->getReadApiKey(), $this->getWriteApiKey());
		}

		public function setVideoStatus($entry_id, $status) {
			$db = Symphony::Database();

			// Update status field:
			$field_id = $db->fetchVar('id', 0, sprintf(
				'
					SELECT
						f.id
					FROM
						`tbl_fields` AS f,
						`tbl_entries` AS e
					WHERE
						e.id = %d
						AND f.parent_section = e.section_id
						AND f.type = "brightcove_status"
					LIMIT 1
				',
				$entry_id
			));

			if (!$field_id) return false;

			$data = $db->fetchRow(0, sprintf(
				'
					SELECT
						d.*
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						d.entry_id = %d
					LIMIT 1
				',
				$field_id, $entry_id
			));

			$data['entry_id'] = $entry_id;
			$data['handle'] = $status;
			$data['value'] = ucwords($status);

			return $db->insert($data, "tbl_entries_data_{$field_id}", true);
		}

		public function getVideoStatus($entry_id) {
			if (class_exists('Administration')) {
				$symphony = Administration::instance();
			}

			else {
				$symphony = Frontend::instance();
			}

			$db = $symphony->Database;
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
				$embed = new XMLElement('brightcove');
				$embed->setValue(sprintf(
					'<object id="myExperience" class="BrightcoveExperience">
						<param name="bgcolor" value="#FFFFFF" />
						<param name="width" value="320" />
						<param name="height" value="240" />
						<param name="playerID" value="%s" />
						<param name="@videoPlayer" value="%s" />
						<param name="isVid" value="true" />
						<param name="isUI" value="true" />
					</object>',
					$this->getFrontendPlayerId(),
					$row['video_id']
				));
				$wrapper->appendChild($embed);
			}
		}

		public function appendMediaPreview($context) {
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
				$this->apppendPublishHeaders();

				$embed = new XMLElement('div');
				$embed->setAttribute('class', 'brightcove-video-embed');
				$embed->setValue(sprintf(
					'<object id="myExperience" class="BrightcoveExperience">
						<param name="bgcolor" value="#FFFFFF" />
						<param name="width" value="320" />
						<param name="height" value="240" />
						<param name="playerID" value="%s" />
						<param name="@videoPlayer" value="%s" />
						<param name="isVid" value="true" />
						<param name="isUI" value="true" />
					</object>',
					$this->getBackendPlayerId(),
					$row['video_id']
				));
				$wrapper->appendChild($embed);
			}
		}

		public function postProccessFile($context) {
			$db = Symphony::Database();
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