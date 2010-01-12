<?php
	
	require_once(EXTENSIONS . '/brightcove/lib/echove.php');
	
	class EventBrightcove_Sync extends Event {
		public static function about(){
			return array(
				'name'				=> 'Brightcove Sync',
				'author'			=> array(
					'name'				=> 'Rowan Lewis',
					'website'			=> 'http://rowanlewis.com',
					'email'				=> 'me@rowanlewis.com'
				),
				'version'			=> '1.0.1',
				'release-date'		=> '2010-01-12'
			);						 
		}
		
		public function load() {			
			return $this->__trigger();
		}
		
		protected function __trigger() {
			$db = Frontend::Database();
			$conf = Frontend::Configuration();
			$page = Frontend::Page();
			$driver = $page->ExtensionManager->create('brightcove');
			$api = new Echove($driver->getReadApiKey(), $driver->getWriteApiKey());
			
			// Attempt to upload a video x number of times:
			$max_attempts = 5;
			
			// Upload x number of videos at a time, makes the job execute faster:
			$upload_limit = 10;
			
			// Upload files:
			$videos = $db->fetch("
				SELECT
					d.*
				FROM
					`tbl_brightcove` AS d
				WHERE
					d.failed = 'no'
					AND d.completed = 'no'
					AND d.encoding = 'no'
					AND d.uploading = 'no'
				LIMIT
					0, {$upload_limit}
			");
			
			if (is_array($videos) and !empty($videos)) foreach ($videos as $data) {
				$name = basename($data['file']);
				$id = $api->createMedia('video', $data['file'], array(
					'name'				=> "#{$data['entry_id']}: {$name}",
					'shortDescription'	=> "Symhony entry {$data['entry_id']}, file: {$data['file']}",
					'linkText'			=> $conf->get('sitename', 'general'),
					'linkURL'			=> URL
				));
				
				if ($id) {
					$data['video_id'] = $id;
					$data['uploading'] = 'yes';
				}
				
				else {
					$data['attempts']++;
					
					if ($data['attempts'] >= $max_attempts) {
						$data['failed'] = 'yes';
					}
				}
				
				$db->insert($data, 'tbl_brightcove', true);
			}
			
			// Check status:
			$videos = $db->fetch("
				SELECT
					d.*
				FROM
					`tbl_brightcove` AS d
				WHERE
					d.failed = 'no'
					AND d.completed = 'no'
			");
			
			if (is_array($videos) and !empty($videos)) foreach ($videos as $data) {
				$status = $api->getStatus('video', $data['video_id']);
				$data['completed'] = 'no';
				$data['encoding'] = 'no';
				$data['uploading'] = 'no';
				
				if ($status == 'UPLOADING') {
					$data['uploading'] = 'yes';
				}
				
				else if ($status == 'PROCESSING') {
					$data['encoding'] = 'yes';
				}
				
				else if ($status == 'COMPLETE') {
					$data['completed'] = 'yes';
				}
				
				else {
					$data['failed'] = 'yes';
				}
				
				$db->insert($data, 'tbl_brightcove', true);
			}
		}
	}
	
?>