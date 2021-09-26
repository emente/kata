<?php

/**
 * contains utility class
 * @package kata
 */

/**
 * routines to generate valid rss/atom feeds
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class FeedUtilityItem {

	/**
	 * @var string $title
	 */
	public $title = '';
	/**
	 * @var string $url
	 */
	public $url = '';
	/**
	 * @var integer $updatedOn unix-timestamp
	 */
	public $updatedOn = 0;
	/**
	 * @var string $html
	 */
	public $html = '';

	/**
	 * create a single item, ready to be added to feed with addItem()
	 *
	 * @param string $title
	 * @param string $url
	 * @param integer $updatedOn unix-timestamp
	 * @param string $html
	 */
	function __construct($title, $url, $updatedOn, $html='') {
		if (!is_string($title)) {
			throw new InvalidArgumentException('title must be a string');
		}
		$this->title = $title;
		if (!is_string($url)) {
			throw new InvalidArgumentException('url must be a string');
		}
		if ('http' != substr($url, 0, 4)) {
			throw new InvalidArgumentException('url must be absolute');
		}
		$this->url = $url;
		if (!is_numeric($updatedOn)) {
			throw new InvalidArgumentException('updateOn must be a string');
		}
		$this->updatedOn = $updatedOn;
		if (!is_string($url)) {
			throw new InvalidArgumentException('html must be a string');
		}
		$this->html = $html;
	}

}

