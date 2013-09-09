<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copytight (C) 2005-2009 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copytight (C) 2012	   Juanjo Menent        <jmenent@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *		\file       htdocs/compta/bank/virement.php
 *		\ingroup    banque
 *		\brief      Page de saisie d'un virement
 */

require 'pre.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';

$langs->load("banks");

if (! $user->rights->banque->transfer)
  accessforbidden();

$action = GETPOST('action','alpha');


/*
 * Action ajout d'un virement
 */
if ($action == 'add')
{
	$langs->load("errors");

	$mesg='';
	$dateo = dol_mktime(12,0,0,GETPOST('remonth','int'),GETPOST('reday','int'),GETPOST('reyear','int'));
	$label = GETPOST('label','alpha');
	$amount= GETPOST('amount','int');
	$num_chq= GETPOST('num_chq','alpha');
	$amount2= GETPOST('amount2','int');

        $account2A = GETPOST('account_to2','int');
        if (! $account2A) {
            $account2A = -1;
        }
	if (! $label)
	{
		$error=1;
		$mesg.="<div class=\"error\">".$langs->trans("ErrorFieldRequired",$langs->transnoentities("Description"))."</div>";
	}
	if (! $amount)
	{
		$error=1;
		$mesg.="<div class=\"error\">".$langs->trans("ErrorFieldRequired",$langs->transnoentities("Amount"))."</div>";
	}
	if (! GETPOST('account_from','int'))
	{
		$error=1;
		$mesg.="<div class=\"error\">".$langs->trans("ErrorFieldRequired",$langs->transnoentities("TransferFrom"))."</div>";
	}
	if (! GETPOST('account_to','int'))
	{
		$error=1;
		$mesg.="<div class=\"error\">".$langs->trans("ErrorFieldRequired",$langs->transnoentities("TransferTo"))."</div>";
	}
        if ($account2A>-1) {
            if (! $amount2)
            {
                $error=1;
                $mesg.="<div class=\"error\">".$langs->trans("ErrorFieldRequired",$langs->transnoentities("Amount")).GETPOST('account_to2','int')." Commission</div>";
            }

        }
	if (! $error)
	{
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

		$accountfrom=new Account($db);
		$accountfrom->fetch(GETPOST('account_from','int'));

		$accountto=new Account($db);
		$accountto->fetch(GETPOST('account_to','int'));

                if ($account2A>-1) {
                    $accountto2 = new Account($db);
                    $accountto2->fetch(GETPOST('account_to2','int'));
                    if ($accountto2->id == $accountfrom->id) {
                        $error=1;
			$mesg.="<div class=\"error\">".$langs->trans("ErrorFromToAccountsMustDiffers")."</div>";
                    }
                }
		if ((! $error) && ($accountto->id != $accountfrom->id))
		{
			$db->begin();

			$error=0;
			$bank_line_id_from=0;
			$bank_line_id_to=0;
			$bank_line_id_from2=0;
			$bank_line_id_to2=0;
			$result=0;

			// By default, electronic transfert from bank to bank
			$typefrom='PRE';
			$typeto='VIR';
			if ($accountto->courant == 2 || $accountfrom->courant == 2)
			{
				// This is transfert of change
				$typefrom='LIQ';
				$typeto='LIQ';
			}
                        if ($account2A>-1) {
                            // By default, electronic transfert from bank to bank
                            $typefrom2='PRE';
                            $typeto2='VIR';
                            if ($accountto2->courant == 2 || $accountfrom->courant == 2)
                            {
                                // This is transfert of change
                                $typefrom2='LIQ';
                                $typeto2='LIQ';
                            }
                        }

			if (! $error) $bank_line_id_from = $accountfrom->addline($dateo, $typefrom, $label, -1*price2num($amount), $num_chq, '', $user);
			if (! ($bank_line_id_from > 0)) $error++;
			if (! $error) $bank_line_id_to = $accountto->addline($dateo, $typeto, $label, price2num($amount), $num_chq, '', $user);
			if (! ($bank_line_id_to > 0)) $error++;

                        if ($account2A>-1) {
                            if (! $error) $bank_line_id_from2 = $accountfrom->addline($dateo, $typefrom2, $label, -1*price2num($amount2), $num_chq, '', $user);
                            if (! ($bank_line_id_from2 > 0)) $error++;
                            if (! $error) $bank_line_id_to2 = $accountto2->addline($dateo, $typeto2, $label, price2num($amount2), $num_chq, '', $user);
                            if (! ($bank_line_id_to2 > 0)) $error++;
                        }


                        if (! $error) $result=$accountfrom->add_url_line($bank_line_id_from, $bank_line_id_to, DOL_URL_ROOT.'/compta/bank/ligne.php?rowid=', '(banktransfert)', 'banktransfert');
			if (! ($result > 0)) $error++;
                        if (! $error) $result=$accountto->add_url_line($bank_line_id_to, $bank_line_id_from, DOL_URL_ROOT.'/compta/bank/ligne.php?rowid=', '(banktransfert)', 'banktransfert');
			if (! ($result > 0)) $error++;

                        if ($account2A>-1) {
                            if (! $error) $result=$accountfrom->add_url_line($bank_line_id_from2, $bank_line_id_to2, DOL_URL_ROOT.'/compta/bank/ligne.php?rowid=', '(banktransfert)', 'banktransfert');
                            if (! ($result > 0)) $error++;
                            if (! $error) $result=$accountto2->add_url_line($bank_line_id_to2, $bank_line_id_from2, DOL_URL_ROOT.'/compta/bank/ligne.php?rowid=', '(banktransfert)', 'banktransfert');
                            if (! ($result > 0)) $error++;
                        }

			if (! $error)
			{
				$mesg.="<div class=\"ok\">";
				$mesg.=$langs->trans("TransferFromToDone","<a href=\"account.php?account=".$accountfrom->id."\">".$accountfrom->label."</a>","<a href=\"account.php?account=".$accountto->id."\">".$accountto->label."</a>",$amount,$langs->transnoentities("Currency".$conf->currency));
                                if ($account2A>-1) {
                                    $mesg.="<br/>";
                                    $mesg.=$langs->trans("TransferFromToDone","<a href=\"account.php?account=".$accountfrom->id."\">".$accountfrom->label."</a>","<a href=\"account.php?account=".$accountto2->id."\">".$accountto2->label."</a>",$amount2,$langs->transnoentities("Currency".$conf->currency));
                                }
				$mesg.="</div>";
				$db->commit();
			}
			else
			{
				$mesg.="<div class=\"error\">".$accountfrom->error.' '.$accountto->error."</div>";
				$db->rollback();
			}
		}
		else
		{
			$mesg.="<div class=\"error\">".$langs->trans("ErrorFromToAccountsMustDiffers")."</div>";
		}
	}
}



