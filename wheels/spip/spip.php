<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Fonctions utiles pour les wheels SPIP
 *
 * @SPIP\Textwheel\Wheel\SPIP\Fonctions
**/

include_spip('inc/texte');

/**
 * Callback pour la puce qui est définissable/surchargeable
 * @return string
 *     Code HTML d'une puce
 */
function replace_puce(){
	static $puce;
	if (!isset($puce))
		$puce = "\n<br />".definir_puce()."&nbsp;";
	return $puce;
}

/**
 * Callback pour les Abbr
 *
 * @example
 *     ```
 *     [ABBR|abbrevation]
 *     [ABBR|abbrevation{lang}]
 *     ```
 * @param array $m
 * @return string
 *     Code HTML d'une abréviation
 */
function inserer_abbr($m){
	$title = attribut_html($m[2]);
	$lang = (isset($m[3])?" lang=\"".$m[3]."\"":"");
	return "<abbr title=\"$title\"$lang>".$m[1]."</abbr>";
}
