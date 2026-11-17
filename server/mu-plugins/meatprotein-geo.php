<?php
/**
 * Plugin Name: Meatprotein GEO schema
 * Description: Añade marca, peso, ingredientes, alérgenos y valores nutricionales (additionalProperty) al schema Product de Rank Math, a partir del meta _mp_nutrition.
 * Version: 1.0.0
 * Author: Adrià López
 */
if (!defined('ABSPATH')) { exit; }

add_filter('rank_math/json_ld', function ($data, $jsonld = null) {
	try {
		if (!function_exists('is_product') || !is_product()) { return $data; }
		$id = get_queried_object_id();
		if (!$id) { return $data; }
		$raw = get_post_meta($id, '_mp_nutrition', true);
		if (!$raw) { return $data; }
		$n = json_decode($raw, true);
		if (!is_array($n)) { return $data; }

		$pv = function ($name, $value, $unit = null) {
			$o = ['@type' => 'PropertyValue', 'name' => $name, 'value' => $value];
			if ($unit) { $o['unitText'] = $unit; }
			return $o;
		};
		$props = [];
		$props[] = $pv('Ingredientes', (string) $n['ingredientes']);
		$props[] = $pv('Alérgenos', 'Contiene ' . mb_strtolower(implode(', ', $n['alergenos'])) . '.' . (($n['trazas'] ?? '') === 'gluten' ? ' Puede contener trazas de gluten.' : ''));
		if (!empty($n['energia_publicable'])) {
			$props[] = $pv('Valor energético por 100 g', (float) $n['kcal'], 'kcal');
		}
		$props[] = $pv('Proteínas por 100 g', (float) $n['proteinas'], 'g');
		$props[] = $pv('Grasas por 100 g', (float) $n['grasas'], 'g');
		$props[] = $pv('Grasas saturadas por 100 g', (float) $n['saturadas'], 'g');
		$props[] = $pv('Hidratos de carbono por 100 g', (float) $n['hidratos'], 'g');
		$props[] = $pv('Azúcares por 100 g', (float) $n['azucares'], 'g');
		$props[] = $pv('Sal por 100 g', (float) $n['sal'], 'g');

		$walk = function (&$node) use (&$walk, $props) {
			if (!is_array($node)) { return; }
			$type = $node['@type'] ?? null;
			if ($type === 'Product' || (is_array($type) && in_array('Product', $type, true))) {
				if (empty($node['brand'])) {
					$node['brand'] = ['@type' => 'Brand', 'name' => 'Meatprotein'];
				}
				if (empty($node['weight'])) {
					$node['weight'] = ['@type' => 'QuantitativeValue', 'value' => 400, 'unitCode' => 'GRM', 'unitText' => 'g'];
				}
				$existing = (isset($node['additionalProperty']) && is_array($node['additionalProperty'])) ? $node['additionalProperty'] : [];
				$node['additionalProperty'] = array_merge($existing, $props);
			}
			foreach ($node as $k => &$child) {
				if (is_array($child)) { $walk($child); }
			}
			unset($child);
		};
		$walk($data);
	} catch (\Throwable $e) {
		// no romper la pagina por el schema
	}
	return $data;
}, 99, 2);