/*
 * Affichage
 */

llxHeader();

$form=new Form($db);

$account_from='';
if (GETPOST('account_from','int')) {
    $account_from =	GETPOST('account_from','int');
}
$account_to='';
if (GETPOST('account_to','int')) {
    $account_to =	GETPOST('account_to','int');
}
$account_to2=''; //+fte second compte -> commission
if (GETPOST('account_to2','int')) {
    $account_to2 =	GETPOST('account_to2','int');
}
$label='';
if (GETPOST('label','alpha')) {
    $label = GETPOST('label','alpha');
}
$amount='';
if ( GETPOST('amount','int')) {
    $amount= GETPOST('amount','int');
}
$amount2=''; //+fte seconde value -> commission
if ( GETPOST('amount2','int')) {
    $amount2= GETPOST('amount2','int');
}
$num_chq=''; //+fte
if (GETPOST('num_chq','alpha')) {
    $num_chq = GETPOST('num_chq','alpha'); //+fte
}

if($error)
{
	$account_from =	GETPOST('account_from','int');
	$account_to	= GETPOST('account_to','int');
	$account_to2	= GETPOST('account_to2','int');//+fte
	$label = GETPOST('label','alpha');
	$amount = GETPOST('amount','int');
	$amount2 = GETPOST('amount2','int');//+fte
        $num_chq = GETPOST('num_chq','alpha'); //+fte
}

print_fiche_titre($langs->trans("BankTransfer"));

dol_htmloutput_mesg($mesg);

print $langs->trans("TransferDesc");
print "<br><br>";

print "<form name='add' method=\"post\" action=\"virement.php\">";
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<input type="hidden" name="action" value="add">';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("TransferFrom").'</td><td>'.$langs->trans("TransferTo").'<br/>Sous Opération</td><td>'.$langs->trans("Date").'</td><td>'.$langs->trans("Description").'</td><td>'.$langs->trans("Amount").'<br/></td>';
print '<td>Num.Vir</td>'; //+fte
print '</tr>';

$var=false;
print '<tr '.$bc[$var].'><td>';
print $form->select_comptes($account_from,'account_from',0,'',1);
print "</td>";

print "<td>\n";
print $form->select_comptes($account_to,'account_to',0,'',1);
print "</td>\n";

print "<td>";
$form->select_date($dateo,'','','','','add');
print "</td>\n";
print '<td><input name="label" class="flat" type="text" size="40" value="'.$label.'"></td>';
print '<td><input name="amount" class="flat" type="text" size="8" value="'.$amount.'"></td>';
print '<td><input name="num_chq" class="flat" type="text" size="20" value="'.$num_chq.'"></td>'; //+fte

print "</tr>\n";
print '<tr '.$bc[!$var].'>';
print "<td>&nbsp;</td>\n";
print "<td>\n";
print $form->select_comptes($account_to2,'account_to2',0,'',1);
print "</td>\n";
print "<td></td>\n";
print "<td></td>\n";
print '<td><input name="amount2" class="flat" type="text" size="8" value="'.$amount2.'"></td>';
print "<td></td>\n";

print '</tr>';
print "</table>";

print '<br><center><input type="submit" class="button" value="'.$langs->trans("Add").'"></center>';

print "</form>";

$db->close();

llxFooter();
?>
