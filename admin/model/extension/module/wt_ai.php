<?php
class ModelExtensionModuleWtAi extends Model {
	/**
	 * @return array{message:string,log:list<string>}
	 */
	public function process($entity, $id) {
		$log = array();
		$source_language_id = (int)$this->config->get('config_language_id');
		$languages = $this->getActiveLanguages();

		if (!isset($languages[$source_language_id])) {
			$code = (string)$this->config->get('config_language');
			foreach ($languages as $lid => $lang) {
				if ($lang['code'] === $code || strpos($lang['code'], $code) === 0) {
					$source_language_id = (int)$lid;
					break;
				}
			}
		}

		if (!isset($languages[$source_language_id])) {
			throw new Exception('Default language not found');
		}

		$source = $this->getDescription($entity, $id, $source_language_id);

		if ($source === null || trim(strip_tags($source['name'] . $source['description'])) === '') {
			throw new Exception('Empty source language content. Fill default language and save.');
		}

		$do_translate = (bool)$this->config->get('module_wt_ai_do_translate');
		$overwrite = (bool)$this->config->get('module_wt_ai_overwrite');
		$seo_fields = $this->enabledSeoFields();

		// SEO for source language
		if ($seo_fields) {
			$seo = $this->generateSeo($entity, $source, $languages[$source_language_id], $seo_fields);
			if ($seo) {
				$this->mergeAndSave($entity, $id, $source_language_id, $source, $seo, $overwrite, true);
				$log[] = 'SEO [' . $languages[$source_language_id]['code'] . ']';
			}
		}

		foreach ($languages as $language_id => $lang) {
			if ((int)$language_id === $source_language_id) {
				continue;
			}

			$existing = $this->getDescription($entity, $id, $language_id);
			if ($existing === null) {
				$existing = $this->emptyDescription();
			}

			$row = $existing;

			if ($do_translate) {
				$translated = $this->translateRow($source, $languages[$source_language_id], $lang);
				if ($translated) {
					$row = $this->applyFields($row, $translated, $overwrite, array('name', 'description', 'tag'));
					$log[] = 'Translate → ' . $lang['code'];
				}
			}

			if ($seo_fields) {
				$base_for_seo = array(
					'name'        => $row['name'] !== '' ? $row['name'] : $source['name'],
					'description' => $row['description'] !== '' ? $row['description'] : $source['description'],
					'tag'         => $row['tag'],
				);
				$seo = $this->generateSeo($entity, $base_for_seo, $lang, $seo_fields);
				if ($seo) {
					$row = $this->applyFields($row, $seo, $overwrite, $seo_fields);
					$log[] = 'SEO [' . $lang['code'] . ']';
				}
			}

			$this->saveDescription($entity, $id, $language_id, $row);
		}

		$message = $log ? implode('; ', $log) : 'Nothing to do (enable checkboxes in module settings)';

		return array('message' => $message, 'log' => $log);
	}

	private function enabledSeoFields() {
		$map = array(
			'meta_title'       => 'module_wt_ai_do_meta_title',
			'meta_description' => 'module_wt_ai_do_meta_description',
			'meta_h1'          => 'module_wt_ai_do_meta_h1',
			'meta_keyword'     => 'module_wt_ai_do_meta_keyword',
		);
		$out = array();
		foreach ($map as $field => $cfg) {
			if ($this->config->get($cfg)) {
				$out[] = $field;
			}
		}

		return $out;
	}

	private function getActiveLanguages() {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE status = '1' ORDER BY sort_order ASC");
		$out = array();
		foreach ($query->rows as $row) {
			$out[(int)$row['language_id']] = $row;
		}

		return $out;
	}

	private function tableFor($entity) {
		if ($entity === 'product') {
			return array('table' => 'product_description', 'pk' => 'product_id');
		}
		if ($entity === 'category') {
			return array('table' => 'category_description', 'pk' => 'category_id');
		}
		if ($entity === 'article') {
			return array('table' => 'article_description', 'pk' => 'article_id');
		}

		throw new Exception('Unknown entity');
	}

	private function emptyDescription() {
		return array(
			'name'             => '',
			'description'      => '',
			'tag'              => '',
			'meta_title'       => '',
			'meta_description' => '',
			'meta_keyword'     => '',
			'meta_h1'          => '',
		);
	}

