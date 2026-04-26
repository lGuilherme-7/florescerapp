<?php
/**
 * StreakService
 *
 * Encapsula toda a lógica de streak, tentativas (water_chances) e estados
 * visuais da planta. Usa diretamente os helpers globais do config/db.php
 * (dbRow, dbQuery, dbExec, dbBegin, dbCommit, dbRollback, getDB) — sem
 * duplicação de lógica de conexão.
 *
 * ── REGRAS DE NEGÓCIO ────────────────────────────────────────────────────
 *  1. Meta cumprida hoje  → streak +1 (somente se ontem também cumpriu OU
 *                           streak era 0), water_chances = 3.
 *                           Idempotente via last_streak_date.
 *  2. Meta NÃO cumprida  → verifica todos os dias perdidos desde
 *                           last_penalty_date até ontem e aplica uma
 *                           penalidade por dia (idempotente via
 *                           last_penalty_date).
 *  3. water_chances = 0  → streak zera, water_chances volta a 3,
 *                           last_death_date = hoje (banner persiste no dia).
 *  4. Usuário novo        → nenhuma penalidade aplicada.
 *
 * ── COLUNA NOVA NECESSÁRIA ───────────────────────────────────────────────
 *  ALTER TABLE `users`
 *      ADD COLUMN `last_death_date` DATE NULL DEFAULT NULL;
 *
 *  (last_streak_date e last_penalty_date já existem no seu banco)
 * ─────────────────────────────────────────────────────────────────────────
 */
