<?php
class Basics extends CI_Model {

	function __construct(){
		parent::__construct();
	}
}

function within_one_day($day_one, $day_two){
	return abs($day_one - $day_two) < (60 * 60 * 24) ? TRUE : FALSE;
}

function states_array(){
	return array('al'=>'alabama','ak'=>'alaska','az'=>'arizona','ar'=>'arkansas','ca'=>'california','co'=>'colorado','ct'=>'connecticut',
		'de'=>'delaware','dc'=>'district of columbia','fl'=>'florida','ga'=>'georgia','hi'=>'hawaii','id'=>'idaho','il'=>'illinois','in'=>'indiana',
		'ia'=>'iowa','ks'=>'kansas','ky'=>'kentucky','la'=>'louisiana','me'=>'maine','md'=>'maryland','ma'=>'massachusetts','mi'=>'michigan','mn'=>'minnesota',
		'ms'=>'mississippi','mo'=>'missouri','mt'=>'montana','ne'=>'nebraska','nv'=>'nevada','nh'=>'new hampshire','nj'=>'new jersey','nm'=>'new mexico',
		'ny'=>'new york','nc'=>'north carolina','nd'=>'north dakota','oh'=>'ohio','ok'=>'oklahoma','or'=>'oregon','pa'=>'pennsylvania','ri'=>'rhode island',
		'sc'=>'south carolina','sd'=>'south dakota','tn'=>'tennessee','tx'=>'texas','ut'=>'utah','vt'=>'vermont','va'=>'virginia','wa'=>'washington',
		'wv'=>'west virginia','wi'=>'wisconsin','wy'=>'wyoming');
}

function strip_inner_tags($string, $tag=FALSE){
	if($tag) :
		return strip_tags(preg_replace('/\<'.$tag.'(.*?)\>(.*?)\<\/'.$tag.'\>/', '', clean_word($string)));
		// return strip_tags(preg_replace('/\<'.$tag.'\>(.*?)\<\/'.$tag.'\>/', '', remove_tag_attributes(clean_word($string))));
		// return remove_tag_attributes(clean_word($string));
	else :
		return strip_tags(preg_replace('/\<(.+?)\>(.+?)\<\/(.+?)\>/', '', clean_word($string)));
	endif;
}

function remove_tag_attributes($string){
	return preg_replace('/\<([a-zA-Z0-9]+)(.*?)\>/', '<$1>', $string);
}

function remove_tag_attributes_a_tags($string){
	return preg_replace('/\<([b-zB-Z0-9]+)(.*?)\>/', '<$1>', $string);
}

function strip_outter_tags($string, $tag=FALSE){
	if($tag) :
	return clean_word(strip_tags(preg_replace('/(.*?)\<'.$tag.'(.*?)\>(.+?)\<\/'.$tag.'\>(.*)/', '$3', clean_word($string))));
	else :
	return clean_word(strip_tags(preg_replace('/(.*?)\<(.*?)\>(.+?)\<(.*?)\>(.*)/', '$3', clean_word($string))));
	endif;
}

function remove_tags($string){
	return preg_replace('/\<(.+?)\>/', '', $string);
}

function remove_brs($string){
	return preg_replace('/\<br(.*?)\>/', '', $string);
}

function normalize_brs($string){
	return preg_replace('/\<br(.*?)\>/', '<br>', $string);
}

function make_array($mixed){
	if(is_array($mixed)) : 
		return $mixed;
	endif;
	return array($mixed);
}

function mm_dd_yy($s, $year='19'){
	if(strlen($s) < 8) : return NULL; endif;
	return strtotime($s[0].$s[1].'/'.$s[3].$s[4].'/'.$year.$s[6].$s[7]);
}

function mmddccyy($s){
	if(strlen($s) < 8) : return NULL; endif;
	return strtotime($s[0].$s[1].'/'.$s[2].$s[3].'/'.$s[4].$s[5].$s[6].$s[7]);
}