	private function hasColumn($table, $column) {
		static $cache = array();
		$key = $table . '.' . $column;
		if (isset($cache[$key])) {
			return $cache[$key];
		}
		$q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE '" . $this->db->escape($column) . "'");
		$cache[$key] = (bool)$q->num_rows;

		return $cache[$key];
	}

	private function getDescription($entity, $id, $language_id) {
		$meta = $this->tableFor($entity);
		$q = $this->db->query("SELECT * FROM `" . DB_PREFIX . $meta['table'] . "` WHERE `" . $meta['pk'] . "` = '" . (int)$id . "' AND language_id = '" . (int)$language_id . "'");
		if (!$q->num_rows) {
			return null;
		}
		$row = $q->row;
		$out = $this->emptyDescription();
		foreach ($out as $k => $v) {
			if (isset($row[$k])) {
				$out[$k] = (string)$row[$k];
			}
		}

		return $out;
	}

	private function saveDescription($entity, $id, $language_id, $data) {
		$meta = $this->tableFor($entity);
		$table = $meta['table'];
		$pk = $meta['pk'];

		$exists = $this->db->query("SELECT * FROM `" . DB_PREFIX . $table . "` WHERE `" . $pk . "` = '" . (int)$id . "' AND language_id = '" . (int)$language_id . "'");

		$fields = array('name', 'description', 'meta_title', 'meta_description', 'meta_keyword');
		if ($this->hasColumn($table, 'tag')) {
			$fields[] = 'tag';
		}
		if ($this->hasColumn($table, 'meta_h1')) {
			$fields[] = 'meta_h1';
		}

		$set = array();
		foreach ($fields as $f) {
			$set[] = "`" . $f . "` = '" . $this->db->escape(isset($data[$f]) ? $data[$f] : '') . "'";
		}

		if ($exists->num_rows) {
			$this->db->query("UPDATE `" . DB_PREFIX . $table . "` SET " . implode(', ', $set) . " WHERE `" . $pk . "` = '" . (int)$id . "' AND language_id = '" . (int)$language_id . "'");
		} else {
			$extra = '';
			if ($this->hasColumn($table, 'image')) {
				$extra = ", `image` = ''";
			}
			$this->db->query("INSERT INTO `" . DB_PREFIX . $table . "` SET `" . $pk . "` = '" . (int)$id . "', language_id = '" . (int)$language_id . "', " . implode(', ', $set) . $extra);
		}
	}

	private function applyFields($row, $incoming, $overwrite, $fields) {
		foreach ($fields as $f) {
			if (!isset($incoming[$f])) {
				continue;
			}
			$val = trim((string)$incoming[$f]);
			if ($val === '') {
				continue;
			}
			if ($overwrite || trim((string)$row[$f]) === '') {
				$row[$f] = $val;
			}
		}

		return $row;
	}

	private function mergeAndSave($entity, $id, $language_id, $base, $seo, $overwrite, $seo_only) {
		$row = $base;
		$row = $this->applyFields($row, $seo, $overwrite, array_keys($seo));
		$this->saveDescription($entity, $id, $language_id, $row);
	}

	private function translateRow($source, $source_lang, $target_lang) {
		$payload = array(
			'name'        => $source['name'],
			'description' => $source['description'],
		);
		if ($source['tag'] !== '') {
			$payload['tag'] = $source['tag'];
		}

		$prompt = (string)$this->config->get('module_wt_ai_prompt_translate');
		$prompt = str_replace(
			array('{source_lang}', '{target_lang}'),
			array($source_lang['name'], $target_lang['name']),
			$prompt
		);

		$material = "SOURCE JSON:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$raw = $this->chat($material, $prompt);
		$json = $this->parseJson($raw);
		if (!$json) {
			throw new Exception('Translate failed for ' . $target_lang['code'] . ': bad JSON');
		}

		return $json;
	}

