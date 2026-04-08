const pageBody = document.body;
const loadingOverlay = document.querySelector('[data-loading-overlay]');
const loadingText = loadingOverlay ? loadingOverlay.querySelector('[data-loading-text]') : null;

const hideLoadingOverlay = () => {
    if (!pageBody) {
        return;
    }

    pageBody.classList.remove('is-booting', 'is-loading');

    if (loadingOverlay) {
        loadingOverlay.setAttribute('aria-hidden', 'true');
    }
};

const showLoadingOverlay = (message = 'Carregando dados do POBJ...') => {
    if (!pageBody) {
        return;
    }

    pageBody.classList.remove('is-booting');
    pageBody.classList.add('is-loading');

    if (loadingText) {
        loadingText.textContent = message;
    }

    if (loadingOverlay) {
        loadingOverlay.setAttribute('aria-hidden', 'false');
    }
};

window.NovoPobjLoading = {
    show: showLoadingOverlay,
    hide: hideLoadingOverlay,
};

window.addEventListener('load', hideLoadingOverlay);
window.addEventListener('pageshow', hideLoadingOverlay);
window.setTimeout(hideLoadingOverlay, 1800);

document.addEventListener('submit', (event) => {
    if (event.target instanceof HTMLFormElement) {
        showLoadingOverlay('Atualizando painel...');
    }
}, true);

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');

    if (!link) {
        return;
    }

    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    if (link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-loading')) {
        return;
    }

    const href = (link.getAttribute('href') || '').trim();

    if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
        return;
    }

    try {
        const targetUrl = new URL(link.href, window.location.href);

        if (targetUrl.origin !== window.location.origin) {
            return;
        }

        showLoadingOverlay('Abrindo tela...');
    } catch (error) {
        console.warn('Nao foi possivel interpretar o link clicado.', error);
    }
}, true);

const submitManagedForm = (form, message = 'Atualizando painel...') => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    showLoadingOverlay(message);

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
    }

    form.submit();
};

document.querySelectorAll('[data-autosubmit]').forEach((element) => {
    element.addEventListener('change', () => {
        submitManagedForm(element.form, 'Aplicando filtros...');
    });
});

document.querySelectorAll('[data-filter-cascade]').forEach((element) => {
    element.addEventListener('change', () => {
        submitManagedForm(element.form, 'Atualizando filtros em cascata...');
    });
});

if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
}

const simplifyFilterText = (value) => (value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();

const initEnhancedFilterSelects = () => {
    const enhancedSelects = document.querySelectorAll('[data-enhanced-select]');

    if (enhancedSelects.length === 0) {
        return;
    }

    const closeOtherEnhancedSelects = (currentSelectRoot = null) => {
        document.querySelectorAll('.filter-select.is-open').forEach((selectRoot) => {
            if (selectRoot === currentSelectRoot) {
                return;
            }

            selectRoot.classList.remove('is-open');

            const panel = selectRoot.querySelector('.filter-select__panel');
            if (panel) {
                panel.hidden = true;
            }
        });
    };

    enhancedSelects.forEach((nativeSelect) => {
        if (!(nativeSelect instanceof HTMLSelectElement) || nativeSelect.dataset.enhancedReady === 'true') {
            return;
        }

        const mode = nativeSelect.dataset.enhancedSelect || 'search';
        const label = nativeSelect.dataset.enhancedLabel || nativeSelect.name || 'opção';
        const placeholder = nativeSelect.dataset.enhancedPlaceholder || nativeSelect.options[0]?.textContent?.trim() || 'Selecione...';
        const wrapper = document.createElement('div');
        wrapper.className = 'filter-select';
        wrapper.dataset.mode = mode;

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'filter-select__trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-label', label);

        const triggerText = document.createElement('span');
        triggerText.className = 'filter-select__trigger-text';

        const triggerIcon = document.createElement('span');
        triggerIcon.className = 'filter-select__icon';
        triggerIcon.setAttribute('aria-hidden', 'true');

        trigger.append(triggerText, triggerIcon);

        const panel = document.createElement('div');
        panel.className = 'filter-select__panel';
        panel.hidden = true;
        panel.setAttribute('role', 'listbox');
        panel.setAttribute('aria-label', label);

        let searchInput = null;

        if (mode === 'search') {
            searchInput = document.createElement('input');
            searchInput.type = 'search';
            searchInput.className = 'filter-select__search';
            searchInput.placeholder = `Pesquisar ${label.toLowerCase()}`;
            searchInput.setAttribute('aria-label', `Pesquisar ${label}`);
            panel.appendChild(searchInput);
        }

        const results = document.createElement('div');
        results.className = 'filter-select__results';
        panel.appendChild(results);

        nativeSelect.classList.add('filter-field__control--native');
        nativeSelect.insertAdjacentElement('afterend', wrapper);
        wrapper.append(trigger, panel);

        const syncTriggerState = () => {
            const selectedOption = nativeSelect.options[nativeSelect.selectedIndex] || null;
            const selectedLabel = selectedOption && selectedOption.value !== ''
                ? (selectedOption.textContent || '').trim()
                : placeholder;

            triggerText.textContent = selectedLabel;
            triggerText.classList.toggle('is-placeholder', !selectedOption || selectedOption.value === '');
            trigger.classList.toggle('is-disabled', nativeSelect.disabled);
            trigger.disabled = nativeSelect.disabled;
        };

        const renderOptions = () => {
            const searchValue = searchInput ? simplifyFilterText(searchInput.value) : '';
            const options = Array.from(nativeSelect.options).filter((option) => {
                if (searchValue === '') {
                    return true;
                }

                return simplifyFilterText(option.textContent || '').includes(searchValue);
            });

            results.innerHTML = '';

            if (options.length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'filter-select__empty';
                emptyState.textContent = 'Nenhum resultado encontrado';
                results.appendChild(emptyState);
                return;
            }

            options.forEach((option) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'filter-select__item';
                item.textContent = (option.textContent || '').trim();
                item.classList.toggle('is-selected', option.value === nativeSelect.value);
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', option.value === nativeSelect.value ? 'true' : 'false');
                item.addEventListener('click', () => {
                    if (nativeSelect.value !== option.value) {
                        nativeSelect.value = option.value;
                        nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    wrapper.classList.remove('is-open');
                    panel.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                    syncTriggerState();
                });

                results.appendChild(item);
            });
        };

        const setOpen = (open) => {
            if (nativeSelect.disabled) {
                return;
            }

            if (open) {
                closeOtherEnhancedSelects(wrapper);
                renderOptions();
            }

            wrapper.classList.toggle('is-open', open);
            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open && searchInput) {
                searchInput.value = '';
                renderOptions();
                window.requestAnimationFrame(() => {
                    searchInput.focus();
                });
            }
        };

        trigger.addEventListener('click', () => {
            setOpen(!wrapper.classList.contains('is-open'));
        });

        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
                return;
            }

            if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                setOpen(true);
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', renderOptions);
            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    setOpen(false);
                    trigger.focus();
                }
            });
        }

        nativeSelect.addEventListener('change', syncTriggerState);

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target) && event.target !== nativeSelect) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        nativeSelect.dataset.enhancedReady = 'true';
        syncTriggerState();
    });
};

