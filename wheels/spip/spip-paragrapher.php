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

	foreach (explode('<p ', $t) as $c => $p) {
		if ($c == 0)
			$t = $p;
		else {
			$pi = strtolower($p);
			if (preg_match(
			",</?(?:stop p|div|pre|ul|ol|li|blockquote|h[1-6r]|t(able|[rdh]|head|body|foot|extarea)|form|object|center|marquee|address|applet|iframe|figure|figcaption|d[ltd]|script|noscript|map|button|fieldset|style)\b,S",
			$pi, $r)) {
				$pos = strpos($pi, $r[0]);
				$t .= "<p " . str_replace("\n", _AUTOBR."\n", rtrim(substr($p,0,$pos)))."</p>\n".substr($p,$pos);
			} else {
				$t .= '<p '.$p;
			}
		}
	}

	if (_AUTOBR) {
		$t = str_replace(_AUTOBR."\n"."<br", "<br", $t); #manque /i
		$reg = ',(<(p|br)\b[^>]*>\s*)'.preg_quote(_AUTOBR."\n", ',').",iS";

		$t = preg_replace($reg, '\1'."\n", $t);
	}

	return $t;
}
