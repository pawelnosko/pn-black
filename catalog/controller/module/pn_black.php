<?php
/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
namespace Opencart\Catalog\Controller\Extension\PnBlack\Module;

class PnBlack extends \Opencart\System\Engine\Controller {
	/**
	 * Homepage sections HTML (injected into common/home).
	 */
	public function home(): string {
		if ($this->config->get('config_theme') != 'pn_black') {
			return '';
		}

		$data = [];
		$data['pn_black'] = $this->config->get('module_pn_black_data') ?: [];
		$data['language_id'] = (int)$this->config->get('config_language_id');

		require_once DIR_EXTENSION . 'pn_black/system/helper/defaults.php';

		$query = $this->db->query("SELECT language_id, code FROM `" . DB_PREFIX . "language` WHERE status = '1'");
		$defaults = \Opencart\System\Helper\Extension\PnBlack\Defaults::mapLanguages(
			\Opencart\System\Helper\Extension\PnBlack\Defaults::load(),
			$query->rows
		);

		$stored = is_array($data['pn_black']) ? \Opencart\System\Helper\Extension\PnBlack\Defaults::decodeEntities($data['pn_black']) : [];
		$data['pn_black'] = \Opencart\System\Helper\Extension\PnBlack\Defaults::merge($stored, $defaults);

		$lid = $data['language_id'];
		$t = function ($field) use ($data, $lid) {
			if (is_array($field)) {
				return $field[$lid] ?? ($field[0] ?? '');
			}
			return (string)$field;
		};

		$data['t'] = $t;
		$data['language'] = $this->config->get('config_language');
		$data['language_id'] = $lid;

		$on = function (string $key) use ($data): bool {
			$status = $data['pn_black'][$key]['status'] ?? 1;

			return (int)$status === 1;
		};

		$data['banner'] = $on('banner') ? $this->prepareBanner($data['pn_black']['banner'] ?? [], $t) : [];
		$data['marquee'] = $on('marquee') ? $this->prepareMarquee($data['pn_black']['marquee'] ?? [], $t) : ['items' => []];
		$data['carousels'] = $on('carousels') ? $this->prepareCarousels($data['pn_black']['carousels'] ?? [], $t) : [];
		$data['collection'] = $on('carousels') ? $this->prepareCollection($data['pn_black']['carousels'] ?? [], $t) : [];
		$data['about'] = $on('about') ? ($data['pn_black']['about'] ?? []) : [];
		$data['metrics'] = $on('metrics') ? $this->prepareMetrics($data['pn_black']['metrics'] ?? [], $data['pn_black']['about'] ?? [], $t) : ['items' => []];
		$data['reviews'] = $on('reviews') ? $this->prepareReviews($data['pn_black']['reviews'] ?? [], $t) : ['items' => []];
		$data['blog'] = $on('blog') ? $this->prepareBlog($data['pn_black']['blog'] ?? [], $t) : ['posts' => []];
		$data['newsletter'] = $on('newsletter') ? ($data['pn_black']['newsletter'] ?? []) : [];

		return $this->load->view('extension/pn_black/common/home_sections', $data);
	}

