-- Dolibarr one-time transaction reset.
-- Keeps master/configuration data, users, third parties, products, warehouses,
-- bank account definitions, accounting plan/journals, document templates,
-- and llx_printformat.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE llx_accounting_bookkeeping;
TRUNCATE TABLE llx_accounting_bookkeeping_piece;
TRUNCATE TABLE llx_accounting_bookkeeping_tmp;

TRUNCATE TABLE llx_actioncomm_resources;
TRUNCATE TABLE llx_actioncomm_reminder;
TRUNCATE TABLE llx_actioncomm_extrafields;
TRUNCATE TABLE llx_actioncomm;

TRUNCATE TABLE llx_bank_url;
TRUNCATE TABLE llx_bank;

TRUNCATE TABLE llx_element_element;
DELETE FROM llx_ecm_files_extrafields
 WHERE fk_object IN (
  SELECT rowid FROM llx_ecm_files
   WHERE src_object_type IN ('propal', 'supplier_proposal', 'commande',
    'commande_fournisseur', 'facture', 'facture_fourn', 'expedition',
    'reception', 'delivery', 'expensereport')
 );
DELETE FROM llx_ecm_files
 WHERE src_object_type IN ('propal', 'supplier_proposal', 'commande',
  'commande_fournisseur', 'facture', 'facture_fourn', 'expedition',
  'reception', 'delivery', 'expensereport');

TRUNCATE TABLE llx_paiement_facture;
TRUNCATE TABLE llx_paiement_extrafields;
TRUNCATE TABLE llx_paiement;
TRUNCATE TABLE llx_paiementfourn_facturefourn;
TRUNCATE TABLE llx_paiementfourn;
TRUNCATE TABLE llx_paiementcharge;
TRUNCATE TABLE llx_paymentexpensereport_expensereport;
TRUNCATE TABLE llx_payment_expensereport;
TRUNCATE TABLE llx_payment_donation;
TRUNCATE TABLE llx_payment_loan;
TRUNCATE TABLE llx_payment_salary;
TRUNCATE TABLE llx_payment_various_extrafields;
TRUNCATE TABLE llx_payment_various;
TRUNCATE TABLE llx_payment_vat;

TRUNCATE TABLE llx_expedition_package;
TRUNCATE TABLE llx_expeditiondet_batch;
TRUNCATE TABLE llx_expeditiondet_extrafields;
TRUNCATE TABLE llx_expeditiondet;
TRUNCATE TABLE llx_expedition_extrafields;
TRUNCATE TABLE llx_expedition;
TRUNCATE TABLE llx_receptiondet_batch_extrafields;
TRUNCATE TABLE llx_receptiondet_batch;
TRUNCATE TABLE llx_reception_extrafields;
TRUNCATE TABLE llx_reception;
TRUNCATE TABLE llx_deliverydet_extrafields;
TRUNCATE TABLE llx_deliverydet;
TRUNCATE TABLE llx_delivery_extrafields;
TRUNCATE TABLE llx_delivery;

TRUNCATE TABLE llx_commandedet_extrafields;
TRUNCATE TABLE llx_commandedet;
TRUNCATE TABLE llx_commande_extrafields;
TRUNCATE TABLE llx_commande;
TRUNCATE TABLE llx_commande_fournisseur_log;
TRUNCATE TABLE llx_commande_fournisseurdet_extrafields;
TRUNCATE TABLE llx_commande_fournisseurdet;
TRUNCATE TABLE llx_commande_fournisseur_extrafields;
TRUNCATE TABLE llx_commande_fournisseur;

TRUNCATE TABLE llx_propaldet_extrafields;
TRUNCATE TABLE llx_propaldet;
TRUNCATE TABLE llx_propal_extrafields;
TRUNCATE TABLE llx_propal_merge_pdf_product;
TRUNCATE TABLE llx_propal;
TRUNCATE TABLE llx_supplier_proposaldet_extrafields;
TRUNCATE TABLE llx_supplier_proposaldet;
TRUNCATE TABLE llx_supplier_proposal_extrafields;
TRUNCATE TABLE llx_supplier_proposal;

