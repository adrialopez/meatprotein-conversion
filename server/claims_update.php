<?php
function nf($v){ return str_replace('.',',',(string)(float)$v); }
$ids=[2427,2450,2455,2457,2458,2459,2460,2461,2462,2463,2497,2498,2499,2500,2501,2502,4142,5098,13621,20065,34470,34859,36141,42057,43882,45578,45906];
$noclaim=[34470,42057];
foreach($ids as $id){
  $p=get_post($id); $n=json_decode(get_post_meta($id,'_mp_nutrition',true),true); if(!$n) continue;
  $low=$n['grasas']<=3; $pub=!empty($n['energia_publicable']);
  $t=nf($n['proteinas']).' g de proteína por cada 100 g';
  if($pub) $t.=($n['kcal']<=130?' y solo ':' y ').nf($n['kcal']).' kcal';
  $t.=': alto contenido en proteínas'.($low?' y bajo en grasa ('.nf($n['grasas']).' g)':'').'.';
  if(!in_array($id,$noclaim,true)) $t.=' Sin sulfitos, colorantes ni conservantes añadidos.';
  $co=preg_replace('#<p class="mp-claims">.*?</p>#s','<p class="mp-claims">'.$t.'</p>',$p->post_content,1,$c);
  if(!$c){ echo "SIN CLAIM $id\n"; continue; }
  wp_update_post(['ID'=>$id,'post_content'=>wp_slash($co)]); wc_delete_product_transients($id);
  echo "$id $t\n";
}
