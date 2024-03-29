<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/class.xsltprocess.php');

	class FieldBrightcove_Status extends Field {
		protected $_driver = null;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent) {
			parent::__construct($parent);

			$this->_name = 'Brightcove Status';
			$this->_driver = Symphony::ExtensionManager()->create('brightcove');

			// Set defaults:
			$this->set('show_column', 'yes');
			$this->set('size', 'medium');
			$this->set('required', 'yes');

			$this->_sizes = array(
				array('single', false, __('Single Line')),
				array('small', false, __('Small Box')),
				array('medium', false, __('Medium Box')),
				array('large', false, __('Large Box')),
				array('huge', false, __('Huge Box'))
			);
		}

		public function createTable() {
			$field_id = $this->get('id');

			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`handle` varchar(255) default NULL,
					`value` varchar(255) default NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `handle` (`handle`),
					KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");
		}

		public function allowDatasourceOutputGrouping() {
			return false;
		}

		public function allowDatasourceParamOutput() {
			return false;
		}

		public function canFilter() {
			return true;
		}

		public function canPrePopulate() {
			return false;
		}

		public function isSortable() {
			return true;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors = null, $append_before = null, $append_after = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$this->appendShowColumnCheckbox($wrapper);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$sortorder = $this->get('sortorder');
			$element_name = $this->get('element_name');
			$classes = array();

			$label = Widget::Label($this->get('label'));
			$message = new XMLElement('span');

			switch ($data['handle']) {
				case 'none':
				case 'completed':
					return;
					break;
				case 'failed':
					$value = __('Video failed to upload.');
					break;
				case 'queued':
					$value = __('Video is waiting in queue.');
					break;
				case 'encoding':
					$value = __('Video is being encoded.');
					break;
				case 'uploading':
					$value = __('Video is being uploaded.');
					break;
			}

			if (isset($value)) {
				$message->setValue($value);
				$label->appendChild($message);
				$wrapper->appendChild($label);
			}
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			$db = Symphony::Database();
			$data = $db->fetchRow(0, sprintf(
				'
					SELECT
						d.handle, d.value
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						entry_id = %d
				',
				$this->get('id'),
				$entry_id
			));
			$id = $db->fetchVar('id', 0, sprintf(
				'
					SELECT
						b.id
					FROM
						`tbl_brightcove` AS b
					WHERE
						entry_id = %d
				',
				$entry_id
			));

			if (!$simulate and $id) $db->insert(
				array(
					'id'		=> $id,
					'edited'	=> 'yes'
				),
				'tbl_brightcove', true
			);

			return $data;
		}
	}

?>