initEnhancedFilterSelects();

const userbox = document.querySelector('[data-userbox]');

if (userbox) {
    const trigger = userbox.querySelector('[data-userbox-trigger]');
    const menu = userbox.querySelector('[data-userbox-menu]');
    const dismissElements = userbox.querySelectorAll('[data-userbox-dismiss]');
    const submenuTrigger = userbox.querySelector('[data-userbox-submenu-trigger]');
    const submenu = userbox.querySelector('[data-userbox-submenu]');

    const setExpanded = (expanded) => {
        userbox.classList.toggle('userbox--open', expanded);

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        if (menu) {
            menu.hidden = !expanded;
        }

        if (!expanded && submenuTrigger && submenu) {
            submenuTrigger.setAttribute('aria-expanded', 'false');
            submenu.hidden = true;
        }
    };

    const setSubmenuExpanded = (expanded) => {
        if (!submenuTrigger || !submenu) {
            return;
        }

        submenuTrigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        submenu.hidden = !expanded;
    };

    if (trigger && menu) {
        setExpanded(false);
        setSubmenuExpanded(false);

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            setExpanded(menu.hidden);
        });

        if (submenuTrigger && submenu) {
            submenuTrigger.addEventListener('click', (event) => {
                event.stopPropagation();

                if (menu.hidden) {
                    setExpanded(true);
                }

                setSubmenuExpanded(submenu.hidden);
            });
        }

        dismissElements.forEach((element) => {
            element.addEventListener('click', () => {
                setExpanded(false);
            });
        });

        document.addEventListener('click', (event) => {
            if (!userbox.contains(event.target)) {
                setExpanded(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setExpanded(false);
            }
        });
    }
}

const filtersToggle = document.querySelector('[data-filters-toggle]');
const advancedFiltersShell = document.querySelector('[data-filters-shell]');
const filtersStateInput = document.querySelector('[data-filters-state]');
const filtersStateLinks = document.querySelectorAll('[data-preserve-filters-state]');

if (filtersToggle && advancedFiltersShell) {
    const storageKey = 'novo-pobj:advanced-filters-state';

    const persistState = (state) => {
        if (filtersStateInput) {
            filtersStateInput.value = state;
        }

        filtersStateLinks.forEach((link) => {
            try {
                const url = new URL(link.href, window.location.href);
                url.searchParams.set('advanced_filters', state);
                link.href = url.toString();
            } catch (error) {
                console.warn('Nao foi possivel atualizar o estado dos filtros na URL.', error);
            }
        });

        try {
            window.localStorage.setItem(storageKey, state);

            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('advanced_filters', state);
            window.history.replaceState({}, '', nextUrl);
        } catch (error) {
            console.warn('Nao foi possivel persistir o estado dos filtros.', error);
        }
    };

    const setFiltersExpanded = (expanded) => {
        const state = expanded ? 'open' : 'closed';

        advancedFiltersShell.classList.toggle('is-collapsed', !expanded);
        filtersToggle.classList.toggle('is-open', expanded);
        filtersToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        filtersToggle.textContent = expanded ? 'Fechar filtros avançados' : 'Abrir filtros avançados';
        persistState(state);
    };

    let storedState = null;

    try {
        storedState = window.localStorage.getItem(storageKey);
    } catch (error) {
        storedState = null;
    }

    const initialExpanded = storedState !== null
        ? storedState !== 'closed'
        : !advancedFiltersShell.classList.contains('is-collapsed');

    setFiltersExpanded(initialExpanded);

    filtersToggle.addEventListener('click', () => {
        setFiltersExpanded(advancedFiltersShell.classList.contains('is-collapsed'));
    });
}

const periodPicker = document.querySelector('[data-period-picker]');

if (periodPicker) {
    const trigger = periodPicker.querySelector('[data-period-trigger]');
    const popover = periodPicker.querySelector('[data-period-popover]');
    const cancelButton = periodPicker.querySelector('[data-period-cancel]');
    const form = periodPicker.querySelector('[data-period-form]');
    const startInput = periodPicker.querySelector('[data-period-start]');
    const endInput = periodPicker.querySelector('[data-period-end]');
    const periodIdInput = periodPicker.querySelector('[data-period-id]');
    const maxDate = startInput ? startInput.max : '';

    const setOpen = (open) => {
        if (!trigger || !popover) {
            return;
        }

        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        popover.hidden = !open;
        periodPicker.classList.toggle('is-open', open);
    };

    const parseDate = (value) => {
        if (!value) {
            return null;
        }

        const parsed = new Date(`${value}T00:00:00`);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const clampDate = (value) => {
        if (!value) {
            return '';
        }

        if (maxDate && value > maxDate) {
            return maxDate;
        }

        return value;
    };

    const dayDistance = (left, right) => Math.abs(left.getTime() - right.getTime()) / 86400000;

    const findBestPeriodId = (periods, startValue, endValue, fallbackId) => {
        const startDate = parseDate(startValue);
        const endDate = parseDate(endValue);

        if (!startDate || !endDate || !Array.isArray(periods) || periods.length === 0) {
            return fallbackId;
        }

        let bestId = fallbackId;
        let bestScore = -Infinity;

        periods.forEach((period) => {
            const periodStart = parseDate(period.start);
            const periodEnd = parseDate(period.end);

            if (!periodStart || !periodEnd || !period.id) {
                return;
            }

            if (period.start === startValue && period.end === endValue) {
                bestId = period.id;
                bestScore = Number.POSITIVE_INFINITY;
                return;
            }

            const overlapStart = Math.max(startDate.getTime(), periodStart.getTime());
            const overlapEnd = Math.min(endDate.getTime(), periodEnd.getTime());
            const overlapDays = Math.max(0, (overlapEnd - overlapStart) / 86400000);
            const distancePenalty = dayDistance(startDate, periodStart) + dayDistance(endDate, periodEnd);
            const score = overlapDays > 0 ? overlapDays - distancePenalty * 0.01 : -distancePenalty;

            if (score > bestScore) {
                bestScore = score;
                bestId = period.id;
            }
        });

        return bestId;
    };

    if (trigger && popover) {
        setOpen(false);

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            setOpen(popover.hidden);
        });

        if (cancelButton) {
            cancelButton.addEventListener('click', () => {
                setOpen(false);
            });
        }

        if (form && periodIdInput && startInput && endInput) {
            const syncDateRange = () => {
                startInput.value = clampDate(startInput.value);
                endInput.value = clampDate(endInput.value);

                if (startInput.value && endInput.value && startInput.value > endInput.value) {
                    if (document.activeElement === startInput) {
                        endInput.value = startInput.value;
                    } else {
                        startInput.value = endInput.value;
                    }
                }
            };

            startInput.addEventListener('change', syncDateRange);
            endInput.addEventListener('change', syncDateRange);

            form.addEventListener('submit', () => {
                let periods = [];

                syncDateRange();

                try {
                    periods = JSON.parse(form.dataset.periodOptions || '[]');
                } catch (error) {
                    periods = [];
                }

                periodIdInput.value = findBestPeriodId(periods, startInput.value, endInput.value, periodIdInput.value);
            });
        }

        document.addEventListener('click', (event) => {
            if (!periodPicker.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });
    }
}

