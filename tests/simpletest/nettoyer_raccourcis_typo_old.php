<?php

require_once('lanceur_spip.php');
require_once('data_test_typo.inc');

class Test_nettoyer_raccourcis_typo_old extends SpipTest {

	use Data_test_typo;

	public function __construct() {
		parent::__construct("Tests de nettoyer_raccourcis_typo() anciens");
		include_spip('inc/lien');
		include_spip('inc/texte_mini');
	}

	protected function _testData(array $data) {
		foreach ($data as $d) {
			$nettoyer = static::nettoyer_raccourcis_typo_old($d['texte']);
			$couper = static::couper_old($d['texte']);
			$this->_printTest($d, $nettoyer, $couper);
		}
	}

	public static function nettoyer_raccourcis_typo_old($texte, $connect = '') {
		$texte = pipeline('nettoyer_raccourcis_typo', $texte);

		if (preg_match_all(_RACCOURCI_LIEN, $texte, $regs, PREG_SET_ORDER)) {
			include_spip('inc/texte');
			foreach ($regs as $reg) {
				list($titre, , ) = traiter_raccourci_lien_atts($reg[1]);
				if (!$titre) {
					$match = typer_raccourci($reg[count($reg) - 1]);
					if (!isset($match[0])) {
						$match[0] = '';
					}
					@list($type, , $id, , , , ) = $match;

					if ($type) {
						$url = generer_url_entite($id, $type, '', '', true);
						if (is_array($url)) {
							list($type, $id) = $url;
						}
						$titre = traiter_raccourci_titre($id, $type, $connect);
					}
					$titre = $titre ? $titre['titre'] : $match[0];
				}
				$titre = corriger_typo(supprimer_tags($titre));
				$texte = str_replace($reg[0], $titre, $texte);
			}
		}

		// supprimer les ancres
		$texte = preg_replace(_RACCOURCI_ANCRE, "", $texte);

		// supprimer les notes
		$texte = preg_replace(",[[][[]([^]]|[]][^]])*[]][]],UimsS", "", $texte);

		// supprimer les codes typos
		$texte = str_replace(array('}', '{'), '', $texte);

		// supprimer les tableaux
		$texte = preg_replace(",(^|\r)\|.*\|(\r),s", "\r", $texte);

		return $texte;
	}

	public static function couper_old($texte, $taille = 50, $suite = '&nbsp;(...)') {
		if (!($length = strlen($texte)) or $taille <= 0) {
			return '';
		}
		$offset = 400 + 2 * $taille;
		while ($offset < $length
			and strlen(preg_replace(",<(!--|\w|/)[^>]+>,Uims", "", substr($texte, 0, $offset))) < $taille) {
			$offset = 2 * $offset;
		}
		if ($offset < $length
			&& ($p_tag_ouvrant = strpos($texte, '<', $offset)) !== null
		) {
			$p_tag_fermant = strpos($texte, '>', $offset);
			if ($p_tag_fermant && ($p_tag_fermant < $p_tag_ouvrant)) {
				$offset = $p_tag_fermant + 1;
			} // prolonger la coupe jusqu'au tag fermant suivant eventuel
		}
		$texte = substr($texte, 0, $offset); /* eviter de travailler sur 10ko pour extraire 150 caracteres */

		// on utilise les \r pour passer entre les gouttes
		$texte = str_replace("\r\n", "\n", $texte);
		$texte = str_replace("\r", "\n", $texte);

		// sauts de ligne et paragraphes
		$texte = preg_replace("/\n\n+/", "\r", $texte);
		$texte = preg_replace("/<(p|br)( [^>]*)?" . ">/", "\r", $texte);

		// supprimer les traits, lignes etc
		$texte = preg_replace("/(^|\r|\n)(-[-#\*]*|_ )/", "\r", $texte);

		// travailler en accents charset
		$texte = unicode2charset(html2unicode($texte, /* secure */
			true));
		if (!function_exists('nettoyer_raccourcis_typo')) {
			include_spip('inc/lien');
		}
		$texte = static::nettoyer_raccourcis_typo_old($texte);

		// supprimer les tags
		$texte = supprimer_tags($texte);
		$texte = trim(str_replace("\n", " ", $texte));
		$texte .= "\n";  // marquer la fin

		// corriger la longueur de coupe
		// en fonction de la presence de caracteres utf
		if ($GLOBALS['meta']['charset'] == 'utf-8') {
			$long = charset2unicode($texte);
			$long = spip_substr($long, 0, max($taille, 1));
			$nbcharutf = preg_match_all('/(&#[0-9]{3,6};)/S', $long, $matches);
			$taille += $nbcharutf;
		}


		// couper au mot precedent
		$long = spip_substr($texte, 0, max($taille - 4, 1));
		$u = $GLOBALS['meta']['pcre_u'];
		$court = preg_replace("/([^\s][\s]+)[^\s]*\n?$/" . $u, "\\1", $long);
		$points = $suite;

		// trop court ? ne pas faire de (...)
		if (spip_strlen($court) < max(0.75 * $taille, 2)) {
			$points = '';
			$long = spip_substr($texte, 0, $taille);
			$texte = preg_replace("/([^\s][\s]+)[^\s]*\n?$/" . $u, "\\1", $long);
			// encore trop court ? couper au caractere
			if (spip_strlen($texte) < 0.75 * $taille) {
				$texte = $long;
			}
		} else {
			$texte = $court;
		}

		if (strpos($texte, "\n"))  // la fin est encore la : c'est qu'on n'a pas de texte de suite
		{
			$points = '';
		}

		// remettre les paragraphes
		$texte = preg_replace("/\r+/", "\n\n", $texte);

		// supprimer l'eventuelle entite finale mal coupee
		$texte = preg_replace('/&#?[a-z0-9]*$/S', '', $texte);

		return quote_amp(trim($texte)) . $points;
	}
}