	public function newsletter(): void {
		$json = [];
		$email = trim((string)($this->request->post['email'] ?? ''));
		$rodo = !empty($this->request->post['rodo']);

		$settings_check = $this->config->get('module_pn_black_data');
		$lid = (int)$this->config->get('config_language_id');
		$rodo_required = false;

		if (is_array($settings_check)) {
			$rodo_cfg = $settings_check['newsletter']['rodo'] ?? '';

			if (is_array($rodo_cfg)) {
				$rodo_required = trim((string)($rodo_cfg[$lid] ?? '')) !== '';
			} else {
				$rodo_required = trim((string)$rodo_cfg) !== '';
			}
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$json['error'] = 'Please enter a valid email address.';
		} elseif ($rodo_required && !$rodo) {
			$json['error'] = 'Please accept the privacy consent.';
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$settings = $this->model_setting_setting->getSetting('module_pn_black');
			$data = $settings['module_pn_black_data'] ?? [];

			if (!is_array($data)) {
				$data = [];
			}

			if (!isset($data['newsletter']) || !is_array($data['newsletter'])) {
				$data['newsletter'] = [];
			}

			$subs = $data['newsletter']['subscribers'] ?? [];
			if (!is_array($subs)) {
				$subs = [];
			}

			foreach ($subs as $row) {
				if (isset($row['email']) && strtolower((string)$row['email']) === strtolower($email)) {
					$json['success'] = 'You are already subscribed!';
					break;
				}
			}

			if (empty($json['success'])) {
				$subs[] = [
					'email' => $email,
					'date'  => date('Y-m-d H:i:s'),
					'ip'    => $this->request->server['REMOTE_ADDR'] ?? ''
				];
				$data['newsletter']['subscribers'] = $subs;
				$this->saveModuleData($data);
				$json['success'] = 'Thank you for subscribing!';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Catalog setting model has no editSetting — persist module_pn_black_data directly.
	 */
	private function saveModuleData(array $data, int $store_id = 0): void {
		$value = json_encode($data);
		$key = 'module_pn_black_data';
		$code = 'module_pn_black';

		$query = $this->db->query("SELECT `setting_id` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '" . (int)$store_id . "' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");

		if ($query->num_rows) {
			$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape($value) . "', `serialized` = '1' WHERE `setting_id` = '" . (int)$query->row['setting_id'] . "'");
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', `serialized` = '1'");
		}
	}

	private function prepareBanner(array $banner, callable $t): array {
		$slides = $banner['slides'] ?? [];
		if (!is_array($slides)) {
			$slides = [];
		}

		$result = [];

		foreach ($slides as $slide) {
			$image = html_entity_decode((string)($slide['image'] ?? ''), ENT_QUOTES, 'UTF-8');
			$thumb = '';

			if ($image && is_file(DIR_IMAGE . $image)) {
				$thumb = 'image/' . $image;
			}

			$result[] = [
				'badge' => $t($slide['badge'] ?? ''),
				'title' => $t($slide['title'] ?? ''),
				'title_accent' => $t($slide['title_accent'] ?? ''),
				'description' => $t($slide['description'] ?? ''),
				'cta1' => $t($slide['cta1'] ?? ''),
				'cta1_link' => $slide['cta1_link'] ?? '#collection',
				'cta2' => $t($slide['cta2'] ?? ''),
				'cta2_link' => $slide['cta2_link'] ?? '#',
				'image' => $thumb,
				'image_badge' => $t($slide['image_badge'] ?? ''),
				'designed_in' => $t($slide['designed_in'] ?? ''),
				'scroll_hint' => $t($slide['scroll_hint'] ?? ''),
			];
		}

		return $result;
	}

	private function prepareMarquee(array $cfg, callable $t): array {
		$items = $cfg['items'] ?? [];

		if (is_array($items) && isset($items['item'])) {
			$items = $items['item'];
		}

		if (!is_array($items)) {
			$items = [];
		}

		// Single associative item (lang map or {text: ...}) → wrap as list
		if ($items && !array_is_list($items)) {
			$items = [$items];
		}

		$list = [];

		foreach ($items as $item) {
			$text = $this->resolveLocalizedText($item, $t);

			if ($text !== '') {
				$list[] = $text;
			}
		}

		return ['items' => $list];
	}

	/**
	 * Resolve multilingual leaf that may still use language codes (en/pl)
	 * when Defaults::mapLanguages skipped a bare lang-map item.
	 */
	private function resolveLocalizedText(mixed $field, callable $t): string {
		if (is_string($field)) {
			return trim($field);
		}

		if (!is_array($field) || $field === []) {
			return '';
		}

		if (isset($field['text'])) {
			return $this->resolveLocalizedText($field['text'], $t);
		}

		$via = trim((string)$t($field));

		if ($via !== '') {
			return $via;
		}

		foreach (['en-gb', 'en', 'pl-pl', 'pl'] as $code) {
			if (!empty($field[$code]) && is_string($field[$code])) {
				return trim($field[$code]);
			}
		}

		$first = reset($field);

		return is_string($first) ? trim($first) : '';
	}

	private function prepareCollection(array $cfg, callable $t): array {
		$ids = $cfg['products'] ?? [];

		if (!is_array($ids)) {
			$ids = [];
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		// Migrate older per-group picks
		if (!$ids) {
			foreach (['latest', 'bestseller', 'special'] as $key) {
				$group_ids = $cfg[$key]['products'] ?? [];

				if (is_array($group_ids)) {
					$ids = array_merge($ids, array_map('intval', $group_ids));
				}
			}

			$ids = array_values(array_unique(array_filter($ids)));
		}

		if ($ids) {
			$products = $this->getProductsByIds($ids, '', '#b3915f');
		} else {
			// Demo fallback only when nothing was ever picked
			$carousels = $this->prepareCarousels($cfg, $t);
			$products = [];
			$seen = [];

			foreach (['latest', 'bestseller', 'special'] as $key) {
				foreach (($carousels[$key]['products'] ?? []) as $product) {
					$id = (int)($product['product_id'] ?? 0);

					if ($id && isset($seen[$id])) {
						continue;
					}

					if ($id) {
						$seen[$id] = true;
					}

					$products[] = $product;
				}
			}

			$products = array_slice($products, 0, 12);
		}

		return [
			'badge' => $t($cfg['badge'] ?? ''),
			'title' => $t($cfg['title'] ?? ''),
			'products' => $products,
		];
	}

	private function prepareCategories(array $cfg, callable $t): array {
		$selected = $cfg['selected'] ?? [];
		$icons = $cfg['icons'] ?? [];
		$sort = $cfg['sort_order'] ?? [];

		if (!is_array($selected)) {
			$selected = array_filter(array_map('intval', explode(',', (string)$selected)));
		}

		if (!is_array($icons)) {
			$icons = [];
		}

		if (!is_array($sort)) {
			$sort = [];
		}

		$selected = array_values(array_filter(array_map('intval', $selected)));

		$this->load->model('catalog/category');

		// Auto-pick top-level categories when none selected (demo defaults)
		if (!$selected) {
			$defaults_icons = ['🚗','🧸','🧩','🪆','🎨','🏗️','🎮','🎭','🔭','⚽','🤖','🎪'];
			$top = $this->model_catalog_category->getCategories(0);
			$i = 0;

			foreach ($top as $category) {
				$cid = (int)$category['category_id'];
				$selected[] = $cid;

				if (empty($icons[$cid]) && empty($icons[(string)$cid])) {
					$icons[$cid] = $defaults_icons[$i % count($defaults_icons)];
				}

				$i++;

				if ($i >= 12) {
					break;
				}
			}
		}

		$items = [];

		foreach ($selected as $category_id) {
			$category_id = (int)$category_id;
			$category = $this->model_catalog_category->getCategory($category_id);

			if (!$category) {
				continue;
			}

			$items[] = [
				'category_id' => $category_id,
				'name' => $category['name'],
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $category_id),
				'icon' => $icons[$category_id] ?? ($icons[(string)$category_id] ?? '🧸'),
				'sort' => (int)($sort[$category_id] ?? $sort[(string)$category_id] ?? 0),
				'bg' => $this->categoryColor($category_id),
			];
		}

		usort($items, static fn($a, $b) => $a['sort'] <=> $b['sort']);

		return [
			'badge' => $t($cfg['badge'] ?? ''),
			'title' => $t($cfg['title'] ?? ''),
			'title_accent' => $t($cfg['title_accent'] ?? ''),
			'description' => $t($cfg['description'] ?? ''),
			'items' => $items,
		];
	}

	private function categoryColor(int $id): string {
		$colors = ['#FF3B5C', '#FFD600', '#00AAFF', '#A855F7', '#FF7B00', '#22C55E', '#06B6D4', '#FF69B4', '#6366F1', '#84CC16', '#F97316', '#EC4899'];
		return $colors[$id % count($colors)];
	}

	private function prepareCarousels(array $cfg, callable $t): array {
		$types = [
			'latest' => ['route' => 'extension/opencart/module/latest', 'accent' => '#b3915f', 'bg' => '#f7f7f5'],
			'bestseller' => ['route' => 'extension/opencart/module/bestseller', 'accent' => '#b3915f', 'bg' => '#f7f7f5'],
			'special' => ['route' => 'extension/opencart/module/special', 'accent' => '#b3915f', 'bg' => '#f7f7f5'],
		];

		$result = [];

		foreach ($types as $key => $meta) {
			$section = $cfg[$key] ?? [];
			$label = $t($section['label'] ?? '');
			$selected = $section['products'] ?? [];

			if (!is_array($selected)) {
				$selected = [];
			}

			$selected = array_values(array_unique(array_filter(array_map('intval', $selected))));

			$result[$key] = [
				'title' => $t($section['title'] ?? ''),
				'title_accent' => $t($section['title_accent'] ?? ''),
				'label' => $label,
				'accent' => $meta['accent'],
				'bg' => $meta['bg'],
				'products' => $selected
					? $this->getProductsByIds($selected, $label, $meta['accent'])
					: $this->getProductsByType($key, $label, $meta['accent']),
			];
		}

		return $result;
	}

	private function getProductsByIds(array $ids, string $label, string $color): array {
		$this->load->model('catalog/product');

		$results = [];

		foreach ($ids as $product_id) {
			$product = $this->model_catalog_product->getProduct((int)$product_id);

			if ($product) {
				$results[] = $product;
			}
		}

		return $this->formatProductCards($results, $label, $color);
	}

	private function getProductsByType(string $type, string $label, string $color): array {
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$limit = 12;
		$results = [];

		if ($type === 'special') {
			$results = $this->model_catalog_product->getSpecials([
				'sort'  => 'p.sort_order',
				'order' => 'ASC',
				'start' => 0,
				'limit' => $limit
			]);
		} elseif ($type === 'bestseller') {
			try {
				$this->load->model('extension/opencart/module/bestseller');

				if (isset($this->model_extension_opencart_module_bestseller) && method_exists($this->model_extension_opencart_module_bestseller, 'getBestSellers')) {
					$results = $this->model_extension_opencart_module_bestseller->getBestSellers($limit);
				}
			} catch (\Throwable $e) {
				$results = [];
			}

			// Demo stores often have empty/missing product_bestseller — fall back
			if (!$results) {
				$results = $this->model_catalog_product->getProducts([
					'sort'  => 'p.sort_order',
					'order' => 'ASC',
					'start' => 0,
					'limit' => $limit
				]);
			}
		} else {
			try {
				$this->load->model('extension/opencart/module/latest');

				if (isset($this->model_extension_opencart_module_latest) && method_exists($this->model_extension_opencart_module_latest, 'getLatest')) {
					$results = $this->model_extension_opencart_module_latest->getLatest($limit);
				}
			} catch (\Throwable $e) {
				$results = [];
			}

			if (!$results) {
				$results = $this->model_catalog_product->getProducts([
					'sort'  => 'p.date_added',
					'order' => 'DESC',
					'start' => 0,
					'limit' => $limit
				]);
			}
		}

		if (!is_array($results)) {
			$results = [];
		}

		// Specials may also be empty in demo — show regular products with SALE badge still useful? Better fallback:
		if ($type === 'special' && !$results) {
			$results = $this->model_catalog_product->getProducts([
				'sort'  => 'p.price',
				'order' => 'ASC',
				'start' => 0,
				'limit' => $limit
			]);
		}

		return $this->formatProductCards($results, $label, $color);
	}

	private function formatProductCards(array $results, string $label, string $color): array {
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$products = [];

		foreach ($results as $result) {
			if (!$result || empty($result['product_id'])) {
				continue;
			}

			// Prefer full product row (name/image/price) when query returns sparse data
			if (empty($result['name']) || !isset($result['price'])) {
				$full = $this->model_catalog_product->getProduct((int)$result['product_id']);

				if ($full) {
					$result = $full + $result;
				}
			}

			$image_file = !empty($result['image']) ? html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8') : '';

			$image = ($image_file && is_file(DIR_IMAGE . $image_file))
				? $this->model_tool_image->resize($image_file, 400, 400)
				: $this->model_tool_image->resize('placeholder.png', 400, 400);

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate((float)$result['price'], $result['tax_class_id'] ?? 0, $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$price = false;
			}

			$special = false;
			if (isset($result['special']) && (float)$result['special'] > 0) {
				$special = $this->currency->format($this->tax->calculate((float)$result['special'], $result['tax_class_id'] ?? 0, $this->config->get('config_tax')), $this->session->data['currency']);
			}

			$category_name = '';

			try {
				$cats = $this->model_catalog_product->getCategories((int)$result['product_id']);

				if ($cats) {
					$first = $this->model_catalog_category->getCategory((int)$cats[0]['category_id']);
					$category_name = $first['name'] ?? '';
				}
			} catch (\Throwable $e) {
				$category_name = '';
			}

			$products[] = [
				'product_id' => (int)$result['product_id'],
				'name' => $result['name'] ?? '',
				'category' => $category_name,
				'thumb' => $image,
				'price' => $price,
				'special' => $special,
				'minimum' => !empty($result['minimum']) ? (int)$result['minimum'] : 1,
				'rating' => isset($result['rating']) ? (int)round((float)$result['rating']) : 0,
				'reviews' => isset($result['reviews']) ? (int)$result['reviews'] : 0,
				'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$result['product_id']),
				'cart' => $this->url->link('common/cart.info', 'language=' . $this->config->get('config_language')),
				'cart_add' => $this->url->link('checkout/cart.add', 'language=' . $this->config->get('config_language')),
				'wishlist_add' => $this->url->link('account/wishlist.add', 'language=' . $this->config->get('config_language')),
				'badge' => $label,
				'badge_color' => $color,
			];
		}

		return $products;
	}

	private function prepareReviews(array $cfg, callable $t): array {
		$items = $cfg['items'] ?? ($cfg['reviews'] ?? []);

		if (is_array($items) && isset($items['review'])) {
			$items = $items['review'];
		}

		if (!is_array($items)) {
			$items = [];
		}

		// Ensure list
		if ($items && !isset($items[0]) && isset($items['author'])) {
			$items = [$items];
		}

		$list = [];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$list[] = [
				'avatar' => $item['avatar'] ?? '',
				'date' => $item['date'] ?? '',
				'stars' => (int)($item['stars'] ?? 5),
				'author' => $item['author'] ?? '',
				'product' => $item['product'] ?? '',
				'number' => $item['number'] ?? '',
				'text' => $t($item['text'] ?? ''),
			];
		}

		return [
			'badge' => $t($cfg['badge'] ?? ''),
			'title' => $t($cfg['title'] ?? ''),
			'title_accent' => $t($cfg['title_accent'] ?? ''),
			'based_on' => $t($cfg['based_on'] ?? ''),
			'rating' => $cfg['rating'] ?? '4.9',
			'items' => $list,
		];
	}

	private function prepareFeatured(array $cfg, callable $t): array {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$blocks = [];
		$gradients = [
			'linear-gradient(135deg, #FF3B5C 0%, #FF7B00 100%)',
			'linear-gradient(135deg, #A855F7 0%, #06B6D4 100%)',
		];

		$product_ids = [];

		for ($i = 1; $i <= 2; $i++) {
			$pid = (int)($cfg['product_' . $i] ?? 0);

			if ($pid && $this->productHasImage($pid)) {
				$product_ids[] = $pid;
			}
		}

		// Auto-pick best products with images when slots empty / invalid
		if (count($product_ids) < 2) {
			$product_ids = array_merge(
				$product_ids,
				$this->getFeaturedFallbackProducts(2 - count($product_ids), $product_ids)
			);
		}

		$i = 0;

		foreach ($product_ids as $pid) {
			$product = $this->model_catalog_product->getProduct($pid);

			if (!$product || empty($product['image']) || !is_file(DIR_IMAGE . html_entity_decode($product['image'], ENT_QUOTES, 'UTF-8'))) {
				continue;
			}

			$image = $this->model_tool_image->resize(html_entity_decode($product['image'], ENT_QUOTES, 'UTF-8'), 500, 500);

			$price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			$special = false;

			if (!is_null($product['special']) && (float)$product['special'] >= 0) {
				$special = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			}

			$badge_key = 'product_' . ($i + 1) . '_badge';

			$blocks[] = [
				'name' => $product['name'],
				'badge' => $t($cfg[$badge_key] ?? ''),
				'description' => oc_substr(strip_tags(html_entity_decode($product['description'] ?? '', ENT_QUOTES, 'UTF-8')), 0, 160) . '..',
				'price' => $special ?: $price,
				'old_price' => $special ? $price : '',
				'thumb' => $image,
				'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $pid),
				'product_id' => $pid,
				'bg' => $gradients[$i % 2],
			];
			$i++;
		}

		return [
			'badge' => $t($cfg['badge'] ?? ''),
			'title' => $t($cfg['title'] ?? ''),
			'title_accent' => $t($cfg['title_accent'] ?? ''),
			'blocks' => $blocks,
			'club_title' => $t($cfg['club_title'] ?? ''),
			'club_description' => $t($cfg['club_description'] ?? ''),
			'club_cta' => $t($cfg['club_cta'] ?? ''),
			'club_link' => $cfg['club_link'] ?? '#',
		];
	}

	/**
	 * Pick top products that have a real image file (bestsellers → rating → sort).
	 *
	 * @param list<int> $exclude
	 *
	 * @return list<int>
	 */
	private function getFeaturedFallbackProducts(int $limit, array $exclude = []): array {
		if ($limit < 1) {
			return [];
		}

		$candidates = [];

		try {
			$this->load->model('extension/opencart/module/bestseller');

			if (isset($this->model_extension_opencart_module_bestseller) && method_exists($this->model_extension_opencart_module_bestseller, 'getBestSellers')) {
				$candidates = $this->model_extension_opencart_module_bestseller->getBestSellers(max(12, $limit * 6));
			}
		} catch (\Throwable $e) {
			$candidates = [];
		}

		if (!$candidates) {
			$candidates = $this->model_catalog_product->getProducts([
				'sort'  => 'rating',
				'order' => 'DESC',
				'start' => 0,
				'limit' => max(20, $limit * 10)
			]);
		}

		if (!$candidates) {
			$candidates = $this->model_catalog_product->getProducts([
				'sort'  => 'p.sort_order',
				'order' => 'ASC',
				'start' => 0,
				'limit' => max(20, $limit * 10)
			]);
		}

		$ids = [];
		$exclude = array_map('intval', $exclude);

		foreach ($candidates as $row) {
			$pid = (int)($row['product_id'] ?? 0);

			if (!$pid || in_array($pid, $exclude, true) || in_array($pid, $ids, true)) {
				continue;
			}

			if (!$this->productHasImage($pid, $row['image'] ?? null)) {
				continue;
			}

			$ids[] = $pid;

			if (count($ids) >= $limit) {
				break;
			}
		}

		return $ids;
	}

	private function productHasImage(int $product_id, ?string $image = null): bool {
		if ($image === null || $image === '') {
			$product = $this->model_catalog_product->getProduct($product_id);
			$image = $product['image'] ?? '';
		}

		$image = html_entity_decode((string)$image, ENT_QUOTES, 'UTF-8');

		return $image !== '' && is_file(DIR_IMAGE . $image);
	}

	private function prepareMetrics(array $cfg, array $about, callable $t): array {
		$items = $cfg['items'] ?? [];

		if (is_array($items) && isset($items['item'])) {
			$items = $items['item'];
		}

		if ((!is_array($items) || !$items) && !empty($about['stats'])) {
			$items = $about['stats'];
			if (!isset($cfg['badge']) && isset($about['stats_badge'])) {
				$cfg['badge'] = $about['stats_badge'];
			}
			if (!isset($cfg['title']) && isset($about['stats_title'])) {
				$cfg['title'] = $about['stats_title'];
			}
		}

		if (!is_array($items)) {
			$items = [];
		}

		if ($items && !array_is_list($items)) {
			$items = array_values($items);
		}

		$list = [];

		foreach ($items as $row) {
			if (!is_array($row)) {
				continue;
			}

			$value = trim((string)($row['value'] ?? ''));
			$label = $t($row['label'] ?? '');

			if ($value === '' && $label === '') {
				continue;
			}

			$list[] = [
				'value'  => $value,
				'suffix' => trim((string)($row['suffix'] ?? '')),
				'label'  => $label,
			];
		}

		return [
			'badge' => $t($cfg['badge'] ?? ($about['stats_badge'] ?? '')),
			'title' => $t($cfg['title'] ?? ($about['stats_title'] ?? '')),
			'items' => $list,
		];
	}

	private function prepareBlog(array $cfg, callable $t): array {
		$posts = [];
		$cms = false;

		try {
			$cms = (bool)$this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "article'")->num_rows;
		} catch (\Throwable $e) {
			$cms = false;
		}

		if ($cms) {
			try {
				$this->load->model('cms/article');
				$this->load->model('tool/image');

				$mode = ((string)($cfg['mode'] ?? 'latest') === 'manual') ? 'manual' : 'latest';
				$topic_id = (int)($cfg['topic_id'] ?? $cfg['category_id'] ?? 0);
				$limit = max(1, min(12, (int)($cfg['limit'] ?? 3)));
				$language = $this->config->get('config_language');
				$rows = [];

				if ($mode === 'manual') {
					$ids = $cfg['articles'] ?? [];

					if (!is_array($ids)) {
						$ids = [];
					}

					$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

					foreach ($ids as $article_id) {
						$article = $this->model_cms_article->getArticle($article_id);

						if ($article && (int)($article['status'] ?? 0) === 1) {
							if ($topic_id && (int)($article['topic_id'] ?? 0) !== $topic_id) {
								continue;
							}

							$rows[] = $article;

							if (count($rows) >= $limit) {
								break;
							}
						}
					}
				} else {
					$filter = [
						'sort'  => 'date_added',
						'order' => 'DESC',
						'start' => 0,
						'limit' => $limit
					];

					if ($topic_id) {
						$filter['filter_topic_id'] = $topic_id;
					}

					$rows = $this->model_cms_article->getArticles($filter);
				}

				$topic_names = [];

				foreach ($rows as $row) {
					$image = !empty($row['image']) ? html_entity_decode($row['image'], ENT_QUOTES, 'UTF-8') : '';
					$thumb = ($image !== '' && is_file(DIR_IMAGE . $image))
						? $this->model_tool_image->resize($image, 600, 400)
						: '';

					$tid = (int)($row['topic_id'] ?? 0);
					$category = '';

					if ($tid) {
						if (!isset($topic_names[$tid])) {
							$tq = $this->db->query("SELECT name FROM `" . DB_PREFIX . "topic_description` WHERE topic_id = '" . $tid . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");
							$topic_names[$tid] = $tq->num_rows ? (string)$tq->row['name'] : '';
						}

						$category = $topic_names[$tid];
					}

					$posts[] = [
						'title'    => (string)($row['name'] ?? ''),
						'excerpt'  => oc_substr(strip_tags(html_entity_decode((string)($row['description'] ?? ''), ENT_QUOTES, 'UTF-8')), 0, 140) . ((oc_strlen(strip_tags(html_entity_decode((string)($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'))) > 140) ? '..' : ''),
						'thumb'    => $thumb,
						'date'     => !empty($row['date_added']) ? date('F j, Y', strtotime($row['date_added'])) : '',
						'category' => $category,
						'href'     => $this->url->link('cms/blog.info', 'language=' . $language . '&article_id=' . (int)$row['article_id']),
					];
				}
			} catch (\Throwable $e) {
				$posts = [];
			}
		}

		if (!$posts) {
			$fallback = $cfg['fallback_posts'] ?? [];

			if (is_array($fallback) && isset($fallback['post'])) {
				$fallback = $fallback['post'];
			}

			if (!is_array($fallback)) {
				$fallback = [];
			}

			if ($fallback && !isset($fallback[0])) {
				$fallback = [$fallback];
			}

			foreach ($fallback as $row) {
				if (!is_array($row)) {
					continue;
				}

				$image = html_entity_decode((string)($row['image'] ?? ''), ENT_QUOTES, 'UTF-8');
				$thumb = ($image !== '' && is_file(DIR_IMAGE . $image)) ? 'image/' . $image : '';

				$posts[] = [
					'title'    => $t($row['title'] ?? ''),
					'excerpt'  => '',
					'thumb'    => $thumb,
					'date'     => (string)($row['date'] ?? ''),
					'category' => $t($row['category'] ?? ''),
					'href'     => (string)($row['href'] ?? '#'),
				];
			}
		}

		$teaser_image = html_entity_decode((string)($cfg['teaser_image'] ?? ''), ENT_QUOTES, 'UTF-8');
		$all_link = $cms
			? $this->url->link('cms/blog', 'language=' . $this->config->get('config_language'))
			: '#';

		$topic_id = (int)($cfg['topic_id'] ?? 0);

		if ($cms && $topic_id) {
			$all_link = $this->url->link('cms/blog', 'language=' . $this->config->get('config_language') . '&topic_id=' . $topic_id);
		}

		return [
			'badge'              => $t($cfg['badge'] ?? ''),
			'title'              => $t($cfg['title'] ?? ''),
			'title_accent'       => $t($cfg['title_accent'] ?? ''),
			'teaser_description' => $t($cfg['teaser_description'] ?? ''),
			'teaser_image'       => ($teaser_image !== '' && is_file(DIR_IMAGE . $teaser_image)) ? 'image/' . $teaser_image : '',
			'teaser_badge'       => $t($cfg['teaser_badge'] ?? ''),
			'teaser_cta'         => $t($cfg['teaser_cta'] ?? ''),
			'grid_badge'         => $t($cfg['grid_badge'] ?? ''),
			'grid_title'         => $t($cfg['grid_title'] ?? ''),
			'read_more'          => $t($cfg['read_more'] ?? 'Read more'),
			'all_articles'       => $t($cfg['all_articles'] ?? 'All stories'),
			'installed'          => $cms || (bool)$posts,
			'posts'              => $posts,
			'all_link'           => $all_link,
		];
	}

	private function prepareBrands(array $cfg, callable $t): array {
		$selected = $cfg['selected'] ?? [];
		if (!is_array($selected)) {
			$selected = array_filter(array_map('intval', explode(',', (string)$selected)));
		}

		$selected = array_values(array_filter(array_map('intval', $selected)));

		$this->load->model('catalog/manufacturer');
		$this->load->model('tool/image');

		if (!$selected) {
			$manufacturers = $this->model_catalog_manufacturer->getManufacturers(['sort' => 'name', 'order' => 'ASC', 'start' => 0, 'limit' => 12]);
			foreach ($manufacturers as $manufacturer) {
				$selected[] = (int)$manufacturer['manufacturer_id'];
			}
		}

		$items = [];

		foreach ($selected as $manufacturer_id) {
			$manufacturer = $this->model_catalog_manufacturer->getManufacturer((int)$manufacturer_id);
			if (!$manufacturer) {
				continue;
			}

			$image = (!empty($manufacturer['image']) && is_file(DIR_IMAGE . $manufacturer['image']))
				? $this->model_tool_image->resize($manufacturer['image'], 120, 120)
				: '';

			$items[] = [
				'name' => $manufacturer['name'],
				'image' => $image,
				'href' => $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $manufacturer_id),
			];
		}

		return [
			'badge' => $t($cfg['badge'] ?? ''),
			'title' => $t($cfg['title'] ?? ''),
			'title_accent' => $t($cfg['title_accent'] ?? ''),
			'items' => $items,
		];
	}
}
