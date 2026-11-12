<?php

namespace OPodSync;

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

	public function sync(): void
	{
		$db = DB::getInstance();
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

	/**
	 * True only if the URL's host resolves exclusively to public, globally-routable addresses.
	 * Fails closed on unresolvable hosts. Used to keep user-supplied feed URLs (and their redirect
	 * targets) from reaching the server's own loopback/private network or cloud metadata.
	 */
	protected static function hostIsPublic(string $url): bool
	{
		$host = parse_url($url, PHP_URL_HOST);

		if (!$host) {
			return false;
		}

		$host = trim($host, '[]');
		$ips = [];

		if (filter_var($host, FILTER_VALIDATE_IP)) {
			$ips[] = $host;
		}
		else {
			foreach ([DNS_A, DNS_AAAA] as $type) {
				$records = @dns_get_record($host, $type);

				if (is_array($records)) {
					foreach ($records as $record) {
						if (!empty($record['ip'])) {
							$ips[] = $record['ip'];
						}
						if (!empty($record['ipv6'])) {
							$ips[] = $record['ipv6'];
						}
					}
				}
			}
		}

		if (empty($ips)) {
			return false;
		}

		foreach ($ips as $ip) {
			// Normalise IPv4-mapped IPv6 (e.g. ::ffff:127.0.0.1) so the private check applies.
			if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m)) {
				$ip = $m[1];
			}

			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return false;
			}

			// CGNAT 100.64.0.0/10 is not covered by FILTER_FLAG_NO_RES_RANGE.
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$long = ip2long($ip);

				if ($long !== false && ($long & 0xffc00000) === (ip2long('100.64.0.0') & 0xffc00000)) {
					return false;
				}
			}
		}

		return true;
	}

	public function fetch(): bool
	{
		// SSRF protection: the feed URL is supplied by a user (add subscription), so it must not be
		// usable to reach loopback/private/reserved addresses, directly or via a redirect.
		if (!self::hostIsPublic($this->feed_url)) {
			return false;
		}

		$body = false;

		if (function_exists('curl_exec')) {
			// Follow redirects manually so every hop can be re-validated against the host filter.
			$url = $this->feed_url;

			for ($i = 0; $i < 5; $i++) {
				if (!self::hostIsPublic($url)) {
					$body = false;
					break;
				}

				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: oPodSync']);
				curl_setopt($ch, CURLOPT_TIMEOUT, 5);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$body = @curl_exec($ch);
				$redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

				if (PHP_VERSION_ID < 80500) {
					curl_close($ch);
				}

				if ($redirect) {
					$url = $redirect;
					$body = false;
					continue;
				}

				break;
			}
		}
		else {
			$ctx = stream_context_create([
				'http' => [
					'header'          => 'User-Agent: oPodSync',
					'max_redirects'   => 0,
					'follow_location' => false,
					'timeout'         => 5,
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

		if (!$body) {
			return false;
		}

		// Only update feed update time, if the server was reached
		$this->last_fetch = time();
		$db = DB::getInstance();
		$db->simple('UPDATE feeds SET last_fetch = ? WHERE feed_url = ?;', time(), $this->feed_url);

		$item_pattern = '!<item[^>]*>(.+?)</item>!s';

		// Not using an XML parser as some feeds are broken :(
		if (!preg_match_all($item_pattern, $body, $match)) {
			// Most likely the feed is missing / 404, don't come back unless we have a new action
			return false;
		}

		// Remove items from body
		$body = preg_replace($item_pattern, '', $body);

		$this->title = $this->getTagValue($body, 'title');

		if (!$this->title) {
			return false;
		}

		$pubdate = $this->getTagValue($body, 'pubDate');
		$language = $this->getTagValue($body, 'language');

		foreach ($match[1] as $item) {
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

		$this->url = $this->getTagValue($body, 'link');
		$this->description = $this->getTagValue($body, 'description');
		$this->language = $language ? substr($language, 0, 2) : null;
		$this->image_url = $this->getTagAttribute($body, 'itunes:image', 'href') ?? $this->getTagValue($body, 'image', 'url');
		$this->pubdate = $pubdate ? new \DateTime($pubdate) : null;

		return true;
	}

	protected function getDuration(?string $str): ?int
	{
		if (!$str) {
			return null;
		}

		if (false !== strpos($str, ':') && ctype_digit(str_replace(':', '', trim($str)))) {
			$parts = explode(':', $str);
			$parts = array_map('intval', $parts);
			$duration = (($parts[0] ?? 0) * 3600) + (($parts[1] ?? 0) * 60) + ($parts[2] ?? 0);
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