	private function generateSeo($entity, $content, $lang, $fields) {
		$want = array();
		foreach ($fields as $f) {
			$want[$f] = '';
		}

		$prompt = (string)$this->config->get('module_wt_ai_prompt_seo');
		$prompt = str_replace('{target_lang}', $lang['name'], $prompt);
		$prompt .= "\nEntity type: " . $entity . "\nRequested keys: " . implode(', ', $fields);

		$material = "CONTENT:\n" . json_encode(array(
			'name'        => $content['name'],
			'description' => $this->plain($content['description']),
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$raw = $this->chat($material, $prompt . "\nReturn JSON with keys: " . implode(', ', $fields));
		$json = $this->parseJson($raw);
		if (!$json) {
			return null;
		}

		$out = array();
		foreach ($fields as $f) {
			if (isset($json[$f]) && trim((string)$json[$f]) !== '') {
				$out[$f] = $this->plain((string)$json[$f]);
			}
		}

		return $out;
	}

	private function plain($html) {
		$text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8');
		$text = preg_replace('/\s+/u', ' ', $text);

		return trim((string)$text);
	}

	private function parseJson($raw) {
		$raw = trim((string)$raw);
		if ($raw === '') {
			return null;
		}
		if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $raw, $m)) {
			$raw = $m[1];
		} elseif (preg_match('/\{.*\}/s', $raw, $m)) {
			$raw = $m[0];
		}
		$data = json_decode($raw, true);

		return is_array($data) ? $data : null;
	}

	/**
	 * Проверка ключа активного (или указанного) провайдера.
	 *
	 * @return array{ok:bool,status:string,message:string,provider:string}
	 */
	public function testConnection($provider = '', $api_key = '', $model = '') {
		$provider = $this->normalizeProvider($provider !== '' ? $provider : $this->config->get('module_wt_ai_provider'));
		$api_key = trim((string)$api_key);
		$model = trim((string)$model);

		if ($api_key === '') {
			$api_key = $this->getProviderApiKey($provider);
		}
		if ($model === '') {
			$model = $this->getProviderModel($provider);
		} else {
			$model = $this->normalizeModel($provider, $model);
		}

		if ($api_key === '') {
			return array(
				'ok'       => false,
				'status'   => 'error',
				'message'  => 'API key is empty',
				'provider' => $provider,
			);
		}

		try {
			$this->providerChat($provider, $api_key, $model, 'Reply with exactly: {"ping":"ok"}', 'Connection test. Return only JSON.');
			return array(
				'ok'       => true,
				'status'   => 'connected',
				'message'  => 'Connected',
				'provider' => $provider,
			);
		} catch (Exception $e) {
			return array(
				'ok'       => false,
				'status'   => 'error',
				'message'  => $e->getMessage(),
				'provider' => $provider,
			);
		}
	}

	public function normalizeProvider($provider) {
		$provider = strtolower(trim((string)$provider));
		if (!in_array($provider, array('gemini', 'deepseek', 'openai'), true)) {
			return 'gemini';
		}

		return $provider;
	}

	public function getProviderApiKey($provider = '') {
		$provider = $this->normalizeProvider($provider !== '' ? $provider : $this->config->get('module_wt_ai_provider'));
		$key = trim((string)$this->config->get('module_wt_ai_api_key_' . $provider));
		if ($key === '' && $provider === 'gemini') {
			$key = trim((string)$this->config->get('module_wt_ai_api_key'));
		}

		return $key;
	}

	public function getProviderModel($provider = '') {
		$provider = $this->normalizeProvider($provider !== '' ? $provider : $this->config->get('module_wt_ai_provider'));
		$defaults = array(
			'gemini'   => 'gemini-3.6-flash',
			'deepseek' => 'deepseek-chat',
			'openai'   => 'gpt-4o-mini',
		);
		$model = trim((string)$this->config->get('module_wt_ai_model_' . $provider));
		if ($model === '' && $provider === 'gemini') {
			$model = trim((string)$this->config->get('module_wt_ai_model'));
		}
		if ($model === '') {
			$model = $defaults[$provider];
		}

		return $this->normalizeModel($provider, $model);
	}