const indicatorCards = document.querySelectorAll('[data-tip-card]');

if (indicatorCards.length > 0) {
    const closeAllIndicatorTips = () => {
        indicatorCards.forEach((card) => {
            card.classList.remove('is-tip-open');

            const trigger = card.querySelector('[data-tip-trigger]');

            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    };

    indicatorCards.forEach((card) => {
        const trigger = card.querySelector('[data-tip-trigger]');

        if (!trigger) {
            return;
        }

        const openTip = () => {
            closeAllIndicatorTips();
            card.classList.add('is-tip-open');
            trigger.setAttribute('aria-expanded', 'true');
        };

        const closeTip = () => {
            card.classList.remove('is-tip-open');
            trigger.setAttribute('aria-expanded', 'false');
        };

        trigger.addEventListener('mouseenter', openTip);
        trigger.addEventListener('focus', openTip);
        trigger.addEventListener('click', (event) => {
            event.stopPropagation();

            if (card.classList.contains('is-tip-open')) {
                closeTip();
                return;
            }

            openTip();
        });

        card.addEventListener('mouseleave', closeTip);
        card.addEventListener('blur', (event) => {
            if (!card.contains(event.relatedTarget)) {
                closeTip();
            }
        }, true);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-tip-card]')) {
            closeAllIndicatorTips();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllIndicatorTips();
        }
    });
}

const resumoModeRoot = document.querySelector('[data-resumo-mode-root]');

if (resumoModeRoot) {
    const storageKey = 'novo-pobj:resumo-mode';
    const buttons = resumoModeRoot.querySelectorAll('[data-resumo-mode-button]');
    const panes = document.querySelectorAll('[data-resumo-pane]');
    const allowedModes = ['cards', 'legacy'];

    const setMode = (mode) => {
        if (!allowedModes.includes(mode)) {
            return;
        }

        panes.forEach((pane) => {
            pane.hidden = pane.dataset.resumoPane !== mode;
        });

        buttons.forEach((button) => {
            const isActive = button.dataset.mode === mode;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        try {
            window.localStorage.setItem(storageKey, mode);
        } catch (error) {
            console.warn('Nao foi possivel persistir a visao do resumo.', error);
        }
    };

    let initialMode = resumoModeRoot.dataset.defaultMode || 'cards';

    try {
        const storedMode = window.localStorage.getItem(storageKey);

        if (storedMode && allowedModes.includes(storedMode)) {
            initialMode = storedMode;
        }
    } catch (error) {
        initialMode = resumoModeRoot.dataset.defaultMode || 'cards';
    }

    setMode(initialMode);

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const nextMode = button.dataset.mode || 'cards';

            if (button.classList.contains('is-active')) {
                return;
            }

            showLoadingOverlay('Alternando visao...');

            window.requestAnimationFrame(() => {
                setMode(nextMode);
                window.requestAnimationFrame(() => {
                    hideLoadingOverlay();
                });
            });
        });
    });
}

const legacySections = document.querySelectorAll('[data-legacy-section]');

if (legacySections.length > 0) {
    legacySections.forEach((section) => {
        const groups = Array.from(section.querySelectorAll('[data-legacy-group]'));
        const toggleAllButton = section.querySelector('[data-legacy-toggle-all]');

        const setGroupOpen = (group, expanded) => {
            group.classList.toggle('is-open', expanded);

            const trigger = group.querySelector('[data-legacy-toggle-row]');

            if (trigger) {
                trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }

            group.querySelectorAll('[data-legacy-child-row]').forEach((row) => {
                row.hidden = !expanded;
            });
        };

        const syncSectionToggle = () => {
            if (!toggleAllButton) {
                return;
            }

            const expandableGroups = groups.filter((group) => group.querySelector('[data-legacy-toggle-row]'));
            const allOpen = expandableGroups.length > 0 && expandableGroups.every((group) => group.classList.contains('is-open'));

            toggleAllButton.textContent = allOpen ? 'Fechar todos os filtros' : 'Abrir todos os filtros';
            toggleAllButton.setAttribute('aria-expanded', allOpen ? 'true' : 'false');
        };

        groups.forEach((group) => {
            const trigger = group.querySelector('[data-legacy-toggle-row]');

            setGroupOpen(group, false);

            if (!trigger) {
                return;
            }

            trigger.addEventListener('click', () => {
                setGroupOpen(group, !group.classList.contains('is-open'));
                syncSectionToggle();
            });
        });

        if (toggleAllButton) {
            toggleAllButton.addEventListener('click', () => {
                const expandableGroups = groups.filter((group) => group.querySelector('[data-legacy-toggle-row]'));
                const shouldOpen = expandableGroups.some((group) => !group.classList.contains('is-open'));

                expandableGroups.forEach((group) => {
                    setGroupOpen(group, shouldOpen);
                });

                syncSectionToggle();
            });
        }

        syncSectionToggle();
    });
}

const legacySimulationRoot = document.querySelector('[data-legacy-simulation-root]');

