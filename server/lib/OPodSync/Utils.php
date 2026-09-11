<?php

namespace OPodSync;

class Utils
{
	static public function format_description(string $str): string
	{
		$str = str_replace('</p>', "\n\n", $str);
		$str = preg_replace_callback('!<a[^>]*href=(".*?"|\'.*?\'|\S+)[^>]*>(.*?)</a>!i', function ($match) {
			$url = trim($match[1], '"\'');
			if ($url === $match[2]) {
				return $match[1];
			}
			else {
				return '[' . $match[2] . '](' . $url . ')';
			}
		}, $str);
		$str = htmlspecialchars(strip_tags($str));
		$str = preg_replace("!(?:\r?\n){3,}!", "\n\n", $str);
		$str = preg_replace('!\[([^\]]+)\]\(([^\)]+)\)!', '<a href="$2">$1</a>', $str);
		$str = preg_replace(';(?<!")https?://[^<\s]+(?!");', '<a href="$0">$0</a>', $str);
		$str = nl2br($str);
		return $str;
	}

	static public function format_duration(?int $duration): string
	{
		if (!$duration) {
			return '0:00';
		}

		$h = floor($duration / 3600);
		$m = floor(($duration % 3600) / 60);
		$s = $duration % 60;

		$out = '';

		if ($h) {
			$out .= $h . ':';
		}

		$out .= sprintf('%02d:%02d', $m, $s);
		return $out;
	}

	static public function relative_date(int $ts): string
	{
		$diff = (new \DateTime)->diff(new \DateTime('@' . $ts));

		if ($diff->y) {
			return $diff->y === 1 ? '1 year' : sprintf('%d years', $diff->y);
		}
		elseif ($diff->m) {
			return $diff->m === 1 ? '1 month' : sprintf('%d months', $diff->m);
		}
		elseif ($diff->d) {
			return $diff->d === 1 ? '1 day' : sprintf('%d days', $diff->d);
		}
		elseif ($diff->h) {
			return $diff->h === 1 ? '1 hour' : sprintf('%d hours', $diff->h);
		}
		else {
			return $diff->i <= 1 ? '1 minute' : sprintf('%d minutes', $diff->i);
		}
	}
}
