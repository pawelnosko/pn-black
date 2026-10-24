<?php
/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
namespace Opencart\System\Helper\Extension\PnBlack;

class Credit {
	public const MARKER = 'pn-black';
	public const URL = 'https://pawelnosko.com/';
	public const LABEL = 'Created by Pawel Nosko';

	/**
	 * Author credit HTML — generated only here (not editable via Twig content).
	 */
	public static function html(): string {
		return '<a href="' . htmlspecialchars(self::URL, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" data-dc-credit="' . self::MARKER . '">' . self::LABEL . '</a>';
	}

	public static function isValid(string $html): bool {
		return (bool)preg_match(
			'#<a\b[^>]*data-dc-credit=["\']' . preg_quote(self::MARKER, '#') . '["\'][^>]*href=["\']https?://(www\.)?pawelnosko\.com/?["\'][^>]*>|<a\b[^>]*href=["\']https?://(www\.)?pawelnosko\.com/?["\'][^>]*data-dc-credit=["\']' . preg_quote(self::MARKER, '#') . '["\'][^>]*>#i',
			$html
		);
	}

	/**
	 * Soft enforce: never block the storefront — reinject the official credit if missing/changed.
	 */
	public static function ensure(string $html): string {
		$html = preg_replace('/<div[^>]*data-dc-license=["\']broken["\'][^>]*>.*?<\/div>/is', '', $html) ?? $html;

		if (self::isValid($html)) {
			return $html;
		}

		$credit = self::html();

		if (str_contains($html, 'pn-footer-bottom-copy') || str_contains($html, 'dc-footer-bottom-copy')) {
			$replaced = false;
			$html = preg_replace_callback(
				'/(<div[^>]*class="[^"]*(?:pn|dc)-footer-bottom-copy[^"]*"[^>]*>)(.*?)(<\/div>)/is',
				static function (array $m) use ($credit, &$replaced): string {
					if ($replaced) {
						return $m[0];
					}

					$replaced = true;
					$inner = $m[2];

					$inner = preg_replace('/<a\b[^>]*>.*?<\/a>/is', '', $inner) ?? $inner;
					$inner = preg_replace('/Created by (?:Design Cart(?:\s*\|\s*Paweł Nosko)?|Pawel Nosko|Paweł Nosko)/iu', '', $inner) ?? $inner;
					$inner = preg_replace('/\s*<span class="text-secondary">\|<\/span>\s*$/i', '', rtrim($inner)) ?? $inner;
					$inner = rtrim($inner);

					if ($inner !== '') {
						$inner .= ' <span class="text-secondary">|</span> ';
					}

					return $m[1] . $inner . $credit . $m[3];
				},
				$html,
				1
			) ?? $html;

			if ($replaced) {
				return $html;
			}
		}

		$html = preg_replace('/<a\b[^>]*(?:data-dc-credit=|pawelnosko\.com|designcart\.pl)[^>]*>.*?<\/a>/is', '', $html) ?? $html;

		if (str_contains($html, '</footer>')) {
			return str_replace('</footer>', '<div class="pn-footer-credit">' . $credit . '</div></footer>', $html);
		}

		return $html . $credit;
	}
}
