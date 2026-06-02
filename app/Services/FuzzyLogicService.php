<?php

namespace App\Services;

use App\Models\FuzzyConfig;

/**
 * ============================================================
 *  FuzzyLogicService — Server-side Fuzzy Logic Engine
 * ============================================================
 *  Implementasi IDENTIK dengan fuzzy logic di ESP32 firmware
 *  (esp32_sena_full.ino) agar server dan ESP32 selalu sinkron.
 *
 *  Input Variables:
 *    1. Kelembapan Tanah (Soil Moisture) — 0–100%
 *    2. pH Tanah — 0–14
 *
 *  Output:
 *    Crisp value 0.0–1.0 (threshold > 0.5 → Pompa ON)
 *
 *  Method: Mamdani inference, MIN operator, Weighted Average defuzzification
 * ============================================================
 */
class FuzzyLogicService
{
    // ─── Membership Functions: Kelembapan Tanah ─────────────

    /**
     * Membership Kering (Dry): trapezoid turun di kering_max
     * Full membership jika m <= (kering_max - 10)
     * Zero jika m >= kering_max
     */
    public static function membershipKering(float $moisture, FuzzyConfig $config): float
    {
        $peak = $config->moisture_kering_max - 10.0;
        if ($moisture <= $peak) return 1.0;
        if ($moisture >= $config->moisture_kering_max) return 0.0;
        return ($config->moisture_kering_max - $moisture) / ($config->moisture_kering_max - $peak);
    }

    /**
     * Membership Normal: segitiga simetris antara normal_min dan normal_max
     * Peak di titik tengah (normal_min + normal_max) / 2
     */
    public static function membershipNormal(float $moisture, FuzzyConfig $config): float
    {
        $center = ($config->moisture_normal_min + $config->moisture_normal_max) / 2.0;
        if ($moisture <= $config->moisture_normal_min || $moisture >= $config->moisture_normal_max) return 0.0;
        if ($moisture <= $center) {
            return ($moisture - $config->moisture_normal_min) / ($center - $config->moisture_normal_min);
        }
        return ($config->moisture_normal_max - $moisture) / ($config->moisture_normal_max - $center);
    }

    /**
     * Membership Basah (Wet): trapezoid naik di basah_min
     * Full membership jika m >= (basah_min + 10)
     * Zero jika m <= basah_min
     */
    public static function membershipBasah(float $moisture, FuzzyConfig $config): float
    {
        $peak = $config->moisture_basah_min + 10.0;
        if ($moisture >= $peak) return 1.0;
        if ($moisture <= $config->moisture_basah_min) return 0.0;
        return ($moisture - $config->moisture_basah_min) / ($peak - $config->moisture_basah_min);
    }

    // ─── Membership Functions: pH Tanah ─────────────────────

    /**
     * Membership Asam (Acidic): trapezoid turun di ph_asam_max
     * Full membership jika ph <= (ph_asam_max - 1)
     * Zero jika ph >= ph_asam_max
     */
    public static function membershipPhAsam(float $ph, FuzzyConfig $config): float
    {
        $peak = $config->ph_asam_max - 1.0;
        if ($ph <= $peak) return 1.0;
        if ($ph >= $config->ph_asam_max) return 0.0;
        return ($config->ph_asam_max - $ph) / ($config->ph_asam_max - $peak);
    }

    /**
     * Membership Netral (Neutral): segitiga simetris antara ph_netral_min dan ph_netral_max
     * Peak di titik tengah
     */
    public static function membershipPhNetral(float $ph, FuzzyConfig $config): float
    {
        $center = ($config->ph_netral_min + $config->ph_netral_max) / 2.0;
        if ($ph <= $config->ph_netral_min || $ph >= $config->ph_netral_max) return 0.0;
        if ($ph <= $center) {
            return ($ph - $config->ph_netral_min) / ($center - $config->ph_netral_min);
        }
        return ($config->ph_netral_max - $ph) / ($config->ph_netral_max - $center);
    }

    /**
     * Membership Basa (Alkaline): trapezoid naik di ph_basa_min
     * Full membership jika ph >= (ph_basa_min + 1)
     * Zero jika ph <= ph_basa_min
     */
    public static function membershipPhBasa(float $ph, FuzzyConfig $config): float
    {
        $peak = $config->ph_basa_min + 1.0;
        if ($ph >= $peak) return 1.0;
        if ($ph <= $config->ph_basa_min) return 0.0;
        return ($ph - $config->ph_basa_min) / ($peak - $config->ph_basa_min);
    }

    // ─── Fuzzy Inference Engine ─────────────────────────────

