<?php
class pompous extends Plugin {
	private $theme= null;
	
	function info() {
		return array(
			'name' => 'pompous',
			'version' => '1.0',
			'url' => 'http://myfla.ws/projects/pompous/',
			'author' => 'Arthus Erea',
			'authorurl' => 'http://myfla.ws',
			'license' => 'Creative Commons Attribution-Share Alike 3.0',
			'description' => 'pompous allows you to brag about your portfolio and experience',
		);
	}
	public function filter_plugin_config( $actions, $plugin_id ) {
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[]= _t('Configure');
		}
		return $actions;
	}
	public function action_plugin_ui( $plugin_id, $action ) {
		if ( $plugin_id == $this->plugin_id() ) {
			switch ( $action ) {
				case _t('Configure') :
					$ui = new FormUI( strtolower( get_class( $this ) ) );
					$xmlurl= $ui->add( 'text', 'xmlurl', _t('XML Resume URL:') );
					$base= $ui->add( 'text', 'rewrite_base', _t('Rewrite Base:') );
					$timeformat= $ui->add( 'text', 'timeformat', _t('Time Format (php):') );
					$forcedsearch= $ui->add( 'text', 'forcedsearch', _t('Forced Search Query:') );
					$ui->on_success( array( $this, 'updated_config' ) );
					$ui->out();
				break;
			}
		}
	}
	public function updated_config ( $ui ) {
		return TRUE;
	}
	public function filter_rewrite_rules( $rules ) {
		$rules[] = new RewriteRule(array(
			'name' => 'item',
			'parse_regex' => '%' . Options::get('pompous:rewrite_base') . '/view/(?P<item>.*)[\/]?$%i',
			'build_str' =>  Options::get('pompous:rewrite_base') . '/view/{$item}',
			'handler' => 'UserThemeHandler',
			'action' => 'display_item',
			'priority' => 6,
			'is_active' => 1,
		));
		$rules[] = new RewriteRule(array(
			'name' => 'search',
			'parse_regex' => '%' . Options::get('pompous:rewrite_base') . '/(?P<search>.*)[\/]?$%i',
			'build_str' =>  Options::get('pompous:rewrite_base') . '/{$search}',
			'handler' => 'UserThemeHandler',
			'action' => 'display_portfolio',
			'priority' => 7,
			'is_active' => 1,
		));
		$rules[] = new RewriteRule(array(
			'name' => 'portfolio',
			'parse_regex' => '/^' . Options::get('pompous:rewrite_base') . '[\/]{0,1}$/i',
			'build_str' => Options::get('pompous:rewrite_base'),
			'handler' => 'UserThemeHandler',
			'action' => 'display_portfolio',
			'priority' => 8,
			'is_active' => 1,
		));

		
		return $rules;
	}
	public function action_handler_display_portfolio($handler_vars) {
		$this->theme= Themes::create();
		
		if(isset($handler_vars['search'])) {
			$items = self::get_items($handler_vars['search']);
		} else {
			$items = self::get_items();
		}
		
		$this->theme->assign( 'portfolio', $items);
		$this->theme->assign( 'title', 'Portfolio');
		$this->theme->display( 'portfolio' );
		exit;
	}
	
	public function action_handler_display_item($handler_vars) {
		$this->theme= Themes::create();
		
		$items = self::get_items('id:' . $handler_vars['item']);
		
		if($items != FALSE) {
			$this->theme->assign( 'portfolio', $items[$handler_vars['item']] );
			$this->theme->assign( 'title', 'Portfolio');
			$this->theme->display( 'portfolio.single' );
			exit;
		} else {
			$this->theme->display( 'portfolio' );
			exit;
		}
	}
	
	public function get_items($search = '') {
		$data = self::fetch_resume( Options::get('pompous:xmlurl'));
		$resume = self::parse_resume($data);
		
		$items = $resume;
		$searches;
		
		if($search != '') {
			$searches = explode('/', $search);
		}
		
		$searches[] = Options::get('pompous:forcedsearch');
				
		foreach($searches as $search) {
			$search = explode(':', $search, 2);
			$items = self::filter_items($items, $search[0], $search[1]);
		}
		
		return $items;
	}
	function parse_resume($resume) {
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
			
			if(isset($record->name)) {
				$records[$id]['name'] = strval($record->name);
			}
			
			if(isset($record->description)) {
				$records[$id]['description'] = strval($record->description);
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
						if(!self::is_url($link)) {
							$url = $link;
						} else {
							$url = dirname(Options::get('pompous:xmlurl')) . '/' . $link;
						}
						$records[$id]['attachments'][$attachid]['url'] = $url;
						$records[$id]['attachments'][$attachid]['link'] = $link;
					}
					if(isset($attachment->thumb)) {
						$link = strval($attachment->thumb);
						if(!self::is_url($link)) {
							$url = $link;
						} else {
							$url = dirname(Options::get('pompous:xmlurl')) . '/' . $link;
						}
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
				$records[$id]['time']['start'] = strtotime(strval($record->start));
				$records[$id]['time']['end'] = strtotime(strval($record->end));
			} elseif(isset($record->end)) {
				$records[$id]['time'] = strtotime(strval($record->end));
			}
			
			if(isset($record->featured)) {
				$records[$id]['featured'] = 'true';
			} else {
				$records[$id]['featured'] = 'false';
			}
			
			if(isset($record->quote)) {
				$records[$id]['quote']['name'] = strval($record->quote['name']);
				$records[$id]['quote']['url'] = strval($record->quote['url']);
				$records[$id]['quote']['text'] = strval($record->quote[0]);
			}
			
			$records[$id]['portfoliourl'] = Site::get_url( 'habari' ) . '/' . Options::get('pompous:rewrite_base') . '/view/' . $id . '/';
		}
		
		return $records;
	}
	
	function is_url($url) {
		if (preg_match('/(http|https):\/\/(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/', $url)) {
			return true;
		} else {
			return false;
		}
	}
	
	function filter_items($items, $field, $value) {
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
	
	function search_field($hay, $needle) {
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
	function fetch_resume($url) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

		$data = curl_exec($ch);

		if (curl_errno($ch)) {
			Error::raise('Failed fetching of resume');
		} else {
			curl_close($ch);
			return $data;
		}
		
	}
}
?>