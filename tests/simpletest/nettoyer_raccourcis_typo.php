<?php

require_once('lanceur_spip.php');
require_once('data_test_typo.inc');

class Test_nettoyer_raccourcis_typo extends SpipTest {

	use Data_test_typo;

	public function __construct() {
		parent::__construct("Tests de nettoyer_raccourcis_typo()");
		include_spip('inc/lien');
		include_spip('inc/texte_mini');
	}

	/**
	 * @param array[] $data Collection of array with keys : [texte, couper, nettoyer]
	 */
	protected function _testData(array $data) {
		foreach ($data as $d) {
			$nettoyer = nettoyer_raccourcis_typo($d['texte']);
			$couper = couper($d['texte']);
			$this->_printTest($d, $nettoyer, $couper);
		}
	}
}