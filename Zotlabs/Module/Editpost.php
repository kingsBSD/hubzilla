<?php
namespace Zotlabs\Module; /** @file */

require_once('include/acl_selectors.php');
require_once('include/crypto.php');
require_once('include/items.php');
require_once('include/taxonomy.php');


class Editpost extends \Zotlabs\Web\Controller {

	function get() {
	
		$o = '';
	
		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}
	
		$post_id = ((argc() > 1) ? intval(argv(1)) : 0);
	
		if(! $post_id) {
			notice( t('Item not found') . EOL);
			return;
		}
	
		$itm = q("SELECT * FROM `item` WHERE `id` = %d AND ( owner_xchan = '%s' OR author_xchan = '%s' ) LIMIT 1",
			intval($post_id),
			dbesc(get_observer_hash()),
			dbesc(get_observer_hash())
		);
	
		if(! count($itm)) {
			notice( t('Item is not editable') . EOL);
			return;
		}
	
		if($itm[0]['resource_type'] === 'event' && $itm[0]['resource_id']) {
			goaway(z_root() . '/events/' . $itm[0]['resource_id'] . '?expandform=1');
		}
	
	
		$owner_uid = $itm[0]['uid'];
	
	
		$plaintext = true;
	//	if(feature_enabled(local_channel(),'richtext'))
	//		$plaintext = false;
	
		$channel = \App::get_channel();
	
		\App::$page['htmlhead'] .= replace_macros(get_markup_template('jot-header.tpl'), array(
			'$baseurl' => z_root(),
			'$editselect' =>  (($plaintext) ? 'none' : '/(profile-jot-text|prvmail-text)/'),
			'$pretext' => '',
			'$ispublic' => '&nbsp;', // t('Visible to <strong>everybody</strong>'),
			'$geotag' => $geotag,
			'$nickname' => $channel['channel_address'],
			'$expireswhen' => t('Expires YYYY-MM-DD HH:MM'),
			'$confirmdelete' => t('Delete item?'),
			'$editor_autocomplete'=> true,
			'$bbco_autocomplete'=> 'bbcode'
		));
	
		if(intval($itm[0]['item_obscured'])) {
			$key = get_config('system','prvkey');
			if($itm[0]['title'])
				$itm[0]['title'] = crypto_unencapsulate(json_decode_plus($itm[0]['title']),$key);
			if($itm[0]['body'])
				$itm[0]['body'] = crypto_unencapsulate(json_decode_plus($itm[0]['body']),$key);
		}
	
		$tpl = get_markup_template("jot.tpl");
			
		$jotplugins = '';
		$jotnets = '';
	
		call_hooks('jot_tool', $jotplugins);
		call_hooks('jot_networks', $jotnets);
	
		//$tpl = replace_macros($tpl,array('$jotplugins' => $jotplugins));	
	
		$voting = feature_enabled($owner_uid,'consensus_tools');	
	
		$category = '';
		$catsenabled = ((feature_enabled($owner_uid,'categories')) ? 'categories' : '');
	
		if ($catsenabled){
		        $itm = fetch_post_tags($itm);
	
	                $cats = get_terms_oftype($itm[0]['term'], TERM_CATEGORY);
	
		        foreach ($cats as $cat) {
		                if (strlen($category))
		                        $category .= ', ';
		                $category .= $cat['term'];
		        }
		}
	
		if($itm[0]['attach']) {
			$j = json_decode($itm[0]['attach'],true);
			if($j) {
				foreach($j as $jj) {
					$itm[0]['body'] .= "\n" . '[attachment]' . basename($jj['href']) . ',' . $jj['revision'] . '[/attachment]' . "\n";
				}
			}
		}
	
		$cipher = get_pconfig(\App::$profile['profile_uid'],'system','default_cipher');
		if(! $cipher)
			$cipher = 'aes256';
	
	
		$editor = replace_macros($tpl,array(
			'$return_path' => $_SESSION['return_url'],
			'$action' => 'item',
			'$share' => t('Edit'),
			'$bold' => t('Bold'),
			'$italic' => t('Italic'),
			'$underline' => t('Underline'),
			'$quote' => t('Quote'),
			'$code' => t('Code'),
			'$upload' => t('Upload photo'),
			'$attach' => t('Attach file'),
			'$weblink' => t('Insert web link'),
			'$youtube' => t('Insert YouTube video'),
			'$video' => t('Insert Vorbis [.ogg] video'),
			'$audio' => t('Insert Vorbis [.ogg] audio'),
			'$setloc' => t('Set your location'),
			'$noloc' => t('Clear browser location'),
			'$voting' => t('Toggle voting'),
			'$feature_voting' => $voting,
			'$consensus' => intval($itm[0]['item_consensus']),
			'$wait' => t('Please wait'),
			'$permset' => t('Permission settings'),
			'$ptyp' => $itm[0]['obj_type'],
			'$content' => undo_post_tagging($itm[0]['body']),
			'$post_id' => $post_id,
			'$parent' => (($itm[0]['parent'] != $itm[0]['id']) ? $itm[0]['parent'] : ''),
			'$baseurl' => z_root(),
			'$defloc' => $channel['channel_location'],
			'$visitor' => false,
			'$public' => t('Public post'),
			'$jotnets' => $jotnets,
			'$title' => htmlspecialchars($itm[0]['title'],ENT_COMPAT,'UTF-8'),
			'$placeholdertitle' => t('Title (optional)'),
			'$category' => $category,
			'$placeholdercategory' => t('Categories (optional, comma-separated list)'),
			'$emtitle' => t('Example: bob@example.com, mary@example.com'),
			'$lockstate' => $lockstate,
			'$acl' => '', 
			'$bang' => '',
			'$profile_uid' => $owner_uid,
			'$preview' => t('Preview'),
			'$jotplugins' => $jotplugins,
			'$sourceapp' => t(\App::$sourcename),
			'$catsenabled' => $catsenabled,
			'$defexpire' => datetime_convert('UTC', date_default_timezone_get(),$itm[0]['expires']),
			'$feature_expire' => ((feature_enabled(\App::$profile['profile_uid'],'content_expire') && (! $webpage)) ? true : false),
			'$expires' => t('Set expiration date'),
			'$feature_encrypt' => ((feature_enabled(\App::$profile['profile_uid'],'content_encrypt') && (! $webpage)) ? true : false),
			'$encrypt' => t('Encrypt text'),
			'$cipher' => $cipher,
			'$expiryModalOK' => t('OK'),
			'$expiryModalCANCEL' => t('Cancel'),
			'$bbcode' => true
		));
	
		$o .= replace_macros(get_markup_template('edpost_head.tpl'), array(
			'$title' => t('Edit post'),
			'$editor' => $editor
		));
	
		return $o;
	
	}
	
	
	
}