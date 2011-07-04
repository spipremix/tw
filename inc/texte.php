<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2011                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/texte_mini');
include_spip('inc/lien');

include_spip('inc/textwheel');

// Avec cette surcharge, cette globale n'est plus définie, et du coup ça plante dans les plugins qui font un foreach dessus comme ZPIP
$GLOBALS['spip_raccourcis_typo'] = array();
if (!isset($GLOBALS['toujours_paragrapher']))
	$GLOBALS['toujours_paragrapher'] = true;

// class_spip : savoir si on veut class="spip" sur p i strong & li
// class_spip_plus : class="spip" sur les ul ol h3 hr quote table...
// la difference c'est que des css specifiques existent pour les seconds
//
if (!isset($GLOBALS['class_spip']))
	$GLOBALS['class_spip'] = '';
if (!isset($GLOBALS['class_spip_plus']))
	$GLOBALS['class_spip_plus'] = ' class="spip"';


//
// echapper les < script ...
//
function echappe_js($t) {
	static $wheel = null;

	if (!isset($wheel))
		$wheel = new TextWheel(
			SPIPTextWheelRuleset::loader($GLOBALS['spip_wheels']['echappe_js'])
		);

	return $wheel->text($t);
}

//
// paragagrapher seulement
//
function paragrapher($t, $toujours_paragrapher = null) {
	static $wheel = array();
	if (is_null($toujours_paragrapher))
		$toujours_paragrapher = $GLOBALS['toujours_paragrapher'];

	if (!isset($wheel[$toujours_paragrapher])) {
		$ruleset = SPIPTextWheelRuleset::loader($GLOBALS['spip_wheels']['paragrapher']);
		if (!$toujours_paragrapher
		  AND $rule=$ruleset->getRule('toujours-paragrapher')) {
			$rule->disabled = true;
			$ruleset->addRules(array('toujours-paragrapher'=>$rule));
		}
		$wheel[$toujours_paragrapher] = new TextWheel($ruleset);
	}

	return $wheel[$toujours_paragrapher]->text($t);
}


// Securite : empecher l'execution de code PHP, en le transformant en joli code
// dans l'espace prive, cette fonction est aussi appelee par propre et typo
// si elles sont appelees en direct
// il ne faut pas desactiver globalement la fonction dans l'espace prive car elle protege
// aussi les balises des squelettes qui ne passent pas forcement par propre ou typo apres
// http://doc.spip.org/@interdire_scripts
function interdire_scripts($arg) {
	// on memorise le resultat sur les arguments non triviaux
	static $dejavu = array();
	static $wheel = null;

	// Attention, si ce n'est pas une chaine, laisser intact
	if (!$arg OR !is_string($arg) OR !strstr($arg, '<')) return $arg; 

	if (isset($dejavu[$GLOBALS['filtrer_javascript']][$arg])) return $dejavu[$GLOBALS['filtrer_javascript']][$arg];

	if (!isset($wheel)){
		$ruleset = SPIPTextWheelRuleset::loader(
			$GLOBALS['spip_wheels']['interdire_scripts']
		);
		// Pour le js, trois modes : parano (-1), prive (0), ok (1)
		// desactiver la regle echappe-js si besoin
		if ($GLOBALS['filtrer_javascript']==1
			OR ($GLOBALS['filtrer_javascript']==0 AND !test_espace_prive()))
			$ruleset->addRules (array('securite-js'=>array('disabled'=>true)));
		$wheel = new TextWheel($ruleset);
	}

	$t = $wheel->text($arg);

	// Reinserer les echappements des modeles
	if (defined('_PROTEGE_JS_MODELES'))
		$t = echappe_retour($t,"javascript"._PROTEGE_JS_MODELES);
	if (defined('_PROTEGE_PHP_MODELES'))
		$t = echappe_retour($t,"php"._PROTEGE_PHP_MODELES);

	return $dejavu[$GLOBALS['filtrer_javascript']][$arg] = $t;
}


// Typographie generale
// avec protection prealable des balises HTML et SPIP