/**
 * routines to generate valid rss/atom feeds
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class FeedUtility {

	/**
	 * @var string $title
	 */
	private $title = '';
	/**
	 * @var string $subtitle
	 */
	private $subtitle = '';
	/**
	 * @var string $url
	 */
	private $url = '';
	/**
	 * @var integer $updatedOn
	 */
	private $updatedOn = 0;
	/**
	 * @var string $author
	 */
	private $author = '';
	/**
	 * @var array $items;
	 */
	private $items = array();

	/**
	 * well...
	 * @param string $subtitle
	 */
	function setSubTitle($subtitle) {
		if (!is_string($subtitle)) {
			throw new InvalidArgumentException('subtitle must be a string');
		}
		$htis->subtitle = $subtitle;
	}

	/**
	 * well...
	 * @param string $author
	 */
	function setAuthor($author) {
		if (!is_string($author)) {
			throw new InvalidArgumentException('author must be a string');
		}
		$this->author = $author;
	}

	/**
	 * start over to create a new feed
	 *
	 * @param string $title feed title
	 * @param string $url url where we can find this feed (not the feeds url!)
	 * @param string $updatedOn optional, if zero takes date of newest item
	 */
	function create($title, $url, $updatedOn=0) {
		unset($this->items);

		if (!is_string($title)) {
			throw new InvalidArgumentException('title must be a string');
		}
		$this->title = $title;
		if (!is_string($url)) {
			throw new InvalidArgumentException('url must be a string');
		}
		if ('http' != substr($url, 0, 4)) {
			throw new InvalidArgumentException('url must be absolute');
		}
		$this->url = $url;
		if (!is_numeric($updatedOn)) {
			throw new InvalidArgumentException('updateOn must be a string');
		}
		$this->updatedOn = $updatedOn;
	}

	/**
	 * add a single feeditem to the stream
	 *
	 * @param FeedUtilityItem $item
	 */
	function addItem(FeedUtilityItem $item) {
		if (empty($this->items)) {
			$this->items = array();
		}
		$this->items[] = $item;
	}

	//////////////////////////////////////////////////////////////////////////

	/**
	 * find newest timestamp of all items
	 *
	 * @return integer
	 */
	private function getTimeOfNewestEntry() {
		$newest = 0;
		foreach ($this->items as $item) {
			if ($item->updatedOn > $newest) {
				$newest = $item->updatedOn;
			}
		}
		return $newest;
	}

	/**
	 * create ISO8601 compliant time string
	 *
	 * @param integer $time unix-timestamp
	 * @return string
	 */
	private function getIso8601Time($time) {
		$date = gmdate("Y-m-d\TH:i:sO", $time);
		$date = substr($date, 0, 22) . ':' . substr($date, -2);
		return $date;
	}

	/**
	 * create RC822 compliant time string
	 *
	 * @param integer $time unix-timestamp
	 * @return string
	 */
	private function GetRfc822Time($time) {
		$date = gmdate("D, d M Y H:i:s", $time);
		return $date;
	}

	/**
	 * create internationlized resource identifier, see RFC3987
	 *
	 * @param string $title
	 * @param string $url
	 * @param integer $updatedOn unix-timestamp
	 * @return string
	 */
	private function getRFC3987IRI($title, $url, $updatedOn) {
		return 'tag:' . parse_url($url, PHP_URL_HOST) . ',2011-01-01:' . sha1($url);
	}

	/**
	 * create a RFC1928 compliant globally unique identifier
	 *
	 * @param string $title
	 * @param string $url
	 * @param integer $updatedOn unix-timestamp
	 * @return string
	 */
	private function getGuid($title, $url, $updatedOn) {
		return $url;
	}

	/**
	 * package given string in a cdata-section
	 *
	 * @param string $data
	 * @return string
	 */
	private function cdata($data) {
		return '<![CDATA[' . $data . ']]>';
	}

	//////////////////////////////////////////////////////////////////////////

	/**
	 * echo feed to browser, including content-type
	 *
	 * @param string $format output-format, can be atom|rss
	 */
	function output($format) {
		switch (strtolower($format)) {
			case 'atom':
				header('Content-type: application/atom+xml; charset=utf-8');
				$this->outputAtom();
				return;
				break;

			case 'rss':
			case 'rss2':
			case 'rss2.0':
				header('Content-type: application/rss+xml; charset=utf-8');
				$this->outputRss20();
				return;
				break;

			default:
				throw new UnexpectedValueException('unknown output format');
				break;
		}
	}

	/**
	 * return complete feed as string, in case you want to cache it
	 *
	 * @param string $format see output()
	 * @return string
	 */
	function outputAsString($format) {
		switch (strtolower($format)) {
			case 'atom':
				ob_start();
				$this->outputAtom();
				return ob_get_clean();
				break;

			case 'rss':
			case 'rss2':
			case 'rss2.0':
				ob_start();
				$this->outputRss20();
				return ob_get_clean();
				break;

			default:
				throw new UnexpectedValueException('unknown output format');
				break;
		}
	}

	//////////////////////////////////////////////////////////////////////////

	/**
	 * output atom-stream
	 */
	private function outputAtom() {
		if (0 == $this->updatedOn) {
			$this->updatedOn = $this->getTimeOfNewestEntry();
		}

		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<feed xmlns=\"http://www.w3.org/2005/Atom\">\n";
		echo "\n";
		echo "  <title type=\"html\">" . $this->cdata($this->title) . "</title>\n";
		if (!empty($this->subtitle)) {
			echo "  <subtitle type=\"html\">" . $this->cdata($this->subtitle) . "</subtitle>\n";
		}
		if (!empty($this->author)) {
			echo " <author><name>" . h($this->author) . "</name></author>\n";
		}
		echo "  <link rel=\"alternate\" href=\"" . $this->url . "\"/>\n";
		echo "  <updated>" . $this->getIso8601Time($this->updatedOn) . "</updated>\n";
		echo "  <generator>kata FeedUtility</generator>\n";
		echo "  <id>" . $this->url . "</id>\n\n";

		foreach ($this->items as $item) {
			$this->outputAtomItem($item);
		}

		echo "</feed>\n";
	}

	/**
	 * output single item in atom-format
	 *
	 * @param FeedUtilityItem $item single item
	 */
	private function outputAtomItem(FeedUtilityItem $item) {
		echo "  <entry>\n";
		echo "    <title type=\"text\">" . $this->cdata($item->title) . "</title>\n";
		echo "    <link href=\"" . $item->url . "\" rel=\"alternate\" title=\"" . h($item->title) . "\" />\n";
		echo "    <id>" . $this->getRFC3987IRI($item->title, $item->url, $item->updatedOn) . "</id>\n";
		echo "    <updated>" . $this->getIso8601Time($item->updatedOn) . "</updated>\n";
		echo "    <published>" . $this->getIso8601Time($item->updatedOn) . "</published>\n";
		echo "    <content type=\"html\">" . $this->cdata($item->html) . "</content>\n";
		echo "  </entry>\n  \n";
	}

	//////////////////////////////////////////////////////////////////////////

	/**
	 * output rss2.0 stream
	 */
	private function outputRss20() {
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
		echo "<!-- generator=\"kata FeedUtility\" -->\n";
		echo "<rss version=\"2.0\">\n";
		echo "<channel>\n";
		echo "	<title>" . $this->cdata($this->title) . "</title>\n";
		echo "	<description>" . $this->cdata($this->subtitle) . "</description>\n";
		echo "	<link>" . $this->cdata($this->url) . "</link>\n";
		echo "	<lastBuildDate>" . $this->getRfc822Time(time()) . " </lastBuildDate>\n";
		echo "	<pubDate>" . $this->getRfc822Time($this->getTimeOfNewestEntry()) . " </pubDate>\n\n";

		foreach ($this->items as $item) {
			$this->outputRss20Item($item);
		}

		echo "</channel>\n";
		echo "</rss>\n";
	}

	/**
	 * output single item in rss2.0 format
	 *
	 * @param FeedUtilityItem $item single iteam
	 */
	private function outputRss20Item(FeedUtilityItem $item) {
		echo "	<item>\n";
		echo "		<title>" . $this->cdata($item->title) . "</title>\n";
		echo "		<description>" . $this->cdata($item->html) . "</description>\n";
		echo "		<link>" . $this->cdata($item->url) . "</link>\n";
		echo "		<guid>" . $this->getGuid($item->title, $item->url, $item->updatedOn) . "</guid>\n";
		echo "		<pubDate>" . $this->GetRfc822Time($item->updatedOn) . " </pubDate>\n";
		echo "	</item>\n";
		echo " \n";
	}

}
