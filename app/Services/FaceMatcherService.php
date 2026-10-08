<?php

namespace App\Services;

use App\Models\FaceDescriptor;

class FaceMatcherService
{
    /** Jarak Euclidean antara dua descriptor 128 dimensi. */
    public static function jarak(array $a, array $b): float
    {
        $total = 0.0;

        for ($i = 0; $i < 128; $i++) {
            $selisih = (float) $a[$i] - (float) $b[$i];
            $total += $selisih * $selisih;
        }

        return sqrt($total);
    }

    /**
     * Cari pegawai aktif yang wajahnya paling mirip (pencocokan 1:N).
     *
     * @return array{user_id:int, jarak:float}|null
     */
    public function cocokkan(array $descriptor, ?int $kecualiUserId = null): ?array
    {
        $terbaik = null;

        $semua = FaceDescriptor::query()
            ->whereHas('user', fn ($q) => $q->where('status', 'Aktif'))
            ->when($kecualiUserId, fn ($q, $id) => $q->where('user_id', '!=', $id))
            ->get(['id', 'user_id', 'descriptor']);

        foreach ($semua as $baris) {
            $jarak = self::jarak($descriptor, $baris->descriptor);

            if ($terbaik === null || $jarak < $terbaik['jarak']) {
                $terbaik = ['user_id' => $baris->user_id, 'jarak' => round($jarak, 3)];
            }
        }

        return $terbaik;
    }
}