// http://doc.spip.org/@typo
function typo($letexte, $echapper=true, $connect=null) {
	// Plus vite !
	if (!$letexte) return $letexte;

	// les appels directs a cette fonction depuis le php de l'espace
	// prive etant historiquement ecrit sans argment $connect
	// on utilise la presence de celui-ci pour distinguer les cas
	// ou il faut passer interdire_script explicitement
	// les appels dans les squelettes (de l'espace prive) fournissant un $connect
	// ne seront pas perturbes
	$interdire_script = false;
	if (is_null($connect)){
		$connect = '';
		$interdire_script = true;
	}

	// Echapper les codes <html> etc
	if ($echapper)
		$letexte = echappe_html($letexte, 'TYPO');

	//
	// Installer les modeles, notamment images et documents ;
	//
	// NOTE : propre() ne passe pas par ici mais directement par corriger_typo
	// cf. inc/lien

	$letexte = traiter_modeles($mem = $letexte, false, $echapper ? 'TYPO' : '', $connect);
	if ($letexte != $mem) $echapper = true;
	unset($mem);

	$letexte = corriger_typo($letexte);
	$letexte = echapper_faux_tags($letexte);

	// reintegrer les echappements
	if ($echapper)
		$letexte = echappe_retour($letexte, 'TYPO');

	// Dans les appels directs hors squelette, securiser ici aussi
	if ($interdire_script)
		$letexte = interdire_scripts($letexte);

	return $letexte;
}

// Correcteur typographique

define('_TYPO_PROTEGER', "!':;?~%-");
define('_TYPO_PROTECTEUR', "\x1\x2\x3\x4\x5\x6\x7\x8");

define('_TYPO_BALISE', ",</?[a-z!][^<>]*[".preg_quote(_TYPO_PROTEGER)."][^<>]*>,imsS");

// http://doc.spip.org/@corriger_typo
function corriger_typo($t, $lang='') {
	static $typographie = array();
	// Plus vite !
	if (!$t) return $t;

	$t = pipeline('pre_typo', $t);

	// Caracteres de controle "illegaux"
	$t = corriger_caracteres($t);

	// Proteger les caracteres typographiques a l'interieur des tags html
	if (preg_match_all(_TYPO_BALISE, $t, $regs, PREG_SET_ORDER)) {
		foreach ($regs as $reg) {
			$insert = $reg[0];
			// hack: on transforme les caracteres a proteger en les remplacant
			// par des caracteres "illegaux". (cf corriger_caracteres())
			$insert = strtr($insert, _TYPO_PROTEGER, _TYPO_PROTECTEUR);
			$t = str_replace($reg[0], $insert, $t);
		}
	}

	// trouver les blocs multi et les traiter a part
	$t = extraire_multi($e = $t, $lang, true);
	$e = ($e === $t);

	// Charger & appliquer les fonctions de typographie
	$idxl = "$lang:" . (isset($GLOBALS['lang_objet'])? $GLOBALS['lang_objet']: $GLOBALS['spip_lang']);
	if (!isset($typographie[$idxl]))
		$typographie[$idxl] = charger_fonction(lang_typo($lang), 'typographie');
	$t = $typographie[$idxl]($t);

	// Les citations en une autre langue, s'il y a lieu
	if (!$e) $t = echappe_retour($t, 'multi');

	// Retablir les caracteres proteges
	$t = strtr($t, _TYPO_PROTECTEUR, _TYPO_PROTEGER);

	// pipeline
	$t = pipeline('post_typo', $t);

	# un message pour abs_url - on est passe en mode texte
	$GLOBALS['mode_abs_url'] = 'texte';

	return $t;
}


//
// Tableaux
//

define('_RACCOURCI_TH_SPAN', '\s*(:?{{[^{}]+}}\s*)?|<');