function ccyymmdd($s){
	if(strlen($s) < 8) : return NULL; endif;
	return strtotime($s[4].$s[5].'/'.$s[6].$s[7].'/'.$s[0].$s[1].$s[2].$s[3]);
}

function strtotime_dashes($s){
	return strtotime(str_replace('-', '/', $s));
}

function query_result_to_single_array($result, $field){
	$return = array();
	foreach($result as $row_num => $row) : 
		$return[$row_num] = $row->$field;
	endforeach;
	return $return;
}

function combine_fields(&$values, $fields){
	$return = "";
	foreach($fields as $field) : 
		$return .= $values[$field].' ';
	endforeach;
	return clean_word($return);
}

function combine_values($values, $attr="id", $delimiter=","){
	$return = "";
	foreach($values as $value) : 
		$return .= $value->$attr.$delimiter;
	endforeach;
	return trim($return, ',');
}

function array_to_string($mixed){
	return is_array($mixed) ? clean_word(implode(' ',$mixed)) : clean_word($mixed);
}

function combine_ids($ids){
	$return = "";
	foreach($ids as $id) : 
		$return .= $id->id.",";
	endforeach;
	return trim($return, ',');
}

function str_replace_once($needle , $replace , $haystack){
	$pos = strpos($haystack, $needle); 
	if ($pos === false)
		return $haystack;
	return substr_replace($haystack, $replace, $pos, strlen($needle)); 
}

function ucstring($words){ // Ad cases for roman numerals
	$roman_numerals_lower = array(' ii', ' iii');
	$roman_numerals_upper = array(' II', ' III');
	return str_ireplace($roman_numerals_lower, $roman_numerals_upper, preg_replace_callback('/(\bmc|\b)\p{Ll}/', '_uc_string_callback', strtolower(clean_word($words))));
}

function _uc_string_callback($match) {
	return ucfirst(uclast($match[0]));
}

function uclast($str) {
	$str[strlen($str)-1] = strtoupper($str[strlen($str)-1]);
	return $str;
}

function make_slug($string, $delimiter='_'){
	return strtolower(trim(preg_replace('/[\W_]+/', $delimiter, html_entity_decode(str_replace(array('&nbsp;','&nbsp','&bull;','&bull'), ' ', $string))), $delimiter));
}

function clean_word($word){
	return trim(preg_replace('/\s+/', ' ', preg_replace('/[^(\x20-\x7F)]+/', ' ', html_entity_decode(str_replace('&rsquo;', "'", str_replace(array('&nbsp;','&nbsp','&bull;','&bull'), ' ', $word)), ENT_QUOTES))));
}
	
function excel_time_to_unix($excel_time){
	return strtotime(date("m/d/Y", ($excel_time * 86400) - 2209075200));
}

function state_full_to_abbr($string){
	$short_to_state = array_flip(state_array());
	return isset($short_to_state[make_slug($string, ' ')]) ? strtoupper($short_to_state[make_slug($string, ' ')]) : $string;
}

function state_abbr_to_full($string){
	$short_to_state = state_array();
	return isset($short_to_state[make_slug($string, ' ')]) ? strtoupper($short_to_state[make_slug($string, ' ')]) : $string;
}

function state_array(){
	return array('al'=>'alabama','ak'=>'alaska','az'=>'arizona','ar'=>'arkansas','ca'=>'california','co'=>'colorado','ct'=>'connecticut',
		'de'=>'delaware','dc'=>'district of columbia','fl'=>'florida','ga'=>'georgia','hi'=>'hawaii','id'=>'idaho','il'=>'illinois','in'=>'indiana',
		'ia'=>'iowa','ks'=>'kansas','ky'=>'kentucky','la'=>'louisiana','me'=>'maine','md'=>'maryland','ma'=>'massachusetts','mi'=>'michigan','mn'=>'minnesota',
		'ms'=>'mississippi','mo'=>'missouri','mt'=>'montana','ne'=>'nebraska','nv'=>'nevada','nh'=>'new hampshire','nj'=>'new jersey','nm'=>'new mexico',
		'ny'=>'new york','nc'=>'north carolina','nd'=>'north dakota','oh'=>'ohio','ok'=>'oklahoma','or'=>'oregon','pa'=>'pennsylvania','ri'=>'rhode island',
		'sc'=>'south carolina','sd'=>'south dakota','tn'=>'tennessee','tx'=>'texas','ut'=>'utah','vt'=>'vermont','wa'=>'washington','wv'=>'west virginia',
		'va'=>'virginia','wi'=>'wisconsin','wy'=>'wyoming');
}
	