if (legacySimulationRoot) {
    const toggle = legacySimulationRoot.querySelector('[data-legacy-simulation-toggle]');
    const resetButton = legacySimulationRoot.querySelector('[data-legacy-simulation-reset]');
    const itemRows = Array.from(document.querySelectorAll('[data-legacy-item-row]'));
    const sections = Array.from(document.querySelectorAll('[data-legacy-sim-section]'));
    const summaryKpiRoot = document.querySelector('[data-summary-kpi-root]');
    const simulationOverrides = new Map();

    let businessDays = { total: 0, elapsed: 0, remaining: 0 };

    try {
        businessDays = JSON.parse(legacySimulationRoot.dataset.legacyBusinessDays || '{}');
    } catch (error) {
        businessDays = { total: 0, elapsed: 0, remaining: 0 };
    }

    const toSafeNumber = (value) => {
        const parsed = Number.parseFloat(String(value || ''));
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const formatDecimal = (value, decimals = 2) => new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number.isFinite(value) ? value : 0);

    const formatInteger = (value) => new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Math.round(Number.isFinite(value) ? value : 0));

    const formatCompactNumber = (value, baseDecimals = 2) => {
        const safeValue = Number.isFinite(value) ? value : 0;
        const absolute = Math.abs(safeValue);
        const scales = [
            { divisor: 1000000000000, suffix: ' tri' },
            { divisor: 1000000000, suffix: ' bi' },
            { divisor: 1000000, suffix: ' mi' },
            { divisor: 1000, suffix: ' mil' },
        ];

        for (const scale of scales) {
            if (absolute < scale.divisor) {
                continue;
            }

            const scaled = safeValue / scale.divisor;
            const scaledAbsolute = Math.abs(scaled);
            let decimals = scaledAbsolute >= 100 ? 0 : 1;

            if (Math.abs(scaled - Math.round(scaled)) < 0.05) {
                decimals = 0;
            }

            return `${new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            }).format(scaled)}${scale.suffix}`;
        }

        let decimals = baseDecimals;
        const rounded = Number(safeValue.toFixed(baseDecimals));

        if (baseDecimals > 0 && Math.abs(rounded - Math.round(rounded)) < 0.005) {
            decimals = 0;
        }

        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(rounded);
    };

    const formatPointsReadable = (value) => {
        const safeValue = Number.isFinite(value) ? value : 0;
        const rounded = Number(safeValue.toFixed(2));
        const decimals = Math.abs(rounded - Math.round(rounded)) < 0.005 || Math.abs(rounded) >= 1000 ? 0 : 2;

        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(rounded);
    };

    const formatMetricReadable = (metricType, value) => {
        const upperMetric = String(metricType || 'VALOR').toUpperCase();
        const safeValue = Number.isFinite(value) ? value : 0;

        if (upperMetric === 'PERCENTUAL') {
            return `${formatDecimal(safeValue, 2)}%`;
        }

        if (upperMetric === 'QUANTIDADE') {
            return formatCompactNumber(safeValue, 0);
        }

        return `R$ ${formatCompactNumber(safeValue, 2)}`;
    };

    const formatPercent = (value) => `${formatDecimal(Math.max(0, Math.min(200, Number.isFinite(value) ? value : 0)), 1)}%`;

    const formatInputValue = (value) => {
        const safeValue = Number.isFinite(value) ? value : 0;
        const rendered = safeValue.toFixed(4).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
        return rendered === '' ? '0' : rendered;
    };

    const resolveMeterClass = (percent) => {
        if (percent >= 100) {
            return 'is-ok';
        }

        if (percent >= 50) {
            return 'is-warn';
        }

        return 'is-low';
    };

    const getCurrentValues = (row) => {
        const itemId = row.dataset.legacyItemId || '';
        const override = simulationOverrides.get(itemId) || {};
        const meta = override.meta ?? toSafeNumber(row.dataset.legacyOriginalMeta);
        const realizado = override.realizado ?? toSafeNumber(row.dataset.legacyOriginalRealizado);
        const pontosTotal = toSafeNumber(row.dataset.legacyPointsTotal);
        const variavelTotal = toSafeNumber(row.dataset.legacyVariableTotal);
        const variavelOriginal = toSafeNumber(row.dataset.legacyVariableOriginal);

        return { itemId, meta, realizado, pontosTotal, variavelTotal, variavelOriginal };
    };

    const calculateRowMetrics = (row) => {
        const { meta, realizado, pontosTotal, variavelTotal, variavelOriginal } = getCurrentValues(row);
        const totalDays = Math.max(0, Number.parseInt(String(businessDays.total || 0), 10));
        const elapsedDays = Math.max(0, Number.parseInt(String(businessDays.elapsed || 0), 10));
        const remainingDays = Math.max(0, Number.parseInt(String(businessDays.remaining || 0), 10));
        const referencia = totalDays > 0 ? (meta / totalDays) * elapsedDays : 0;
        const faltaParaMeta = Math.max(0, meta - realizado);
        const metaDiaria = remainingDays > 0 ? faltaParaMeta / remainingDays : 0;
        const forecast = elapsedDays > 0 ? realizado + ((realizado / elapsedDays) * remainingDays) : realizado;
        const atingimento = meta > 0 ? (realizado / meta) * 100 : 0;
        const pontos = meta > 0 ? (realizado / meta) * pontosTotal : 0;
        const variavel = meta > 0 ? (realizado / meta) * variavelTotal : variavelOriginal;

        return {
            meta,
            realizado,
            pontosTotal,
            variavelTotal,
            variavel,
            referencia,
            metaDiaria,
            forecast,
            atingimento,
            pontos,
        };
    };

    const setEditingState = (row, active) => {
        row.querySelectorAll('[data-legacy-edit]').forEach((editField) => {
            editField.hidden = !active;
        });

        row.querySelectorAll('[data-legacy-display]').forEach((displayField) => {
            displayField.hidden = active;
        });
    };

    const updateRow = (row) => {
        const metricType = row.dataset.legacyMetricType || 'VALOR';
        const metrics = calculateRowMetrics(row);
        const simulationActive = toggle instanceof HTMLInputElement && toggle.checked;
        const metaDisplay = row.querySelector('[data-legacy-display="meta"]');
        const realizadoDisplay = row.querySelector('[data-legacy-display="realizado"]');
        const metaPreview = row.querySelector('[data-legacy-preview="meta"]');
        const realizadoPreview = row.querySelector('[data-legacy-preview="realizado"]');
        const referenciaNode = row.querySelector('[data-legacy-value="referencia"]');
        const forecastNode = row.querySelector('[data-legacy-value="forecast"]');
        const metaDiaNode = row.querySelector('[data-legacy-value="meta-dia"]');
        const pontosNode = row.querySelector('[data-legacy-value="pontos"]');
        const atingimentoNode = row.querySelector('[data-legacy-value="atingimento"]');
        const meter = row.querySelector('[data-legacy-meter]');
        const metaInput = row.querySelector('[data-legacy-input="meta"]');
        const realizadoInput = row.querySelector('[data-legacy-input="realizado"]');
        const formattedMeta = formatMetricReadable(metricType, metrics.meta);
        const formattedRealizado = formatMetricReadable(metricType, metrics.realizado);

        if (metaDisplay) {
            metaDisplay.textContent = formattedMeta;
        }

        if (realizadoDisplay) {
            realizadoDisplay.textContent = formattedRealizado;
        }

        if (metaPreview) {
            metaPreview.textContent = formattedMeta;
        }

        if (realizadoPreview) {
            realizadoPreview.textContent = formattedRealizado;
        }

        if (referenciaNode) {
            referenciaNode.textContent = formatMetricReadable(metricType, metrics.referencia);
        }

        if (forecastNode) {
            forecastNode.textContent = formatMetricReadable(metricType, metrics.forecast);
        }

        if (metaDiaNode) {
            metaDiaNode.textContent = formatMetricReadable(metricType, metrics.metaDiaria);
        }

        if (pontosNode) {
            pontosNode.textContent = `${formatPointsReadable(metrics.pontos)} pts`;
        }

        if (atingimentoNode) {
            atingimentoNode.textContent = metrics.meta > 0 ? formatPercent(metrics.atingimento) : '—';
        }

        if (meter) {
            meter.classList.remove('is-low', 'is-warn', 'is-ok');
            meter.classList.add(resolveMeterClass(metrics.atingimento));
            meter.style.setProperty('--fill', `${Math.max(0, Math.min(100, metrics.atingimento))}%`);
            meter.setAttribute('aria-valuenow', String(Math.max(0, Math.min(200, metrics.atingimento)).toFixed(1)));
        }

        if (metaInput && document.activeElement !== metaInput) {
            metaInput.value = formatInputValue(metrics.meta);
        }

        if (realizadoInput && document.activeElement !== realizadoInput) {
            realizadoInput.value = formatInputValue(metrics.realizado);
        }

        setEditingState(row, simulationActive);
    };

    const updateSection = (section) => {
        const rows = Array.from(section.querySelectorAll('[data-legacy-item-row]'));
        let pointsHit = 0;
        let pointsTotal = 0;
        let percentSum = 0;
        let percentCount = 0;

        rows.forEach((row) => {
            const metrics = calculateRowMetrics(row);
            pointsHit += metrics.pontos;
            pointsTotal += metrics.pontosTotal;
            percentSum += metrics.atingimento;
            percentCount += 1;
        });

        const pointsHitNode = section.querySelector('[data-legacy-section-points-hit]');
        const pointsTotalNode = section.querySelector('[data-legacy-section-points-total]');
        const atingimentoNode = section.querySelector('[data-legacy-section-atingimento]');

        if (pointsHitNode) {
            pointsHitNode.textContent = formatPointsReadable(pointsHit);
        }

        if (pointsTotalNode) {
            pointsTotalNode.textContent = formatPointsReadable(pointsTotal);
        }

        if (atingimentoNode) {
            atingimentoNode.textContent = percentCount > 0 ? formatPercent(percentSum / percentCount) : '0,0%';
        }
    };

    const resolveKpiBarClass = (percent) => {
        if (percent < 50) {
            return 'summary-kpi__hitbar--low';
        }

        if (percent < 100) {
            return 'summary-kpi__hitbar--warn';
        }

        return 'summary-kpi__hitbar--ok';
    };

    const applyKpiCardState = (card, achieved, total, percent, formatter) => {
        const achievedNode = card.querySelector('[data-summary-kpi-achieved]');
        const totalNode = card.querySelector('[data-summary-kpi-total]');
        const percentNode = card.querySelector('[data-summary-kpi-percent]');
        const progressNode = card.querySelector('[data-summary-kpi-progress]');
        const trackNode = card.querySelector('[data-summary-kpi-track]');
        const boundedPercent = Math.max(0, Math.min(100, Number.isFinite(percent) ? percent : 0));

        if (achievedNode) {
            achievedNode.textContent = formatter(achieved);
        }

        if (totalNode) {
            totalNode.textContent = formatter(total);
        }

        if (percentNode) {
            percentNode.textContent = `${formatDecimal(percent, 1)}%`;
        }

        if (progressNode) {
            progressNode.classList.remove('summary-kpi__hitbar--low', 'summary-kpi__hitbar--warn', 'summary-kpi__hitbar--ok');
            progressNode.classList.add(resolveKpiBarClass(percent));
            progressNode.setAttribute('aria-valuenow', String(Number.isFinite(percent) ? percent.toFixed(1) : '0.0'));
        }

        if (trackNode) {
            trackNode.style.setProperty('--target', `${boundedPercent.toFixed(2)}%`);
            trackNode.style.setProperty('--thumb', `${boundedPercent.toFixed(2)}%`);
        }
    };

    const updateSummaryKpis = () => {
        if (!summaryKpiRoot) {
            return;
        }

        const cards = Array.from(summaryKpiRoot.querySelectorAll('[data-summary-kpi-card]'));
        const simulationActive = toggle instanceof HTMLInputElement && toggle.checked;

        if (!simulationActive) {
            cards.forEach((card) => {
                const achieved = toSafeNumber(card.dataset.summaryKpiOriginalAchieved);
                const total = toSafeNumber(card.dataset.summaryKpiOriginalTotal);
                const percent = toSafeNumber(card.dataset.summaryKpiOriginalPercent);
                const key = card.dataset.summaryKpiCard || '';
                const formatter = key === 'indicadores'
                    ? formatInteger
                    : (key === 'variavel' ? ((value) => `R$ ${formatCompactNumber(value, 2)}`) : formatPointsReadable);

                applyKpiCardState(card, achieved, total, percent, formatter);
            });

            return;
        }

        const rowMetrics = itemRows.map(calculateRowMetrics);
        const indicatorsTotal = rowMetrics.length;
        const indicatorsAchieved = rowMetrics.filter((metrics) => metrics.meta > 0 && metrics.realizado >= metrics.meta).length;
        const indicatorsPercent = indicatorsTotal > 0 ? (indicatorsAchieved / indicatorsTotal) * 100 : 0;
        const pointsAchieved = rowMetrics.reduce((carry, metrics) => carry + metrics.pontos, 0);
        const pointsTotal = rowMetrics.reduce((carry, metrics) => carry + metrics.pontosTotal, 0);
        const pointsPercent = pointsTotal > 0 ? (pointsAchieved / pointsTotal) * 100 : 0;
        const variableAchieved = rowMetrics.reduce((carry, metrics) => carry + metrics.variavel, 0);
        const variableTotal = rowMetrics.reduce((carry, metrics) => carry + metrics.variavelTotal, 0);
        const variablePercent = variableTotal > 0 ? (variableAchieved / variableTotal) * 100 : 0;

        cards.forEach((card) => {
            switch (card.dataset.summaryKpiCard || '') {
                case 'indicadores':
                    applyKpiCardState(card, indicatorsAchieved, indicatorsTotal, indicatorsPercent, formatInteger);
                    break;

                case 'variavel':
                    applyKpiCardState(card, variableAchieved, variableTotal, variablePercent, (value) => `R$ ${formatCompactNumber(value, 2)}`);
                    break;

                case 'pontos':
                default:
                    applyKpiCardState(card, pointsAchieved, pointsTotal, pointsPercent, formatPointsReadable);
                    break;
            }
        });
    };

    const syncResetState = () => {
        if (!(resetButton instanceof HTMLButtonElement)) {
            return;
        }

        resetButton.disabled = simulationOverrides.size === 0;
    };

    const refreshSimulationState = () => {
        legacySimulationRoot.classList.toggle('is-active', toggle instanceof HTMLInputElement && toggle.checked);
        itemRows.forEach(updateRow);
        sections.forEach(updateSection);
        updateSummaryKpis();
        syncResetState();
    };

    itemRows.forEach((row) => {
        const itemId = row.dataset.legacyItemId || '';
        const metaInput = row.querySelector('[data-legacy-input="meta"]');
        const realizadoInput = row.querySelector('[data-legacy-input="realizado"]');

        const bindInput = (input, key) => {
            if (!(input instanceof HTMLInputElement) || itemId === '') {
                return;
            }

            input.addEventListener('input', () => {
                const nextValue = input.value.trim();
                const current = simulationOverrides.get(itemId) || {};

                if (nextValue === '') {
                    delete current[key];
                } else {
                    current[key] = toSafeNumber(nextValue);
                }

                if (current.meta === undefined && current.realizado === undefined) {
                    simulationOverrides.delete(itemId);
                } else {
                    simulationOverrides.set(itemId, current);
                }

                refreshSimulationState();
            });
        };

        bindInput(metaInput, 'meta');
        bindInput(realizadoInput, 'realizado');
    });

    if (toggle instanceof HTMLInputElement) {
        toggle.addEventListener('change', () => {
            if (!toggle.checked) {
                simulationOverrides.clear();
            }

            refreshSimulationState();
        });
    }

    if (resetButton instanceof HTMLButtonElement) {
        resetButton.addEventListener('click', () => {
            simulationOverrides.clear();
            refreshSimulationState();
        });
    }

    refreshSimulationState();
}