// http://doc.spip.org/@traiter_tableau
function traiter_tableau($bloc) {
	// id "unique" pour les id du tableau
	$tabid = substr(md5($bloc),0,4);

	// Decouper le tableau en lignes
	preg_match_all(',([|].*)[|]\n,UmsS', $bloc, $regs, PREG_PATTERN_ORDER);
	$lignes = array();
	$debut_table = $summary = '';
	$l = 0;
	$numeric = true;

	// Traiter chaque ligne
	$reg_line1 = ',^(\|(' . _RACCOURCI_TH_SPAN . '))+$,sS';
	$reg_line_all = ',^('  . _RACCOURCI_TH_SPAN . ')$,sS';
	$hc = $hl = array();
	foreach ($regs[1] as $ligne) {
		$l ++;

		// Gestion de la premiere ligne :
		if ($l == 1) {
		// - <caption> et summary dans la premiere ligne :
		//   || caption | summary || (|summary est optionnel)
			if (preg_match(',^\|\|([^|]*)(\|(.*))?$,sS', rtrim($ligne,'|'), $cap)) {
				$l = 0;
				if ($caption = trim($cap[1]))
					$debut_table .= "<caption>".$caption."</caption>\n";
				$summary = ' summary="'.entites_html(trim($cap[3])).'"';
			}
		// - <thead> sous la forme |{{titre}}|{{titre}}|
		//   Attention thead oblige a avoir tbody
			else if (preg_match($reg_line1,	$ligne, $thead)) {
			  	preg_match_all('/\|([^|]*)/S', $ligne, $cols);
				$ligne='';$cols= $cols[1];
				$colspan=1;
				for($c=count($cols)-1; $c>=0; $c--) {
					$attr='';
					if($cols[$c]=='<') {
					  $colspan++;
					} else {
					  if($colspan>1) {
						$attr= " colspan='$colspan'";
						$colspan=1;
					  }
					  // inutile de garder le strong qui n'a servi que de marqueur 
					  $cols[$c] = str_replace(array('{','}'), '', $cols[$c]);
					  $ligne= "<th id='id{$tabid}_c$c'$attr>$cols[$c]</th>$ligne";
						$hc[$c] = "id{$tabid}_c$c"; // pour mettre dans les headers des td
					}
				}

				$debut_table .= "<thead><tr class='row_first'>".
					$ligne."</tr></thead>\n";
				$l = 0;
			}
		}

		// Sinon ligne normale
		if ($l) {
			// Gerer les listes a puce dans les cellules
			if (strpos($ligne,"\n-*")!==false OR strpos($ligne,"\n-#")!==false)
				$ligne = traiter_listes($ligne);

			// Pas de paragraphes dans les cellules
			$ligne = preg_replace("/\n{2,}/", "<br /><br />\n", $ligne);

			// tout mettre dans un tableau 2d
			preg_match_all('/\|([^|]*)/S', $ligne, $cols);
			$lignes[]= $cols[1];
		}
	}

	// maintenant qu'on a toutes les cellules
	// on prepare une liste de rowspan par defaut, a partir
	// du nombre de colonnes dans la premiere ligne.
	// Reperer egalement les colonnes numeriques pour les cadrer a droite
	$rowspans = $numeric = array();
	$n = count($lignes[0]);
	$k = count($lignes);
	// distinguer les colonnes numeriques a point ou a virgule,
	// pour les alignements eventuels sur "," ou "."
	$numeric_class = array('.'=>'point',','=>'virgule');
	for($i=0;$i<$n;$i++) {
	  $align = true;
	  for ($j=0;$j<$k;$j++) {
		  $rowspans[$j][$i] = 1;
			if ($align AND preg_match('/^\d+([.,]?)\d*$/', trim($lignes[$j][$i]), $r)){
				if ($r[1])
					$align = $r[1];
			}
			else
				$align = '';
	  }
	  $numeric[$i] = $align ? (" class='numeric ".$numeric_class[$align]."'") : '';
	}
	for ($j=0;$j<$k;$j++) {
		if (preg_match($reg_line_all, $lignes[$j][0])) {
			$hl[$j] = "id{$tabid}_l$j"; // pour mettre dans les headers des td
		}
		else
			unset($hl[0]);
	}
	if (!isset($hl[0]))
		$hl = array(); // toute la colonne ou rien

	// et on parcourt le tableau a l'envers pour ramasser les
	// colspan et rowspan en passant
	$html = '';

	for($l=count($lignes)-1; $l>=0; $l--) {
		$cols= $lignes[$l];
		$colspan=1;
		$ligne='';

		for($c=count($cols)-1; $c>=0; $c--) {
			$attr= $numeric[$c]; 
			$cell = trim($cols[$c]);
			if($cell=='<') {
			  $colspan++;

			} elseif($cell=='^') {
			  $rowspans[$l-1][$c]+=$rowspans[$l][$c];

			} else {
			  if($colspan>1) {
				$attr .= " colspan='$colspan'";
				$colspan=1;
			  }
			  if(($x=$rowspans[$l][$c])>1) {
				$attr.= " rowspan='$x'";
			  }
			  $b = ($c==0 AND isset($hl[$l]))?'th':'td';
				$h = (isset($hc[$c])?$hc[$c]:'').' '.(($b=='td' AND isset($hl[$l]))?$hl[$l]:'');
				if ($h=trim($h))
					$attr.=" headers='$h'";
				// inutile de garder le strong qui n'a servi que de marqueur
				if ($b=='th') {
					$attr.=" id='".$hl[$l]."'";
					$cols[$c] = str_replace(array('{','}'), '', $cols[$c]);
				}
			  $ligne= "\n<$b".$attr.'>'.$cols[$c]."</$b>".$ligne;
			}
		}

		// ligne complete
		$class = alterner($l+1, 'odd', 'even');
		$html = "<tr class='row_$class $class'>$ligne</tr>\n$html";
	}
	return "\n\n<table".$GLOBALS['class_spip_plus'].$summary.">\n"
		. $debut_table
		. "<tbody>\n"
		. $html
		. "</tbody>\n"
		. "</table>\n\n";
}


