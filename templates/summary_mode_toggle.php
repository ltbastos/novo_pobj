<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'resumo' || (($summaryFilterValues['visao_acumulada'] ?? 'MENSAL') === 'ANUAL')) {
    return;
}
?>
<section class="resumo-mode" data-resumo-mode-root data-default-mode="cards" aria-label="Alternar visão do resumo">
    <div class="resumo-mode__toggle segmented" role="group" aria-label="Alterar visão do resumo">
        <button type="button" class="seg-btn is-active" data-resumo-mode-button data-mode="cards" aria-pressed="true">
            Visão por cards
        </button>
        <button type="button" class="seg-btn" data-resumo-mode-button data-mode="legacy" aria-pressed="false">
            Visão clássica
        </button>
    </div>
</section>