<?php

require_once __DIR__.'/lib/common.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

$pieceNum = GETPOSTINT('piece_num');
if ($pieceNum <= 0 || (!$user->admin && !$user->hasRight('accounting', 'mouvements', 'creer'))) {
	accessforbidden();
}

$returnUrl = DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?piece_num='.$pieceNum;
$object = new BookKeeping($db);
if ($object->fetchPerMvt($pieceNum) <= 0 || empty($object->piece_num)) {
	accessforbidden('Transaksi jurnal tidak ditemukan.');
}
$object->fetchAllPerMvt($pieceNum);

function piJournalRows($pi, $pieceNum, $lock = false)
{
	return $pi->rows('SELECT * FROM '.$pi->table('accounting_bookkeeping').' WHERE entity='.$pi->entity.' AND piece_num='.(int) $pieceNum.' ORDER BY rowid'.($lock ? ' FOR UPDATE' : ''));
}

function piAssertJournalCanReopen($rows, $object)
{
	if (!$rows) throw new DomainException('Baris jurnal tidak ditemukan.');
	$allowedTypes = array('', 'manual', 'various');
	foreach ($rows as $row) {
		if (!empty($row->date_export)) {
			throw new DomainException('Journal sudah diekspor dan tidak dapat dibuka kembali. Buat jurnal koreksi/reversal.');
		}
		if (!in_array((string) $row->doc_type, $allowedTypes, true)) {
			throw new DomainException('Journal otomatis harus direvisi dari dokumen sumbernya ('.($row->doc_type ?: 'unknown').').');
		}
	}
	if (empty($object->date_validation)) {
		throw new DomainException('Journal ini masih Draft dan dapat langsung diedit tanpa proses revisi.');
	}
	if ($object->validBookkeepingDate($object->doc_date) <= 0) {
		throw new DomainException('Periode akuntansi jurnal sudah ditutup sehingga journal tidak dapat dibuka kembali. Buat jurnal koreksi pada periode aktif.');
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	piPost();
	$reason = trim(GETPOST('reason', 'restricthtml'));
	if (mb_strlen($reason) < 5) {
		setEventMessages('Alasan pembatalan/revisi wajib diisi minimal 5 karakter.', null, 'errors');
	} else {
		try {
			$pi->transaction(function () use ($pi, $pieceNum, $object, $user, $reason) {
				$rows = piJournalRows($pi, $pieceNum, true);
				piAssertJournalCanReopen($rows, $object);
				$snapshot = json_encode(array('version' => 1, 'piece_num' => $pieceNum, 'lines' => array_map('get_object_vars', $rows)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if ($snapshot === false) throw new DomainException('Snapshot journal gagal dibuat.');
				$pi->q('UPDATE '.$pi->table('accounting_bookkeeping').' SET date_validated=NULL,fk_user=NULL,fk_user_modif='.(int) $user->id.' WHERE entity='.$pi->entity.' AND piece_num='.(int) $pieceNum);
				$pi->recordDocumentAction('accounting_journal', $pieceNum, (string) ($object->ref ?: 'Jurnal #'.$pieceNum), 'amend', $reason, $user, $snapshot);
			});
			setEventMessages('Journal berhasil dikembalikan ke Draft dan sekarang dapat direvisi.', null, 'mesgs');
			header('Location: '.$returnUrl);
			exit;
		} catch (Throwable $e) {
			setEventMessages($e->getMessage(), null, 'errors');
		}
	}
}

$rows = piJournalRows($pi, $pieceNum);
$canReopen = true;
$blockMessage = '';
try {
	piAssertJournalCanReopen($rows, $object);
} catch (Throwable $e) {
	$canReopen = false;
	$blockMessage = $e->getMessage();
}

piHeader('Cancel & Amend Accounting Journal');
print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
print '<div class="info">Journal: <b>'.piEscape($object->ref ?: '#'.$pieceNum).'</b> — transaksi #'.(int) $pieceNum.'. Snapshot seluruh baris akan disimpan sebelum journal dibuka kembali.</div>';
if (!$canReopen) {
	print '<div class="warning">'.piEscape($blockMessage).'</div>';
	print '<p><a class="button" href="'.piEscape($returnUrl).'">Kembali</a></p></div>';
	piEnd();
	exit;
}
print '<form method="post" class="pi-form">';
piToken('confirm');
print '<input type="hidden" name="piece_num" value="'.(int) $pieceNum.'">';
print '<label for="reason">Alasan pembatalan/revisi</label><textarea class="minwidth500" id="reason" name="reason" rows="4" minlength="5" maxlength="1000" required>'.piEscape(GETPOST('reason', 'restricthtml')).'</textarea>';
print '<p class="pi-muted">Tanggal validasi akan dilepas sehingga journal kembali editable. Journal otomatis dan journal yang sudah diekspor tidak dapat dibuka melalui proses ini.</p>';
print '<button class="button button-save" type="submit">Konfirmasi Cancel & Amend</button> <a class="button" href="'.piEscape($returnUrl).'">Kembali</a></form></div>';
piEnd();
