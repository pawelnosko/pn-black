<?php
/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
namespace Opencart\Admin\Controller\Extension\PnBlack\Theme;

class PnBlack extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/pn_black/theme/pn_black');

		$this->document->setTitle($this->language->get('heading_title'));

		$store_id = (int)($this->request->get['store_id'] ?? 0);

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme')
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/pn_black/theme/pn_black', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id)
		];

		$data['save'] = $this->url->link('extension/pn_black/theme/pn_black.save', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme');
		$data['settings'] = $this->url->link('extension/pn_black/module/pn_black', 'user_token=' . $this->session->data['user_token']);

		$data['theme_pn_black_status'] = $this->config->get('theme_pn_black_status');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/pn_black/theme/pn_black', $data));
	}

	public function save(): void {
		$this->load->language('extension/pn_black/theme/pn_black');

		$json = [];
		$store_id = (int)($this->request->get['store_id'] ?? 0);

		if (!$this->user->hasPermission('modify', 'extension/pn_black/theme/pn_black')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('theme_pn_black', $this->request->post, $store_id);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		if (!$this->user->hasPermission('modify', 'extension/theme')) {
			return;
		}

		try {
			$this->load->model('setting/startup');
			$this->model_setting_startup->deleteStartupByCode('pn_black');
			$this->model_setting_startup->addStartup([
				'code'        => 'pn_black',
				'description' => 'Theme Black',
				'action'      => 'catalog/extension/pn_black/startup/pn_black',
				'status'      => 1,
				'sort_order'  => 0
			]);

			// Register module entry (OC 4.1: type, extension, code)
			$this->load->model('setting/extension');
			$this->model_setting_extension->install('module', 'pn_black', 'pn_black');

			// Register package in extension_install so admin language/template paths resolve
			// (without this the modules list shows "pn_black_heading_title")
			$this->ensureExtensionInstall();

			// Permissions for settings module
			$this->load->model('user/user_group');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/pn_black/module/pn_black');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/pn_black/module/pn_black');

			$this->copyDefaultImages();
			$this->seedDefaults();
		} catch (\Throwable $e) {
			// Never break theme install AJAX — log and continue
			if (defined('DIR_LOGS') && is_dir(DIR_LOGS)) {
				@file_put_contents(DIR_LOGS . 'pn_black_install.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
			}
		}
	}

	public function uninstall(): void {
		if (!$this->user->hasPermission('modify', 'extension/theme')) {
			return;
		}

		$this->load->model('setting/startup');
		$this->model_setting_startup->deleteStartupByCode('pn_black');
	}

	/**
	 * Ensure oc_extension_install has a pn_black row (registers language/template paths on startup).
	 */
	private function ensureExtensionInstall(): void {
		$this->load->model('setting/extension');

		$installs = $this->model_setting_extension->getInstalls();

		foreach ($installs as $install) {
			if (($install['code'] ?? '') === 'pn_black') {
				return;
			}
		}

		$meta = [
			'extension_id'          => 0,
			'extension_download_id' => 0,
			'name'                  => 'Theme Black',
			'description'           => 'Minimal luxury fashion theme for OpenCart 4 by Paweł Nosko / Design Cart.',
			'code'                  => 'pn_black',
			'version'               => '1.0.0',
			'author'                => 'Paweł Nosko / Design Cart',
			'link'                  => 'https://pawelnosko.com'
		];

		if (is_file(DIR_EXTENSION . 'pn_black/install.json')) {
			$json = json_decode((string)file_get_contents(DIR_EXTENSION . 'pn_black/install.json'), true);

			if (is_array($json)) {
				foreach (['name', 'description', 'code', 'version', 'author', 'link'] as $key) {
					if (!empty($json[$key])) {
						$meta[$key] = (string)$json[$key];
					}
				}
			}
		}

		$install_id = $this->model_setting_extension->addInstall($meta);

		if ($install_id) {
			$this->db->query("UPDATE `" . DB_PREFIX . "extension_install` SET `status` = '1' WHERE `extension_install_id` = '" . (int)$install_id . "'");
		}
	}

	private function copyDefaultImages(): void {
		$src = DIR_EXTENSION . 'pn_black/catalog/view/image/catalog/pn_black/';
		$dst = DIR_IMAGE . 'catalog/pn_black/';

		if (!is_dir($src)) {
			return;
		}

		if (!is_dir($dst)) {
			@mkdir($dst, 0755, true);
		}

		foreach (glob($src . '*') as $file) {
			if (is_file($file)) {
				$target = $dst . basename($file);
				if (!is_file($target)) {
					@copy($file, $target);
				}
			}
		}
	}

	private function seedDefaults(): void {
		require_once DIR_EXTENSION . 'pn_black/system/helper/defaults.php';

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		$defaults = \Opencart\System\Helper\Extension\PnBlack\Defaults::load();
		$mapped = \Opencart\System\Helper\Extension\PnBlack\Defaults::mapLanguages($defaults, $languages);

		$this->load->model('setting/setting');
		$existing = $this->model_setting_setting->getSetting('module_pn_black');

		if (empty($existing['module_pn_black_data'])) {
			$this->model_setting_setting->editSetting('module_pn_black', [
				'module_pn_black_status' => 1,
				'module_pn_black_data'   => $mapped
			]);
		}
	}
}
