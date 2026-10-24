<?php
/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
namespace Opencart\System\Helper\Extension\PnBlack;

class Defaults {
	public static function path(): string {
		return DIR_EXTENSION . 'pn_black/defaults.xml';
	}

	public static function load(): array {
		$file = static::path();

		if (!is_file($file)) {
			return [];
		}

		$xml = @simplexml_load_file($file);

		if ($xml === false) {
			return [];
		}

		return self::xmlToArray($xml);
	}

	public static function getSection(string $section): array {
		$data = self::load();

		return $data[$section] ?? [];
	}

	/**
	 * Merge stored settings with defaults.
	 * - Indexed lists (socials, slides, …) are taken from stored as a whole when present.
	 * - Empty strings are kept (so cleared fields stay cleared).
	 * - Missing keys still fall back to defaults.
	 */
	public static function merge(array $stored, array $defaults): array {
		$result = $defaults;

		foreach ($stored as $key => $value) {
			if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
				if (self::isList($value) || self::isList($defaults[$key])) {
					$result[$key] = $value;
				} else {
					$result[$key] = self::merge($value, $defaults[$key]);
				}
			} elseif ($value !== null) {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Undo OpenCart Request::clean() htmlspecialchars (may be applied more than once).
	 */
	public static function decodeEntities(mixed $data): mixed {
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = self::decodeEntities($value);
			}

			return $data;
		}

		if (!is_string($data) || $data === '') {
			return $data;
		}

		$previous = null;
		$current = $data;
		$guard = 0;

		while ($current !== $previous && $guard < 5) {
			$previous = $current;
			$current = html_entity_decode($current, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$guard++;
		}

		return $current;
	}

	private static function isList(array $value): bool {
		if ($value === []) {
			return true;
		}

		return array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * Remap language-code keyed texts to language_id keys.
	 * $languages: [language_id => ['code' => 'en-gb', ...], ...]
	 */
	public static function mapLanguages(array $data, array $languages): array {
		$out = [];

		foreach ($data as $key => $value) {
			if (!is_array($value)) {
				$out[$key] = $value;
				continue;
			}

			// Multilingual leaf: keys look like language codes (en, en-gb, pl, pl-pl)
			if (self::isLangMap($value)) {
				$mapped = [];

				foreach ($languages as $language) {
					$code = $language['code'];
					$short = substr($code, 0, 2);
					$lid = (int)$language['language_id'];

					if (isset($value[$code])) {
						$mapped[$lid] = $value[$code];
					} elseif (isset($value[$short])) {
						$mapped[$lid] = $value[$short];
					} elseif (isset($value['en'])) {
						$mapped[$lid] = $value['en'];
					} elseif (isset($value['en-gb'])) {
						$mapped[$lid] = $value['en-gb'];
					} else {
						$mapped[$lid] = reset($value);
					}
				}

				$out[$key] = $mapped;
			} elseif (isset($value[0]) && is_array($value[0])) {
				// List of items
				$items = [];

				foreach ($value as $item) {
					$items[] = self::mapLanguages($item, $languages);
				}

				$out[$key] = $items;
			} else {
				$out[$key] = self::mapLanguages($value, $languages);
			}
		}

		return $out;
	}

	private static function isLangMap(array $value): bool {
		if (!$value || isset($value[0])) {
			return false;
		}

		foreach (array_keys($value) as $k) {
			if (!is_string($k)) {
				return false;
			}

			if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $k)) {
				return false;
			}
		}

		return true;
	}

	private static function xmlToArray(\SimpleXMLElement $xml): array {
		$out = [];

		foreach ($xml->children() as $child) {
			$name = $child->getName();
			$attrs = [];

			foreach ($child->attributes() as $ak => $av) {
				$attrs[(string)$ak] = (string)$av;
			}

			if ($child->count() > 0) {
				$value = self::xmlToArray($child);

				// <text lang="en">...</text> → ['en' => '...']
				if ($name === 'text' && isset($attrs['lang'])) {
					$out[$attrs['lang']] = trim((string)$child);
					continue;
				}

				// Repeated nodes become lists
				if (isset($out[$name])) {
					if (!isset($out[$name][0])) {
						$out[$name] = [$out[$name]];
					}
					$out[$name][] = $attrs ? array_merge($attrs, $value) : $value;
				} else {
					$out[$name] = $attrs ? array_merge($attrs, $value) : $value;
				}
			} else {
				$text = trim((string)$child);

				if ($name === 'text' && isset($attrs['lang'])) {
					$out[$attrs['lang']] = $text;
				} elseif (isset($out[$name])) {
					if (!is_array($out[$name]) || !isset($out[$name][0])) {
						$out[$name] = [$out[$name]];
					}
					$out[$name][] = $text !== '' ? $text : $attrs;
				} else {
					$out[$name] = $text !== '' ? $text : ($attrs ?: '');
				}
			}
		}

		// Normalize list wrappers at parent level: slides/items/features/...
		foreach (['slides' => 'slide', 'items' => 'review', 'features' => 'feature', 'stats' => 'stat', 'tags' => 'tag', 'socials' => 'social', 'contacts' => 'contact', 'channels' => 'channel'] as $wrap => $item) {
			if (isset($out[$wrap]) && is_array($out[$wrap]) && isset($out[$wrap][$item])) {
				$list = $out[$wrap][$item];
				$out[$wrap] = (is_array($list) && isset($list[0])) ? $list : [$list];
			}
		}

		// Generic <items><item>…</item></items> lists (marquee, metrics, …)
		if (isset($out['items']) && is_array($out['items']) && isset($out['items']['item']) && !isset($out['items'][0])) {
			$list = $out['items']['item'];
			$out['items'] = (is_array($list) && isset($list[0])) ? $list : [$list];
		}

		// When this node only contains repeated child items, return a flat list
		if (count($out) === 1) {
			$key = array_key_first($out);
			if (in_array($key, ['slide', 'review', 'feature', 'stat', 'tag', 'social', 'contact', 'channel', 'item'], true) && is_array($out[$key])) {
				$list = $out[$key];
				return (is_array($list) && isset($list[0])) ? $list : [$list];
			}
		}

		return $out;
	}
}
