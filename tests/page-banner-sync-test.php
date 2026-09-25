<?php
/**
 * DS_Page_Banner: the Featured Image and the Banner "Background Photo" follow
 * whichever one the partner changed, including a removal.
 *
 * Needs a real WordPress with ds-toolkit and ACF active and at least 3 images:
 *   wp eval-file tests/page-banner-sync-test.php
 * Drives the classic-editor save path (edit_post with the ACF nonce) on a
 * throwaway draft page, then deletes it. Exit code 1 on any failure.
 */
wp_set_current_user(1);
require_once ABSPATH.'wp-admin/includes/post.php';
$imgs = get_posts(['post_type'=>'attachment','post_mime_type'=>'image','numberposts'=>3,'fields'=>'ids']);
[$A,$B,$C] = $imgs;
$pid = wp_insert_post(['post_type'=>'page','post_title'=>'ds banner sync test','post_status'=>'draft']);
function save_like_editor($pid,$thumb,$bg){
  $_POST = $_REQUEST = ['post_ID'=>$pid,'post_type'=>'page','action'=>'editpost','post_title'=>'ds banner sync test',
    '_thumbnail_id'=>$thumb ? $thumb : -1,'_acf_nonce'=>wp_create_nonce('post'),'_acf_post_id'=>$pid,
    'acf'=>['field_dst_banner_image'=>$bg ? $bg : '']];
  edit_post();
  clean_post_cache($pid); wp_cache_flush();
  return [(int)get_post_thumbnail_id($pid),(int)get_post_meta($pid,'page_hero_banner_image',true)];
}
$GLOBALS["fail"]=0;
function check($label,$got,$want){ $ok=$got===$want; if(!$ok)$GLOBALS["fail"]++; printf("%s %-45s thumb=%d bg=%d (want %d/%d)\n",$ok?'PASS':'FAIL',$label,$got[0],$got[1],$want[0],$want[1]); }
check('set banner A only -> thumb follows', save_like_editor($pid,0,$A), [$A,$A]);
check('change featured to B (banner still A)', save_like_editor($pid,$B,$A), [$B,$B]);
check('remove featured (banner still B)', save_like_editor($pid,0,$B), [0,0]);
check('set featured C only -> banner follows', save_like_editor($pid,$C,0), [$C,$C]);
check('change banner to A (featured still C)', save_like_editor($pid,$C,$A), [$A,$A]);
check('remove banner (featured still A)', save_like_editor($pid,$A,0), [0,0]);
check('re-save with nothing changed', save_like_editor($pid,0,0), [0,0]);
// Classic editor: picking/removing the featured image saves over AJAX first, then Update posts.
save_like_editor($pid,$A,$A);
set_post_thumbnail($pid,$B);
check('AJAX pick featured B, then Update', save_like_editor($pid,$B,$A), [$B,$B]);
delete_post_thumbnail($pid);
check('AJAX remove featured, then Update', save_like_editor($pid,0,$B), [0,0]);
// State written outside the editor (import, WP-CLI) with no featured image: banner is kept.
update_post_meta($pid,'page_hero_banner_image',$A); delete_post_meta($pid,'_ds_banner_featured_touched');
check('banner only (written directly), plain Update', save_like_editor($pid,0,$A), [$A,$A]);
// ...and a later featured change still wins.
set_post_thumbnail($pid,$C);
check('then AJAX pick featured C, Update', save_like_editor($pid,$C,$A), [$C,$C]);
wp_delete_post($pid,true);
echo $GLOBALS["fail"] ? "FAILURES: {$GLOBALS["fail"]}\n" : "ALL PASS\n";
if ( $GLOBALS["fail"] ) { exit( 1 ); }
