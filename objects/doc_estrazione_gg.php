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


require_once( dirname(__FILE__) . '/../libraries/e3g_doc_routines.php' );


class doc_estrazione_gg extends P4A_Mask
{
	
	// Variabile per definire il tipo Anagrafica Cliente / Fornitore
	var $codicetipodoc = '';

    // -------------------------------------------------------------------------
	function doc_estrazione_gg()
    // -------------------------------------------------------------------------
	{
		$this->p4a_mask();
		$this->addCss(E3G_TEMPLATE_DIR . 'css/style.css');
		$p4a =& p4a::singleton();
		$db =& p4a_db::singleton();


		$this->SetTitle( "Estrazione Ordini per Fornitore" );


		// Toolbar
		$this->build( "p4a_quit_toolbar", "toolbar" );


		// Message
		$message =& $this->build("p4a_message", "message");
		$message->setWidth( 700 );


		// COMBO FORNITORI
		$this->build( "p4a_db_source", "ds_forn" );
        $this->ds_forn->setSelect( "a.idanag, a.codice, CONCAT_WS( ' / ', a.descrizione, a.desc_agg ) AS descrizione" );
		$this->ds_forn->setTable( $p4a->e3g_prefix . "anagrafiche a" );
        if ( $p4a->e3g_utente_tipo == "R" )
            $this->ds_forn->setWhere( "a.tipocfa = 'F' AND " . str_replace("#CAMPOCODICE#", "a.codice", $p4a->e3g_where_referente) );
        else                
            $this->ds_forn->setWhere( "a.tipocfa = 'F'" );                
        $this->ds_forn->addJoin( $p4a->e3g_prefix . "carrello c", "c.codfornitore = a.codice AND c.stato='A' " );
        $this->ds_forn->addGroup( "a.idanag, a.codice, a.descrizione" );
        $this->ds_forn->addOrder( "a.descrizione" );

		$this->ds_forn->setPk( "a.idanag" );
		$this->ds_forn->load();
		$this->ds_forn->firstRow();
		
        $this->build("p4a_field", "fld_fornitore");
        $this->fld_fornitore->setLabel( "Fornitore" );
        $this->fld_fornitore->label->setWidth( 120 );
        $this->fld_fornitore->setWidth( 300 );
        $this->fld_fornitore->setType( "select" );
        $this->fld_fornitore->setSource( $this->ds_forn );
        $this->fld_fornitore->setSourceValueField( "codice" );
        $this->fld_fornitore->setSourceDescriptionField( "descrizione" );
        $this->fld_fornitore->setNewValue( $this->ds_forn->fields->codice->getNewValue() );
        $this->fld_fornitore->addAction( "onChange" );
        $this->intercept( $this->fld_fornitore, "onChange","fld_fornitoreChange" );
        

        // PULSANTE Estrazione ordine per fornitore
        $this->build("p4a_button", "bu_estrai_ordine");
        $this->bu_estrai_ordine->setLabel("Estrai ordine fornitore");
        $this->bu_estrai_ordine->setIcon("execute");
        $this->bu_estrai_ordine->addAction("onClick");
        $this->intercept($this->bu_estrai_ordine, "onClick", "bu_estrai_ordineClick");
        $this->bu_estrai_ordine->setWidth(300);


        // PULSANTE esportazione PDF ordine appena estratto
        $this->build("p4a_button", "bu_esporta_ordine");
        $this->bu_esporta_ordine->setLabel("Esporta ordine come PDF...");
        $this->bu_esporta_ordine->setIcon("pdf");
        $this->bu_esporta_ordine->addAction("onClick");
        $this->intercept($this->bu_esporta_ordine, "onClick", "bu_esporta_ordine");
        $this->bu_esporta_ordine->setWidth(300);        
        $this->bu_esporta_ordine->setInvisible();


        // Data documento ordine
        $this->build( "p4a_field", "fld_data_doc" );
        $this->fld_data_doc->setLabel( "Data doc. ordine" );
        $this->fld_data_doc->setValue( date("d/m/Y") );
        $this->fld_data_doc->setType( "date" );
        $this->fld_data_doc->label->setWidth( 120 );
        $this->fld_data_doc->setWidth( 100 );
        

        // Etichetta descrittiva dell'ordine
        $this->build("p4a_label", "lbl_desc_ordine");
        $this->lbl_desc_ordine->setWidth( 900 );


        // ----------------------- Griglia spesa dettaglio (articoli per utente)
        
        // Data source
        $this->build( "p4a_db_source", "ds_doct" );
        $this->ds_doct->setSelect( 
            " c.idriga, c.data, c.codarticolo, c.descrizione, " .
            " CONCAT_WS( ' ', a.um_qta, a.um ) AS um_qta_um, " .  // CONCAT_WS non è vuoto se manca l'UM
            " a.um_qta, " . 
            " c.qta, c.qta_agg, c.qtaconsegnata, c.prezzoven, " .
            " ( (c.qta + c.qta_agg) * c.prezzoven ) AS importo, " . 
            " c.username, c.codcaumov, c.carscar, c.sconto, " .
            " c.codiva, c.codfornitore, c.stato, c.um, c.codutente" );
        $this->ds_doct->setTable( $p4a->e3g_prefix . "carrello c" );
        $this->ds_doct->addJoin( $p4a->e3g_prefix . "articoli a", "a.codice = c.codarticolo" );
        $this->ds_doct->setWhere( "c.stato = 'A' AND c.codfornitore = '" . $this->ds_forn->fields->codice->getNewValue() . "'" );
        $this->ds_doct->addOrder( "c.codfornitore" ); // perchè così quando estraggo faccio il sum degli articoli
        $this->ds_doct->addOrder( "c.codarticolo" );  // perchè così quando estraggo faccio il sum degli articoli
        $this->ds_doct->setPk( "c.idriga" );
        $this->ds_doct->load();
        $this->ds_doct->firstRow();
        
        $this->setSource( $this->ds_doct );

        $this->build( "p4a_db_source", "ds_anagc" );
        $this->ds_anagc->setTable( $p4a->e3g_prefix . "anagrafiche" );
        $this->ds_anagc->setWhere( "tipocfa = 'C'" );       
        $this->ds_anagc->setPk( "codice" );       
        $this->ds_anagc->load();

        
        // Tabella
        $tab_dettaglio =& $this->build("p4a_table", "tab_dettaglio");
        $this->tab_dettaglio->setWidth(E3G_TABLE_IN_TAB_PANE_WIDTH);
        $this->tab_dettaglio->setSource($this->ds_doct);
        $this->tab_dettaglio->setVisibleCols( array( "idriga",
            "codutente", "descrizione", "um_qta_um", "um_qta", "qta", "qta_agg", "prezzoven", "importo") );
        $this->intercept( $this->tab_dettaglio->rows, "beforeDisplay", "tab_dettaglio_beforeDisplay" );  
        $this->tab_dettaglio->showNavigationBar();

        $this->tab_dettaglio->cols->idriga->setVisible( false );
        $this->tab_dettaglio->cols->um_qta->setVisible( false );

        $this->tab_dettaglio->cols->codutente->setLabel( "Utente" );
        $this->tab_dettaglio->cols->descrizione->setLabel( "Articolo" );
        $this->tab_dettaglio->cols->um_qta_um->setLabel( "Conf." );
        $this->tab_dettaglio->cols->qta->setLabel(" Q.ta" );
        $this->tab_dettaglio->cols->qta_agg->setLabel( "Q.ta' aggiunta" );
        $this->tab_dettaglio->cols->prezzoven->setLabel( "Prezzo" );
        $this->tab_dettaglio->cols->importo->setLabel( "Importo" );

        $this->tab_dettaglio->cols->codutente->setWidth( 150 );
//      $this->tab_dettaglio->cols->descrizione->setWidth();  per differenza
        $this->tab_dettaglio->cols->um_qta_um->setWidth( 50 );
        $this->tab_dettaglio->cols->qta->setWidth( 50 );
        $this->tab_dettaglio->cols->qta_agg->setWidth( 50 );
        $this->tab_dettaglio->cols->prezzoven->setWidth( 50 );
        $this->tab_dettaglio->cols->importo->setWidth( 50 );

        $this->tab_dettaglio->cols->importo->setOrderable( false );
        $this->tab_dettaglio->cols->um_qta_um->setOrderable( false );

        $this->tab_dettaglio->cols->codutente->setSourceValueField('codice');
        $this->tab_dettaglio->cols->codutente->setSourceDescriptionField('descrizione');
        $this->tab_dettaglio->cols->codutente->setSource( $this->ds_anagc );


        // ------------------------------------------ Griglia spesa per articolo

        // Data source
        $this->build("p4a_db_source", "ds_spesa_articolo");
        $this->ds_spesa_articolo->setSelect(
            " c.stato, c.codfornitore, c.codarticolo, c.descrizione, " .
            " CONCAT_WS( ' ', art.um_qta, art.um ) AS um_qta_um, " .  // CONCAT_WS non è vuoto se manca l'UM
            " art.um_qta, " . 
            " art.qtaminordine AS pezzi_per_cartone, " .
            " SUM( c.qta )             AS pezzi_in_ordine_orig, " .
            " SUM( c.qta_agg )         AS pezzi_in_ordine_agg, " .
            " SUM( c.qta + c.qta_agg ) AS pezzi_in_ordine_tot, " .
            " CEILING( SUM(c.qta+c.qta_agg) / art.qtaminordine ) AS cartoni, " .
            " ( CEILING(SUM(c.qta+c.qta_agg) / art.qtaminordine) * art.qtaminordine ) - SUM(c.qta+c.qta_agg) AS surplus_pezzi, " .
            " FORMAT( c.prezzoven, $p4a->e3g_azienda_n_decimali_prezzi ) AS prezzoven, " .
            " FORMAT(SUM(c.prezzoven * (c.qta+c.qta_agg)), $p4a->e3g_azienda_n_decimali_prezzi) AS importo" );
            
        $this->ds_spesa_articolo->setTable( $p4a->e3g_prefix."carrello c, " . $p4a->e3g_prefix . "articoli art" );
        $this->where_ds_spesa_articolo = "c.stato='A' AND c.codfornitore='".$this->ds_forn->fields->codice->getNewValue()."' AND c.codarticolo = art.codice " .
            ( $p4a->e3g_utente_tipo == 'R' ? "AND " . str_replace("#CAMPOCODICE#", "c.codfornitore", $p4a->e3g_where_referente) : "" );
        $this->ds_spesa_articolo->setWhere( $this->where_ds_spesa_articolo );
        $this->ds_spesa_articolo->addGroup( "c.codarticolo" );
        $this->ds_spesa_articolo->addOrder("c.codfornitore");
        $this->ds_spesa_articolo->addOrder("c.descrizione");

        $this->ds_spesa_articolo->setPk("c.codarticolo");
        $this->ds_spesa_articolo->setPageLimit( $p4a->e3g_utente_db_source_page_limit );
        $this->ds_spesa_articolo->load();
        $this->ds_spesa_articolo->firstRow();


        // Tabella
        $this->build("p4a_table", "tab_articolo");
        $this->tab_articolo->setWidth(E3G_TABLE_IN_TAB_PANE_WIDTH);
        $this->tab_articolo->setSource($this->ds_spesa_articolo); 
        $this->tab_articolo->setVisibleCols( array(
            "descrizione", "um_qta_um", "um_qta", "pezzi_per_cartone", 
            "pezzi_in_ordine_orig", "pezzi_in_ordine_agg", "pezzi_in_ordine_tot", "cartoni", "surplus_pezzi", "prezzoven","importo") );
        $this->intercept( $this->tab_articolo->rows, "beforeDisplay", "tab_articolo_beforeDisplay" );  
        $this->tab_articolo->showNavigationBar();

        $this->tab_articolo->cols->um_qta->setVisible( false );

        $this->tab_articolo->cols->descrizione->setLabel( "Articolo" );
        $this->tab_articolo->cols->um_qta_um->setLabel( "Conf." );
        $this->tab_articolo->cols->pezzi_per_cartone->setLabel( "Pezzi per cartone" );
        $this->tab_articolo->cols->pezzi_in_ordine_orig->setLabel( "Pezzi in ordine (orig.) ");
        $this->tab_articolo->cols->pezzi_in_ordine_agg->setLabel( "Pezzi aggiunti" );
        $this->tab_articolo->cols->pezzi_in_ordine_tot->setLabel( "Pezzi in ordine (TOT) ");
        $this->tab_articolo->cols->cartoni->setLabel( "N. cartoni" );
        $this->tab_articolo->cols->surplus_pezzi->setLabel( "Surplus [pezzi]" );
        $this->tab_articolo->cols->prezzoven->setLabel( "Prezzo" );
        $this->tab_articolo->cols->importo->setLabel( "Importo" );

        $this->tab_articolo->data->fields->prezzoven->setType("float");
        $this->tab_articolo->data->fields->importo->setType("float");

//      $this->tab_articolo->cols->descrizione->setWidth();  per differenza
        $this->tab_articolo->cols->um_qta_um->setWidth( 50 );
        $this->tab_articolo->cols->pezzi_per_cartone->setWidth(50);
        $this->tab_articolo->cols->pezzi_in_ordine_orig->setWidth(50);
        $this->tab_articolo->cols->pezzi_in_ordine_agg->setWidth(50);
        $this->tab_articolo->cols->pezzi_in_ordine_tot->setWidth(50);
        $this->tab_articolo->cols->cartoni->setWidth(50);
        $this->tab_articolo->cols->surplus_pezzi->setWidth(50);
        $this->tab_articolo->cols->prezzoven->setWidth(50);
        $this->tab_articolo->cols->importo->setWidth(50);

        $this->tab_articolo->cols->um_qta_um->setOrderable( false );
        $this->tab_articolo->cols->pezzi_per_cartone->setOrderable( false );
        $this->tab_articolo->cols->pezzi_in_ordine_orig->setOrderable( false );
        $this->tab_articolo->cols->pezzi_in_ordine_agg->setOrderable( false );
        $this->tab_articolo->cols->pezzi_in_ordine_tot->setOrderable( false );
        $this->tab_articolo->cols->cartoni->setOrderable( false );
        $this->tab_articolo->cols->surplus_pezzi->setOrderable( false );
        $this->tab_articolo->cols->prezzoven->setOrderable( false );
        $this->tab_articolo->cols->importo->setOrderable( false );


        // ------------------------------------------------------------ Pannelli 
        $this->build("p4a_tab_pane", "tab_pane");        
        $this->tab_pane->pages->build("p4a_frame", "tabframe1");
        $this->tab_pane->pages->build("p4a_frame", "tabframe2");
        $this->tab_pane->pages->tabframe1->setLabel("Spesa dettaglio (articoli per utente)");
        $this->tab_pane->pages->tabframe1->anchor( $this->tab_dettaglio);      
        $this->tab_pane->pages->tabframe2->setLabel("Spesa per articolo");
        $this->tab_pane->pages->tabframe2->anchor( $this->tab_articolo);       
        

        // ----------------------------------------- Campi invisibili per stampa
        $this->build("p4a_field", "fldcodtipo");
        $this->fldcodtipo->setNewValue("");

        $this->build("p4a_field", "flddatadoc");
        $this->flddatadoc->setNewValue("");
        
        $this->build("p4a_field", "fldfornitore");
        $this->fldfornitore->setNewValue("");
        
        $this->build("p4a_field", "fldiddoc");
        $this->fldiddoc->setNewValue("");

        $this->build("p4a_field", "fldnumdocum");
        $this->fldnumdocum->setNewValue("");


        // -------------------------------------------------- Ancoraggio oggetti
        
        $frm=& $this->build("p4a_frame", "frm");
        $frm->setWidth( E3G_MAIN_FRAME_WIDTH );

		$frm->anchor( $this->message );
        $frm->anchor( $this->fld_fornitore );
        $frm->anchorRight( $this->bu_estrai_ordine );
		$frm->anchor( $this->fld_data_doc );
        $frm->anchorRight( $this->bu_esporta_ordine );
		$frm->anchor( $this->lbl_desc_ordine );
		$frm->anchor( $this->tab_pane );

		
		e3g_scrivi_footer( $this, $frm );


		// Display
		$this->display("main", $frm);
		$this->display("menu", $p4a->menu);
		$this->display("top", $this->toolbar);


        $this->update_message();
        $this->fld_fornitoreChange();
	}