class StreakService
{
    /**
     * Ponto de entrada único. Chame uma vez por carregamento de página.
     *
     * @param int    $userId
     * @param string $today            'Y-m-d'
     * @param string $yesterday        'Y-m-d'
     * @param bool   $goalReachedToday
     *
     * @return array{
     *   streak:         int,
     *   waterLeft:      int,
     *   dropsDisplay:   int,
     *   streakJustDied: bool,
     *   penaltyToday:   bool,
     *   seedState:      string
     * }
     */
    public static function syncAndRead(
        int    $userId,
        string $today,
        string $yesterday,
        bool   $goalReachedToday
    ): array {

        // ── 1. RECOMPENSA ─────────────────────────────────────────────────
        if ($goalReachedToday) {
            self::applyReward($userId, $today, $yesterday);
        }

        // ── 2. PENALIDADES ────────────────────────────────────────────────
        $streakJustDied = false;
        $penaltyToday   = false;
        if (!$goalReachedToday) {
            [$streakJustDied, $penaltyToday] = self::applyPenalties(
                $userId, $today, $yesterday
            );
        }

        // ── 3. LEITURA FINAL ──────────────────────────────────────────────
        $u         = self::readUser($userId);
        $streak    = (int)$u['streak'];
        $waterLeft = (int)$u['water_chances'];

        $dropsDisplay = $goalReachedToday ? 3 : $waterLeft;

        // Banner de morte persiste no mesmo dia via last_death_date
        $diedToday = ($u['last_death_date'] === $today);

        if ($diedToday || $streakJustDied) {
            $seedState = 'dead';
        } elseif (!$goalReachedToday && $waterLeft < 3) {
            $seedState = 'warning';
        } else {
            $seedState = 'healthy';
        }

        return compact(
            'streak', 'waterLeft', 'dropsDisplay',
            'streakJustDied', 'penaltyToday', 'seedState'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Incrementa o streak ao cumprir a meta.
     * Só considera sequência consecutiva se last_streak_date === $yesterday.
     * Caso contrário reinicia em 1 — a sequência estava quebrada.
     */
    private static function applyReward(
        int $userId, string $today, string $yesterday
    ): void {
        try {
            dbBegin();

            $u = self::lockUser($userId);

            // Idempotência: já recompensamos hoje
            if ($u['last_streak_date'] === $today) {
                dbCommit();
                return;
            }

            $currentStreak = (int)$u['streak'];
            $lastDate      = $u['last_streak_date'];

            // Consecutivo: último incremento foi ontem, ou está começando do zero
            if ($lastDate === $yesterday || $currentStreak === 0) {
                $newStreak = $currentStreak + 1;
            } else {
                // Havia dias perdidos antes de hoje — reinicia em 1
                $newStreak = 1;
            }

            dbExec(
                'UPDATE users
                    SET streak           = ?,
                        water_chances    = 3,
                        last_streak_date = ?
                  WHERE id = ?',
                [$newStreak, $today, $userId]
            );

            dbCommit();

        } catch (\Throwable $e) {
            dbRollback();
            error_log('[StreakService] applyReward falhou: ' . $e->getMessage());
        }
    }

    /**
     * Aplica UMA penalidade por dia perdido desde last_penalty_date até ontem.
     * Garante que usuários ausentes por vários dias recebam todas as penalidades.
     *
     * @return array{bool, bool}  [streakJustDied, algumaPenalidadeAplicada]
     */
    private static function applyPenalties(
        int $userId, string $today, string $yesterday
    ): array {

        $u = self::readUser($userId);

        // Determina de onde começar a verificar penalidades
        $lastPenalty = $u['last_penalty_date'];
        if ($lastPenalty === null) {
            $first = dbRow(
                'SELECT MIN(study_date) AS d FROM daily_summaries WHERE user_id = ?',
                [$userId]
            );
            if (!$first || !$first['d']) {
                return [false, false]; // usuário novo sem histórico
            }
            $startFrom = date('Y-m-d', strtotime($first['d'] . ' +1 day'));
        } else {
            $startFrom = date('Y-m-d', strtotime($lastPenalty . ' +1 day'));
        }

        // Nada a processar
        if ($startFrom > $yesterday) {
            return [false, false];
        }

        // Busca todos os registros do intervalo de uma vez
        $summaries = dbQuery(
            'SELECT study_date, goal_reached
               FROM daily_summaries
              WHERE user_id    = ?
                AND study_date >= ?
                AND study_date <= ?
              ORDER BY study_date ASC',
            [$userId, $startFrom, $yesterday]
        );
        $summaryMap = array_column($summaries, null, 'study_date');

        // Monta lista de dias sem meta cumprida
        $daysToPenalize = [];
        $cursor = $startFrom;
        while ($cursor <= $yesterday) {
            $rec = $summaryMap[$cursor] ?? null;
            if ($rec === null || !(bool)$rec['goal_reached']) {
                $daysToPenalize[] = $cursor;
            }
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        if (empty($daysToPenalize)) {
            return [false, false];
        }

        $streakJustDied = false;
        try {
            dbBegin();

            $u = self::lockUser($userId);

            // Dupla checagem pós-lock (evita race condition com duas abas abertas)
            if ($u['last_penalty_date'] === $today) {
                dbCommit();
                return [false, false];
            }

            $water  = (int)$u['water_chances'];
            $streak = (int)$u['streak'];

            foreach ($daysToPenalize as $_day) {
                $water--;
                if ($water <= 0) {
                    $water          = 3; // reseta para novo ciclo
                    $streak         = 0;
                    $streakJustDied = true;
                }
            }

            // Persiste last_death_date para o banner de morte aparecer
            // em recarregamentos posteriores no mesmo dia
            $deathDate = $streakJustDied ? $today : $u['last_death_date'];

            dbExec(
                'UPDATE users
                    SET water_chances     = ?,
                        streak            = ?,
                        last_penalty_date = ?,
                        last_death_date   = ?
                  WHERE id = ?',
                [$water, $streak, $today, $deathDate, $userId]
            );

            dbCommit();
            return [$streakJustDied, true];

        } catch (\Throwable $e) {
            dbRollback();
            error_log('[StreakService] applyPenalties falhou: ' . $e->getMessage());
            return [false, false];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS INTERNOS
    // ─────────────────────────────────────────────────────────────────────

    /** Leitura simples — usa o helper global dbRow() do db.php */
    private static function readUser(int $userId): array
    {
        return dbRow(
            'SELECT streak, water_chances,
                    last_streak_date, last_penalty_date, last_death_date
               FROM users WHERE id = ?',
            [$userId]
        ) ?? self::defaultUser();
    }

    /**
     * SELECT com FOR UPDATE — deve ser chamado dentro de uma transação ativa.
     * Usa getDB() diretamente: o singleton do db.php garante que seja a
     * mesma conexão da transação aberta por dbBegin(), tornando o lock válido.
     */
    private static function lockUser(int $userId): array
    {
        $stmt = getDB()->prepare(
            'SELECT streak, water_chances,
                    last_streak_date, last_penalty_date, last_death_date
               FROM users WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: self::defaultUser();
    }

    private static function defaultUser(): array
    {
        return [
            'streak'            => 0,
            'water_chances'     => 3,
            'last_streak_date'  => null,
            'last_penalty_date' => null,
            'last_death_date'   => null,
        ];
    }
}