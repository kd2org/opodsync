<?php

class Feed
{
	public ?string $feed_url = null;
	public ?string $image_url = null;
	public ?string $url = null;
	public ?string $language = null;
	public ?string $title = null;
	public ?string $description = null;
	public ?\DateTime $pubdate = null;
	public int $last_fetch;
	protected array $episodes = [];

	public function __construct(string $url)
	{
		$this->feed_url = $url;
	}

	public function load(\stdClass $data): void
	{
		foreach ($data as $key => $value) {
			if ($key === 'id') {
				continue;
			}
			elseif ($key === 'pubdate' && $value) {
				$this->$key = new \DateTime($value);
			}
			else {
				$this->$key = $value;
			}
		}
	}

	public function sync(DB $db): void
	{
		$db->exec('BEGIN;');
		$db->upsert('feeds', $this->export(), ['feed_url']);
		$feed_id = $db->firstColumn('SELECT id FROM feeds WHERE feed_url = ?;', $this->feed_url);
		$db->simple('UPDATE subscriptions SET feed = ? WHERE url = ?;', $feed_id, $this->feed_url);

		foreach ($this->episodes as $episode) {
			$episode = (array) $episode;
			$episode['pubdate'] = $episode['pubdate']->format('Y-m-d H:i:s \U\T\C');
			$episode['feed'] = $feed_id;
			$db->upsert('episodes', $episode, ['feed', 'media_url']);
			$id = $db->firstColumn('SELECT id FROM episodes WHERE media_url = ?;', $episode['media_url']);
			$db->simple('UPDATE episodes_actions SET episode = ? WHERE url = ?;', $id, $episode['media_url']);
		}

		$db->exec('END');
	}

	public function fetch(): void
	{
		if (function_exists('curl_exec')) {
			$ch = curl_init($this->feed_url);
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: oPodSync']);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$body = @curl_exec($ch);

			if (false === $body) {
				$error = curl_error($ch);
			}

			curl_close($ch);
		}
		else {
			$ctx = stream_context_create([
				'http' => [
					'header'          => 'User-Agent: oPodSync',
					'max_redirects'   => 5,
					'follow_location' => true,
					'timeout'         => 10,
					'ignore_errors'   => true,
				],
				'ssl'  => [
					'verify_peer'       => true,
					'verify_peer_name'  => true,
					'allow_self_signed' => true,
					'SNI_enabled'       => true,
				],
			]);

			$body = @file_get_contents($this->feed_url, false, $ctx);
		}

		$this->last_fetch = time();


		if (!$body) {
			return;
		}

		while (preg_match('!<item[^>]*>(.*?)</item>!s', $body, $match)) {
			$body = str_replace($match[0], '', $body);
			$item = $match[1];
			$pubdate = $this->getTagValue($item, 'pubDate');
			$url = $this->getTagAttribute($item, 'enclosure', 'url');

			// Not an episode, just a regular blog post, ignore
			if (!$url) {
				continue;
			}

			$this->episodes[] = (object) [
				'image_url'   => $this->getTagAttribute($item, 'itunes:image', 'href') ?? $this->getTagValue($item, 'image', 'url'),
				'url'         => $this->getTagValue($item, 'link'),
				'media_url'   => $url,
				'pubdate'     => $pubdate ? new \DateTime($pubdate) : null,
				'title'       => $this->getTagValue($item, 'title'),
				'description' => $this->getTagValue($item, 'description') ?? $this->getTagValue($item, 'content:encoded'),
				'duration'    => $this->getDuration($this->getTagValue($item, 'itunes:duration') ?? $this->getTagAttribute($item, 'enclosure', 'length')),
			];
		}

		$pubdate = $this->getTagValue($body, 'pubDate');
		$language = $this->getTagValue($body, 'language');

		$this->url = $this->getTagValue($body, 'link');
		$this->title = $this->getTagValue($body, 'title');
		$this->description = $this->getTagValue($body, 'description');
		$this->language = $language ? substr($language, 0, 2) : null;
		$this->image_url = $this->getTagAttribute($body, 'itunes:image', 'href') ?? $this->getTagValue($body, 'image', 'url');
		$this->pubdate = $pubdate ? new \DateTime($pubdate) : null;

		if (!$this->title) {
			throw new \LogicException('Missing title for: ' . $this->feed_url);
		}

		foreach ($this->episodes as $episode) {
			if (empty($episode->media_url) || empty($episode->title)) {
				var_dump($this->feed_url, $episode); exit;
			}
		}
	}

	protected function getDuration(?string $str): ?int
	{
		if (!$str) {
			return null;
		}

		if (false !== strpos($str, ':')) {
			$parts = explode(':', $str);
			$duration = ($parts[2] ?? 0) * 3600 + ($parts[1] ?? 0) * 60 + $parts[0] ?? 0;
		}
		else {
			$duration = (int) $str;
		}

		// Duration is less than 20 seconds? probably an error
		if ($duration <= 20) {
			return null;
		}

		return $duration;
	}

	public function getTagValue(string $str, string $name, ?string $sub = null): ?string
	{
		if (!preg_match('!<' . $name . '[^>]*>(.*?)</' . $name . '>!is', $str, $match)) {
			return null;
		}

		$str = $match[1];

		if ($sub !== null) {
			return $this->getTagValue($str, $sub);
		}

		$str = trim(html_entity_decode($str));

		if ($str === '') {
			return null;
		}

		$str = str_replace(['<![CDATA[', ']]>'], '', $str);
		return $str;
	}

	public function getTagAttribute(string $str, string $name, string $attr): ?string
	{
		if (!preg_match('!<' . $name . '[^>]+' . $attr . '=(".*?"|\'.*?\'|[^\s]+)[^>]*>!is', $str, $match)) {
			return null;
		}

		$value = trim($match[1], '"\'');

		return trim(rawurldecode(htmlspecialchars_decode($value))) ?: null;
	}

	public function export(): array
	{
		$out = get_object_vars($this);
		$out['pubdate'] = $out['pubdate'] ? $out['pubdate']->format('Y-m-d H:i:s \U\T\C') : null;
		unset($out['episodes']);
		return $out;
	}

	public function listEpisodes(): array
	{
		return $this->episodes;
	}
}
