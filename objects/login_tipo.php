<?php
/**
 * Progetto e3g - Equogest/GestiGAS
 *   Software gestionali per l'economia solidale
 *   <http://www.progettoe3g.org>
 *
 * Copyright (C) 2003-2009
 *   Andrea Piazza <http://www.andreapiazza.it>
 *   Marco Munari  <http://www.marcomunari.it>
 *
 * @package Progetto e3g - Equogest/GestiGAS
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * 
 * Questo  programma e' software  libero; e' lecito redistribuirlo  o
 * modificarlo secondo i termini  della Licenza Pubblica Generica GNU
 * come  pubblicata dalla Free  Software  Foundation; o la versione 2
 * della licenza o (a propria scelta) una versione successiva.
 * 
 * Questo programma e' distribuito nella  speranza che sia  utile, ma
 * SENZA  ALCUNA GARANZIA;  senza  neppure la  garanzia implicita  di
 * NEGOZIABILITA' o di APPLICABILITA' PER  UN PARTICOLARE  SCOPO.  Si
 * veda la Licenza Pubblica Generica GNU per avere maggiori dettagli.
 * 
 * Questo  programma deve  essere  distribuito assieme  ad una  copia
 * della Licenza Pubblica Generica GNU.
*/


require_once( dirname(__FILE__) . '/../libraries/e3g_utils.php' );
require_once( dirname(__FILE__) . '/../config.php' );


class login_tipo extends P4A_Mask
{
	function &login_tipo ()
	{
		$this->p4a_mask();
		$this->addCss(E3G_TEMPLATE_DIR . 'css/style.css');
		$p4a =& p4a::singleton();


		//Istanzio un nuovo db_source. Un db_source e' una sorgente dati storata
		//su database.
		//Sorgente dati principale
		// data sources
		$this->build("p4a_db_source", "ds_tipo");
		$this->ds_tipo->setTable("_login_tipo_utente");
		$this->ds_tipo->setPk("codice");

		$this->ds_tipo->addOrder("descrizione");
		$this->ds_tipo->load();

		$this->setSource($this->ds_tipo);
		$this->ds_tipo->firstRow();

		// Fields properties
		$fields =& $this->fields;

		// Campi Obbligatori Fields
	    $this->mf = array("codice");
		foreach($this->mf as $mf){
			$fields->$mf->label->setFontWeight("bold");
		}


		// Aggiungo i campi della maschera
		//Campo codice
		$this->fields->codice->setLabel('codice');

		//Campo descrizione
		$this->fields->descrizione->setLabel('descrizione');
		$this->fields->descrizione->setWidth(250);

		// Toolbar
		$this->build("p4a_standard_toolbar", "toolbar");
		$this->toolbar->setMask($this);


		//Setto il titolo della maschera
		if ( E3G_TIPO_GESTIONE == 'G' )
		{
			$this->SetTitle('tipo Utente');
		}
		else 
		{
			// Equogest 
			$this->SetTitle('tipo Utente');
		}


		// Message
		$message =& $this->build("p4a_message", "message");
		$message->setWidth("300");


		//Fieldset con l'elenco dei campi
		$fset=& $this->build("p4a_fieldset", "frame");

 		$fset->anchor($this->fields->codice);
 		$fset->anchor($this->fields->descrizione);

		$fset->setWidth(730);


		// Frame
		$frm=& $this->build("p4a_frame", "frm");
		$frm->setWidth(730);
		$frm->anchor($message);
		$frm->anchor($fset);

		e3g_scrivi_footer( $this, $frm );

  		// Display
		$this->display("main", $frm);
		$this->display("menu", $p4a->menu);
		$this->display("top", $this->toolbar);
	}

	
	function main()
	{
		parent::main();

		foreach($this->mf as $mf){
			$this->fields->$mf->unsetStyleProperty("border");
		}
	}


}
?>