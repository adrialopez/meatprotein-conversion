<?php
// Aplica ingredientes, alérgenos y tabla nutricional a las burgers (produccion)
$home = getenv('HOME');
$backup = trim(file_get_contents("$home/backups/.last_burgers"));
$BASE = 'https://meatprotein.eu';
$N = json_decode(file_get_contents("$home/nutri.json"), true);
$skip_energy = [2497, 2498, 42057]; // energia incoherente en la etiqueta: no publicar kcal/kJ

function nf($v) { $s = (string)(float)$v; return str_replace('.', ',', $s); }
function list_es(array $a) { if (count($a) < 2) return implode('', $a); $last = array_pop($a); return implode(', ', $a).' y '.$last; }

$before = [];
foreach ($N as $id => $d) {
  $p = get_post($id); if (!$p) { echo "NO POST $id\n"; continue; }
  $before[$id] = ['content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'rm_desc'=>get_post_meta($id,'rank_math_description',true),'nutri'=>get_post_meta($id,'_mp_nutrition',true)];
}
file_put_contents("$backup/burgers-nutri-before.json", json_encode($before, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

$titles = [];
foreach ($N as $id => $d) {
  $id = (int)$id; $p = get_post($id); $title = $p->post_title; $titles[$id] = $title;
  $noE = in_array($id, $skip_energy, true);
  $isAlb = ($id === 2500);

  // meta estructurada (fuente unica para schema y textos)
  $d['energia_publicable'] = !$noE;
  update_post_meta($id, '_mp_nutrition', wp_slash(wp_json_encode($d, JSON_UNESCAPED_UNICODE)));

  // imagenes existentes
  preg_match_all('/<img\b[^>]*>/i', $p->post_content, $m);
  $imgs = ''; foreach ($m[0] as $img) $imgs .= '<p>'.$img."</p>\n";

  $ing = esc_html($d['ingredientes']);
  $ing = preg_replace('/\b(HUEVO|LECHE|TRIGO|LACTOSA)\b/u', '<strong>$1</strong>', $ing);
  $al = array_map('mb_strtolower', $d['alergenos']);
  $rows = '';
  if (!$noE) $rows .= '<tr><th scope="row">Valor energético</th><td>'.nf($d['kj']).' kJ / '.nf($d['kcal']).' kcal</td></tr>';
  $rows .= '<tr><th scope="row">Grasas</th><td>'.nf($d['grasas']).' g</td></tr>';
  $rows .= '<tr><th scope="row">de las cuales saturadas</th><td>'.nf($d['saturadas']).' g</td></tr>';
  $rows .= '<tr><th scope="row">Hidratos de carbono</th><td>'.nf($d['hidratos']).' g</td></tr>';
  $rows .= '<tr><th scope="row">de los cuales azúcares</th><td>'.nf($d['azucares']).' g</td></tr>';
  $rows .= '<tr><th scope="row">Proteínas</th><td>'.nf($d['proteinas']).' g</td></tr>';
  $rows .= '<tr><th scope="row">Sal</th><td>'.nf($d['sal']).' g</td></tr>';

  $fmt = $isAlb ? 'bandeja de 400 g de albóndigas proteicas frescas' : 'bandeja de 400 g con 4 hamburguesas de 100 g, frescas y proteicas';
  $html  = '<p><strong>'.esc_html($title)."</strong> de Meatprotein: $fmt.</p>\n";
  $html .= "<h2>Ingredientes y alérgenos</h2>\n<p><strong>Ingredientes:</strong> $ing</p>\n";
  $html .= '<p><strong>Alérgenos:</strong> contiene '.esc_html(list_es($al)).'. Puede contener trazas de gluten.</p>'."\n";
  $html .= "<h2>Información nutricional por 100 g</h2>\n";
  $html .= '<table class="mp-nutri"><tbody>'.$rows."</tbody></table>\n";
  $html .= '<p>Peso neto: '.($isAlb ? '400 g por bandeja.' : '100 g por unidad, 400 g por bandeja (4 unidades).')."</p>\n";
  $html .= "<h2>Cómo la recibes</h2>\n<ul>\n";
  $html .= "<li>Producto fresco, enviado con transporte refrigerado (SEUR).</li>\n";
  $html .= "<li>Entrega antes de las 14:00 en Península y Portugal. Haz tu pedido antes de las 13:00 para el envío según el calendario habitual.</li>\n";
  $html .= "<li>Envío gratis a la Península con el <a href=\"$BASE/producto/pack-10-bandejas-hamburguesas/\">pack de 10 bandejas</a>.</li>\n</ul>\n";
  $html .= "<p>Más detalles en <a href=\"$BASE/envios-y-devoluciones/\">Envíos y devoluciones</a> y en <a href=\"$BASE/controles-de-calidad/\">Controles de calidad</a>.</p>\n";
  $html .= $imgs;

  $prot = nf($d['proteinas']); $kc = nf($d['kcal']);
  $core = $noE ? "$prot g de proteína por 100 g" : "$prot g de proteína y $kc kcal por 100 g";
  $excerpt = $isAlb ? "$title: bandeja de 400 g de albóndigas proteicas con $core. Entrega refrigerada antes de las 14:00 en Península y Portugal."
                    : "$title: bandeja de 400 g (4 x 100 g) con $core. Entrega refrigerada antes de las 14:00 en Península y Portugal.";
  // meta description <= 160
  $c1 = "$title: $core. Bandeja de 400 g (4 x 100 g), fresca. Entrega refrigerada antes de las 14:00 en Península y Portugal.";
  $c2 = "$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00 en Península y Portugal.";
  $c3 = "$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00.";
  $c4 = "$title: $core. Bandeja de 400 g. Entrega refrigerada antes de las 14:00.";
  $desc = $c1; foreach ([$c2,$c3,$c4] as $c) { if (mb_strlen($desc) <= 160) break; $desc = $c; }
  if ($isAlb) $desc = str_replace('(4 x 100 g)', '', str_replace('Bandeja de 400 g (4 x 100 g)', 'Bandeja de 400 g', $desc));

  wp_update_post(['ID'=>$id,'post_content'=>wp_slash($html),'post_excerpt'=>wp_slash($excerpt)]);
  update_post_meta($id, 'rank_math_description', $desc);
  wc_delete_product_transients($id);
  echo "OK $id | ".mb_strlen($desc)." | $desc\n";
}

// CSS de la tabla (Additional CSS, betheme)
$css = wp_get_custom_css('betheme');
if (strpos($css, '.mp-nutri') === false) {
  $css .= "\n.mp-nutri{width:100%;max-width:520px;border-collapse:collapse;margin:0 0 1rem}.mp-nutri th,.mp-nutri td{padding:.5rem .75rem;border:1px solid #d9d9d9;text-align:left;font-size:.95em}.mp-nutri th{font-weight:600;width:60%}\n";
  $r = wp_update_custom_css_post($css, ['stylesheet'=>'betheme']);
  echo is_wp_error($r) ? "CSS ERR ".$r->get_error_message()."\n" : "CSS ok\n";
}

// llms.txt
$cats = ['pollo'=>'Hamburguesas de pollo','ternera'=>'Hamburguesas de ternera','pavo'=>'Hamburguesas de pavo','potro'=>'Hamburguesas de potro','albondigas'=>'Albóndigas proteicas'];
$catslug = ['pollo'=>'hamburguesas-de-pollo','ternera'=>'hamburguesas-de-ternera','pavo'=>'hamburguesas-de-pavo','potro'=>'hamburguesas-de-potro','albondigas'=>'albondigas-proteicas'];
$L  = "# Meatprotein\n\n";
$L .= "> Tienda online española de hamburguesas, albóndigas y productos fitness altos en proteína. Bandejas frescas de 400 g (4 x 100 g) con entrega refrigerada en Península y Portugal.\n\n";
$L .= "## Información clave\n\n";
$L .= "- Envío: transporte refrigerado con SEUR. Entrega antes de las 14:00 en Península y Portugal; pedidos antes de las 13:00 para el envío según el calendario habitual. No se envía a Baleares, Canarias, Ceuta ni Melilla.\n";
$L .= "- Envío gratis a la Península con el pack de 10 bandejas.\n";
$L .= "- Pago con tarjeta, Bizum, Apple Pay, Google Pay y PayPal.\n";
$L .= "- Formato de las hamburguesas: bandeja de 400 g con 4 hamburguesas de 100 g.\n";
$L .= "- Cada ficha de producto incluye ingredientes, alérgenos e información nutricional por 100 g, tal como figura en la etiqueta.\n\n";
$L .= "## Packs\n\n- [Pack de 10 bandejas de hamburguesas, envío gratis]($BASE/producto/pack-10-bandejas-hamburguesas/): elige 10 bandejas entre todos los sabores y recibe una de regalo.\n\n";
$L .= "## Hamburguesas y albóndigas por tipo de carne (proteína y energía por 100 g)\n";
foreach ($cats as $k => $name) {
  $L .= "\n### $name\n\n";
  $term = get_term_by('slug', $catslug[$k], 'product_cat');
  $ids = get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$term->term_id,'include_children'=>false]],'orderby'=>'title','order'=>'ASC']);
  foreach ($ids as $id) {
    $n = json_decode(get_post_meta($id,'_mp_nutrition',true), true);
    $extra = '';
    if ($n) { $extra = ': '.nf($n['proteinas']).' g de proteína'.(!empty($n['energia_publicable']) ? ', '.nf($n['kcal']).' kcal' : ''); }
    $L .= '- ['.get_the_title($id).']('.get_permalink($id).")$extra\n";
  }
}
$L .= "\n## Otras categorías\n\n";
foreach (['pizza-fitness','pan','salsas-y-cremas','snacks-dulces','pasta-konjac','suplantacion','queso'] as $s) {
  $t = get_term_by('slug',$s,'product_cat'); if ($t) $L .= "- [".$t->name."](".get_term_link($t).")\n";
}
$L .= "\n## Información\n\n- [Envíos y devoluciones]($BASE/envios-y-devoluciones/)\n- [Controles de calidad]($BASE/controles-de-calidad/)\n- [Preguntas frecuentes]($BASE/faqs/)\n- [Contacto]($BASE/contacto/)\n";
file_put_contents(ABSPATH.'llms.txt', $L);
echo "llms.txt ok\n";
