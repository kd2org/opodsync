<?php

namespace KD2\HTTP;

class URL
{
	/**
	 * Validate URL, allowing IDN domain names
	 */
	static public function isValidURL(string $url): bool
	{
		$url = parse_url($url);

		if (empty($url['host']) || empty($url['scheme'])) {
			return false;
		}

		if (!in_array($url['scheme'], ['http', 'https'], true)) {
			return false;
		}

		$url['host'] = idn_to_ascii($url['host']);
		$n = $url['scheme'] . '://' . $url['host'];

		if (!empty($url['port'])
			&& !($url['port'] == 80 && $url['scheme'] === 'http')
			&& !($url['port'] == 443 && $url['scheme'] === 'https')) {
			$n .= ':' . $url['port'];
		}

		$n .= $url['path'] ?? '/';

		if (!empty($url['query'])) {
			$n .= '?' . $url['query'];
		}

		return filter_var($n, FILTER_VALIDATE_URL) !== false;
	}

	/**
	 * Validates that a URL is valid and is not an IP address or some kind of DNS poisoning (SSRF)
	 * (eg. http://localhost.mydomain.com/ points to 127.0.0.1)
	 */
	static public function validateExternalURL(string $url, ?string $allowed_path = null): void
	{
		$parts = @parse_url($url);

		if (!$parts) {
			throw new \InvalidArgumentException('Invalid URL: ' . $url);
		}

		$parts['scheme'] ??= '';
		$host = $parts['host'] ?? '';

		if (null !== $allowed_path
			&& !empty($parts['path'])
			&& $parts['path'] !== $allowed_path) {
			throw new \InvalidArgumentException(sprintf('Unexpected path "%s" in URL (only path allowed is "%s")', $parts['path'], $allowed_path));
		}

		if (!in_array($parts['scheme'], ['http', 'https'])) {
			throw new \InvalidArgumentException(sprintf('Invalid scheme "%s" in URL', $parts['scheme']));
		}

		if (!empty($parts['port'])) {
			throw new \InvalidArgumentException('Unauthorized port in URL');
		}

		if (!trim($host) || preg_match('/^[\d.]+$|\[/', $host)) {
			throw new \InvalidArgumentException(sprintf('Unauthorized host "%s" in URL', $host));
		}

		$host = idn_to_ascii($host);

		static $allowed_hosts = [];

		if (!array_key_exists($host, $allowed_hosts)) {
			$records = @dns_get_record($host, DNS_AAAA | DNS_A);
			$found = null;

			foreach ($records ?: [] as $record) {
				$ip = $record['ip'] ?? ($record['ipv6'] ?? null);

				// Normalise IPv4-mapped IPv6 (e.g. ::ffff:127.0.0.1) so the private check applies.
				if ($ip && preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m)) {
					$ip = $m[1];
				}

				if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					$found = $ip ?: '[no IP found]';
					break;
				}

				// CGNAT 100.64.0.0/10 is not covered by FILTER_FLAG_NO_RES_RANGE.
				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
					$long = ip2long($ip);

					if ($long !== false && ($long & 0xffc00000) === (ip2long('100.64.0.0') & 0xffc00000)) {
						$found = $ip;
						break;
					}
				}
			}

			$hosts_cache[$host] = $found ?? '[no IP found]';
		}

		// Don't allow to make requests to localhost or internal networks
		if ($ip = $hosts_cache[$host]) {
			throw new \InvalidArgumentException(sprintf('Unauthorized resolved IP %s for host "%s" in URL', $ip, $host));
		}
	}


	/**
	 * Transforms a parse_url array back into a string
	 * @param  array  $url
	 * @return string
	 */
	static public function glue(array $url): string
	{
		static $parts = [
			'scheme'   => '%s:',
			'host'     => '//%s',
			'port'     => ':%d',
			'user'     => '%s',
			'pass'     => ':%s',
			'path'     => '%s',
			'query'    => '?%s',
			'fragment' => '#%s',
		];

		$out = [];

		foreach ($parts as $name => $str)
		{
			if (isset($url[$name]))
			{
				$out[] = sprintf($str, $url[$name]);
			}

			if ($name == 'pass' && isset($url['user']) || isset($url['pass']))
			{
				$out[] = '@';
			}
		}

		return implode('', $out);
	}

	/**
	 * Merge two URLs, managing relative $b URL
	 * @param  string $a Primary URL
	 * @param  string $b New URL
	 * @param  boolean $dismiss_query Set to TRUE to dismiss query part of the primary URL
	 * @return string
	 */
	static public function merge(string $a, string $b, bool $dismiss_query = false): string
	{
		$a = parse_url($a);
		$b = parse_url($b);

		if ($dismiss_query)
		{
			// Don't propagate query params between redirects
			unset($a['query']);
		}
		else {
			parse_str($a['query'] ?? '', $a_query);
			parse_str($b['query'] ?? '', $b_query);
			$b['query'] = http_build_query(array_merge($a_query, $b_query));

			if ($b['query'] == '') {
				unset($b['query']);
			}
		}

		// Relative URL
		if (!isset($b['host']) && isset($b['path']) && substr(trim($b['path']), 0, 1) != '/')
		{
			$path = preg_replace('![^/]*$!', '', $a['path']);
			$path.= preg_replace('!^\./!', '', $b['path']);
			unset($a['path']);

			// replace // or  '/./' or '/foo/../' with '/'
			$b['path'] = preg_replace('#/(?!\.\.)[^/]+/\.\./|/\.?/#', '/', $path);
		}

		$url = array_merge($a, $b);
		return self::glue($url);
	}

	/**
	 * Return root application URI (absolute, but no host or scheme)
	 * @param  string $app_root Directory root of current application (eg. __DIR__ of the public/www directory)
	 * @return string
	 */
	static public function getRootURI(string $app_root): string
	{
		// Convert from Windows paths to UNIX paths
		$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
		$app_root = str_replace('\\', '/', $app_root);

		$document_root = rtrim($document_root, '/');
		$app_root = rtrim($app_root, '/');

		if ('' === trim($app_root)) {
			throw new \UnexpectedValueException('Invalid document root: empty app root');
		}

		if ('' === trim($document_root)) {
			throw new \UnexpectedValueException('Invalid document root: empty document root');
		}

		// Find the relative path inside the server document root
		if (0 === strpos($document_root, $app_root)) {
			// document root is below the app root: great!
			// eg. app_root = /home/user/www/app/www
			// and document_root = /home/user/www/app/www
			// or document_root = /home/user/www/app/www/admin
			$path = substr($document_root, strlen($app_root));
		}
		elseif (0 === strpos($app_root, $document_root)) {
			// document root is ABOVE the app root: not great, but should still work
			// eg. app_root = /home/user/www/app/www
			// and document_root = /home/user/www
			$path = substr($app_root, strlen($document_root));
		}
		else {
			throw new \UnexpectedValueException('Invalid document root: cannot find app root');
		}

		$path = trim($path, '/') . '/';

		if ($path[0] != '/') {
			$path = '/' . $path;
		}

		return $path;
	}

	/**
	 * Return current HTTP host / server name
	 * @return string Host, will be 'host.invalid' if the supplied host (via 'Host' HTTP header or SERVER_NAME is invalid)
	 */
	static public function getHost(): string
	{
		if (!isset($_SERVER['HTTP_HOST']) && !isset($_SERVER['SERVER_NAME']) && !isset($_SERVER['SERVER_ADDR'])) {
			return 'host.unknown';
		}

		$host = isset($_SERVER['HTTP_HOST'])
			? $_SERVER['HTTP_HOST']
			: (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['SERVER_ADDR']);

		// Host name must be lowercase
		$host = strtolower(trim($host));

		// Host name MUST be less than 255 bytes
		if (strlen($host) > 255) {
			return 'host.invalid';
		}
		// The host can come from the user
		// check that it does not contain forbidden characters (see RFC 952 and RFC 2181)

		// Delete allowed special characters for check
		$valid_host = str_replace(['_', '-', ':', '[', ']', '.'], '', $host);

		if (!ctype_alnum($valid_host)) {
			return 'host.invalid';
		}

		if (isset($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], [80, 443])) {
			$host .= ':' . $_SERVER['SERVER_PORT'];
		}

		return $host;
	}

	/**
	 * Return current HTTP scheme
	 * @return string http or https
	 */
	static public function getScheme(): string
	{
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			return 'https';
		}
		elseif (($_SERVER['SERVER_PORT'] ?? null) == 443) {
			return 'https';
		}
		else {
			return 'http';
		}
	}

	/**
	 * Return complete app URL
	 * @return string
	 */
	static public function getAppURL(string $app_root): string
	{
		return self::getScheme() . '://' . self::getHost() . self::getRootURI($app_root);
	}

	/**
	 * Return current complete request URL
	 * @param bool $with_query_string Use FALSE to return the request URL without the query string (?param=a...)
	 * @return string
	 */
	static public function getRequestURL(bool $with_query_string = true): string
	{
		$path = $_SERVER['REQUEST_URI'];

		if (!$with_query_string && false !== ($pos = strpos($path, '?'))) {
			$path = substr($path, 0, $pos);
		}

		return self::getScheme() . '://' . self::getHost() . $path;
	}

	/**
	 * RFC 6570 URI template replacement, supports level 1 and level 2
	 * @param string $uri    URI with placeholders
	 * @param array  $params Parameters (placeholders)
	 * @link  https://www.rfc-editor.org/rfc/rfc6570.txt
	 * @return string
	 */
	static public function applyTemplate(string $uri, array $params = []): string
	{
		static $var_name = '(?:[0-9a-zA-Z_]|%[0-9A-F]{2})+';

		// Delimiters
		static $delims = [
			'%3A' => ':', '%2F' => '/', '%3F' => '?', '%23' => '#',
			'%5B' => '[', '%5D' => ']', '%40' => '@', '%21' => '!',
			'%24' => '$', '%26' => '&', '%27' => '\'', '%28' => '(',
			'%29' => ')', '%2A' => '*', '%2B' => '+', '%2C' => ',',
			'%3B' => ';', '%3D' => '=',
		];

		// Level 2: {#variable} => #/foo/bar
		$uri = preg_replace_callback('/\{#(' . $var_name . ')\}/i', function ($match) use ($params, $delims) {
			if (!isset($params[$match[1]]))
			{
				return '';
			}

			return '#' . strtr(rawurlencode($params[$match[1]]), $delims);
		}, $uri);

		// Level 2: {+variable} => /foo/bar
		$uri = preg_replace_callback('/\{\+(' . $var_name . ')\}/i', function ($match) use ($params, $delims) {
			if (!isset($params[$match[1]]))
			{
				return '';
			}

			return strtr(rawurlencode($params[$match[1]]), $delims);
		}, $uri);

		// Level 1: {variable} => %2Ffoo%2Fbar
		$uri = preg_replace_callback('/\{(' . $var_name . ')\}/i', function ($match) use ($params) {
			if (!isset($params[$match[1]]))
			{
				return '';
			}

			return rawurlencode($params[$match[1]]);
		}, $uri);

		return $uri;
	}

}