    // -------------------------------------------------------------------------
	function main()
    // -------------------------------------------------------------------------
	{
        $p4a =& p4a::singleton();

		parent::main();

        $this->update_message();
	}

	
    // -------------------------------------------------------------------------
	function bu_esporta_ordine()
    // -------------------------------------------------------------------------
	{
		$p4a =& p4a::singleton();
		$db =& p4a_db::singleton();
		
		if ($this->fldcodtipo->getNewValue() != "" && $this->fldfornitore->getNewValue() != "" && $this->fldiddoc->getNewValue() != "" && $this->fldnumdocum->getNewValue() != "" && $this->flddatadoc->getNewValue() != "" )
		{
			$pagamento = "";
			genera_stampa_pdf($this->fldnumdocum->getNewValue(), $this->fldiddoc->getNewValue(), $this->fldcodtipo->getNewValue(), $this->fldfornitore->getNewValue(), $pagamento, 0 );
		}	
	}
	

    // -------------------------------------------------------------------------
	function fld_fornitoreChange()
    // -------------------------------------------------------------------------
	{
		$p4a =& p4a::singleton();
		$db =& p4a_db::singleton();
	
		$this->ds_doct->setWhere( "codfornitore = '" . $this->fld_fornitore->getNewValue() . "'" );
		$this->ds_doct->load();
		
		$this->where_ds_spesa_articolo = "c.stato = 'A' AND c.codfornitore = '" . $this->fld_fornitore->getNewValue() . "' AND c.codarticolo = art.codice " .
			( $p4a->e3g_utente_tipo == 'R' ? "AND " . str_replace("#CAMPOCODICE#", "c.codfornitore", $p4a->e3g_where_referente) : "" );
		$this->ds_spesa_articolo->setWhere( $this->where_ds_spesa_articolo );
		$this->ds_spesa_articolo->load();
		
		
        $pezzi = $db->queryOne(
            "SELECT SUM( c.qta + c.qta_agg ) AS pezzi_in_ordine_tot " .
            "  FROM " . $p4a->e3g_prefix . "carrello c " .
            " WHERE c.stato = 'A' AND c.codfornitore = '" . $this->fld_fornitore->getNewValue() . "' ");
        
        if ( $pezzi == 0 ) {
			$this->lbl_desc_ordine->setValue( "Nessun articolo in ordine." );
		}
		else {
			$cartoni = $db->queryOne(
			    "SELECT CEILING( SUM(c.qta+c.qta_agg) / art.qtaminordine ) AS cartoni " .
			    "  FROM " . $p4a->e3g_prefix . "carrello c, " . $p4a->e3g_prefix . "articoli art " .
			    " WHERE c.stato = 'A' AND c.codarticolo = art.codice AND c.codfornitore = '" . $this->fld_fornitore->getNewValue() . "' ");
			$utenti = $db->queryOne(
			    "SELECT COUNT( DISTINCT(c.codutente) ) AS utenti_diversi " .
			    "  FROM " . $p4a->e3g_prefix . "carrello c " .
			    " WHERE c.stato = 'A' AND c.codfornitore = '" . $this->fld_fornitore->getNewValue() . "' ");
			$importo = $db->queryOne(
			    "SELECT FORMAT(SUM(c.prezzoven * (c.qta+c.qta_agg)), $p4a->e3g_azienda_n_decimali_prezzi) AS importo " .
			    "  FROM " . $p4a->e3g_prefix . "carrello c " .
			    " WHERE c.stato = 'A' AND c.codfornitore = '" . $this->fld_fornitore->getNewValue() . "' "); 
			
			$this->lbl_desc_ordine->setValue(
			    "In ordine: " . $pezzi . " pezzi, " . $cartoni . " cartoni, " . $utenti . " utenti. " .
			    "Importo totale: " . $importo . " euro" );
		}

        $this->bu_esporta_ordine->setInvisible();
    }
	

