<?php
namespace App\Http\Controllers\Comunidad;

use App\Http\Controllers\Controller;
use App\Models\RoulettePrize;
use App\Models\RouletteSpin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouletteController extends Controller
{
    // ==================================================================
    // GET /api/roulette/prizes
    // ==================================================================
    public function prizes()
    {
        $prizes = \App\Models\RoulettePrize::eligible()
            ->map(fn($p) => [
                'id'     => $p->id,
                'label'  => $p->label,
                'color'  => $p->color,
                'weight' => (int) $p->weight,
            ])
            ->values();

        return response()->json([
            'prizes'         => $prizes,
            'total_segments' => $prizes->count(),
        ]);
    }

    // ==================================================================
    // GET /api/roulette/status
    // ==================================================================
    public function status(Request $request)
{
    $userId  = $request->user()->id;
    $weekKey = roulette_current_week_key();

    $lastSpin = RouletteSpin::where('user_id', $userId)
        ->orderByDesc('spin_at')
        ->first();

    $thisSpin = RouletteSpin::where('user_id', $userId)
        ->where('week_key', $weekKey)
        ->first();

    $baseStatus = roulette_payload_status($lastSpin, $thisSpin, $weekKey);

    $restantes = roulette_premios_restantes_semana($weekKey);
    $cap       = roulette_weekly_prize_cap();

    $baseStatus['prizes_available'] = $restantes > 0;
    $baseStatus['prizes_remaining'] = $restantes === PHP_INT_MAX ? null : $restantes;
    $baseStatus['weekly_cap']       = $cap <= 0 ? null : $cap;

    return response()->json($baseStatus);
}

    // ==================================================================
    // POST /api/roulette/spin
    // ==================================================================
    public function spin(Request $request)
{
    $userId  = $request->user()->id;
    $weekKey = roulette_current_week_key();

    // 1) ¿Ya giró esta semana?
    if (roulette_ya_giro_esta_semana($userId)) {
        return response()->json([
            'error'        => 'already_spun',
            'message'      => 'Ya giraste la ruleta esta semana. Vuelve el lunes.',
            'next_spin_at' => roulette_next_spin_at(),
        ], 429);
    }

    // 2) ¿Ya se entregó el premio semanal (cap alcanzado)?
    if (!roulette_hay_premios_disponibles($weekKey)) {
        return response()->json([
            'error'        => 'weekly_prize_taken',
            'message'      => 'El premio de esta semana ya fue reclamado. ¡Vuelve el lunes!',
            'next_spin_at' => roulette_next_spin_at(),
        ], 429);
    }

    // 3) Filtro de premios (racha + cuota)
    $prizes = roulette_premios_elegibles_para($userId, $weekKey);
    if ($prizes->isEmpty()) {
        return response()->json([
            'error'   => 'no_prizes',
            'message' => 'No hay premios disponibles. Intenta más tarde.',
        ], 409);
    }

    // 4) Selección ponderada (fuera de la transacción)
    $winningPrize = roulette_seleccionar_premio_ponderado($prizes);
    if (!$winningPrize) {
        return response()->json([
            'error'   => 'no_prizes',
            'message' => 'No hay premios disponibles. Intenta más tarde.',
        ], 409);
    }

    // 5) Transacción atómica (con re-check del cap dentro)
    try {
        $result = DB::transaction(function () use ($userId, $weekKey, $winningPrize) {

            // 🔒 Re-check DENTRO de la transacción para evitar race conditions.
            $cap = roulette_weekly_prize_cap();
            if ($cap > 0) {
                $entregados = RouletteSpin::where('prize_type', '!=', 'none')
                    ->where('week_key', $weekKey)
                    ->lockForUpdate()
                    ->count();

                if ($entregados >= $cap) {
                    throw new \RuntimeException('weekly_prize_taken');
                }
            }

            $prize = RoulettePrize::where('id', $winningPrize->id)
                ->lockForUpdate()
                ->first();

            if (!$prize || !$prize->is_active) {
                throw new \RuntimeException('prize_unavailable');
            }
            if ($prize->stock !== -1 && $prize->stock <= 0) {
                throw new \RuntimeException('prize_out_of_stock');
            }

            $spinData = [
                'user_id'      => $userId,
                'prize_id'     => $prize->id,
                'prize_type'   => $prize->prize_type,
                'reward_value' => $prize->reward_value,
                'week_key'     => $weekKey,
                'credited'     => 0,
                'spin_at'      => now(),
            ];

            if ($prize->prize_type === 'recharge' && (float)$prize->reward_value > 0) {
                if (roulette_acreditar_recarga($userId, (float)$prize->reward_value)) {
                    $spinData['credited']    = 1;
                    $spinData['credited_at'] = now();
                }
            }

            $spin = RouletteSpin::create($spinData);

            if ($prize->stock !== -1) {
                $prize->decrement('stock');
            }

            return ['spin' => $spin, 'prize' => $prize];
        });
    } catch (\Illuminate\Database\QueryException $e) {
        if ($e->getCode() === '23000') {
            return response()->json([
                'error'        => 'already_spun',
                'message'      => 'Ya giraste la ruleta esta semana.',
                'next_spin_at' => roulette_next_spin_at(),
            ], 429);
        }
        throw $e;
    } catch (\RuntimeException $e) {
        if ($e->getMessage() === 'weekly_prize_taken') {
            return response()->json([
                'error'        => 'weekly_prize_taken',
                'message'      => 'El premio de esta semana ya fue reclamado. ¡Vuelve el lunes!',
                'next_spin_at' => roulette_next_spin_at(),
            ], 429);
        }
        return response()->json([
            'error'   => $e->getMessage(),
            'message' => 'El premio se agotó. Intenta de nuevo.',
        ], 409);
    }

    // 6) Posición del segmento ganador
    $ordered = $prizes->values();
    $index   = $ordered->search(fn($p) => $p->id === $result['prize']->id);

    return response()->json(
        roulette_payload_spin_result(
            $result['prize'],
            $index === false ? 0 : $index,
            $ordered->count()
        ),
        201
    );
}
}
