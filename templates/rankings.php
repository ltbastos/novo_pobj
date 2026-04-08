<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'rankings') {
    return;
}

$rankingRows = $rankingData['rows'] ?? [];
$rankingLevelLabel = (string) ($rankingData['level_label'] ?? 'Gerente de gestão');
?>
<section class="ranking-shell" aria-label="Rankings">
    <div class="ranking-card">
        <header class="ranking-card__header">
            <div>
                <h2 class="ranking-card__title">Rankings</h2>
                <p class="ranking-card__copy">Compare a pontuação por unidade respeitando os filtros aplicados.</p>
            </div>

            <div class="ranking-card__badges">
                <span class="ranking-card__badge"><strong>Nível:</strong> <?= e($rankingLevelLabel) ?></span>
                <span class="ranking-card__badge"><strong>Participantes:</strong> <?= e(format_int_readable((float) count($rankingRows))) ?></span>
                <form class="ranking-card__group-form" method="get" action="index.php">
                    <input type="hidden" name="page" value="pobj-dashboard">
                    <input type="hidden" name="periodo" value="<?= e($selectedPeriodId ?? '') ?>">
                    <input type="hidden" name="tab" value="rankings">
                    <input type="hidden" name="advanced_filters" value="<?= e($advancedFiltersState ?? 'open') ?>">
                    <?php foreach (($summaryFilterValues ?? []) as $filterKey => $filterValue): ?>
                        <?php if (!is_string($filterValue) || $filterValue === '') {
                            continue;
                        } ?>
                        <input type="hidden" name="<?= e($filterKey) ?>" value="<?= e($filterValue) ?>">
                    <?php endforeach; ?>
                    <?php if (($hasManualDateRange ?? false) === true): ?>
                        <input type="hidden" name="period_mode" value="manual">
                        <?php if (($requestedPeriodStart ?? '') !== ''): ?>
                            <input type="hidden" name="period_start" value="<?= e($requestedPeriodStart) ?>">
                        <?php endif; ?>
                        <?php if (($requestedPeriodEnd ?? '') !== ''): ?>
                            <input type="hidden" name="period_end" value="<?= e($requestedPeriodEnd) ?>">
                        <?php endif; ?>
                    <?php endif; ?>

                    <label class="ranking-card__group-label" for="ranking-group">Grupo</label>
                    <select id="ranking-group" name="grupo" class="ranking-card__group-select" data-enhanced-select="basic" data-enhanced-label="Grupo" data-enhanced-placeholder="Todos" data-autosubmit>
                        <option value="">Todos</option>
                        <?php foreach (($rankingGroupOptions ?? []) as $groupOption): ?>
                            <option value="<?= e((string) ($groupOption['value'] ?? '')) ?>"<?= selected_attr((string) ($groupOption['value'] ?? ''), $requestedRankingGroup ?? '') ?>><?= e((string) ($groupOption['label'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </header>

        <?php if ($rankingRows === []): ?>
            <div class="ranking-card__empty">Não há dados de ranking para os filtros atuais.</div>
        <?php else: ?>
            <div class="ranking-card__table-wrapper">
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th scope="col" class="ranking-table__col ranking-table__col--pos">#</th>
                            <th scope="col" class="ranking-table__col ranking-table__col--label">Unidade</th>
                            <th scope="col" class="ranking-table__col ranking-table__col--points">Pontos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rankingRows as $index => $rankingRow): ?>
                            <tr class="ranking-table__row<?= $index === 0 ? ' is-top' : '' ?>">
                                <td class="ranking-table__col ranking-table__col--pos"><?= e((string) ($rankingRow['position'] ?? ($index + 1))) ?></td>
                                <td class="ranking-table__col ranking-table__col--label"><?= e((string) ($rankingRow['display_label'] ?? $rankingRow['label'] ?? '-')) ?></td>
                                <td class="ranking-table__col ranking-table__col--points"><?= e(format_points_readable((float) ($rankingRow['pontos'] ?? 0.0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>