const executiveHeatmapRoots = document.querySelectorAll('[data-executive-heatmap]');

if (executiveHeatmapRoots.length > 0) {
    executiveHeatmapRoots.forEach((root) => {
        const buttons = Array.from(root.querySelectorAll('[data-executive-heatmap-button]'));
        const panes = Array.from(root.querySelectorAll('[data-executive-heatmap-pane]'));
        const defaultMode = (root.dataset.defaultMode || '').trim() || (buttons[0]?.dataset.executiveHeatmapButton || 'secoes');

        const setMode = (mode) => {
            const nextMode = (mode || '').trim() || defaultMode;

            buttons.forEach((button) => {
                const isActive = button.dataset.executiveHeatmapButton === nextMode;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            panes.forEach((pane) => {
                pane.hidden = pane.dataset.executiveHeatmapPane !== nextMode;
            });
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                setMode(button.dataset.executiveHeatmapButton || defaultMode);
            });
        });

        setMode(defaultMode);
    });
}

const detailRoot = document.querySelector('[data-detail-root]');

if (detailRoot) {
    const modal = detailRoot.querySelector('[data-detail-modal]');
    const openModalButton = detailRoot.querySelector('[data-detail-open-modal]');
    const closeModalButtons = Array.from(detailRoot.querySelectorAll('[data-detail-close-modal]'));
    const cancelModalButton = detailRoot.querySelector('[data-detail-cancel-modal]');
    const applyColumnsButton = detailRoot.querySelector('[data-detail-apply-columns]');
    const saveViewButton = detailRoot.querySelector('[data-detail-save-view-modal]');
    const searchInput = detailRoot.querySelector('[data-detail-search]');
    const viewHosts = Array.from(detailRoot.querySelectorAll('[data-detail-views-host]'));
    const defaultViewButtons = Array.from(detailRoot.querySelectorAll('[data-detail-default-view-trigger]'));
    const availableColumnsHost = detailRoot.querySelector('[data-detail-available-columns]');
    const selectedColumnsHost = detailRoot.querySelector('[data-detail-selected-columns]');
    const viewNameInput = detailRoot.querySelector('[data-detail-view-name]');
    const tableViewButtons = Array.from(detailRoot.querySelectorAll('[data-detail-table-view]'));
    const tablePanes = Array.from(detailRoot.querySelectorAll('[data-detail-table-pane]'));
    const tables = Array.from(detailRoot.querySelectorAll('[data-detail-table]'));
    const expandAllButton = detailRoot.querySelector('[data-detail-expand-all]');
    const collapseAllButton = detailRoot.querySelector('[data-detail-collapse-all]');
    const storageColumnsKey = 'novo-pobj:detail-visible-columns';
    const storageViewsKey = 'novo-pobj:detail-saved-views';
    const maxSavedViews = 5;

    let columnsConfig = [];
    let allColumnKeys = [];
    let appliedColumns = [];
    let draftColumns = [];
    let activeViewId = '__default__';
    let defaultColumns = [];
    let activeTableViewId = tableViewButtons.find((button) => button.classList.contains('is-active'))?.dataset.detailTableView
        || tablePanes[0]?.dataset.detailTablePane
        || 'diretoria';

    try {
        columnsConfig = JSON.parse(detailRoot.dataset.detailColumnsConfig || '[]');
        allColumnKeys = columnsConfig
            .map((column) => column.key)
            .filter((value) => typeof value === 'string' && value !== '');
    } catch (error) {
        columnsConfig = [];
        allColumnKeys = [];
    }

    try {
        defaultColumns = JSON.parse(detailRoot.dataset.detailDefaultColumns || '[]');
    } catch (error) {
        defaultColumns = [];
    }

    const columnLabels = columnsConfig.reduce((map, column) => {
        map[column.key] = column.label;
        return map;
    }, {});

    const arraysEqual = (left, right) => left.length === right.length && left.every((value, index) => value === right[index]);

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const normalizeColumns = (columns) => {
        const uniqueColumns = [];

        (Array.isArray(columns) ? columns : []).forEach((columnKey) => {
            if (allColumnKeys.includes(columnKey) && !uniqueColumns.includes(columnKey)) {
                uniqueColumns.push(columnKey);
            }
        });

        return uniqueColumns.length > 0
            ? uniqueColumns
            : defaultColumns.filter((columnKey) => allColumnKeys.includes(columnKey));
    };

    defaultColumns = normalizeColumns(defaultColumns);

    const readSavedViews = () => {
        try {
            const parsed = JSON.parse(window.localStorage.getItem(storageViewsKey) || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    };

    const writeSavedViews = (views) => {
        window.localStorage.setItem(storageViewsKey, JSON.stringify(views));
    };

    const persistVisibleColumns = (columns) => {
        window.localStorage.setItem(storageColumnsKey, JSON.stringify(columns));
    };

    const readStoredColumns = () => {
        try {
            const parsed = JSON.parse(window.localStorage.getItem(storageColumnsKey) || '[]');
            return normalizeColumns(parsed);
        } catch (error) {
            return normalizeColumns(defaultColumns);
        }
    };

    const resolveActiveViewId = (columns) => {
        if (arraysEqual(columns, defaultColumns)) {
            return '__default__';
        }

        const matchedView = readSavedViews().find((view) => arraysEqual(normalizeColumns(view.columns), columns));
        return matchedView ? matchedView.id : '__custom__';
    };

    const getActivePane = () => tablePanes.find((pane) => pane.dataset.detailTablePane === activeTableViewId) || null;

    const paneCaches = new WeakMap();

    tablePanes.forEach((pane) => {
        const rows = Array.from(pane.querySelectorAll('[data-tree-row]'));
        const detailRows = Array.from(pane.querySelectorAll('[data-tree-detail-row]'));
        const rowById = new Map();
        const childrenByParentId = new Map();
        const detailByParentId = new Map();

        rows.forEach((row) => {
            const nodeId = row.dataset.treeId || '';
            const parentId = row.dataset.treeParentId || '';

            if (nodeId !== '') {
                rowById.set(nodeId, row);
            }

            if (!childrenByParentId.has(parentId)) {
                childrenByParentId.set(parentId, []);
            }

            childrenByParentId.get(parentId).push(row);
        });

        detailRows.forEach((row) => {
            const parentId = row.dataset.parentId || '';

            if (parentId !== '') {
                detailByParentId.set(parentId, row);
            }
        });

        paneCaches.set(pane, {
            rows,
            detailRows,
            rowById,
            childrenByParentId,
            detailByParentId,
        });
    });

    const getPaneCache = (pane) => paneCaches.get(pane) || {
        rows: [],
        detailRows: [],
        rowById: new Map(),
        childrenByParentId: new Map(),
        detailByParentId: new Map(),
    };

    const findTreeRow = (pane, nodeId) => getPaneCache(pane).rowById.get(nodeId) || null;

    const findTreeDetailRow = (pane, nodeId) => getPaneCache(pane).detailByParentId.get(nodeId) || null;

    const findChildRows = (pane, nodeId) => getPaneCache(pane).childrenByParentId.get(nodeId) || [];

    const setToggleState = (row, expanded) => {
        row.classList.toggle('tree-row--expanded', expanded);

        const trigger = row.querySelector('[data-tree-toggle]');

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            trigger.classList.toggle('is-expanded', expanded);
        }
    };

    const collapseBranch = (pane, nodeId) => {
        const row = findTreeRow(pane, nodeId);

        if (row) {
            setToggleState(row, false);
        }

        const detailRow = findTreeDetailRow(pane, nodeId);

        if (detailRow) {
            detailRow.hidden = true;
        }

        const stack = [...findChildRows(pane, nodeId)];

        while (stack.length > 0) {
            const childRow = stack.pop();

            if (!childRow) {
                continue;
            }

            childRow.hidden = true;
            setToggleState(childRow, false);

            const childId = childRow.dataset.treeId || '';
            const childDetailRow = findTreeDetailRow(pane, childId);

            if (childDetailRow) {
                childDetailRow.hidden = true;
            }

            findChildRows(pane, childId).forEach((nestedChild) => {
                stack.push(nestedChild);
            });
        }
    };

    const setNodeExpanded = (pane, nodeId, expanded) => {
        const row = findTreeRow(pane, nodeId);

        if (!row) {
            return;
        }

        setToggleState(row, expanded);

        const detailRow = findTreeDetailRow(pane, nodeId);
        if (detailRow) {
            detailRow.hidden = !expanded;
        }

        findChildRows(pane, nodeId).forEach((childRow) => {
            childRow.hidden = !expanded;

            if (!expanded) {
                collapseBranch(pane, childRow.dataset.treeId || '');
            }
        });
    };

    const collapseAllInPane = (pane) => {
        const { rows, detailRows } = getPaneCache(pane);

        rows.forEach((row) => {
            const isRoot = !row.dataset.treeParentId;
            row.hidden = !isRoot;
            setToggleState(row, false);
        });

        detailRows.forEach((row) => {
            row.hidden = true;
        });
    };

    const expandAllInPane = (pane) => {
        const { rows, detailRows } = getPaneCache(pane);

        rows.forEach((row) => {
            row.hidden = false;

            if (row.querySelector('[data-tree-toggle]')) {
                setToggleState(row, true);
            }
        });

        detailRows.forEach((row) => {
            row.hidden = false;
        });
    };

    const updateTreeDetailColspan = (visibleColumns) => {
        detailRoot.querySelectorAll('[data-tree-detail-colspan]').forEach((cell) => {
            cell.colSpan = visibleColumns.length + 1;
        });
    };

    const reorderTables = (visibleColumns) => {
        tables.forEach((table) => {
            table.querySelectorAll('tr').forEach((row) => {
                const cells = Array.from(row.querySelectorAll('[data-detail-column]'));

                if (cells.length === 0) {
                    return;
                }

                const cellMap = new Map(cells.map((cell) => [cell.dataset.detailColumn, cell]));
                const orderedKeys = [...visibleColumns, ...allColumnKeys.filter((columnKey) => !visibleColumns.includes(columnKey))];

                orderedKeys.forEach((columnKey) => {
                    const cell = cellMap.get(columnKey);

                    if (cell) {
                        row.appendChild(cell);
                    }
                });
            });
        });
    };

    const renderColumnLists = () => {
        if (!availableColumnsHost || !selectedColumnsHost) {
            return;
        }

        availableColumnsHost.innerHTML = '';
        selectedColumnsHost.innerHTML = '';

        const selectedKeys = normalizeColumns(draftColumns);
        const availableKeys = allColumnKeys.filter((columnKey) => !selectedKeys.includes(columnKey));

        if (availableKeys.length === 0) {
            const emptyState = document.createElement('p');
            emptyState.className = 'detail-designer__empty';
            emptyState.textContent = 'Todas as colunas já estão na tabela.';
            availableColumnsHost.appendChild(emptyState);
        }

        availableKeys.forEach((columnKey) => {
            const item = document.createElement('div');
            item.className = 'detail-item detail-item--available';

            const handle = document.createElement('span');
            handle.className = 'detail-item__handle';
            handle.setAttribute('aria-hidden', 'true');
            handle.textContent = '⋮⋮';

            const label = document.createElement('span');
            label.className = 'detail-item__label';
            label.textContent = columnLabels[columnKey] || columnKey;

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'detail-item__add';
            addButton.textContent = '+';
            addButton.setAttribute('aria-label', `Adicionar ${label.textContent}`);
            addButton.addEventListener('click', () => {
                draftColumns = [...selectedKeys, columnKey];
                renderColumnLists();
            });

            item.append(handle, label, addButton);
            availableColumnsHost.appendChild(item);
        });

        if (selectedKeys.length === 0) {
            const emptyState = document.createElement('p');
            emptyState.className = 'detail-designer__empty';
            emptyState.textContent = 'Escolha ao menos uma coluna.';
            selectedColumnsHost.appendChild(emptyState);
        }

        selectedKeys.forEach((columnKey, index) => {
            const item = document.createElement('div');
            item.className = 'detail-item';

            const handle = document.createElement('span');
            handle.className = 'detail-item__handle';
            handle.setAttribute('aria-hidden', 'true');
            handle.textContent = '⋮⋮';

            const label = document.createElement('span');
            label.className = 'detail-item__label';
            label.textContent = columnLabels[columnKey] || columnKey;

            const controls = document.createElement('div');
            controls.className = 'detail-modal__column-controls';

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'detail-item__remove';
            removeButton.textContent = '×';
            removeButton.disabled = selectedKeys.length === 1;
            removeButton.setAttribute('aria-label', `Remover ${label.textContent}`);
            removeButton.addEventListener('click', () => {
                if (selectedKeys.length === 1) {
                    return;
                }

                draftColumns = selectedKeys.filter((currentKey) => currentKey !== columnKey);
                renderColumnLists();
            });

            controls.append(removeButton);
            item.append(handle, label, controls);
            selectedColumnsHost.appendChild(item);
        });
    };

    const renderSavedViews = () => {
        const savedViews = readSavedViews();

        viewHosts.forEach((host) => {
            host.querySelectorAll('[data-detail-view-item]').forEach((item) => {
                item.remove();
            });

            const hostType = host.dataset.detailViewsHost || 'inline';
            const defaultButton = host.querySelector('[data-detail-default-view-trigger]');

            if (defaultButton) {
                defaultButton.classList.toggle('is-active', activeViewId === '__default__');
            }

            savedViews.forEach((view) => {
                const item = document.createElement(hostType === 'modal' ? 'div' : 'span');
                item.setAttribute('data-detail-view-item', 'true');
                item.className = hostType === 'modal' ? 'detail-view-chip-wrapper' : 'detail-view-bar__item';

                const applyButton = document.createElement('button');
                applyButton.type = 'button';
                applyButton.className = hostType === 'modal' ? 'detail-view-chip' : 'detail-chip';
                applyButton.textContent = view.name;
                applyButton.classList.toggle('is-active', activeViewId === view.id);
                applyButton.addEventListener('click', () => {
                    if (hostType === 'modal') {
                        activeViewId = view.id;
                        draftColumns = normalizeColumns(view.columns);
                        renderSavedViews();
                        renderColumnLists();
                        return;
                    }

                    applyVisibleColumns(view.columns);
                });

                item.appendChild(applyButton);

                if (hostType === 'modal') {
                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'detail-view-chip__delete';
                    removeButton.setAttribute('aria-label', `Excluir visao ${view.name}`);
                    removeButton.textContent = '×';
                    removeButton.addEventListener('click', () => {
                        const remainingViews = savedViews.filter((entry) => entry.id !== view.id);
                        writeSavedViews(remainingViews);

                        if (activeViewId === view.id) {
                            activeViewId = '__default__';
                            draftColumns = [...defaultColumns];
                            applyVisibleColumns(defaultColumns);
                        }

                        renderSavedViews();
                        renderColumnLists();
                    });

                    item.appendChild(removeButton);
                }

                host.appendChild(item);
            });
        });
    };

    const applyVisibleColumns = (columns, persist = true) => {
        const visibleColumns = normalizeColumns(columns);

        appliedColumns = [...visibleColumns];
        reorderTables(visibleColumns);
        updateTreeDetailColspan(visibleColumns);

        allColumnKeys.forEach((columnKey) => {
            detailRoot.querySelectorAll(`[data-detail-column="${columnKey}"]`).forEach((cell) => {
                cell.hidden = !visibleColumns.includes(columnKey);
            });
        });

        if (persist) {
            persistVisibleColumns(visibleColumns);
        }

        activeViewId = resolveActiveViewId(visibleColumns);
        renderSavedViews();
    };

    const applyTreeSearch = () => {
        const pane = getActivePane();

        if (!pane) {
            return;
        }

        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

        if (query === '') {
            collapseAllInPane(pane);
            return;
        }

        const { rows, detailRows } = getPaneCache(pane);

        rows.forEach((row) => {
            const matches = (row.dataset.treeSearchText || '').toLowerCase().includes(query);
            row.hidden = !matches;

            if (matches && row.querySelector('[data-tree-toggle]')) {
                setToggleState(row, true);
            } else {
                setToggleState(row, false);
            }
        });

        detailRows.forEach((detailRow) => {
            const parentRow = findTreeRow(pane, detailRow.dataset.parentId || '');
            detailRow.hidden = !parentRow || parentRow.hidden || !parentRow.classList.contains('tree-row--expanded');
        });
    };

    const setActiveTableView = (viewId) => {
        activeTableViewId = viewId;

        tableViewButtons.forEach((button) => {
            const isActive = button.dataset.detailTableView === viewId;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        tablePanes.forEach((pane) => {
            pane.hidden = pane.dataset.detailTablePane !== viewId;
        });

        applyTreeSearch();
    };

    const openModal = () => {
        if (!modal) {
            return;
        }

        draftColumns = [...appliedColumns];
        renderSavedViews();
        renderColumnLists();
        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('has-modal-open');

        if (openModalButton) {
            openModalButton.setAttribute('aria-expanded', 'true');
        }

        if (viewNameInput) {
            viewNameInput.value = '';
        }
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('has-modal-open');

        if (openModalButton) {
            openModalButton.setAttribute('aria-expanded', 'false');
        }
    };

    tableViewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const viewId = button.dataset.detailTableView;

            if (!viewId) {
                return;
            }

            setActiveTableView(viewId);
        });
    });

    detailRoot.querySelectorAll('[data-tree-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-tree-row]');
            const pane = button.closest('[data-detail-table-pane]');

            if (!row || !pane) {
                return;
            }

            const nodeId = row.dataset.treeId || '';
            const expanded = button.getAttribute('aria-expanded') === 'true';
            setNodeExpanded(pane, nodeId, !expanded);
        });
    });

    if (expandAllButton) {
        expandAllButton.addEventListener('click', () => {
            const pane = getActivePane();

            if (pane) {
                expandAllInPane(pane);
            }
        });
    }

    if (collapseAllButton) {
        collapseAllButton.addEventListener('click', () => {
            const pane = getActivePane();

            if (pane) {
                collapseAllInPane(pane);
            }
        });
    }

    if (openModalButton) {
        openModalButton.addEventListener('click', openModal);
    }

    closeModalButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    if (cancelModalButton) {
        cancelModalButton.addEventListener('click', closeModal);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });

    defaultViewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (button.closest('[data-detail-views-host="modal"]')) {
                activeViewId = '__default__';
                draftColumns = [...defaultColumns];
                renderSavedViews();
                renderColumnLists();
                return;
            }

            applyVisibleColumns(defaultColumns);
        });
    });

    if (applyColumnsButton) {
        applyColumnsButton.addEventListener('click', () => {
            applyVisibleColumns(draftColumns);
            closeModal();
        });
    }

    if (saveViewButton && viewNameInput) {
        saveViewButton.addEventListener('click', () => {
            const trimmedName = viewNameInput.value.trim();

            if (trimmedName === '') {
                viewNameInput.focus();
                return;
            }

            const viewId = slugify(trimmedName) || `view-${Date.now()}`;
            const savedViews = readSavedViews();
            const existingView = savedViews.find((view) => view.id === viewId);

            if (!existingView && savedViews.length >= maxSavedViews) {
                window.alert('Voce pode guardar ate 5 visoes personalizadas.');
                return;
            }

            const nextViews = savedViews.filter((view) => view.id !== viewId);
            nextViews.push({
                id: viewId,
                name: trimmedName,
                columns: normalizeColumns(draftColumns),
            });

            writeSavedViews(nextViews);
            activeViewId = viewId;
            viewNameInput.value = '';
            renderSavedViews();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyTreeSearch);
    }

    appliedColumns = readStoredColumns();
    draftColumns = [...appliedColumns];
    applyVisibleColumns(appliedColumns, false);
    renderSavedViews();
    setActiveTableView(activeTableViewId);
}