    // -------------------------------------------------------------------------
	function bu_estrai_ordineClick()
    // -------------------------------------------------------------------------
	{
		$p4a =& p4a::singleton();
		$db =& p4a_db::singleton();

		$importototale = $db->queryOne( 
            "SELECT SUM(qta * prezzoven) as importo " .
            "  FROM " . $p4a->e3g_prefix . "carrello " .
            " WHERE stato = 'A' AND codfornitore = '" . $this->ds_forn->fields->codice->getNewValue() . "'" );
		
		if ( $p4a->e3g_azienda_ordine_minimo > 0 and $importototale < $p4a->e3g_azienda_ordine_minimo ) {
				// importo non sufficiente
				$this->message->setValue( "Impossibile generare l'ordine. Importo inferiore all'importo minimo previsto." );
				return;
		}
			
		// controllo se ci sono ancora ordini aperti per questo referente
		$ordini_aperti = $db->queryOne("SELECT COUNT(*) as ordini_aperti " .
				"  FROM " . $p4a->e3g_prefix . "fornitoreperiodo fp " .
				"  LEFT JOIN " . $p4a->e3g_prefix . "anagrafiche a ON a.codice = fp.fornitore " .
				" WHERE " . e3g_where_ordini_aperti("fp")." AND fp.fornitore = '".$this->fld_fornitore->getNewValue()."'");
		// se ci sono ancora ordini aperti per uno o più fornitori di questo referente 
	    // allora impedisco l'estrazione (l'estrazione deve essere possibile solo dopo la data di chiusura del periodo ordini)
    	if ( $ordini_aperti > 0 ) {
			// uno o più ordini aperti
			$this->message->setValue( "Impossibile generare l'ordine. Il periodo ordini e' ancora aperto per il fornitore selezionato." );
			return;
		}
		
		// estraggo le righe con il fornitore indicato
		// genero l'ordine a fornitore 
		// setto le righe Stato = A --> Stato = L
		$fornitore = $this->fld_fornitore->getNewValue();
		
		
		//$this->ds_doct->dropFilter("codfornitore");
		$this->ds_doct->setWhere("codfornitore='".$fornitore."'");

		
		$this->ds_doct->load();
		$this->ds_doct->firstRow();
		
		
		if ( $this->ds_doct->getNumRows() == 0 ) {
			$this->message->setValue( "Nessun articolo in ordine per questo fornitore." );
			return;		
		}
		
		$codtipodoc =$p4a->e3g_azienda_gg_cod_doc_ordine;
					
		$anno = $db->queryOne( "SELECT annocontabile FROM ".$p4a->e3g_prefix."azienda" );
		if ( !is_numeric($anno) ) {
			$anno = date("Y");
		}
		
		$registro = $db->queryOne("SELECT codregdoc FROM ".$p4a->e3g_prefix."doctipidoc WHERE codice='".$codtipodoc."'");
		$numdocum = $db->queryOne("SELECT MAX(seriale) FROM ".$p4a->e3g_prefix."docregistri WHERE codice='".$registro."'");
		if ( is_numeric($numdocum) )
			$numdocum++;
		else
			$numdocum = 1;
		
		$query = "UPDATE ".$p4a->e3g_prefix."docregistri SET seriale=".$numdocum." WHERE codice='".$registro."'";
		$db->query($query);
		
		$iddoc = $db->queryOne("SELECT MAX(iddoc) as iddoc FROM ".$p4a->e3g_prefix."doct");
		if ( is_numeric($iddoc) )
			$iddoc++;	
		else
			$iddoc = 1;
		
       	// Inserisco la testa del documento 	
       	$db->query(
            "INSERT INTO ".$p4a->e3g_prefix."doct (iddoc,codclifor,numdocum,codtipodoc,anno,data, data_ins, idanag) " .
            " VALUES ( ".$iddoc.",'".$fornitore."',".$numdocum.",'".$codtipodoc."','".$anno."','" . 
            e3g_format_data_to_mysql( $this->fld_data_doc->getNewValue() ) . "','" .
            date ("Y-m-d H:i:s")."',".$p4a->e3g_utente_idanag.")");
		
		// Inserisco le righe
		$this->build("p4a_db_source", "ds_docr");
		$this->ds_docr->setTable($p4a->e3g_prefix."docr");
		$this->ds_docr->setPk("idriga");		
		$this->ds_docr->load();
		$riga = $db->queryOne("SELECT idriga FROM ".$p4a->e3g_prefix."docr ORDER BY idriga DESC");
		if ( !is_numeric($riga) )
			$riga = 0;
		  
		$riga++;
		$rigarel = 1 ;
		// data Source per la ricerca degli articoli 

		$this->ds_doct->firstRow(); 
		$strselect = "AND idriga IN (";

		$totimponibile = 0 ;
		$totimposta = 0 ; 
		$totdoc = 0 ;
				
		//$oldarticolo =	$this->ds_doct->fields->codarticolo->getNewValue();
		while($rigarel <= $this->ds_doct->getNumRows())
		{				
			$qtamin = $db->queryOne("SELECT qtaminordine FROM ".$p4a->e3g_prefix."articoli WHERE codice='".$this->ds_doct->fields->codarticolo->getNewValue()."'");
			$qtainordine = $db->queryOne("SELECT SUM(qta) FROM ".$p4a->e3g_prefix."carrello WHERE codarticolo='".$this->ds_doct->fields->codarticolo->getNewValue()."'");
			$codiva = $db->queryOne("SELECT codiva FROM ".$p4a->e3g_prefix."articoli WHERE codice='".$this->ds_doct->fields->codarticolo->getNewValue()."'");
			
			// estraggo comunqu tutto perchè poi la qta viene integrata
			//if (($qtamin == 0) || ($qtamin > 0 && $qtainordine >= $qtamin))
			//{	
    			$strdescrizione = $db->queryOne("SELECT descrizione FROM ".$p4a->e3g_prefix."articoli WHERE barcode='".$this->fields->codarticolo->getNewValue()."' OR codice='".$this->fields->codarticolo->getNewValue()."'");
    			
    			$this->ds_docr->newRow();
				
				// Aggiungo la riga invisibile con il riferimento all'utente     			
    			// Creo le righe del documento ORDINE A FORNITORE $p4a->e3g_azienda_gg_cod_doc_ordine
    			$this->ds_docr->fields->idriga->setNewValue($riga);
    			$this->ds_docr->fields->codice->setNewValue($this->ds_doct->fields->codarticolo->getNewValue());
    			$this->ds_docr->fields->descrizione->setNewValue($strdescrizione);		
    			$this->ds_docr->fields->iddocr->setNewValue($iddoc);
    			$this->ds_docr->fields->anno->setNewValue($anno);
    			$this->ds_docr->fields->codtipodoc->setNewValue($codtipodoc);
    			$this->ds_docr->fields->data->setNewValue( e3g_format_data_to_mysql( $this->fld_data_doc->getNewValue() ) );
    			$this->ds_docr->fields->numdocum->setNewValue($numdocum);
    			$this->ds_docr->fields->quantita->setValue($this->ds_doct->fields->qta->getNewValue() + $this->ds_doct->fields->qta_agg->getNewValue());
    			$this->ds_docr->fields->quantita2->setValue($this->ds_doct->fields->qta_agg->getNewValue());
    			$this->ds_docr->fields->codiva->setNewValue($codiva);
    			
    			// nelle righe invisibili (con i dati per singolo utente) utilizzo il Prezzo di Vendita
				$this->ds_docr->fields->prezzo->setNewValue($this->ds_doct->fields->prezzoven->getNewValue());
    			//$this->ds_docr->fields->nriga->setNewValue($rigarel);
    			$this->ds_docr->fields->nriga->setNewValue($riga);
    			$this->ds_docr->fields->rifidriga->setNewValue($this->ds_doct->fields->idriga->getNewValue());
    			$this->ds_docr->fields->codutente->setNewValue($this->ds_doct->fields->codutente->getNewValue());
    			$this->ds_docr->fields->dataordine->setNewValue($this->ds_doct->fields->data->getNewValue());				
				$this->ds_docr->fields->visibile->setNewValue("N");
				$this->ds_docr->fields->delta_prezzo->setNewValue(0); // lo imposto a 0 perchè il campo non può essere NULL errore riscontrato il 15.04.09 AP
				
/*    			
				// ricavo la mail per l'invio automatico
				if ($_SESSION["mailordforn"] == "S")
    			{
					$strmail = $db->queryOne("SELECT email FROM ".$p4a->e3g_prefix."anagrafiche WHERE codice='".$this->ds_doct->fields->codutente->getNewValue()."'");
					$desfornitore = $db->queryOne("SELECT descrizione FROM ".$p4a->e3g_prefix."anagrafiche WHERE codice='".$fornitore."'");
					mail($strmail, "Ordine Fornitore Inviato", "L'ordine al fornitore: ".$desfornitore." è stato inviato in data ".$this->fld_data_doc->getNewValue());
				}
*/				
    			if ( $strselect == "AND idriga IN (" )
    				$strselect = $strselect.$this->ds_doct->fields->idriga->getNewValue();
    			else
    				$strselect = $strselect.", ".$this->ds_doct->fields->idriga->getNewValue();
    			$this->calcola_tot_riga();
    			
    			$totimponibile = $totimponibile + $this->ds_docr->fields->imponibile->getNewValue();
        		$totimposta = $totimposta + $this->ds_docr->fields->imposta->getNewValue();
		        $totdoc = $totdoc + $this->ds_docr->fields->totale->getNewValue();
    			
    			$this->ds_docr->saveRow();
			//}
			//else
			//{
			//	// non ho la qta minima necessaria
			//	$strmessage = $strmessage."Articolo: [".$this->ds_doct->fields->codarticolo->getNewValue()."] Q.ta min. ordinabile = ".$qtamin."<br>";
			//}
			
			$this->ds_doct->nextRow();
			
			$rigarel++;
			$riga++;
		}

				
		// Aggiungo le righe Visibili con il SUM per ogni articolo 
		//
		$this->build("p4a_db_source", "ds_totart");
		$this->ds_totart->setTable($p4a->e3g_prefix."articoli");
		$this->ds_totart->setPk("idarticolo");
		$this->ds_totart->setSelect("DISTINCT idarticolo");
		$this->ds_totart->setWhere("codfornitore='".$this->fld_fornitore->getNewValue()."'");
		$this->ds_totart->addJoin( $p4a->e3g_prefix . "carrello",  $p4a->e3g_prefix."articoli.codice = ".$p4a->e3g_prefix."carrello.codarticolo");
		
		$querynum ="SELECT COUNT(DISTINCT(codarticolo)) FROM ".$p4a->e3g_prefix."carrello WHERE codfornitore='".$this->fld_fornitore->getNewValue()."'";
				
		$this->ds_totart->load();
		$this->ds_totart->firstRow();
		$numrows = $db->queryOne($querynum);

		$prog = 1 ;
		while ( $prog <= $numrows ) {				
			// descrizione articolo 
			$codarticolo = $db->queryOne("SELECT codice FROM ".$p4a->e3g_prefix."articoli WHERE idarticolo='".$this->ds_totart->fields->idarticolo->getNewValue()."'");
			$strdescrizione = $db->queryOne("SELECT descrizione FROM ".$p4a->e3g_prefix."articoli WHERE codice='".$codarticolo."'");
			$iva = $db->queryOne("SELECT codiva FROM ".$p4a->e3g_prefix."articoli WHERE codice='".$codarticolo."'");
			// nelle righe visibili (con i dati raggruppati per il fornitore) utilizzo il Prezzo di Acquisto
			$prezzo = $db->queryOne("SELECT prezzoacq FROM ".$p4a->e3g_prefix."articoli WHERE codice='".$codarticolo."'");
			$quantita = $db->queryOne("SELECT SUM(qta + qta_agg) FROM ".$p4a->e3g_prefix."carrello WHERE codarticolo='".$codarticolo."'");
			
			// QUERY MODIFICATA 06 aprile 2007 AP 
			//$surplus = $db->queryOne("SELECT ( CEILING(SUM(c.qta+c.qta_agg) / art.qtaminordine) * art.qtaminordine ) - SUM(c.qta+c.qta_agg) AS surplus_pezzi FROM ".$p4a->e3g_prefix."carrello c,".$p4a->e3g_prefix."articoli art  WHERE c.codarticolo=art.codice AND c.codarticolo='".$codarticolo."'");
			$surplus = $db->queryOne("SELECT SUM(qta_agg) FROM ".$p4a->e3g_prefix."carrello WHERE codarticolo='".$codarticolo."'");
												
			
			// aggiungo la riga del totale
			$this->ds_docr->newRow();
			
			// Creo le righe del documento ORDINE A FORNITORE $p4a->e3g_azienda_gg_cod_doc_ordine
			$this->ds_docr->fields->idriga->setNewValue($riga);
			$this->ds_docr->fields->codice->setNewValue($codarticolo);
			$this->ds_docr->fields->descrizione->setNewValue($strdescrizione);
			$this->ds_docr->fields->iddocr->setNewValue($iddoc);
			$this->ds_docr->fields->anno->setNewValue($anno);
			$this->ds_docr->fields->codtipodoc->setNewValue($codtipodoc);
			$this->ds_docr->fields->data->setNewValue( e3g_format_data_to_mysql( $this->fld_data_doc->getNewValue() ) );
			$this->ds_docr->fields->numdocum->setNewValue($numdocum);
			$this->ds_docr->fields->quantita->setValue($quantita);
			$this->ds_docr->fields->quantita2->setValue($surplus);
			$this->ds_docr->fields->codiva->setNewValue($iva);
			$this->ds_docr->fields->prezzo->setNewValue($prezzo);
			$this->ds_docr->fields->nriga->setNewValue($riga);
			$this->ds_docr->fields->visibile->setValue("S");
			$this->ds_docr->fields->delta_prezzo->setValue(0); // lo imposto a 0 perchè il campo non può essere NULL errore riscontrato il 15.04.09 AP
			$this->calcola_tot_riga();		
			$this->ds_docr->saveRow();
					
			
			$riga++;
			$rigarel++;
			
			$this->ds_totart->nextRow();
			$prog++;
		}
		//////////////////////////////////////////////

		// Update dei totali nella testa del documento
		$db->query("UPDATE ".$p4a->e3g_prefix."doct SET imponibile = ".str_replace(",", ".",$totimponibile).",imposta = ".str_replace(",", ".",$totimposta).",totdoc = ".str_replace(",", ".",$totdoc)." WHERE iddoc=".$iddoc);


		// Eliminazione righe nel carrello
		if ( $strselect == "AND idriga IN (" )
			$strselect = "";
		else
			$strselect = $strselect.")";
				
	
		// elimino le righe che non mi servono piu' 
		if ( $strselect == "" ) {
			// nessuna riga eliminata
		}
		else {
			$db->query("DELETE FROM ".$p4a->e3g_prefix."carrello WHERE codfornitore='".$fornitore."' ".$strselect);
		}

		// 07.02.09 AP modifica inserita per consentire il corretto posizionamento sul nuovo Fornitore
		// dopo l'estrazione del precedente (prima di questa modifica bisognava uscire e rientrare nel programma)		
		
		// aggiorno il data source dei fornitori 
		if ( $p4a->e3g_utente_tipo == "R" )
			$this->ds_forn->setWhere( "a.tipocfa = 'F' AND " . str_replace("#CAMPOCODICE#", "a.codice", $p4a->e3g_where_referente) );        	
    	else
    		$this->ds_forn->setWhere( "a.tipocfa = 'F'" );                	
	    
		$this->ds_forn->firstRow();
	    $this->ds_forn->firstRow();
    	$this->fld_fornitore->setNewValue($this->ds_forn->fields->codice->getNewValue());
    	
    	// aggiorno la Tabella Dettaglio per il nuovo Fornitore selezionato dopo l'estrazione del precedente
		$this->ds_doct->setWhere("stato='A' AND codfornitore='".$this->ds_forn->fields->codice->getNewValue()."'");    
		//$this->ds_doct->firstRow(); // AP 11.01.10 commentato per errore nel retrieve number rows
		 
		
		// aggiorno la Label con i totali per il nuovo Fornitore selezionato dopo l'estrazione del precedente
		$this->fld_fornitoreChange();
		
		
		// salvo i valori per poi utilizzarli col tasto stampa
		$this->fldcodtipo->setNewValue($codtipodoc);
		$this->fldfornitore->setNewValue($fornitore);
		$this->fldiddoc->setNewValue($iddoc);
		$this->fldnumdocum->setNewValue($numdocum);
		$this->flddatadoc->setNewValue($this->fld_data_doc->getNewValue());
		
		$this->bu_esporta_ordine->setVisible();

        $this->update_message();
	}
	
    
    // -------------------------------------------------------------------------
	function calcola_tot_riga()
    // -------------------------------------------------------------------------
	{
		$p4a =& p4a::singleton();
		$db =& p4a_db::singleton();

		$iva = str_replace(",", ".",$db->queryOne("SELECT iva FROM ".$p4a->e3g_prefix."aliquoteiva WHERE codice='".$this->ds_docr->fields->codiva->getNewValue()."'"));

		$quantita = str_replace(",", ".",$this->ds_docr->fields->quantita->getNewValue());
		
		if ( !is_numeric($quantita) )
       		$quantita = 0;
		
		$prezzo = str_replace(",", ".",$this->ds_docr->fields->prezzo->getNewValue());
		
		if ( !is_numeric($prezzo) )
   	    	$prezzo = 0;
		
		$sconto = str_replace(",", ".",$this->ds_docr->fields->sconto->getNewValue());
		
		if ( !is_numeric($sconto) )
   	    	$sconto = 0;
	
        $imponibile = (($quantita * $prezzo) * (1 - $sconto / 100) / (100 + $iva)) * 100;
        $imposta    = (($quantita * $prezzo) * (1 - $sconto / 100)) - $imponibile;
        $totriga    = (($quantita * $prezzo) * (1 - $sconto / 100));
    
    	
        $this->ds_docr->fields->imponibile->setNewValue(round($imponibile, 2));
        $this->ds_docr->fields->imposta->setNewValue(round($imposta,2));
        $this->ds_docr->fields->totale->setNewValue(round($totriga,2));

		return 0;
	}


