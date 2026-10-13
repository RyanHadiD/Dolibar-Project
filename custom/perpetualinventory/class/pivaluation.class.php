<?php
/** Moving-average valuation. Amounts rounded at eight decimals; GL uses currency precision. */
class PiValuation
{
	public static function move($qty, $value, $delta, $incomingCost = null, $forcedValue = null)
	{
		foreach (array($qty, $value, $delta) as $number) {
			if (!is_numeric($number) || !is_finite((float) $number)) {
				throw new InvalidArgumentException('Angka stok tidak valid.');
			}
		}
		$qty = round((float) $qty, 8);
		$value = round((float) $value, 8);
		$delta = round((float) $delta, 8);
		if ($qty < 0 || $value < 0 || ($qty == 0 && $value != 0) || $delta == 0 || round($qty + $delta, 8) < 0) {
			throw new DomainException('Stok negatif, nilai saldo tidak konsisten, atau jumlah nol.');
		}
		if ($delta > 0 && (!is_numeric($incomingCost) || !is_finite((float) $incomingCost) || $incomingCost < 0)) {
			throw new DomainException('Biaya per unit barang masuk wajib diisi dan tidak boleh negatif.');
		}
		$cost = $delta > 0 ? (float) $incomingCost : $value / $qty;
		$change = round($delta * $cost, 8);
		if ($forcedValue !== null) {
			if (!is_numeric($forcedValue) || !is_finite((float) $forcedValue) || $forcedValue * $delta < 0) {
				throw new DomainException('Nilai pembalik tidak valid.');
			}
			$change = round((float) $forcedValue, 8);
			$cost = abs($change / $delta);
		}
		$newQty = round($qty + $delta, 8);
		if ($newQty == 0 && $forcedValue === null) {
			$change = -$value; // Consume the rounding remainder on the final issue.
		}
		$newValue = round($value + $change, 8);
		if ($newValue < -0.00000001 || ($newQty == 0 && abs($newValue) > 0.00000001)) {
			throw new DomainException('Retur/pembalik menyisakan nilai tanpa stok. Batalkan transaksi berikutnya dahulu.');
		}
		return array('qty' => $newQty, 'value' => max(0, $newValue), 'delta' => $change, 'cost' => $cost);
	}
}
