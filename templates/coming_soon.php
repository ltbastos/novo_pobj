<?php

declare(strict_types=1);

$comingSoonTabs = [
    'simuladores' => [
        'title' => 'Simuladores',
        'copy' => 'Estamos finalizando os simuladores do POBJ para seguir o mesmo padrão visual e operacional do restante do painel.',
    ],
    'campanhas' => [
        'title' => 'Campanhas',
        'copy' => 'Esta área será conectada às campanhas vigentes assim que a visão final estiver pronta.',
    ],
];

$currentTab = $activeTab ?? 'resumo';

if (!isset($comingSoonTabs[$currentTab])) {
    return;
}

$comingSoon = $comingSoonTabs[$currentTab];
?>
<section class="coming-soon" aria-label="Página em breve">
    <div class="coming-soon__card">
        <span class="coming-soon__eyebrow">Em breve</span>
        <h2 class="coming-soon__title"><?= e($comingSoon['title']) ?></h2>
        <p class="coming-soon__copy"><?= e($comingSoon['copy']) ?></p>
    </div>
</section>