function _get_two_letter_combos(){
	$letter_combos = array(
		'AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ',
		'BA','BE','BH','BI','BL','BO','BR','BU','BY',
		'CA','CE','CH','CI','CL','CO','CR','CS','CU','CY','CZ',
		'DA','DE','DH','DI','DO','DR','DU','DW','DY','DZ',
		'EA','EB','EC','ED','EF','EG','EH','EI','EK','EL','EM','EN','EP','ER','ES','ET','EU','EV','EW','EX','EY','EZ',
		'FA','FE','FI','FL','FO','FR','FU','FY',
		'GA','GE','GH','GI','GL','GO','GR','GU','GW','GY',
		'HA','HE','HI','HO','HR','HU','HY',
		'IA','IB','IC','ID','IG','IK','IL','IM','IN','IO','IP','IR','IS','IT','IV','IZ',
		'JA','JE','JI','JO','JR','JU',
		'KA','KE','KH','KI','KJ','KL','KN','KO','KR','KU','KW','KY',
		'LA','LE','LH','LI','LL','LO','LU','LY',
		'MA','MB','MC','ME','MI','MO','MR','MU','MY',
		'NA','ND','NE','NG','NI','NO','NU','NW','NY',
		'OA','OB','OC','OD','OE','OF','OG','OH','OJ','OK','OL','OM','ON','OP','OQ','OR','OS','OT','OU','OV','OW','OX','OY','OZ',
		'PA','PE','PF','PH','PI','PL','PO','PR','PU','PY',
		'QA','QU',
		'RA','RE','RH','RI','RO','RU','RY',
		'SA','SC','SE','SG','SH','SI','SK','SL','SM','SN','SO','SP','SQ','SR','ST','SU','SV','SW','SY','SZ',
		'TA','TE','TH','TI','TO','TR','TS','TU','TW','TY','TZ',
		'UB','UC','UD','UG','UH','UL','UM','UN','UP','UR','US','UT','UZ',
		'VA','VE','VI','VL','VO','VR','VU',
		'WA','WE','WH','WI','WO','WR','WU','WY',
		'XA','XI','XU',
		'YA','YB','YE','YI','YO','YS','YU',
		'ZA','ZE','ZH','ZI','ZO','ZU','ZW','ZY'
	);
	return $letter_combos;
}

function wait($seconds){
	for(; $seconds >= 0; --$seconds) :
		sleep(1);
		echo format_seconds($seconds)."\r";
	endfor;
	echo "\n";
}

function format_seconds($seconds){
	$hours = floor($seconds / (60 * 60));
	$minutes = floor(($seconds - ($hours * 60 * 60)) / (60));
	$seconds = $seconds % 60;
	return str_pad($hours, 2, '0', STR_PAD_LEFT).":".str_pad($minutes, 2, '0', STR_PAD_LEFT).":".str_pad($seconds, 2, '0', STR_PAD_LEFT);
}

function cli_table($string){
	return str_pad($string, 10, ' ', STR_PAD_RIGHT);
}

function debug($array=NULL){
	if(isset($array)) : 
		die('<pre>'.print_r($array,TRUE));
	else :
		$ci =& get_instance();
		@die('<pre>'.print_r($ci->domain, TRUE).print_r($ci->user, TRUE).print_r($ci->geo, TRUE).print_r($ci->copy, TRUE).print_r($ci->data, TRUE).print_r($ci->session->userdata, TRUE).print_r($ci->db->queries, TRUE).print_r($ci->db->query_times, TRUE));
	endif;
}

?>