    /**
     * Jalankan evaluasi Fuzzy Logic Mamdani.
     *
     * Rule Base (9 rules, MIN operator):
     * ─────────────────────────────────────────────────────────
     * R1: IF Moisture=Kering  AND pH=Netral → Pompa=TINGGI  (1.0)
     * R2: IF Moisture=Kering  AND pH=Asam   → Pompa=SEDANG  (0.7)
     * R3: IF Moisture=Kering  AND pH=Basa   → Pompa=SEDANG  (0.7)
     * R4: IF Moisture=Normal  AND pH=Netral → Pompa=RENDAH  (0.2)
     * R5: IF Moisture=Normal  AND pH=Asam   → Pompa=SEDANG  (0.5)
     * R6: IF Moisture=Normal  AND pH=Basa   → Pompa=SEDANG  (0.5)
     * R7: IF Moisture=Basah   AND pH=Netral → Pompa=MATI    (0.0)
     * R8: IF Moisture=Basah   AND pH=Asam   → Pompa=MATI    (0.0)
     * R9: IF Moisture=Basah   AND pH=Basa   → Pompa=MATI    (0.0)
     *
     * Defuzzifikasi: Weighted Average (Centroid simplified)
     *
     * @param  float        $soilMoisture  Kelembapan tanah (0–100%)
     * @param  float        $ph            Nilai pH tanah (0–14)
     * @param  FuzzyConfig  $config        Parameter konfigurasi fuzzy
     * @return array  ['crisp_output' => float, 'pump_decision' => bool, 'memberships' => [...], 'rules' => [...]]
     */
    public static function evaluate(float $soilMoisture, float $ph, FuzzyConfig $config): array
    {
        // ── Fuzzifikasi Kelembapan Tanah ────────────────────
        $mKering = self::membershipKering($soilMoisture, $config);
        $mNormal = self::membershipNormal($soilMoisture, $config);
        $mBasah  = self::membershipBasah($soilMoisture, $config);

        // ── Fuzzifikasi pH ──────────────────────────────────
        $pAsam   = self::membershipPhAsam($ph, $config);
        $pNetral = self::membershipPhNetral($ph, $config);
        $pBasa   = self::membershipPhBasa($ph, $config);

        // ── Evaluasi Rules (MIN operator – Mamdani) ─────────
        $r1 = min($mKering, $pNetral); // → Tinggi (1.0)
        $r2 = min($mKering, $pAsam);   // → Sedang (0.7)
        $r3 = min($mKering, $pBasa);   // → Sedang (0.7)
        $r4 = min($mNormal, $pNetral); // → Rendah (0.2)
        $r5 = min($mNormal, $pAsam);   // → Sedang (0.5)
        $r6 = min($mNormal, $pBasa);   // → Sedang (0.5)
        $r7 = min($mBasah,  $pNetral); // → Mati   (0.0)
        $r8 = min($mBasah,  $pAsam);   // → Mati   (0.0)
        $r9 = min($mBasah,  $pBasa);   // → Mati   (0.0)

        // ── Output Singleton Values (identik dengan ESP32) ──
        $OUT_TINGGI = 1.0;
        $OUT_SEDANG_HIGH = 0.7;  // Untuk R2, R3 (kering + pH tidak ideal)
        $OUT_SEDANG_MID  = 0.5;  // Untuk R5, R6 (normal + pH tidak ideal)
        $OUT_RENDAH = 0.2;
        $OUT_MATI   = 0.0;

        // ── Defuzzifikasi: Weighted Average ─────────────────
        $numerator = ($r1 * $OUT_TINGGI)
                   + ($r2 * $OUT_SEDANG_HIGH)
                   + ($r3 * $OUT_SEDANG_HIGH)
                   + ($r4 * $OUT_RENDAH)
                   + ($r5 * $OUT_SEDANG_MID)
                   + ($r6 * $OUT_SEDANG_MID)
                   + ($r7 * $OUT_MATI)
                   + ($r8 * $OUT_MATI)
                   + ($r9 * $OUT_MATI);

        $denominator = $r1 + $r2 + $r3 + $r4 + $r5 + $r6 + $r7 + $r8 + $r9;

        $crispOutput = $denominator > 0 ? $numerator / $denominator : 0.0;
        $pumpDecision = $crispOutput > 0.5;

        return [
            'crisp_output'  => round($crispOutput, 4),
            'pump_decision' => $pumpDecision,
            'memberships'   => [
                'soil' => [
                    'kering' => round($mKering, 4),
                    'normal' => round($mNormal, 4),
                    'basah'  => round($mBasah, 4),
                ],
                'ph' => [
                    'asam'   => round($pAsam, 4),
                    'netral' => round($pNetral, 4),
                    'basa'   => round($pBasa, 4),
                ],
            ],
            'rules' => [
                'R1_kering_netral' => round($r1, 4),
                'R2_kering_asam'   => round($r2, 4),
                'R3_kering_basa'   => round($r3, 4),
                'R4_normal_netral' => round($r4, 4),
                'R5_normal_asam'   => round($r5, 4),
                'R6_normal_basa'   => round($r6, 4),
                'R7_basah_netral'  => round($r7, 4),
                'R8_basah_asam'    => round($r8, 4),
                'R9_basah_basa'    => round($r9, 4),
            ],
        ];
    }

    /**
     * Evaluasi sederhana — hanya return crisp output (0.0–1.0).
     * Shortcut untuk use case yang tidak perlu detail membership/rules.
     */
    public static function evaluateSimple(float $soilMoisture, float $ph, FuzzyConfig $config): float
    {
        return self::evaluate($soilMoisture, $ph, $config)['crisp_output'];
    }

    /**
     * Evaluasi dan return boolean pompa ON/OFF.
     */
    public static function shouldPumpOn(float $soilMoisture, float $ph, FuzzyConfig $config): bool
    {
        return self::evaluate($soilMoisture, $ph, $config)['pump_decision'];
    }
}
