<?php
// Etiquetas nuevas + bandejas nuevas (Images/New, 2026-11-17)
require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
$home=getenv('HOME'); $bk=trim(file_get_contents("$home/backups/.last_burgers")); $BASE='https://meatprotein.eu';
$N=json_decode(file_get_contents("$home/nutri_new.json"),true);
$map=json_decode(file_get_contents("$home/bandejas2/map.json"),true);
$seal_ids=get_option('mp_sellos_ids'); $seal=['alto'=>wp_get_attachment_url($seal_ids['alto']),'bajo'=>wp_get_attachment_url($seal_ids['bajo'])];
$skip_energy=[2497,42057]; $noclaim=[42057];
function nf($v){ return str_replace('.',',',(string)(float)$v); }
function list_es(array $a){ if(count($a)<2) return implode('',$a); $l=array_pop($a); return implode(', ',$a).' y '.$l; }
function lcf($s){ return mb_strtolower(mb_substr($s,0,1)).mb_substr($s,1); }

$before=[]; foreach($N as $id=>$d){ $p=get_post($id); $before[$id]=['content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'rm_desc'=>get_post_meta($id,'rank_math_description',true),'nutri'=>get_post_meta($id,'_mp_nutrition',true),'thumb'=>get_post_thumbnail_id($id)]; }
file_put_contents("$bk/nuevas-before.json",json_encode($before,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

foreach($N as $id=>$d){
  $id=(int)$id; $p=get_post($id); if(!$p){ echo "NO POST $id\n"; continue; }
  $title=$p->post_title; $slug=$p->post_name; $isAlb=($id===2500); $noE=in_array($id,$skip_energy,true);
  $d['energia_publicable']=!$noE;
  update_post_meta($id,'_mp_nutrition',wp_slash(wp_json_encode($d,JSON_UNESCAPED_UNICODE)));

  preg_match_all('/<img\b[^>]*>/i',$p->post_content,$m); $imgs=''; foreach($m[0] as $img){ if(strpos($img,'sello-')!==false) continue; $imgs.='<p>'.$img."</p>\n"; }
  $ing=preg_replace('/\b(HUEVO|LECHE|TRIGO|LACTOSA|MOSTAZA)\b/u','<strong>$1</strong>',esc_html($d['ingredientes']));
  $al=array_map('mb_strtolower',$d['alergenos']); $gl=($d['trazas']==='gluten');
  $rows='';
  if(!$noE){ $rows.='<tr><th scope="row">Valor energético</th><td>'.($d['kj']!==null?nf($d['kj']).' kJ / ':'').nf($d['kcal']).' kcal</td></tr>'; }
  foreach([['Grasas','grasas'],['de las cuales saturadas','saturadas'],['Hidratos de carbono','hidratos'],['de los cuales azúcares','azucares'],['Proteínas','proteinas'],['Sal','sal']] as [$lab,$k]) $rows.='<tr><th scope="row">'.$lab.'</th><td>'.nf($d[$k]).' g</td></tr>';

  $high=true; $low=($d['grasas']<=3);
  $t=nf($d['proteinas']).' g de proteína por cada 100 g';
  if(!$noE) $t.=($d['kcal']<=130?' y solo ':' y ').nf($d['kcal']).' kcal';
  $t.=': alto contenido en proteínas'.($low?' y bajo en grasa ('.nf($d['grasas']).' g)':'').'.';
  if(!in_array($id,$noclaim,true)) $t.=' Sin sulfitos, colorantes ni conservantes añadidos.';

  $fmt=$isAlb?'bandeja de 400 g de albóndigas proteicas frescas':'bandeja de 400 g con 4 hamburguesas de 100 g, frescas y proteicas';
  $html ='<p><strong>'.esc_html($title)."</strong> de Meatprotein: $fmt.</p>\n";
  $html.='<p class="mp-claims">'.$t."</p>\n";
  $html.="<h2>Ingredientes y alérgenos</h2>\n<p><strong>Ingredientes:</strong> $ing</p>\n";
  $html.='<p><strong>Alérgenos:</strong> contiene '.esc_html(list_es($al)).'.'.($gl?' Puede contener trazas de gluten.':'')."</p>\n";
  $html.="<h2>Información nutricional por 100 g</h2>\n<table class=\"mp-nutri\"><tbody>$rows</tbody></table>\n";
  $html.='<p>Peso neto: '.($isAlb?'400 g por bandeja.':'100 g por unidad, 400 g por bandeja (4 unidades).')."</p>\n";
  $html.="<h2>Cómo la recibes</h2>\n<ul>\n<li>Producto fresco, enviado con transporte refrigerado (SEUR).</li>\n<li>Entrega antes de las 14:00 en Península y Portugal. Haz tu pedido antes de las 13:00 para el envío según el calendario habitual.</li>\n<li>Envío gratis a la Península con el <a href=\"$BASE/producto/pack-10-bandejas-hamburguesas/\">pack de 10 bandejas</a>.</li>\n</ul>\n";
  $html.="<p>Más detalles en <a href=\"$BASE/envios-y-devoluciones/\">Envíos y devoluciones</a> y en <a href=\"$BASE/controles-de-calidad/\">Controles de calidad</a>.</p>\n".$imgs;

  $prot=nf($d['proteinas']); $core=$noE?"$prot g de proteína por 100 g":"$prot g de proteína y ".nf($d['kcal'])." kcal por 100 g";
  $excerpt=($isAlb?"$title: bandeja de 400 g de albóndigas proteicas con $core.":"$title: bandeja de 400 g (4 x 100 g) con $core.")." Entrega refrigerada antes de las 14:00 en Península y Portugal.";
  $excerpt.="\n".'<p class="mp-sellos">'.'<img src="'.esc_url($seal['alto']).'" alt="Alto contenido en proteínas" width="68" height="64" loading="lazy" />'.($low?'<img src="'.esc_url($seal['bajo']).'" alt="Bajo en grasa" width="92" height="64" loading="lazy" />':'').'</p>';
  $desc="$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00 en Península y Portugal.";
  foreach(["$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00.","$title: $core. Bandeja de 400 g. Entrega refrigerada antes de las 14:00."] as $c){ if(mb_strlen($desc)<=160) break; $desc=$c; }
  if($isAlb) $desc=str_replace(' (4 x 100 g)','',$desc);

  wp_update_post(['ID'=>$id,'post_content'=>wp_slash($html),'post_excerpt'=>wp_slash($excerpt)]);
  update_post_meta($id,'rank_math_description',$desc);

  // imagen destacada nueva
  if(isset($map[(string)$id])){
    $f="bandeja-$slug.jpg"; $tmp=wp_tempnam($f); copy("$home/bandejas2/".$map[(string)$id],$tmp);
    $alt='Bandeja de '.lcf($title).', 400 g';
    $aid=media_handle_sideload(['name'=>$f,'tmp_name'=>$tmp],$id,$alt);
    if(is_wp_error($aid)) echo "ERR img $id ".$aid->get_error_message()."\n"; else { update_post_meta($aid,'_wp_attachment_image_alt',$alt); set_post_thumbnail($id,$aid); }
  }
  wc_delete_product_transients($id);
  echo "OK $id | $title | ".mb_strlen($desc)."\n";
}

// llms.txt
$cats=['pollo'=>['Hamburguesas de pollo','hamburguesas-de-pollo'],'ternera'=>['Hamburguesas de ternera','hamburguesas-de-ternera'],'pavo'=>['Hamburguesas de pavo','hamburguesas-de-pavo'],'potro'=>['Hamburguesas de potro','hamburguesas-de-potro'],'albondigas'=>['Albóndigas proteicas','albondigas-proteicas']];
$L="# Meatprotein\n\n> Tienda online española de hamburguesas, albóndigas y productos fitness altos en proteína. Bandejas frescas de 400 g (4 x 100 g) con entrega refrigerada en Península y Portugal.\n\n## Información clave\n\n";
$L.="- Envío: transporte refrigerado con SEUR. Entrega antes de las 14:00 en Península y Portugal; pedidos antes de las 13:00 para el envío según el calendario habitual. No se envía a Baleares, Canarias, Ceuta ni Melilla.\n- Envío gratis a la Península con el pack de 10 bandejas.\n- Pago con tarjeta, Bizum, Apple Pay, Google Pay y PayPal.\n- Formato de las hamburguesas: bandeja de 400 g con 4 hamburguesas de 100 g.\n";
$L.="- Calidad: todas las hamburguesas y albóndigas son de alto contenido en proteínas (más del 20 % de la energía procede de las proteínas) y las que llevan el sello \"Bajo en grasa\" tienen 3 g de grasa o menos por 100 g. Sin sulfitos, colorantes ni conservantes añadidos, salvo indicación en la ficha.\n";
$L.="- Cada ficha de producto incluye ingredientes, alérgenos e información nutricional por 100 g, tal como figura en la etiqueta.\n\n";
$L.="## Packs\n\n- [Pack de 10 bandejas de hamburguesas, envío gratis]($BASE/producto/pack-10-bandejas-hamburguesas/): elige 10 bandejas entre todos los sabores y recibe una de regalo.\n\n## Hamburguesas y albóndigas por tipo de carne (proteína y energía por 100 g)\n";
foreach($cats as $k=>[$name,$cs]){
  $L.="\n### $name\n\n"; $term=get_term_by('slug',$cs,'product_cat');
  $ids=get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$term->term_id,'include_children'=>false]],'orderby'=>'title','order'=>'ASC']);
  foreach($ids as $pid){ $n=json_decode(get_post_meta($pid,'_mp_nutrition',true),true); $x=''; if($n){ $x=': '.nf($n['proteinas']).' g de proteína'.(!empty($n['energia_publicable'])?', '.nf($n['kcal']).' kcal':''); } $L.='- ['.get_the_title($pid).']('.get_permalink($pid).")$x\n"; }
}
$L.="\n## Otras categorías\n\n"; foreach(['pizza-fitness','pan','salsas-y-cremas','snacks-dulces','pasta-konjac','suplantacion','queso'] as $s){ $t=get_term_by('slug',$s,'product_cat'); if($t) $L.="- [".$t->name."](".get_term_link($t).")\n"; }
$L.="\n## Información\n\n- [Envíos y devoluciones]($BASE/envios-y-devoluciones/)\n- [Controles de calidad]($BASE/controles-de-calidad/)\n- [Preguntas frecuentes]($BASE/faqs/)\n- [Contacto]($BASE/contacto/)\n";
file_put_contents(ABSPATH.'llms.txt',$L); wp_cache_flush(); echo "llms ok\n";
