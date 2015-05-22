<?php

/**
 * Twitter tweet's entities parser & formatter
 * @author mrubio
 *
 */
class Tweet {
	
    const ENCODING = 'UTF-8';
    
	/**
	 * The raw tweet object, as returned from the api
	 * @var stdclass
	 */
	protected $rawTweet;
	
	/**
	 * Link attributes
	 * @var array
	 */
	protected $attributes=array();
	
	/**
	 * Constructor
	 * @param stdclass $rawTweet The raw tweet object as returned from the api
	 */
	public function __construct(\stdClass $rawTweet) {
		$this->rawTweet = $rawTweet;
	}
	
	/**
	 * Orders the entities so we can work on replacing pieces of the string afterwards
	 * @return array The ordered entities
	 */
	protected function _getOrderedEntities() {
		$entities = array();
		$rawEnts = $this->rawTweet->entities;
		foreach($rawEnts as $key => $set) {
			foreach($set as $ent) {
				if (isset($ent->indices[0])) {
					$entities[$ent->indices[0]] = array('type'=>$key,'entity'=>$ent);
				}
			}
		}
		ksort($entities,SORT_NUMERIC);
		return $entities;
	}
	
	/**
	 * Sets an attribute for the links that'll be generated
	 * @param string $name attribute name
	 * @param string $value attribute value
	 */
	public function setLinkAttribute($name,$value) {
		$this->attributes[$name] = $value;
	}
	
	/**
	 * Formats a twitter entity
	 * @param string $type Entity type
	 * @param stdclass $entity Twitter entity
	 * @return string
	 */
	protected function formatEntity($type,$entity) {
		$funcName = 'format_'.$type;
		if (method_exists($this,$funcName))
			return $this->$funcName($entity);
		else {
			$length = $entity->indices[1] - $entity->indices[0];
			return mb_substr($this->rawTweet->text, $entity->indices[0], $length, self::ENCODING);
		}
	}
	
	/**
	 * Builds an A tag
	 * @param string $url
	 * @param string $text
	 * @return string
	 */
	protected function getLinkTag($url,$text) {
		$attrs = array();
		foreach($this->attributes as $name=>$val) {
			$attrs[] = htmlspecialchars($name).'="'.htmlspecialchars($val).'"';
		}
		
		return sprintf(
			'<a href="%s" %s>%s</a>',
			htmlspecialchars($url),
			implode(' ',$attrs),
			htmlspecialchars($text)
		);
	}
	
	/**
	 * Formats a url entity
	 * @param stdclass $entity
	 * @return string
	 */
	protected function format_urls($entity) {
		return $this->getLinkTag($entity->expanded_url, $entity->display_url);
	}
	
	/**
	 * Formats a hashtag entity
	 * @param stdclass $entity
	 * @return string
	 */
	protected function format_hashtags($entity) {
		$url = 'https://twitter.com/search?q=%23'.urlencode($entity->text).'&src=hash';
		return $this->getLinkTag($url, '#'.$entity->text);
	}
	
	/**
	 * Formats a user_mentions entity
	 * @param stdclass $entity
	 * @return string
	 */
	protected function format_user_mentions($entity) {
		$url = 'https://twitter.com/'.urlencode($entity->screen_name);
		return $this->getLinkTag($url, '@'.$entity->screen_name);
	}
	
	/**
	 * Formats a media entity(photos).
	 * @param stdclass $entity
	 * @return string
	 */
	protected function format_media($entity) {
		return $this->format_urls($entity);
	}
	
	/**
	 * Retrieves the creation date of the tweet
	 * @return \DateTime|false
	 */
	public function getCreationDate() {
	    $datetime = date_create($this->rawTweet->created_at);
	    return $datetime;
	}
	
	/**
	 * Outputs the formatted tweet
	 * @return string
	 */
	public function __toString() {	    
		$text = $this->rawTweet->text;
		$entities = $this->_getOrderedEntities();
		$newText = '';
		$startPos = 0;
		foreach($entities as $val) {
			$type = $val['type'];
			$entity = $val['entity'];
			
			$newText .= mb_substr($text, $startPos, $entity->indices[0] - $startPos, self::ENCODING);
			$newText .= $this->formatEntity($type,$entity);
			$startPos = $entity->indices[1];
		}
		$newText .= mb_substr($text, $startPos, null, self::ENCODING);
		
		return strip_tags($newText,'<a>');
	}
}