<?php

/**
 * Fonctions utiles pour les wheels SPIP sur les paragraphes
 *
 * @SPIP\Textwheel\Wheel\SPIP\Fonctions
**/

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Callback fermer-para-mano
 * 
 * On refait le preg, à la main
 *
 * @param string $t
 * @return string
 */
function fermer_para_mano(&$t) {
	# match: ",<p (.*)<(/?)(STOP P|div|pre|ul|ol|li|blockquote|h[1-6r]|t(able|[rdh]|head|body|foot|extarea)|form|object|center|marquee|address|applet|iframe|figure|figcaption|d[ltd]|script|noscript|map|button|fieldset|style)\b,UimsS"
	# replace: "\n<p "+trim($1)+"</p>\n<$2$3"

	foreach (array('<p '=>"</p>\n",'<li'=>"<br-li/>") as $cut=>$close){
		if (strpos($t,$cut)!==false){
			foreach (explode($cut, $t) as $c => $p) {
				if ($c == 0)
					$t = $p;
				else {
					$pi = strtolower($p);
					if (preg_match(
					",</?(?:stop p|div|pre|ul|ol|li|blockquote|h[1-6r]|t(able|[rdh]|head|body|foot|extarea)|form|object|center|marquee|address|applet|iframe|figure|figcaption|d[ltd]|script|noscript|map|button|fieldset|style)\b,S",
					$pi, $r)) {
						$pos = strpos($pi, $r[0]);
						$t .= $cut . str_replace("\n", _AUTOBR."\n", ($close?rtrim(substr($p,0,$pos)):substr($p,0,$pos))). $close . substr($p,$pos);
					} else {
						$t .= $cut . $p;
					}
				}
			}
		}
	}

	if (strpos($t,"<br-li/>")!==false){
		$t = str_replace("<br-li/></li>","</li>",$t); // pour respecter les non-retour lignes avant </li>
		$t = str_replace("<br-li/><ul>","<ul>",$t); // pour respecter les non-retour lignes avant <ul>
		$t = str_replace("<br-li/>","\n",$t);
	}
	if (_AUTOBR) {
		$t = str_replace(_AUTOBR."\n"."<br", "\n<br", $t); #manque /i
		$reg = ',(<(p|br|li)\b[^>]*>\s*)'.preg_quote(_AUTOBR."\n", ',').",iS";
		$t = preg_replace($reg, '\1'."\n", $t);
	}

	return $t;
}