TRUNCATE TABLE llx_facturedet_extrafields;
TRUNCATE TABLE llx_facturedet;
TRUNCATE TABLE llx_facture_extrafields;
TRUNCATE TABLE llx_facture;
TRUNCATE TABLE llx_facturedet_rec_extrafields;
TRUNCATE TABLE llx_facturedet_rec;
TRUNCATE TABLE llx_facture_rec_extrafields;
TRUNCATE TABLE llx_facture_rec;
TRUNCATE TABLE llx_facture_fourn_det_extrafields;
TRUNCATE TABLE llx_facture_fourn_det;
TRUNCATE TABLE llx_facture_fourn_extrafields;
TRUNCATE TABLE llx_facture_fourn;
TRUNCATE TABLE llx_facture_fourn_det_rec_extrafields;
TRUNCATE TABLE llx_facture_fourn_det_rec;
TRUNCATE TABLE llx_facture_fourn_rec_extrafields;
TRUNCATE TABLE llx_facture_fourn_rec;

TRUNCATE TABLE llx_societe_remise_except;

TRUNCATE TABLE llx_stock_mouvement_extrafields;
TRUNCATE TABLE llx_stock_mouvement;
TRUNCATE TABLE llx_product_batch;
TRUNCATE TABLE llx_product_stock;
-- llx_product.stock is a denormalized stock cache in this installation.
-- Keep the product master itself, but reset quantity and weighted valuation.
UPDATE llx_product SET stock = 0, pmp = 0;
TRUNCATE TABLE llx_inventorydet;
TRUNCATE TABLE llx_inventory_extrafields;
TRUNCATE TABLE llx_inventory;
TRUNCATE TABLE llx_stocktransfer_stocktransferline_extrafields;
TRUNCATE TABLE llx_stocktransfer_stocktransferline;
TRUNCATE TABLE llx_stocktransfer_stocktransfer_extrafields;
TRUNCATE TABLE llx_stocktransfer_stocktransfer;

TRUNCATE TABLE llx_ob_posting_link;
TRUNCATE TABLE llx_ob_stock_line;
TRUNCATE TABLE llx_ob_party_line;
TRUNCATE TABLE llx_ob_gl_line;
TRUNCATE TABLE llx_ob_batch;
TRUNCATE TABLE llx_pi_match;
TRUNCATE TABLE llx_pi_document_action;
TRUNCATE TABLE llx_pi_entry;
TRUNCATE TABLE llx_pi_balance;

TRUNCATE TABLE llx_expensereport_det_extrafields;
TRUNCATE TABLE llx_expensereport_det;
TRUNCATE TABLE llx_expensereport_extrafields;
TRUNCATE TABLE llx_expensereport;
TRUNCATE TABLE llx_holiday_logs;
TRUNCATE TABLE llx_holiday_extrafields;
TRUNCATE TABLE llx_holiday;
TRUNCATE TABLE llx_salary_extrafields;
TRUNCATE TABLE llx_salary;
TRUNCATE TABLE llx_loan_schedule;
TRUNCATE TABLE llx_loan;
TRUNCATE TABLE llx_chargesociales;
TRUNCATE TABLE llx_don_extrafields;
TRUNCATE TABLE llx_don;

TRUNCATE TABLE llx_fichinterdet_extrafields;
TRUNCATE TABLE llx_fichinterdet;
TRUNCATE TABLE llx_fichinter_extrafields;
TRUNCATE TABLE llx_fichinter;
TRUNCATE TABLE llx_contratdet_extrafields;
TRUNCATE TABLE llx_contratdet;
TRUNCATE TABLE llx_contrat_extrafields;
TRUNCATE TABLE llx_contrat;
TRUNCATE TABLE llx_mrp_production_extrafields;
TRUNCATE TABLE llx_mrp_production;
TRUNCATE TABLE llx_mrp_mo_extrafields;
TRUNCATE TABLE llx_mrp_mo;
TRUNCATE TABLE llx_ticket_extrafields;
TRUNCATE TABLE llx_ticket;

SET FOREIGN_KEY_CHECKS = 1;
