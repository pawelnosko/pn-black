<?php
/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
namespace Opencart\System\Helper\Extension\PnBlack;

/**
 * Storefront thumbnail builder.
 *
 * Replaces OpenCart's letterboxed (white-padded) thumbs via
 * model/tool/image.resize/after with a cover-crop + WebP pipeline.
 */
class Image {
	private const QUALITY = 82;

	/**
	 * Cover-crop to exact $width × $height and save as WebP when possible.
	 * No white bars — source fills the box (center crop).
	 */
	public static function resize(string $filename, int $width, int $height): string {
		$filename = html_entity_decode($filename, ENT_QUOTES, 'UTF-8');
		$filename = ltrim(str_replace('\\', '/', $filename), '/');

		if ($width < 1 || $height < 1) {
			return '';
		}

		$source = DIR_IMAGE . $filename;

		if (!is_file($source)) {
			return '';
		}

		$real = realpath($source);

		if ($real === false || !str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', DIR_IMAGE))) {
			return '';
		}

		$info = @getimagesize($source);

		if ($info === false) {
			return '';
		}

		[$src_w, $src_h, $image_type] = $info;

		if (!$src_w || !$src_h || !in_array($image_type, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
			return '';
		}

		$use_webp = function_exists('imagewebp');
		$ext = $use_webp ? 'webp' : strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
		$basename = pathinfo($filename, PATHINFO_FILENAME);
		$dir = dirname($filename);
		$relative = 'cache/' . ($dir !== '.' ? $dir . '/' : '') . $basename . '-' . $width . 'x' . $height . '-cover.' . $ext;
		$destination = DIR_IMAGE . $relative;

		if (!is_file($destination) || filemtime($source) > filemtime($destination)) {
			self::ensureDirectory(dirname($relative));

			$gd_src = self::createFrom($source, $image_type);

			if ($gd_src === null) {
				return '';
			}

			// Cover: scale so the box is fully filled, then center-crop.
			$scale = max($width / $src_w, $height / $src_h);
			$scaled_w = (int)max(1, round($src_w * $scale));
			$scaled_h = (int)max(1, round($src_h * $scale));
			$src_x = (int)max(0, round(($scaled_w - $width) / 2 / $scale));
			$src_y = (int)max(0, round(($scaled_h - $height) / 2 / $scale));
			$crop_w = (int)min($src_w, round($width / $scale));
			$crop_h = (int)min($src_h, round($height / $scale));

			$gd_dst = imagecreatetruecolor($width, $height);

			if ($gd_dst === false) {
				imagedestroy($gd_src);

				return '';
			}

			if ($use_webp || $image_type === IMAGETYPE_PNG || $image_type === IMAGETYPE_GIF) {
				imagealphablending($gd_dst, false);
				imagesavealpha($gd_dst, true);
				$transparent = imagecolorallocatealpha($gd_dst, 0, 0, 0, 127);
				imagefilledrectangle($gd_dst, 0, 0, $width, $height, $transparent);
				imagealphablending($gd_dst, true);
			}

			imagecopyresampled($gd_dst, $gd_src, 0, 0, $src_x, $src_y, $width, $height, $crop_w, $crop_h);
			imagedestroy($gd_src);

			$ok = self::save($gd_dst, $destination, $ext);
			imagedestroy($gd_dst);

			if (!$ok) {
				return '';
			}
		}

		return 'image/' . str_replace(' ', '%20', $relative);
	}

	private static function createFrom(string $file, int $image_type) {
		return match ($image_type) {
			IMAGETYPE_JPEG => @imagecreatefromjpeg($file) ?: null,
			IMAGETYPE_PNG  => @imagecreatefrompng($file) ?: null,
			IMAGETYPE_GIF  => @imagecreatefromgif($file) ?: null,
			IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($file) ?: null) : null,
			default        => null,
		};
	}

	private static function save($image, string $file, string $ext): bool {
		return match ($ext) {
			'webp' => (bool)imagewebp($image, $file, self::QUALITY),
			'png'  => (bool)imagepng($image, $file, 6),
			'gif'  => (bool)imagegif($image, $file),
			default => (bool)imagejpeg($image, $file, self::QUALITY),
		};
	}

	private static function ensureDirectory(string $relative_dir): void {
		$path = '';

		foreach (explode('/', $relative_dir) as $directory) {
			if ($directory === '' || $directory === '.') {
				continue;
			}

			$path .= '/' . $directory;

			if (!is_dir(DIR_IMAGE . $path)) {
				@mkdir(DIR_IMAGE . $path, 0777);
			}
		}
	}
}
