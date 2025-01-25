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
}