//
// Traitement des listes (merci a Michael Parienti)
//
// http://doc.spip.org/@traiter_listes
function traiter_listes ($texte) {
	global $class_spip, $class_spip_plus;
	$parags = preg_split(",\n[[:space:]]*\n,S", $texte);
	$texte ='';

	// chaque paragraphe est traite a part
	while (list(,$para) = each($parags)) {
		$niveau = 0;
		$pile_li = $pile_type = array();
		$lignes = explode("\n-", "\n" . $para);

		// ne pas toucher a la premiere ligne
		list(,$debut) = each($lignes);
		$texte .= $debut;

		// chaque item a sa profondeur = nb d'etoiles
		$type ='';
		while (list(,$item) = each($lignes)) {
			preg_match(",^([*]*|[#]*)([^*#].*)$,sS", $item, $regs);
			$profond = strlen($regs[1]);

			if ($profond > 0) {
				$ajout='';

				// changement de type de liste au meme niveau : il faut
				// descendre un niveau plus bas, fermer ce niveau, et
				// remonter
				$nouv_type = (substr($item,0,1) == '*') ? 'ul' : 'ol';
				$change_type = ($type AND ($type <> $nouv_type) AND ($profond == $niveau)) ? 1 : 0;
				$type = $nouv_type;

				// d'abord traiter les descentes
				while ($niveau > $profond - $change_type) {
					$ajout .= $pile_li[$niveau];
					$ajout .= $pile_type[$niveau];
					if (!$change_type)
						unset ($pile_li[$niveau]);
					$niveau --;
				}

				// puis les identites (y compris en fin de descente)
				if ($niveau == $profond && !$change_type) {
					$ajout .= $pile_li[$niveau];
				}

				// puis les montees (y compris apres une descente un cran trop bas)
				while ($niveau < $profond) {
					if ($niveau == 0) $ajout .= "\n\n";
					elseif (!isset($pile_li[$niveau])) {
						$ajout .= "<li$class_spip>";
						$pile_li[$niveau] = "</li>";
					}
					$niveau ++;
					$ajout .= "<$type$class_spip_plus>";
					$pile_type[$niveau] = "</$type>";
				}

				$ajout .= "<li$class_spip>";
				$pile_li[$profond] = "</li>";
			}
			else {
				$ajout = "\n-";	// puce normale ou <hr>
			}

			$texte .= $ajout . $regs[2];
		}

		// retour sur terre
		$ajout = '';
		while ($niveau > 0) {
			$ajout .= $pile_li[$niveau];
			$ajout .= $pile_type[$niveau];
			$niveau --;
		}
		$texte .= $ajout;

		// paragraphe
		$texte .= "\n\n";
	}

	// sucrer les deux derniers \n
	return substr($texte, 0, -2);
}


// Ces deux constantes permettent de proteger certains caracteres
// en les remplacanat par des caracteres "illegaux". (cf corriger_caracteres)

define('_RACCOURCI_PROTEGER', "{}_-");
define('_RACCOURCI_PROTECTEUR', "\x1\x2\x3\x4");

define('_RACCOURCI_BALISE', ",</?[a-z!][^<>]*[".preg_quote(_RACCOURCI_PROTEGER)."][^<>]*>,imsS");