    // -------------------------------------------------------------------------
    function update_message()
    // -------------------------------------------------------------------------
    {
        $p4a =& p4a::singleton();
        $db =& p4a_db::singleton();

        if ( $this->ds_forn->getNumRows() <> 0 ) {
            $this->bu_estrai_ordine->enable();
            $this->message->setValue( "" );
        }
        else {
            $this->bu_estrai_ordine->disable();
            $this->message->setValue( "Nessun articolo in ordine" );
    
            $this->bu_esporta_ordine->setInvisible();
        }
    }


    // -------------------------------------------------------------------------
    function tab_dettaglio_beforeDisplay( $obj, $rows ) 
    // -------------------------------------------------------------------------
    {  
        for( $i=0; $i<count($rows); $i++ ) {  
//          if ( $rows[$i]["idriga"] == $this->tab_dettaglio->fields->idriga->getNewValue() )
//              $rows[$i]["descrizione"] = "<span style='color:black;font-weight:bold;'>" . $rows[$i]["descrizione"] . "</span>";
            if ( $rows[$i]["um_qta"] == "" )
                $rows[$i]["um_qta_um"] = "";
//          $rows[$i]["bio"] = ( $rows[$i]["bio"] == 1 ? "Bio" : "" );
        }  
        return $rows;  
    }  


    // -------------------------------------------------------------------------
    function tab_articolo_beforeDisplay( $obj, $rows ) 
    // -------------------------------------------------------------------------
    {  
        for( $i=0; $i<count($rows); $i++ ) {  
            if ( $rows[$i]["um_qta"] == "" )
                $rows[$i]["um_qta_um"] = "";
        }  
        return $rows;  
    }  


}

?>