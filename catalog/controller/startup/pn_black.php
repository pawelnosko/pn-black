<?php
/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
namespace Opencart\Catalog\Controller\Extension\PnBlack\Startup;

require_once DIR_EXTENSION . 'pn_black/system/helper/defaults.php';
require_once DIR_EXTENSION . 'pn_black/system/helper/image.php';
require_once DIR_EXTENSION . 'pn_black/system/helper/credit.php';

use Opencart\System\Helper\Extension\PnBlack\Defaults;
use Opencart\System\Helper\Extension\PnBlack\Image as DcImage;
use Opencart\System\Helper\Extension\PnBlack\Credit;

class PnBlack extends \Opencart\System\Engine\Controller {
	public function index(): void {
		if ($this->config->get('config_theme') != 'pn_black' || !$this->config->get('theme_pn_black_status')) {
			return;
		}

		$this->event->register(
			'view/*/before',
			new \Opencart\System\Engine\Action('extension/pn_black/startup/pn_black.override')
		);

		$this->event->register(
			'view/*/before',
			new \Opencart\System\Engine\Action('extension/pn_black/startup/pn_black.injectSettings')
		);

		// OC4 model events use a dot before the method: model/{route}.{method}/after
		$this->event->register(
			'model/tool/image.resize/after',
			new \Opencart\System\Engine\Action('extension/pn_black/startup/pn_black.resizeAfter')
		);

		$this->event->register(
			'view/*/after',
			new \Opencart\System\Engine\Action('extension/pn_black/startup/pn_black.breadcrumbAfter')
		);

		$this->event->register(
			'view/*/after',
			new \Opencart\System\Engine\Action('extension/pn_black/startup/pn_black.creditAfter')
		);
	}

	/**
	 * OC4 view/before signature: [&$route, &$data, &$code, &$output]
	 */
	public function override(string &$route, array &$data, mixed &$code = null, mixed &$output = null): void {
		$override_path = DIR_EXTENSION . 'pn_black/catalog/view/template/' . $route . '.twig';

		if (is_file($override_path)) {
			$route = 'extension/pn_black/' . $route;
		}
	}

	/**
	 * Inject theme settings into template $data (NOT $data[0]).
	 */
	public function injectSettings(string &$route, array &$data, mixed &$code = null, mixed &$output = null): void {
		$data['pn_black'] = $this->getThemeData();
		$data['pn_black_language_id'] = (int)$this->config->get('config_language_id');
		$data['dc_credit'] = Credit::html();

		if (empty($data['name'])) {
			$data['name'] = (string)$this->config->get('config_name');
		}

		if (empty($data['home'])) {
			$data['home'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
		}

		if (empty($data['login'])) {
			$data['login'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
		}

		// Pre-decode footer WYSIWYG columns (OC htmlspecialchars on save)
		$lid = $data['pn_black_language_id'];
		$footer = $data['pn_black']['footer'] ?? [];
		$data['dc_footer_columns'] = [];

		for ($i = 1; $i <= 4; $i++) {
			$html = $footer['column_' . $i][$lid] ?? ($footer['column_' . $i][(string)$lid] ?? '');
			$data['dc_footer_columns'][$i] = is_string($html) ? Defaults::decodeEntities($html) : '';
		}

		if (!empty($footer['description'][$lid]) || !empty($footer['description'][(string)$lid])) {
			$desc = $footer['description'][$lid] ?? $footer['description'][(string)$lid];
			$data['pn_black']['footer']['description'][$lid] = Defaults::decodeEntities((string)$desc);
		}

		// Admin stores information_id; front needs a real SEO/route URL
		$language = (string)$this->config->get('config_language');

		foreach (['information_terms', 'information_privacy', 'information_cookies'] as $info_key) {
			$information_id = (int)($footer[$info_key] ?? 0);

			if ($information_id > 0) {
				$data['pn_black']['footer'][$info_key] = $this->url->link(
					'information/information',
					'language=' . $language . '&information_id=' . $information_id
				);
			} else {
				$data['pn_black']['footer'][$info_key] = '';
			}
		}

		$request_route = (string)($this->request->get['route'] ?? '');
		$is_home_request = ($request_route === '' || $request_route === 'common/home');
		$is_home_view = in_array($route, ['common/home', 'extension/pn_black/common/home'], true);

		if ($is_home_request && $is_home_view && empty($data['pn_black_home'])) {
			$result = $this->load->controller('extension/pn_black/module/pn_black.home');

			if ($result instanceof \Exception) {
				$data['pn_black_home'] = '<!-- Theme Black home error: ' . htmlspecialchars($result->getMessage()) . ' -->';
			} else {
				$data['pn_black_home'] = (string)$result;
			}
		}
	}

	public function resizeAfter(string &$route, array &$args, mixed &$output = null): void {
		if (!is_array($args) || count($args) < 3) {
			return;
		}

		$filename = (string)$args[0];
		$width = (int)$args[1];
		$height = (int)$args[2];

		// Skip tiny icons / placeholders — leave OC default.
		if ($width < 40 || $height < 40) {
			return;
		}

		$cover = DcImage::resize($filename, $width, $height);

		if ($cover === '') {
			return;
		}

		$server = (string)$this->config->get('config_url');

		if (!empty($this->request->server['HTTPS']) && ((string)$this->request->server['HTTPS'] === 'on' || (string)$this->request->server['HTTPS'] === '1')) {
			$ssl = (string)$this->config->get('config_ssl');

			if ($ssl !== '') {
				$server = $ssl;
			}
		}

		$output = rtrim($server, '/') . '/' . ltrim($cover, '/');
	}

	/**
	 * Replace default Bootstrap breadcrumbs with the theme bar on every page.
	 */
	public function breadcrumbAfter(string &$route, array &$data, mixed &$output = null): void {
		static $replacing = false;

		if ($replacing || !is_string($output) || $output === '') {
			return;
		}

		if (str_contains($output, 'dc-breadcrumb') || empty($data['breadcrumbs']) || !is_array($data['breadcrumbs'])) {
			return;
		}

		if (!preg_match('/<ul\s+class="breadcrumb"[^>]*>.*?<\/ul>/is', $output)) {
			return;
		}

		$replacing = true;

		try {
			$html = $this->load->view('common/breadcrumb', [
				'breadcrumbs' => $data['breadcrumbs']
			]);
		} finally {
			$replacing = false;
		}

		if ($html === '' || !is_string($html)) {
			return;
		}

		$output = preg_replace('/<ul\s+class="breadcrumb"[^>]*>.*?<\/ul>/is', $html, $output, 1) ?? $output;
	}

	/**
	 * Enforce author credit (Pawel Nosko) in the footer output.
	 * Removing {{ dc_credit }} from footer.twig triggers a soft reinjection of the credit link.
	 */
	public function creditAfter(string &$route, array &$data, mixed &$output = null): void {
		if (!is_string($output) || $output === '') {
			return;
		}

		if (!str_ends_with($route, 'common/footer')) {
			return;
		}

		$output = Credit::ensure($output);
	}

	private function getThemeData(): array {
		static $cache = null;

		if ($cache !== null) {
			return $cache;
		}

		$stored = $this->config->get('module_pn_black_data');

		$query = $this->db->query("SELECT language_id, code FROM `" . DB_PREFIX . "language` WHERE status = '1'");
		$languages = $query->rows;

		$defaults = Defaults::mapLanguages(Defaults::load(), $languages);

		if (!is_array($stored) || !$stored) {
			$cache = $defaults;
		} else {
			$stored = Defaults::decodeEntities($stored);
			$cache = Defaults::merge($stored, $defaults);
		}

		return $cache;
	}
}