// Nettoie un texte, traite les raccourcis autre qu'URL, la typo, etc.

// mais d'abord, une callback de reconfiguration des raccourcis
// a partir de globales (est-ce old-style ? on conserve quand meme
// par souci de compat ascendante)
function personnaliser_raccourcis(&$ruleset){
	if (isset($GLOBALS['debut_intertitre']) AND $rule=$ruleset->getRule('intertitres')){
		$rule->replace[0] = preg_replace(',<[^>]*>,Uims',$GLOBALS['debut_intertitre'],$rule->replace[0]);
		$rule->replace[1] = preg_replace(',<[^>]*>,Uims',$GLOBALS['fin_intertitre'],$rule->replace[1]);
		$ruleset->addRules(array('intertitres'=>$rule));
	}
	if (isset($GLOBALS['debut_gras']) AND $rule=$ruleset->getRule('gras')){
		$rule->replace[0] = preg_replace(',<[^>]*>,Uims',$GLOBALS['debut_gras'],$rule->replace[0]);
		$rule->replace[1] = preg_replace(',<[^>]*>,Uims',$GLOBALS['fin_gras'],$rule->replace[1]);
		$ruleset->addRules(array('gras'=>$rule));
	}
	if (isset($GLOBALS['debut_italique']) AND $rule=$ruleset->getRule('italiques')){
		$rule->replace[0] = preg_replace(',<[^>]*>,Uims',$GLOBALS['debut_italique'],$rule->replace[0]);
		$rule->replace[1] = preg_replace(',<[^>]*>,Uims',$GLOBALS['fin_italique'],$rule->replace[1]);
		$ruleset->addRules(array('italiques'=>$rule));
	}
	if (isset($GLOBALS['ligne_horizontale']) AND $rule=$ruleset->getRule('ligne-horizontale')){
		$rule->replace = preg_replace(',<[^>]*>,Uims',$GLOBALS['ligne_horizontale'],$rule->replace);
		$ruleset->addRules(array('ligne-horizontale'=>$rule));
	}
	if (isset($GLOBALS['toujours_paragrapher']) AND !$GLOBALS['toujours_paragrapher']
	  AND $rule=$ruleset->getRule('toujours-paragrapher')) {
		$rule->disabled = true;
		$ruleset->addRules(array('toujours-paragrapher'=>$rule));
	}
}

// http://doc.spip.org/@traiter_raccourcis
function traiter_raccourcis($t) {
	static $wheel, $notes;
	// Appeler les fonctions de pre_traitement
	$t = pipeline('pre_propre', $t);

	if (!isset($wheel)) {
		$ruleset = SPIPTextWheelRuleset::loader(
			$GLOBALS['spip_wheels']['raccourcis'],'personnaliser_raccourcis'
		);
		$wheel = new TextWheel($ruleset);

		if (_request('var_mode') == 'wheel'
		AND autoriser('debug')) {
			$f = $wheel->compile();
			echo "<pre>\n".htmlspecialchars($f)."</pre>\n";
			exit;
		}
		$notes = charger_fonction('notes', 'inc');
	}

	// Gerer les notes (ne passe pas dans le pipeline)
	list($t, $mes_notes) = $notes($t);

	$t = $wheel->text($t);

	// Appeler les fonctions de post-traitement
	$t = pipeline('post_propre', $t);

	if ($mes_notes)
		$notes($mes_notes);

	return $t;
}


// Filtre a appliquer aux champs du type #TEXTE*
// http://doc.spip.org/@propre
function propre($t, $connect=null) {
	// les appels directs a cette fonction depuis le php de l'espace
	// prive etant historiquement ecrits sans argment $connect
	// on utilise la presence de celui-ci pour distinguer les cas
	// ou il faut passer interdire_script explicitement
	// les appels dans les squelettes (de l'espace prive) fournissant un $connect
	// ne seront pas perturbes
	$interdire_script = false;
	if (is_null($connect)){
		$connect = '';
		$interdire_script = true;
	}

	if (!$t) return strval($t);

	$t = echappe_html($t);
	$t = expanser_liens($t,$connect);
	$t = traiter_raccourcis($t);
	$t = echappe_retour_modeles($t, $interdire_script);

	return $t;
}
?>
