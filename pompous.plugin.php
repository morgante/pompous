<?php
class pompous extends Plugin {
	/**
	 * Build the configuration settings
	 */
	public function configure()
	{
		$ui = new FormUI( 'pompous_config' );

		// Add a text control for the address you want the email sent to
		$xml = $ui->append( 'text', 'xml', 'option:pompous__xmlurl', _t( 'XML URL: ' ) );
		$xml->add_validator( 'validate_required' );
		
		// Add a text control for the address you want the email sent to
		$search = $ui->append( 'text', 'search', 'option:pompous__forcedsearch', _t( 'Forced search: ' ) );
		
		$ui->append( 'submit', 'save', 'Save' );
		return $ui;
	}
	
	/**
	 * Fetch an XML resume
	 */
	private static function fetch_resume($url) {
		
		if (Cache::has('pompous__resume')) {
			$content = Cache::get('pompous__resume');
		} else {
			$content = RemoteRequest::get_contents($url);
			Cache::set('pompous__resume',$content);
		}
		
		return $content;
	}
	
	/**
	 * Parse an XML resume
	 */
	private static function parse_resume($resume) {
		$resume = new SimpleXMLElement( $resume );

		$items = $resume->items;

		foreach($items->item as $record) {

			if(isset($record['id'])) {
				$id = strval($record['id']);
				$records[$id]['id'] = $id;
			}

			if(isset($record['type'])) {
				$records[$id]['type'] = strval($record['type']);
			}

			if(isset($record['scope'])) {
				$records[$id]['scope'] = strval($record['scope']);
			}

			if(isset($record->name)) {
				$records[$id]['name'] = strval($record->name);
			}
			
			if(isset($record->description)) {
				$records[$id]['description'] = strval($record->description);
			}
			
			if(isset($record->location)) {
				$records[$id]['location'] = strval($record->location);
			}

			if(isset($record->link)) {
				$records[$id]['link'] = strval($record->link);
			}

			if(isset($record->tags)) {
				$tags = strval($record->tags);
				$records[$id]['tags'] = explode(' ', $tags);
			}

			if(isset($record->role)) {
				if(count($record->role) > 1) {
					foreach($record->role as $role) {
						$records[$id]['role'][] = strval($role);
					}
				} else {
					$records[$id]['role'][] = strval($record->role);
				}
			}

			if(isset($record->attach)) {
				foreach($record->attach as $attachment) {
					$attachid = strval($attachment['id']);
					$records[$id]['attachments'][$attachid]['id'] = $attachid;
					if(isset($attachment['rel'])) {
						$records[$id]['attachments'][$attachid]['rel'] = strval($attachment['rel']);
					}
					if(isset($attachment->name)) {
						$records[$id]['attachments'][$attachid]['name'] = strval($attachment->name);
					}
					if(isset($attachment->link)) {
						$link = strval($attachment->link);
						$url = $link;
						$records[$id]['attachments'][$attachid]['url'] = $url;
						$records[$id]['attachments'][$attachid]['link'] = $link;
					}
					if(isset($attachment->thumb)) {
						$link = strval($attachment->thumb);
						$url = $link;
						$records[$id]['attachments'][$attachid]['thumb']['url'] = $url;
						$records[$id]['attachments'][$attachid]['thumb']['link'] = $link;
					}
					if(isset($attachment->description)) {
						$records[$id]['attachments'][$attachid]['description'] = strval($attachment->description);
					}
					if(isset($attachment->featured)) {
						$records[$id]['attachments'][$attachid]['featured'] = TRUE;
					} else {
						$records[$id]['attachments'][$attachid]['featured'] = FALSE;
					}

				}
			}

			if(isset($record->start) && isset($record->end)) {
				$records[$id]['time']['start'] = HabariDateTime::date_create(strval($record->start));
				$records[$id]['time']['end'] = HabariDateTime::date_create(strval($record->end));
			} elseif(isset($record->end)) {
				$records[$id]['time'] = HabariDateTime::date_create(strval($record->end));
			}

			if(isset($record->quote)) {
				$records[$id]['quote']['name'] = strval($record->quote['name']);
				$records[$id]['quote']['url'] = strval($record->quote['url']);
				$records[$id]['quote']['text'] = strval($record->quote[0]);
			}
		}

		return $records;
	}
	
	/**
	 * Filter items based on search criteria
	 */
	private static function filter_items($items, $field, $value) {
		$return = FALSE;

		$aliases = array(
			'tag' => 'tags',
			'date' => 'time'
			);

		foreach($aliases as $from => $to) {
			if($field == $from) {
				$field = $to;
			}
		}

		foreach($items as $id => $item) {
			if(isset($item[$field])) {
				if($field == 'time') {
					if(is_array($item[$field])) {
						if(($item[$field]['start'] <= $value) && ($item[$field]['end'] >= $value)) {
							$return[$id] = $item;
						}
					} else {
						if($item[$field] == $value) {
							$return[$id] = $item;
						}
					}
				} elseif(self::search_field($item[$field], $value)) {
					$return[$id] = $item;
				}
			}

		}

		return $return;
	}
	
	/**
	 * Search a field for a given value
	 */
	private static function search_field($hay, $needle) {
		if(is_string($hay)) {
			if(str_replace($needle, '', $hay) != $hay) {
				return TRUE;
			} else {
				return FALSE;
			}
		} elseif(is_array($hay)) {
			foreach($hay as $straw) {
				if(self::search_field($straw, $needle)) {
					return TRUE;
				}
			}
			return FALSE;
		}
	}
		
	/**
	 * Get items from resume
	 */
	public static function get_items($search = '') {
		$data = self::fetch_resume(Options::get('pompous__xmlurl'));
		$resume = self::parse_resume($data);
		
		$items = $resume;
		
		$search .= Options::get('pompous__forcedsearch');
		
		if($search != '') {
			$searches = explode(' ', $search);
		}

		foreach($searches as $search) {
			$search = explode(':', $search, 2);
			if (sizeof($search) >= 2) {
				$items = self::filter_items($items, $search[0], $search[1]);
			}
		}

		return $items;
				
	}
	
}

require_once('experience.php');
?>