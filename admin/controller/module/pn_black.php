<?php
/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
namespace Opencart\Admin\Controller\Extension\PnBlack\Module;

class PnBlack extends \Opencart\System\Engine\Controller {
	private array $sections = [
		'header'     => ['icon' => '⬛', 'title' => 'Header'],
		'banner'     => ['icon' => '🖼', 'title' => 'Hero'],
		'marquee'    => ['icon' => '〰', 'title' => 'Marquee'],
		'carousels'  => ['icon' => '◇', 'title' => 'Collection'],
		'about'      => ['icon' => '◐', 'title' => 'Manifest'],
		'metrics'    => ['icon' => '＃', 'title' => 'Metrics'],
		'reviews'    => ['icon' => '★', 'title' => 'Reviews'],
		'blog'       => ['icon' => '✎', 'title' => 'Blog'],
		'newsletter' => ['icon' => '✉', 'title' => 'Newsletter'],
		'footer'     => ['icon' => '▬', 'title' => 'Footer'],
		'product'    => ['icon' => '▣', 'title' => 'Product'],
		'contact'    => ['icon' => '⌖', 'title' => 'Contact'],
	];

	public function index(): void {
		$this->load->language('extension/pn_black/module/pn_black');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->addAssets();

		$data = $this->getCommonData();
		$data['sections'] = [];

		foreach ($this->sections as $code => $meta) {
			$data['sections'][] = [
				'code'  => $code,
				'icon'  => $meta['icon'],
				'title' => $this->language->get('section_' . $code) ?: $meta['title'],
				'href'  => $this->url->link('extension/pn_black/module/pn_black.section', 'user_token=' . $this->session->data['user_token'] . '&section=' . $code)
			];
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/pn_black/module/pn_black/dashboard', $data));
	}

	public function section(): void {
		$this->load->language('extension/pn_black/module/pn_black');

		$section = preg_replace('/[^a-z_]/', '', (string)($this->request->get['section'] ?? ''));

		if (!isset($this->sections[$section])) {
			$this->response->redirect($this->url->link('extension/pn_black/module/pn_black', 'user_token=' . $this->session->data['user_token']));
			return;
		}

		$this->document->setTitle($this->language->get('section_' . $section));
		$this->addAssets();

		$data = $this->getCommonData();
		$data['section'] = $section;
		$data['section_title'] = $this->language->get('section_' . $section);
		$data['section_icon'] = $this->sections[$section]['icon'];
		$data['save'] = $this->url->link('extension/pn_black/module/pn_black.save', 'user_token=' . $this->session->data['user_token'] . '&section=' . $section);
		$data['back'] = $this->url->link('extension/pn_black/module/pn_black', 'user_token=' . $this->session->data['user_token']);

		$settings = $this->getSettings();
		$data['settings'] = $settings[$section] ?? [];

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$this->load->model('tool/image');
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		if ($section === 'categories') {
			$this->load->model('catalog/category');
			$categories = $this->model_catalog_category->getCategories(['sort' => 'name']);
			$sort = $data['settings']['sort_order'] ?? [];

			if (!is_array($sort)) {
				$sort = [];
			}

			usort($categories, static function (array $a, array $b) use ($sort): int {
				$sa = (int)($sort[$a['category_id']] ?? $sort[(string)$a['category_id']] ?? PHP_INT_MAX);
				$sb = (int)($sort[$b['category_id']] ?? $sort[(string)$b['category_id']] ?? PHP_INT_MAX);

				if ($sa === $sb) {
					return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
				}

				return $sa <=> $sb;
			});

			$data['categories'] = $categories;
			$data['emoji_list'] = $this->getEmojiList();
		}

		if ($section === 'brands') {
			$this->load->model('catalog/manufacturer');
			$data['manufacturers'] = $this->model_catalog_manufacturer->getManufacturers(['sort' => 'name']);
		}

		if ($section === 'featured') {
			$this->load->model('catalog/product');
			$data['products'] = $this->model_catalog_product->getProducts(['sort' => 'pd.name', 'order' => 'ASC', 'start' => 0, 'limit' => 500]);
		}

		if ($section === 'carousels') {
			$data['product_autocomplete'] = $this->url->link('extension/pn_black/module/pn_black.productAutocomplete', 'user_token=' . $this->session->data['user_token']);
			$data['product_catalog'] = $this->resolveProductCatalog(20);

			$pick_ids = $data['settings']['products'] ?? [];

			// Migrate older per-group selections into one homepage list
			if (!is_array($pick_ids) || !$pick_ids) {
				$pick_ids = [];
				foreach (['latest', 'bestseller', 'special'] as $group) {
					$group_ids = $data['settings'][$group]['products'] ?? [];
					if (is_array($group_ids)) {
						$pick_ids = array_merge($pick_ids, $group_ids);
					}
				}
			}

			$data['product_picks'] = $this->resolveProductTiles($pick_ids);
		}

		if ($section === 'blog') {
			$data['cms_blog_available'] = $this->isCmsBlogAvailable();
			$data['cms_topics'] = $data['cms_blog_available'] ? $this->getCmsTopics() : [];
			$data['article_catalog'] = $data['cms_blog_available'] ? $this->resolveArticleCatalog(20) : [];

			$article_ids = $data['settings']['articles'] ?? [];
			$data['article_picks'] = $this->resolveArticleTiles($article_ids);
		}

		if ($section === 'footer') {
			$this->load->model('catalog/information');
			$data['informations'] = $this->model_catalog_information->getInformations();
			$data['emoji_list'] = $this->getEmojiList();

			$data['social_thumbs'] = [];
			foreach (($data['settings']['socials'] ?? []) as $i => $social) {
				$icon = is_array($social) ? (string)($social['icon'] ?? '') : '';

				if ($icon !== '' && is_file(DIR_IMAGE . html_entity_decode($icon, ENT_QUOTES, 'UTF-8'))) {
					$data['social_thumbs'][$i] = $this->model_tool_image->resize(html_entity_decode($icon, ENT_QUOTES, 'UTF-8'), 100, 100);
				} else {
					$data['social_thumbs'][$i] = $data['placeholder'];
				}
			}
		}

		if ($section === 'banner') {
			$data['slide_thumbs'] = [];
			foreach (($data['settings']['slides'] ?? []) as $i => $slide) {
				$image = is_array($slide) ? (string)($slide['image'] ?? '') : '';
				$image = html_entity_decode($image, ENT_QUOTES, 'UTF-8');

				if ($image !== '' && is_file(DIR_IMAGE . $image)) {
					$data['slide_thumbs'][$i] = $this->model_tool_image->resize($image, 100, 100);
				} else {
					$data['slide_thumbs'][$i] = $data['placeholder'];
				}
			}
		}

		if ($section === 'reviews') {
			$data['emoji_people'] = $this->getEmojiPeople();
		}

		if ($section === 'newsletter') {
			$subs = $settings['newsletter']['subscribers'] ?? [];
			if (!is_array($subs)) {
				$subs = [];
			}
			$data['subscribers'] = $subs;
			$data['export'] = $this->url->link('extension/pn_black/module/pn_black.export', 'user_token=' . $this->session->data['user_token']);
		}

		if ($section === 'categories' || $section === 'about' || $section === 'newsletter' || $section === 'reviews' || $section === 'banner') {
			$data['emoji_list'] = $data['emoji_list'] ?? $this->getEmojiList();
		}

		// Resolve image thumbs for known image fields
		$data['image_fields'] = $this->resolveImageThumbs($data['settings'], $data['placeholder']);

		$data['user_token'] = $this->session->data['user_token'];
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$template = 'extension/pn_black/module/pn_black/' . $section;

		if (!is_file(DIR_EXTENSION . 'pn_black/admin/view/template/module/pn_black/' . $section . '.twig')) {
			$template = 'extension/pn_black/module/pn_black/section_generic';
		}

		$this->response->setOutput($this->load->view($template, $data));
	}

	public function save(): void {
		$this->load->language('extension/pn_black/module/pn_black');

		$json = [];
		$section = preg_replace('/[^a-z_]/', '', (string)($this->request->get['section'] ?? ''));

		if (!$this->user->hasPermission('modify', 'extension/pn_black/module/pn_black')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->sections[$section])) {
			$json['error'] = $this->language->get('error_section');
		}

		// OC Request::clean() runs htmlspecialchars on every POST value — undo for HTML editors / URLs.
		require_once DIR_EXTENSION . 'pn_black/system/helper/defaults.php';
		$payload = \Opencart\System\Helper\Extension\PnBlack\Defaults::decodeEntities(
			$this->request->post['module_pn_black'] ?? []
		);

		if (is_array($payload) && array_key_exists('status', $payload)) {
			// hidden 0 + checkbox 1 may arrive as array; keep final on/off flag
			$status = $payload['status'];

			if (is_array($status)) {
				$status = end($status);
			}

			$payload['status'] = (int)$status ? 1 : 0;
		}

		if (!$json && (!is_array($payload) || $payload === [])) {
			$json['error'] = 'No form data received. Please reload the page and try again.';
		}

		if (!$json) {
			$all = $this->getStoredSettings();

			// Keep newsletter subscribers when saving newsletter section
			if ($section === 'newsletter' && isset($all['newsletter']['subscribers'])) {
				$payload['subscribers'] = $all['newsletter']['subscribers'];
			}

			if ($section === 'carousels') {
				$ids = $payload['products'] ?? [];

				if (!is_array($ids)) {
					$ids = [];
				}

				$payload['products'] = array_values(array_unique(array_filter(array_map('intval', $ids))));
			}

			if ($section === 'blog') {
				$mode = (string)($payload['mode'] ?? 'latest');
				$payload['mode'] = ($mode === 'manual') ? 'manual' : 'latest';
				$payload['topic_id'] = (int)($payload['topic_id'] ?? 0);
				$payload['limit'] = max(1, min(12, (int)($payload['limit'] ?? 3)));

				$ids = $payload['articles'] ?? [];

				if (!is_array($ids)) {
					$ids = [];
				}

				$payload['articles'] = array_values(array_unique(array_filter(array_map('intval', $ids))));
			}

			if ($section === 'metrics') {
				$items = $payload['items'] ?? [];

				if (!is_array($items)) {
					$items = [];
				}

				if ($items && !array_is_list($items)) {
					$items = array_values($items);
				}

				$payload['items'] = $items;
			}

			$all[$section] = $payload;

			$encoded = json_encode($all, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

			if ($encoded === false) {
				$json['error'] = 'Could not encode settings (JSON error).';
			} else {
				$this->load->model('setting/setting');
				$this->model_setting_setting->editSetting('module_pn_black', [
					'module_pn_black_status' => 1,
					'module_pn_black_data'   => $all
				]);

				// Verify write
				$check = $this->model_setting_setting->getSetting('module_pn_black');
				$saved = $check['module_pn_black_data'] ?? null;

				if (!is_array($saved) || !isset($saved[$section])) {
					$json['error'] = 'Save failed — data was not written to the database.';
				} else {
					$json['success'] = $this->language->get('text_success');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function productAutocomplete(): void {
		$json = [];

		if (!$this->user->hasPermission('access', 'extension/pn_black/module/pn_black') && !$this->user->hasPermission('modify', 'extension/pn_black/module/pn_black')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$filter = html_entity_decode(trim((string)($this->request->get['filter_name'] ?? '')), ENT_QUOTES, 'UTF-8');

		try {
			$json = $this->resolveProductCatalog(20, $filter);
		} catch (\Throwable $e) {
			$json = [];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function articleAutocomplete(): void {
		$json = [];

		if (!$this->user->hasPermission('access', 'extension/pn_black/module/pn_black') && !$this->user->hasPermission('modify', 'extension/pn_black/module/pn_black')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$filter = html_entity_decode(trim((string)($this->request->get['filter_name'] ?? '')), ENT_QUOTES, 'UTF-8');

		try {
			$json = $this->resolveArticleCatalog(20, $filter);
		} catch (\Throwable $e) {
			$json = [];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function export(): void {
		if (!$this->user->hasPermission('modify', 'extension/pn_black/module/pn_black')) {
			$this->response->redirect($this->url->link('extension/pn_black/module/pn_black', 'user_token=' . $this->session->data['user_token']));
			return;
		}

		$settings = $this->getSettings();
		$subs = $settings['newsletter']['subscribers'] ?? [];

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="pn_black_newsletter_' . date('Y-m-d') . '.csv"');

		$out = fopen('php://output', 'w');
		fputcsv($out, ['email', 'date', 'ip']);

		if (is_array($subs)) {
			foreach ($subs as $row) {
				fputcsv($out, [
					$row['email'] ?? '',
					$row['date'] ?? '',
					$row['ip'] ?? ''
				]);
			}
		}

		fclose($out);
		exit;
	}

	public function install(): void {
		if (!$this->user->hasPermission('modify', 'extension/module')) {
			return;
		}

		require_once DIR_EXTENSION . 'pn_black/system/helper/defaults.php';

		$src = DIR_EXTENSION . 'pn_black/catalog/view/image/catalog/pn_black/';
		$dst = DIR_IMAGE . 'catalog/pn_black/';

		if (is_dir($src)) {
			if (!is_dir($dst)) {
				@mkdir($dst, 0755, true);
			}
			foreach (glob($src . '*') as $file) {
				if (is_file($file) && !is_file($dst . basename($file))) {
					@copy($file, $dst . basename($file));
				}
			}
		}

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		$defaults = \Opencart\System\Helper\Extension\PnBlack\Defaults::load();
		$mapped = \Opencart\System\Helper\Extension\PnBlack\Defaults::mapLanguages($defaults, $languages);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_pn_black', [
			'module_pn_black_status' => 1,
			'module_pn_black_data'   => $mapped
		]);
	}

	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/module')) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->deleteSetting('module_pn_black');
		}
	}

	private function getSettings(): array {
		require_once DIR_EXTENSION . 'pn_black/system/helper/defaults.php';

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		$defaults = \Opencart\System\Helper\Extension\PnBlack\Defaults::mapLanguages(
			\Opencart\System\Helper\Extension\PnBlack\Defaults::load(),
			$languages
		);

		$stored = $this->getStoredSettings();

		if (!$stored) {
			return $defaults;
		}

		// Migrate legacy Manifest stats → Metrics tile (once)
		if (!isset($stored['metrics']) && !empty($stored['about']['stats'])) {
			$stored['metrics'] = [
				'status' => 1,
				'badge'  => $stored['about']['stats_badge'] ?? ($defaults['metrics']['badge'] ?? []),
				'title'  => $stored['about']['stats_title'] ?? ($defaults['metrics']['title'] ?? []),
				'items'  => $stored['about']['stats'],
			];

			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('module_pn_black', [
				'module_pn_black_status' => 1,
				'module_pn_black_data'   => $stored
			]);
		}

		return \Opencart\System\Helper\Extension\PnBlack\Defaults::merge($stored, $defaults);
	}

	/**
	 * Raw module settings from DB (decoded), without merging defaults.
	 */
	private function getStoredSettings(): array {
		require_once DIR_EXTENSION . 'pn_black/system/helper/defaults.php';

		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('module_pn_black');
		$stored = $setting['module_pn_black_data'] ?? [];

		if (!is_array($stored) || !$stored) {
			// Fallback to config (same request after other writers)
			$stored = $this->config->get('module_pn_black_data');
		}

		if (!is_array($stored) || !$stored) {
			return [];
		}

		return \Opencart\System\Helper\Extension\PnBlack\Defaults::decodeEntities($stored);
	}

	private function getCommonData(): array {
		$data = [];
		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/pn_black/module/pn_black', 'user_token=' . $this->session->data['user_token'])
		];
		$data['heading_title'] = $this->language->get('heading_title');
		$data['user_token'] = $this->session->data['user_token'];
		$data['home'] = $this->url->link('extension/pn_black/module/pn_black', 'user_token=' . $this->session->data['user_token']);
		// Admin <base> points to /admindc/ — assets live under catalog HTTP root
		$data['dc_asset'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'extension/pn_black/admin/view/' : '../extension/pn_black/admin/view/';

		return $data;
	}

	private function addAssets(): void {
		$base = (defined('HTTP_CATALOG') ? HTTP_CATALOG : '../') . 'extension/pn_black/admin/view/';
		$this->document->addStyle($base . 'stylesheet/dc-interface.css');
		$this->document->addStyle($base . 'stylesheet/pn-black-admin.css?v=20261024h');
		$this->document->addStyle('https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css');
		$this->document->addScript('https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js');
		$this->document->addScript($base . 'javascript/dc-interface.js?v=20261024h');
		$this->document->addScript($base . 'javascript/pn-black-admin.js?v=20261024h');

		$section = preg_replace('/[^a-z_]/', '', (string)($this->request->get['section'] ?? ''));

		if ($section === 'footer') {
			$this->document->addScript('view/javascript/ckeditor/ckeditor.js');
			$this->document->addScript('view/javascript/ckeditor/adapters/jquery.js');
		}
	}

	private function resolveProductCatalog(int $limit = 20, string $filter = ''): array {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$filter_data = [
			'sort'  => 'pd.name',
			'order' => 'ASC',
			'start' => 0,
			'limit' => max(1, $limit)
		];

		if ($filter !== '') {
			$filter_data['filter_name'] = $filter;
		}

		$results = $this->model_catalog_product->getProducts($filter_data);
		$placeholder = $this->model_tool_image->resize('no_image.png', 120, 120);
		$tiles = [];

		foreach ($results as $result) {
			$image = $placeholder;
			$path = !empty($result['image']) ? html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8') : '';

			if ($path !== '' && is_file(DIR_IMAGE . $path)) {
				$image = $this->model_tool_image->resize($path, 120, 120);
			}

			$tiles[] = [
				'product_id' => (int)$result['product_id'],
				'name'       => (string)($result['name'] ?? ''),
				'image'      => $image
			];
		}

		return $tiles;
	}

	private function resolveProductTiles(mixed $ids): array {
		if (!is_array($ids)) {
			$ids = [];
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids) {
			return [];
		}

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$placeholder = $this->model_tool_image->resize('no_image.png', 120, 120);
		$tiles = [];

		foreach ($ids as $product_id) {
			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product) {
				continue;
			}

			$image = $placeholder;
			$path = !empty($product['image']) ? html_entity_decode($product['image'], ENT_QUOTES, 'UTF-8') : '';

			if ($path !== '' && is_file(DIR_IMAGE . $path)) {
				$image = $this->model_tool_image->resize($path, 120, 120);
			}

			$tiles[] = [
				'product_id' => $product_id,
				'name'       => (string)($product['name'] ?? ''),
				'image'      => $image
			];
		}

		return $tiles;
	}

	private function resolveImageThumbs(array $settings, string $placeholder): array {
		$thumbs = [];
		$keys = ['logo', 'image', 'teaser_image'];

		foreach ($keys as $key) {
			$path = !empty($settings[$key]) && is_string($settings[$key])
				? html_entity_decode($settings[$key], ENT_QUOTES, 'UTF-8')
				: '';

			if ($path !== '' && is_file(DIR_IMAGE . $path)) {
				$thumbs[$key] = $this->model_tool_image->resize($path, 100, 100);
			} else {
				$thumbs[$key] = $placeholder;
			}
		}

		return $thumbs;
	}

	private function isCmsBlogAvailable(): bool {
		try {
			$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "article'");

			return (bool)$query->num_rows;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function getCmsTopics(): array {
		if (!$this->isCmsBlogAvailable()) {
			return [];
		}

		try {
			// Include disabled topics too — admin needs every CMS category available for filtering
			$query = $this->db->query("SELECT t.topic_id, td.name, t.status FROM `" . DB_PREFIX . "topic` t LEFT JOIN `" . DB_PREFIX . "topic_description` td ON (t.topic_id = td.topic_id) WHERE td.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY td.name ASC");

			return $query->rows;
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Article tiles for the Blog picker. Also exposes product_id so the shared product-picker JS works.
	 */
	private function resolveArticleCatalog(int $limit = 20, string $filter = ''): array {
		if (!$this->isCmsBlogAvailable()) {
			return [];
		}

		$this->load->model('tool/image');

		$language_id = (int)$this->config->get('config_language_id');
		$sql = "SELECT a.article_id, ad.name, ad.image FROM `" . DB_PREFIX . "article` a LEFT JOIN `" . DB_PREFIX . "article_description` ad ON (a.article_id = ad.article_id) WHERE ad.language_id = '" . $language_id . "'";

		if ($filter !== '') {
			$sql .= " AND LCASE(ad.name) LIKE '%" . $this->db->escape(oc_strtolower($filter)) . "%'";
		}

		$sql .= " ORDER BY a.date_added DESC LIMIT " . max(1, (int)$limit);

		$query = $this->db->query($sql);
		$placeholder = $this->model_tool_image->resize('no_image.png', 120, 120);
		$tiles = [];

		foreach ($query->rows as $row) {
			$image = $placeholder;
			$path = !empty($row['image']) ? html_entity_decode($row['image'], ENT_QUOTES, 'UTF-8') : '';

			if ($path !== '' && is_file(DIR_IMAGE . $path)) {
				$image = $this->model_tool_image->resize($path, 120, 120);
			}

			$id = (int)$row['article_id'];

			$tiles[] = [
				'article_id' => $id,
				'product_id' => $id,
				'name'       => (string)($row['name'] ?? ''),
				'image'      => $image
			];
		}

		return $tiles;
	}

	private function resolveArticleTiles(mixed $ids): array {
		if (!is_array($ids)) {
			$ids = [];
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids || !$this->isCmsBlogAvailable()) {
			return [];
		}

		$this->load->model('tool/image');

		$placeholder = $this->model_tool_image->resize('no_image.png', 120, 120);
		$tiles = [];
		$language_id = (int)$this->config->get('config_language_id');

		foreach ($ids as $article_id) {
			$query = $this->db->query("SELECT a.article_id, ad.name, ad.image FROM `" . DB_PREFIX . "article` a LEFT JOIN `" . DB_PREFIX . "article_description` ad ON (a.article_id = ad.article_id) WHERE a.article_id = '" . (int)$article_id . "' AND ad.language_id = '" . $language_id . "' LIMIT 1");

			if (!$query->num_rows) {
				continue;
			}

			$row = $query->row;
			$image = $placeholder;
			$path = !empty($row['image']) ? html_entity_decode($row['image'], ENT_QUOTES, 'UTF-8') : '';

			if ($path !== '' && is_file(DIR_IMAGE . $path)) {
				$image = $this->model_tool_image->resize($path, 120, 120);
			}

			$id = (int)$row['article_id'];

			$tiles[] = [
				'article_id' => $id,
				'product_id' => $id,
				'name'       => (string)($row['name'] ?? ''),
				'image'      => $image
			];
		}

		return $tiles;
	}

	private function getEmojiList(): array {
		// Curated set from getemoji.com categories (compact for picker)
		return [
			'😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙',
			'😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥',
			'😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓',
			'🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣',
			'😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾',
			'🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾','🙈','🙉','🙊','💋','💌','💘','💝','💖','💗','💓',
			'💞','💕','💟','❣️','💔','❤️','🧡','💛','💚','💙','💜','🤎','🖤','🤍','💯','💢','💥','💫','💦','💨',
			'🕳️','💣','💬','👁️‍🗨️','🗨️','🗯️','💭','💤','👋','🤚','🖐️','✋','🖖','👌','🤏','✌️','🤞','🤟','🤘','🤙',
			'👈','👉','👆','🖕','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✍️','💅',
			'🤳','💪','🦾','🦿','🦵','🦶','👂','🦻','👃','🧠','🦷','🦴','👀','👁️','👅','👄','👶','🧒','👦','👧',
			'🧑','👱','👨','🧔','👩','🧓','👴','👵','🙍','🙎','🙅','🙆','💁','🙋','🧏','🙇','🤦','🤷','👮','🕵️',
			'💂','🥷','👷','🤴','👸','👳','👲','🧕','🤵','👰','🤰','🤱','👼','🎅','🤶','🦸','🦹','🧙','🧚','🧛',
			'🧜','🧝','🧞','🧟','💆','💇','🚶','🧍','🧎','🏃','💃','🕺','🕴️','👯','🧘','🛀','🛌','🧑‍🤝‍🧑','👭','👫',
			'👬','💏','💑','👪','🗣️','👤','👥','🫂','👣','🐵','🐶','🐺','🐱','🦁','🐯','🦒','🦊','🦝','🐮','🐷',
			'🐗','🐭','🐹','🐰','🐻','🐨','🐼','🐸','🐔','🐧','🐦','🐤','🐣','🦆','🦅','🦉','🦇','🐴','🦄','🐝',
			'🪱','🐛','🦋','🐌','🐞','🐜','🪰','🪲','🪳','🦟','🦗','🕷️','🦂','🐢','🐍','🦎','🦖','🦕','🐙','🦑',
			'🦐','🦞','🦀','🐡','🐠','🐟','🐬','🐳','🐋','🦈','🐊','🐅','🐆','🦓','🦍','🦧','🦣','🐘','🦛','🦏',
			'🐪','🐫','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙','🐐','🦌','🐕','🐩','🦮','🐈','🪶',
			'🐓','🦃','🦤','🦚','🦜','🦢','🦩','🕊️','🐇','🦝','🦨','🦡','🦫','🦦','🦥','🐁','🐀','🐿️','🦔',
			'🌱','🌲','🌳','🌴','🌵','🌶️','🌾','🌿','☘️','🍀','🍁','🍂','🍃','🍄','🌰','🦀','🍇','🍈','🍉','🍊',
			'🍋','🍌','🍍','🥭','🍎','🍏','🍐','🍑','🍒','🍓','🫐','🥝','🍅','🫒','🥥','🥑','🍆','🥔','🥕','🌽',
			'🌶️','🫑','黄瓜','🥬','🥦','🧄','🧅','🥜','🫘','🌰','🍞','🥐','🥖','🫓','🥨','🥯','🥞','🧇','🧀','🍖',
			'🍗','🥩','🥓','🍔','🍟','🍕','🌭','🥪','🌮','🌯','🫔','🥙','🧆','🥚','🍳','🥘','🍲','🫕','🥣','🥗',
			'🍿','🧈','🧂','🥫','🍱','🍘','🍙','🍚','🍛','🍜','🍝','🍠','🍢','🍣','🍤','🍥','🥮','🍡','🥟','🥠',
			'🥡','🍦','🍧','🍨','🍩','🍪','🎂','🍰','🧁','🥧','🍫','🍬','🍭','🍮','🍯','🍼','🥛','☕','🫖','🍵',
			'🍶','🍾','🍷','🍸','🍹','🍺','🍻','🥂','🥃','🫗','🥤','🧋','🧃','🧉','🧊','🥢','🍽️','🍴','🥄','🔪',
			'🏺','🌍','🌎','🌏','🌐','🗺️','🗾','🧭','🏔️','⛰️','🌋','🗻','🏕️','🏖️','🏜️','🏝️','🏞️','🏟️','🏛️','🏗️',
			'🧱','🪨','🪵','🛖','🏘️','🏚️','🏠','🏡','🏢','🏣','🏤','🏥','🏦','🏨','🏩','🏪','🏫','🏬','🏭','🏯',
			'🏰','💒','🗼','🗽','⛪','🕌','🛕','🕍','⛩️','🕋','⛲','⛺','🌁','🌃','🏙️','🌄','🌅','🌆','🌇','🌉',
			'♨️','🎠','🎡','🎢','💈','🎪','🚂','🚃','🚄','🚅','🚆','🚇','🚈','🚉','🚊','🚝','🚞','🚋','🚌','🚍',
			'🚎','🚐','🚑','🚒','🚓','🚔','🚕','🚖','🚗','🚘','🚙','🛻','🚚','🚛','🚜','🏎️','🏍️','🛵','🦽','🦼',
			'🛺','🚲','🛴','🛹','🛼','🚏','🛣️','🛤️','🛢️','⛽','🚨','🚥','🚦','🛑','🚧','⚓','🛟','⛵','🛶','🚤',
			'🛳️','⛴️','🛥️','🚢','✈️','🛩️','🛫','🛬','🪂','💺','🚁','🚟','🚠','🚡','🛰️','🚀','🛸','🛎️','🧳','⌛',
			'⏳','⌚','⏰','⏱️','⏲️','🕰️','🌡️','☀️','🌝','🌞','🪐','⭐','🌟','🌠','🌌','☁️','⛅','⛈️','🌤️','🌥️',
			'🌦️','🌧️','🌨️','🌩️','🌪️','🌫️','🌬️','🌀','🌈','🌂','☂️','☔','⛱️','⚡','❄️','☃️','⛄','☄️','🔥','💧',
			'🌊','🎃','🎄','🎆','🎇','✨','🎈','🎉','🎊','🎋','🎍','🎎','🎏','🎐','🎑','🧧','🎀','🎁','🎗️','🎟️',
			'🎫','🎖️','🏆','🏅','🥇','🥈','🥉','⚽','⚾','🥎','🏀','🏐','🏈','🏉','🎾','🥏','🎳','🏏','🏑','🏒',
			'🥍','🏓','🏸','🥊','🥋','🥅','⛳','⛸️','🎣','🤿','🎽','🎿','🛷','🥌','🎯','🪀','🪁','🎱','🔮','🪄',
			'🧿','🎮','🕹️','🎰','🎲','🧩','🧸','🪅','🪩','🪆','♠️','♥️','♦️','♣️','♟️','🃏','🀄','🎴','🎭','🖼️',
			'🎨','🧵','🪡','🧶','👓','🕶️','🥽','🥼','🦺','👔','👕','👖','🧣','🧤','🧥','🧦','👗','👘','🥻','🩱',
			'🩲','🩳','👙','👚','👛','👜','👝','🛍️','🎒','🩴','👞','👟','🥾','🥿','👠','👡','🩰','👢','👑','👒',
			'🎩','🎓','🧢','🪖','⛑️','📿','💄','💍','💎','🔇','🔈','🔉','🔊','📢','📣','📯','🔔','🔕','🎼','🎵',
			'🎶','🎙️','🎚️','🎛️','🎤','🎧','📻','🎷','🪗','🎸','🎹','🎺','🎻','🪕','🥁','🪘','📱','📲','☎️','📞',
			'📟','📠','🔋','🔌','💻','🖥️','🖨️','⌨️','🖱️','🖲️','💽','💾','💿','📀','🧮','🎥','🎞️','📽️','🎬','📺',
			'📷','📸','📹','📼','🔍','🔎','🕯️','💡','🔦','🏮','🪔','📔','📕','📖','📗','📘','📙','📚','📓','📒',
			'📃','📜','📄','📰','🗞️','📑','🔖','🏷️','💰','🪙','💴','💵','💶','💷','💸','💳','🧾','💹','✉️','📧',
			'📨','📩','📤','📥','📦','📫','📪','📬','📭','📮','🗳️','✏️','✒️','🖋️','🖊️','🖌️','🖍️','📝','💼','📁',
			'📂','🗂️','📅','📆','🗒️','🗓️','📇','📈','📉','📊','📋','📌','📍','📎','🖇️','📏','📐','✂️','🗃️','🗄️',
			'🗑️','🔒','🔓','🔏','🔐','🔑','🗝️','🔨','🪓','⛏️','⚒️','🛠️','🗡️','⚔️','🔫','🪃','🏹','🛡️','🪚','🔧',
			'🪛','🔩','⚙️','🗜️','⚖️','🦯','🔗','⛓️','🪝','🧰','🧲','🪜','⚗️','🧪','🧫','🧬','🔬','🔭','📡','💉',
			'🩸','💊','🩹','🩺','🚪','🛗','🪞','🪟','🛏️','🛋️','🪑','🚽','🪠','🚿','🛁','🪤','🪒','🧴','🧷','🧹',
			'🧺','🧻','🪣','🧼','🫧','🪥','🧽','🧯','🛒','🚬','⚰️','🪦','⚱️','🗿','🪧','🏧','🚮','🚰','♿','🚹',
			'🚺','🚻','🚼','🚾','🛂','🛃','🛄','🛅','⚠️','🚸','⛔','🚫','🚳','🚭','🚯','🚱','🚷','📵','🔞','☢️',
			'☣️','⬆️','↗️','➡️','↘️','⬇️','↙️','⬅️','↖️','↕️','↔️','↩️','↪️','⤴️','⤵️','🔃','🔄','🔙','🔚','🔛',
			'🔜','🔝','🛐','⚛️','🕉️','✡️','☸️','☯️','✝️','☦️','☪️','☮️','🕎','🔯','♈','♉','♊','♋','♌','♍',
			'♎','♏','♐','♑','♒','♓','⛎','🔀','🔁','🔂','▶️','⏩','⏭️','⏯️','◀️','⏪','⏮️','🔼','⏫','🔽',
			'⏬','⏸️','⏹️','⏺️','⏏️','🎦','🔅','🔆','📶','📳','📴','♀️','♂️','⚧️','✖️','➕','➖','➗','♾️','‼️',
			'⁉️','❓','❔','❕','❗','〰️','💱','💲','⚕️','♻️','⚜️','🔱','📛','🔰','⭕','✅','☑️','✔️','❌','❎',
			'➰','➿','〽️','✳️','✴️','❇️','©️','®️','™️','#️⃣','*️⃣','0️⃣','1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣',
			'9️⃣','🔟','🔠','🔡','🔢','🔣','🔤','🅰️','🆎','🅱️','🆑','🆒','🆓','ℹ️','🆔','Ⓜ️','🆕','🆖','🅾️','🆗',
			'🅿️','🆘','🆙','🆚','🈁','🈂️','🈷️','🈶','🈯','🉐','🈹','🈚','🈲','🉑','🈸','🈴','🈳','㊗️','㊙️','🈺',
			'🈵','🔴','🟠','🟡','🟢','🔵','🟣','🟤','⚫','⚪','🟥','🟧','🟨','🟩','🟦','🟪','🟫','⬛','⬜','◼️',
			'◻️','◾','◽','▪️','▫️','🔶','🔷','🔸','🔹','🔺','🔻','💠','🔘','🔳','🔲','🏁','🚩','🎌','🏴','🏳️',
			'🏳️‍🌈','🏳️‍⚧️','🏴‍☠️','🧸','🚗','🧩','🪆','🎨','🏗️','🎮','🎭','🔭','⚽','🤖','🎪','🎲','🎈','🎁','⭐','🌟',
			'🎊','🎉','🌈','☀️','🏖️','♟️','🃏'
		];
	}

	private function getEmojiPeople(): array {
		return [
			'😀','😃','😄','😁','😆','😅','🤣','😂','🙂','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙',
			'😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥',
			'😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓',
			'🧐','😕','😟','🙁','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣',
			'😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾',
			'🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾','🙈','🙉','🙊',
			'👶','🧒','👦','👧','🧑','👱','👨','🧔','👩','🧓','👴','👵',
			'👮','🕵️','💂','🥷','👷','🤴','👸','👳','👲','🧕','🤵','👰','🤰','👼','🎅','🤶',
			'🦸','🦹','🧙','🧚','🧛','🧜','🧝','🧞','🧟',
			'👩‍🦰','👨‍🦰','👩‍🦱','👨‍🦱','👩‍🦳','👨‍🦳','👩‍🦲','👨‍🦲',
			'👩','👨','👩‍🏫','👨‍💼','👩‍🎨','🧑‍⚕️','🧑‍🎓','🧑‍💻'
		];
	}
}