	/**
	 * Старые ID моделей Gemini больше не принимаются API.
	 */
	public function normalizeModel($provider, $model) {
		$provider = $this->normalizeProvider($provider);
		$model = trim((string)$model);

		if ($provider === 'gemini') {
			$retired = array(
				'gemini-2.0-flash',
				'gemini-2.0-flash-001',
				'gemini-2.0-flash-lite',
				'gemini-1.5-flash',
				'gemini-1.5-flash-latest',
				'gemini-1.5-pro',
				'gemini-1.5-pro-latest',
				'models/gemini-2.0-flash',
			);
			if ($model === '' || in_array($model, $retired, true)) {
				return 'gemini-3.6-flash';
			}
		}

		return $model !== '' ? $model : $this->getProviderModel($provider);
	}

	private function chat($material, $instruction) {
		$provider = $this->normalizeProvider($this->config->get('module_wt_ai_provider'));
		$api_key = $this->getProviderApiKey($provider);
		$model = $this->getProviderModel($provider);

		if ($api_key === '') {
			throw new Exception('API key is empty for ' . $provider);
		}

		return $this->providerChat($provider, $api_key, $model, $material, $instruction);
	}

	private function providerChat($provider, $api_key, $model, $material, $instruction) {
		if ($provider === 'gemini') {
			return $this->geminiRequest($api_key, $model, $material, $instruction);
		}
		if ($provider === 'deepseek') {
			return $this->openaiCompatibleRequest(
				'https://api.deepseek.com/v1/chat/completions',
				$api_key,
				$model,
				$material,
				$instruction,
				'DeepSeek'
			);
		}

		return $this->openaiCompatibleRequest(
			'https://api.openai.com/v1/chat/completions',
			$api_key,
			$model,
			$material,
			$instruction,
			'ChatGPT'
		);
	}

	private function geminiRequest($api_key, $model, $material, $instruction) {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($api_key);

		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array('text' => $instruction . "\n\n" . $material),
					),
				),
			),
			'generationConfig' => array(
				'temperature'      => 0.4,
				'responseMimeType' => 'application/json',
			),
		);

		list($code, $decoded, $curl_error) = $this->httpJson('POST', $url, array('Content-Type: application/json'), $body);

		if ($curl_error !== '') {
			throw new Exception('Gemini cURL: ' . $curl_error);
		}

		if ($code >= 400) {
			$msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : ('HTTP ' . $code);
			unset($body['generationConfig']['responseMimeType']);
			list($code, $decoded, $curl_error) = $this->httpJson('POST', $url, array('Content-Type: application/json'), $body);
			if ($curl_error !== '') {
				throw new Exception('Gemini cURL: ' . $curl_error);
			}
			if ($code >= 400) {
				$msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : $msg;
				throw new Exception('Gemini: ' . $msg);
			}
		}

		$text = '';
		if (isset($decoded['candidates'][0]['content']['parts'])) {
			foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
				if (isset($part['text'])) {
					$text .= $part['text'];
				}
			}
		}

		if ($text === '') {
			throw new Exception('Gemini returned empty response');
		}

		return $text;
	}

	private function openaiCompatibleRequest($url, $api_key, $model, $material, $instruction, $label) {
		$body = array(
			'model'       => $model,
			'temperature' => 0.4,
			'messages'    => array(
				array('role' => 'system', 'content' => $instruction),
				array('role' => 'user', 'content' => $material),
			),
		);

		list($code, $decoded, $curl_error) = $this->httpJson(
			'POST',
			$url,
			array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $api_key,
			),
			$body
		);

		if ($curl_error !== '') {
			throw new Exception($label . ' cURL: ' . $curl_error);
		}

		if ($code >= 400) {
			$msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : ('HTTP ' . $code);
			throw new Exception($label . ': ' . $msg);
		}

		$text = '';
		if (isset($decoded['choices'][0]['message']['content'])) {
			$text = (string)$decoded['choices'][0]['message']['content'];
		}

		if ($text === '') {
			throw new Exception($label . ' returned empty response');
		}

		return $text;
	}

	/**
	 * @return array{0:int,1:?array,2:string}
	 */
	private function httpJson($method, $url, $headers, $body) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_POSTFIELDS     => json_encode($body),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 120,
		));
		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = $errno ? curl_error($ch) : '';
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$decoded = json_decode((string)$response, true);
		if (!is_array($decoded)) {
			$decoded = null;
		}

		return array($code, $decoded, $error);
	}
}
