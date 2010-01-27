<?php
	
	require_once(EXTENSIONS . '/brightcove/lib/echove.php');
	require_once(EXTENSIONS . '/symql/lib/class.symql.php');
	
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
			// This header is not to be removed.
			header('content-type: text/plain');
			
			$db = Frontend::Database();
			$conf = Frontend::Configuration();
			$page = Frontend::Page();
			$driver = $page->ExtensionManager->create('brightcove');
			$api = $driver->getAPI();
			$stats = array(
				'uploading'	=> array(),
				'starting'	=> array(),
				'encoding'	=> array(),
				'completed'	=> array()
			);
			
			// Attempt to upload a video x number of times:
			$max_attempts = 5;
			
			// Upload x number of videos at a time, makes the job execute faster:
			$upload_limit = 2;
			
			// Upload files:
			$videos = $db->fetch(sprintf(
				'
					SELECT
						d.*
					FROM
						`tbl_brightcove` AS d
					WHERE
						d.failed = "no"
						AND d.completed = "no"
						AND d.encoding = "no"
						AND d.uploading = "no"
					LIMIT
						0, %d
				',
				$upload_limit
			));
			
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
					$stats['starting'][] = sprintf(
						'Entry #%s, Video #%d, File %s',
						$data['entry_id'], $data['video_id'], basename($data['file'])
					);
				}
				
				else {
					$data['attempts']++;
					
					if ($data['attempts'] >= $max_attempts) {
						$data['failed'] = 'yes';
					}
				}
				
				###
				# Delegate: BrightCove_VideoCreated
				# Description: Allow data changes and api calls after a video is created.
				$page->ExtensionManager->notifyMembers(
					'BrightCove_VideoCreated',
					'/publish/', array(
						'api'		=> $api,
						'data'		=> &$data,
						'driver'	=> $driver,
						'database'	=> $db,
						'page'		=> $page
					)
				);
				
				$db->insert($data, 'tbl_brightcove', true);
			}
			
			// Check status:
			$videos = $db->fetch("
				SELECT
					d.*
				FROM
					`tbl_brightcove` AS d
				WHERE
					(
						d.failed = 'no'
						AND d.completed = 'no'
					)
					OR (
						d.edited = 'yes'
					)
			");
			
			if (is_array($videos) and !empty($videos)) foreach ($videos as $data) {
				$status = $api->getStatus('video', $data['video_id']);
				$data['completed'] = 'no';
				$data['encoding'] = 'no';
				$data['uploading'] = 'no';
				$data['edited'] = 'no';
				
				if ($status == 'UPLOADING') {
					$data['uploading'] = 'yes';
					$stats['uploading'][] = sprintf(
						'Entry #%s, Video #%d, File %s',
						$data['entry_id'], $data['video_id'], basename($data['file'])
					);
				}
				
				else if ($status == 'PROCESSING') {
					$data['encoding'] = 'yes';
					$stats['encoding'][] = sprintf(
						'Entry #%s, Video #%d, File %s',
						$data['entry_id'], $data['video_id'], basename($data['file'])
					);
				}
				
				else if ($status == 'COMPLETE') {
					$data['completed'] = 'yes';
					$stats['completed'][] = sprintf(
						'Entry #%s, Video #%d, File %s',
						$data['entry_id'], $data['video_id'], basename($data['file'])
					);
				}
				
				else {
					$data['failed'] = 'yes';
				}
				
				###
				# Delegate: BrightCove_VideoUpdated
				# Description: Allow data changes and api calls after a video is created.
				$page->ExtensionManager->notifyMembers(
					'BrightCove_VideoUpdated',
					'/publish/', array(
						'api'		=> $api,
						'data'		=> &$data,
						'driver'	=> $driver,
						'database'	=> $db,
						'page'		=> $page
					)
				);
				
				$db->insert($data, 'tbl_brightcove', true);
				$status = $driver->getVideoStatus($data['entry_id']);
				$driver->setVideoStatus($data['entry_id'], $status);
			}
			
			$template = "
%d videos uploading (%d just started):
	%s
	
%d videos are being encoded:
	%s

%d videos have been finished:
	%s
			";
			
			printf(
				trim($template),
				count($stats['uploading']),
				count($stats['starting']),
				implode("\n\t", $stats['uploading']),
				count($stats['encoding']),
				implode("\n\t", $stats['encoding']),
				count($stats['completed']),
				implode("\n\t", $stats['completed'])
			);
			
			exit;
		}
	}
	
?>