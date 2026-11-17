<?php
// Catalogo OCT2026: sellos, nombres del catalogo, producto de arandanos
require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
global $wpdb;
$home=getenv('HOME'); $bk=trim(file_get_contents("$home/backups/.last_burgers")); $BASE='https://meatprotein.eu';
function nf($v){ return str_replace('.',',',(string)(float)$v); }
function list_es(array $a){ if(count($a)<2) return implode('',$a); $l=array_pop($a); return implode(', ',$a).' y '.$l; }
function lcf($s){ return mb_strtolower(mb_substr($s,0,1)).mb_substr($s,1); }
function meta_desc($title,$core){
  foreach(["$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00 en Península y Portugal.",
           "$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00.",
           "$title: $core. Bandeja de 400 g. Entrega refrigerada antes de las 14:00."] as $c){ if(mb_strlen($c)<=160) return $c; }
  return $c;
}

$ids_all=[2427,2450,2455,2457,2458,2459,2460,2461,2462,2463,2497,2498,2499,2500,2501,2502,4142,5098,13621,20065,34470,34859,36141,42057,43882,45578];
$before=[]; foreach($ids_all as $id){ $p=get_post($id); $before[$id]=['title'=>$p->post_title,'slug'=>$p->post_name,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'rm_title'=>get_post_meta($id,'rank_math_title',true),'rm_desc'=>get_post_meta($id,'rank_math_description',true),'rm_kw'=>get_post_meta($id,'rank_math_focus_keyword',true),'permalink'=>get_permalink($id)]; }
file_put_contents("$bk/catalogo-before.json",json_encode($before,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

// ---- A) sellos a la mediateca
$seal=[];
foreach(['alto'=>['sello-alto-proteina.png','Alto contenido en proteínas'],'bajo'=>['sello-bajo-grasa.png','Bajo en grasa']] as $k=>[$f,$alt]){
  $tmp=wp_tempnam($f); copy("$home/bandejas/$f",$tmp);
  $aid=media_handle_sideload(['name'=>$f,'tmp_name'=>$tmp],0,$alt);
  if(is_wp_error($aid)){ die("ERR sello $k ".$aid->get_error_message()."\n"); }
  update_post_meta($aid,'_wp_attachment_image_alt',$alt);
  $seal[$k]=['id'=>$aid,'url'=>wp_get_attachment_url($aid)];
  echo "SELLO $k => $aid ".$seal[$k]['url']."\n";
}
update_option('mp_sellos_ids',['alto'=>$seal['alto']['id'],'bajo'=>$seal['bajo']['id']],false);

// ---- B) renombrados segun catalogo / caja
$ren=[
 43882=>['Hamburguesa de pollo con miel y mostaza','hamburguesa-de-pollo-con-miel-y-mostaza'],
 2459 =>['Hamburguesa de pollo con rulo de cabra','hamburguesa-de-pollo-con-rulo-de-cabra'],
 42057=>['Hamburguesa de pollo con pepinillos y queso cheddar','hamburguesa-de-pollo-con-pepinillos-y-queso-cheddar'],
];
$rtbl=$wpdb->prefix.'rank_math_redirections'; $llm_repl=[];
foreach($ren as $id=>[$new,$slug]){
  $p=get_post($id); $old=$p->post_title; $oldlink=get_permalink($id);
  wp_update_post(['ID'=>$id,'post_title'=>wp_slash($new),'post_name'=>$slug,
    'post_content'=>wp_slash(str_replace($old,$new,$p->post_content)),'post_excerpt'=>wp_slash(str_replace($old,$new,$p->post_excerpt))]);
  $newlink=get_permalink($id);
  $desc=str_replace($old,$new,get_post_meta($id,'rank_math_description',true));
  if(mb_strlen($desc)>160) $desc=str_replace(' (4 x 100 g)','',$desc);
  update_post_meta($id,'rank_math_description',$desc);
  update_post_meta($id,'rank_math_title',"$new | Meatprotein");
  update_post_meta($id,'rank_math_focus_keyword',mb_strtolower($new));
  if($t=get_post_thumbnail_id($id)) update_post_meta($t,'_wp_attachment_image_alt','Bandeja de '.lcf($new).', 400 g');
  $wpdb->update($rtbl,['url_to'=>$newlink],['url_to'=>$oldlink]); // cadenas: origen antiguo -> destino final
  $path=trim(parse_url($oldlink,PHP_URL_PATH),'/');
  try{ \RankMath\Redirections\Redirection::from(['url_to'=>$newlink,'sources'=>[['pattern'=>$path,'comparison'=>'exact']],'header_code'=>301,'status'=>'active'])->save(); }catch(\Throwable $e){ echo "REDIR ERR ".$e->getMessage()."\n"; }
  delete_post_meta($id,'_wp_old_slug'); 
  $llm_repl["[$old]($oldlink)"]="[$new]($newlink)";
  echo "REN $id | $new | ".mb_strlen($desc)." | $newlink\n";
}

// ---- C) producto nuevo: pollo con arandanos (borrador)
$title='Hamburguesa de pollo con arándanos'; $slug='hamburguesa-de-pollo-con-arandanos';
$d=['imagen'=>'Pollo con arándanos.png','ingredientes'=>'Pechuga de pollo 84%, arándanos 8%, agua 3%, tomate triturado, clara de HUEVO, fibra vegetal, sal, pimienta, dextrosa, cebolla deshidratada.','alergenos'=>['Huevo'],'trazas'=>'gluten','kj'=>343,'kcal'=>82,'grasas'=>1.9,'saturadas'=>0.6,'hidratos'=>2.9,'azucares'=>0.5,'proteinas'=>13.2,'sal'=>0.2,'peso_unidad_g'=>100,'peso_bandeja_g'=>400,'unidades'=>4,'energia_publicable'=>true];
$existing=get_page_by_path($slug,OBJECT,'product');
if($existing){ $nid=$existing->ID; echo "YA EXISTE $nid\n"; }
else {
  $pr=new WC_Product_Simple(); $pr->set_name($title); $pr->set_slug($slug); $pr->set_status('draft');
  $pr->set_regular_price('5.90'); $pr->set_tax_status('taxable'); $pr->set_manage_stock(true); $pr->set_stock_quantity(0); $pr->set_stock_status('outofstock');
  $pr->set_category_ids([34,433]);
  $tids=[]; foreach([['hamburguesa','hamburguesa'],['pollo','pollo'],['arandanos','arándanos']] as [$s,$n]){ $t=term_exists($s,'product_tag'); if(!$t) $t=wp_insert_term($n,'product_tag',['slug'=>$s]); $tids[]=(int)(is_array($t)?$t['term_id']:$t); }
  $pr->set_tag_ids($tids); $nid=$pr->save();
  update_post_meta($nid,'rank_math_primary_product_cat',433);
  echo "NUEVO $nid\n";
}
$rows=''; foreach([['Valor energético',nf($d['kj']).' kJ / '.nf($d['kcal']).' kcal'],['Grasas',nf($d['grasas']).' g'],['de las cuales saturadas',nf($d['saturadas']).' g'],['Hidratos de carbono',nf($d['hidratos']).' g'],['de los cuales azúcares',nf($d['azucares']).' g'],['Proteínas',nf($d['proteinas']).' g'],['Sal',nf($d['sal']).' g']] as [$a,$b]) $rows.='<tr><th scope="row">'.$a.'</th><td>'.$b.'</td></tr>';
$ing=preg_replace('/\b(HUEVO|LECHE|TRIGO|LACTOSA)\b/u','<strong>$1</strong>',esc_html($d['ingredientes']));
$html ='<p><strong>'.esc_html($title)."</strong> de Meatprotein: bandeja de 400 g con 4 hamburguesas de 100 g, frescas y proteicas.</p>\n";
$html.="<h2>Ingredientes y alérgenos</h2>\n<p><strong>Ingredientes:</strong> $ing</p>\n<p><strong>Alérgenos:</strong> contiene huevo. Puede contener trazas de gluten.</p>\n";
$html.="<h2>Información nutricional por 100 g</h2>\n<table class=\"mp-nutri\"><tbody>$rows</tbody></table>\n<p>Peso neto: 100 g por unidad, 400 g por bandeja (4 unidades).</p>\n";
$html.="<h2>Cómo la recibes</h2>\n<ul>\n<li>Producto fresco, enviado con transporte refrigerado (SEUR).</li>\n<li>Entrega antes de las 14:00 en Península y Portugal. Haz tu pedido antes de las 13:00 para el envío según el calendario habitual.</li>\n<li>Envío gratis a la Península con el <a href=\"$BASE/producto/pack-10-bandejas-hamburguesas/\">pack de 10 bandejas</a>.</li>\n</ul>\n";
$html.="<p>Más detalles en <a href=\"$BASE/envios-y-devoluciones/\">Envíos y devoluciones</a> y en <a href=\"$BASE/controles-de-calidad/\">Controles de calidad</a>.</p>\n";
$core='13,2 g de proteína y 82 kcal por 100 g';
wp_update_post(['ID'=>$nid,'post_content'=>wp_slash($html),'post_excerpt'=>wp_slash("$title: bandeja de 400 g (4 x 100 g) con $core. Entrega refrigerada antes de las 14:00 en Península y Portugal.")]);
update_post_meta($nid,'_mp_nutrition',wp_slash(wp_json_encode($d,JSON_UNESCAPED_UNICODE)));
update_post_meta($nid,'rank_math_title',"$title | Meatprotein");
update_post_meta($nid,'rank_math_description',meta_desc($title,$core));
update_post_meta($nid,'rank_math_focus_keyword',mb_strtolower($title));
if(!get_post_thumbnail_id($nid)){
  $f='bandeja-hamburguesa-de-pollo-con-arandanos.jpg'; $tmp=wp_tempnam($f); copy("$home/bandejas/$f",$tmp);
  $alt='Bandeja de hamburguesa de pollo con arándanos, 400 g';
  $aid=media_handle_sideload(['name'=>$f,'tmp_name'=>$tmp],$nid,$alt);
  if(is_wp_error($aid)) echo "ERR img ".$aid->get_error_message()."\n"; else { update_post_meta($aid,'_wp_attachment_image_alt',$alt); set_post_thumbnail($nid,$aid); }
}
wc_delete_product_transients($nid);

// ---- D) sellos y afirmaciones en todas las fichas
$all=array_merge($ids_all,[$nid]); $noclaim=[34470,42057]; // vinagre balsamico (sulfitos) / achiote (colorante): no se afirma "sin ... " 
foreach($all as $id){
  $p=get_post($id); $n=json_decode(get_post_meta($id,'_mp_nutrition',true),true); if(!$n){ echo "SIN NUTRI $id\n"; continue; }
  $pe=$n['proteinas']*4; $en=$pe+$n['grasas']*9+$n['hidratos']*4; $high=($pe/$en*100)>=20; $low=($n['grasas']<=3);
  $imgs='';
  if($high) $imgs.='<img src="'.esc_url($seal['alto']['url']).'" alt="Alto contenido en proteínas" width="68" height="64" loading="lazy" />';
  if($low)  $imgs.='<img src="'.esc_url($seal['bajo']['url']).'" alt="Bajo en grasa" width="92" height="64" loading="lazy" />';
  $block='<p class="mp-sellos">'.$imgs.'</p>';
  $ex=preg_replace('#\s*<p class="mp-sellos">.*?</p>#s','',$p->post_excerpt)."\n".$block;
  $cl='Alto contenido en proteínas: más del 20 % de la energía procede de las proteínas.';
  if($low) $cl.=' Bajo en grasa: '.nf($n['grasas']).' g de grasa por 100 g.';
  if(!in_array($id,$noclaim,true)) $cl.=' Sin sulfitos, colorantes ni conservantes añadidos.';
  $co=preg_replace('#\s*<p class="mp-claims">.*?</p>#s','',$p->post_content);
  $co=preg_replace('#</p>#','</p>'."\n".'<p class="mp-claims">'.$cl.'</p>',$co,1);
  wp_update_post(['ID'=>$id,'post_content'=>wp_slash($co),'post_excerpt'=>wp_slash($ex)]);
  wc_delete_product_transients($id);
  echo "SELLOS $id ".($high?'A':'-').($low?'B':'-')."\n";
}

// ---- E) CSS y llms.txt
$css=wp_get_custom_css('betheme');
if(strpos($css,'.mp-sellos')===false){ $css.="\n.mp-sellos{margin:.75rem 0}.mp-sellos img{height:64px;width:auto;margin:0 14px 0 0;display:inline-block;vertical-align:middle}.mp-claims{font-weight:600}\n"; $r=wp_update_custom_css_post($css,['stylesheet'=>'betheme']); echo is_wp_error($r)?"CSS ERR\n":"CSS ok\n"; }
$L=file_get_contents(ABSPATH.'llms.txt');
foreach($llm_repl as $a=>$b) $L=str_replace($a,$b,$L);
$claim="- Calidad: todas las hamburguesas y albóndigas son de alto contenido en proteínas (más del 20 % de la energía procede de las proteínas) y las que llevan el sello \"Bajo en grasa\" tienen 3 g de grasa o menos por 100 g. Sin sulfitos, colorantes ni conservantes añadidos, salvo indicación en la ficha.\n";
if(strpos($L,'- Calidad:')===false) $L=str_replace("- Cada ficha de producto incluye",$claim."- Cada ficha de producto incluye",$L);
file_put_contents(ABSPATH.'llms.txt',$L);
wp_cache_flush(); echo "FIN nuevo=$nid\n";
