document.documentElement.classList.add('js');

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (! window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-project-switcher]').forEach((switcher) => {
    switcher.addEventListener('change', () => {
        if (switcher.value) window.location.assign(switcher.value);
    });
});

document.querySelectorAll('[data-project-wizard]').forEach((form) => {
    const panels = [...form.querySelectorAll('[data-wizard-panel]')];
    const indicators = [...form.querySelectorAll('[data-wizard-indicator]')];
    const status = form.querySelector('[data-wizard-status]');
    const stepLabels = ['Identidad', 'Cuenta', 'Presupuesto', 'Revisión'];
    const firstServerError = form.querySelector('.field__control--invalid');
    let currentStep = Number(firstServerError?.closest('[data-wizard-panel]')?.dataset.wizardPanel || 1);
    let furthestStep = currentStep;

    const input = (selector) => form.querySelector(selector);
    const output = (name) => form.querySelector(`[data-review-output="${name}"]`);

    const formatMoney = (rawValue) => {
        const value = rawValue.trim().replaceAll(' ', '');
        let normalized = value;

        if (value.includes(',')) {
            normalized = value.replaceAll('.', '').replace(',', '.');
        } else if (/^-?\d{1,3}(?:\.\d{3})+$/.test(value)) {
            normalized = value.replaceAll('.', '');
        }

        const amount = Number(normalized);
        if (! Number.isFinite(amount)) return `${rawValue || '0,00'} €`;

        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDate = (value) => {
        const [year, month, day] = value.split('-');
        return year && month && day ? `${day}/${month}/${year}` : 'Sin fecha';
    };

    const synchronizeReview = () => {
        const name = input('[data-review-name]');
        const description = input('[data-review-description]');
        const color = input('[data-review-color]');
        const icon = input('[data-review-icon]');
        const accountName = input('[data-review-account-name]');
        const accountType = input('[data-review-account-type]');
        const balance = input('[data-review-balance]');
        const balanceDate = input('[data-review-balance-date]');
        const budget = input('[data-review-budget]');

        output('name').textContent = name.value.trim() || 'Sin nombre';
        output('description').textContent = description.value.trim() || 'Sin descripción';
        output('icon').textContent = icon.selectedOptions[0]?.dataset.symbol || '⌂';
        output('icon').style.backgroundColor = color.value;
        output('account-name').textContent = accountName.value.trim() || 'Sin nombre';
        output('account-type').textContent = accountType.selectedOptions[0]?.textContent.trim() || '';
        output('balance').textContent = formatMoney(balance.value);
        output('balance-date').textContent = formatDate(balanceDate.value);
        output('budget').textContent = formatMoney(budget.value);
    };

    const showStep = (step, focusHeading = false) => {
        currentStep = Math.min(Math.max(step, 1), panels.length);
        furthestStep = Math.max(furthestStep, currentStep);

        panels.forEach((panel) => {
            const isCurrent = Number(panel.dataset.wizardPanel) === currentStep;
            panel.hidden = ! isCurrent;
            panel.setAttribute('aria-hidden', String(! isCurrent));
        });

        indicators.forEach((indicator) => {
            const indicatorStep = Number(indicator.dataset.wizardIndicator);
            const button = indicator.querySelector('[data-wizard-go]');
            const isCurrent = indicatorStep === currentStep;

            indicator.classList.toggle('wizard-progress__item--active', isCurrent);
            indicator.classList.toggle('wizard-progress__item--complete', indicatorStep < currentStep);
            button.disabled = indicatorStep > furthestStep;
            if (isCurrent) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });

        status.textContent = `Paso ${currentStep} de ${panels.length}: ${stepLabels[currentStep - 1]}`;
        synchronizeReview();

        if (focusHeading) {
            const heading = panels[currentStep - 1].querySelector('.wizard__title');
            heading.setAttribute('tabindex', '-1');
            heading.focus();
        }
    };

    const validatePanel = (panel, report = true) => {
        const fields = [...panel.querySelectorAll('input, select, textarea')];
        const firstInvalid = fields.find((field) => ! field.checkValidity());

        if (! firstInvalid) return true;
        if (report) firstInvalid.reportValidity();
        return false;
    };

    form.querySelectorAll('[data-wizard-next]').forEach((button) => {
        button.addEventListener('click', () => {
            if (! validatePanel(panels[currentStep - 1])) return;
            showStep(currentStep + 1, true);
        });
    });

    form.querySelectorAll('[data-wizard-previous]').forEach((button) => {
        button.addEventListener('click', () => showStep(currentStep - 1, true));
    });

    form.querySelectorAll('[data-wizard-go]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetStep = Number(button.dataset.wizardGo);
            if (targetStep <= furthestStep) showStep(targetStep, true);
        });
    });

    form.addEventListener('input', synchronizeReview);
    form.addEventListener('change', synchronizeReview);
    form.addEventListener('submit', (event) => {
        const invalidPanel = panels.slice(0, 3).find((panel) => ! validatePanel(panel, false));

        if (invalidPanel) {
            event.preventDefault();
            showStep(Number(invalidPanel.dataset.wizardPanel));
            validatePanel(invalidPanel);
            return;
        }

        if (currentStep < panels.length) {
            event.preventDefault();
            showStep(panels.length, true);
        }
    });

    showStep(currentStep);
});

const categoryType = document.querySelector('[data-category-type]');
const categoryParent = document.querySelector('[data-category-parent]');

if (categoryType && categoryParent) {
    const synchronizeCategoryParents = () => {
        const selectedType = categoryType.value;

        [...categoryParent.options].forEach((option) => {
            if (! option.dataset.categoryType) {
                return;
            }

            const belongsToType = option.dataset.categoryType === selectedType;
            option.hidden = ! belongsToType;
            option.disabled = ! belongsToType;

            if (option.selected && ! belongsToType) {
                categoryParent.value = '';
            }
        });
    };

    categoryType.addEventListener('change', synchronizeCategoryParents);
    synchronizeCategoryParents();
}

document.querySelectorAll('[data-movement-form]').forEach((form) => {
    const type = form.querySelector('[data-movement-type]');
    const category = form.querySelector('[data-movement-category]');
    const subcategory = form.querySelector('[data-movement-subcategory]');
    const account = form.querySelector('[data-movement-account]');

    const synchronizeMovementFields = () => {
        const selectedType = type.value;

        [...category.options].forEach((option) => {
            if (! option.dataset.categoryType) return;
            const visible = option.dataset.categoryType === selectedType;
            option.hidden = ! visible;
            option.disabled = ! visible;
            if (option.selected && ! visible) category.value = '';
        });

        [...subcategory.options].forEach((option) => {
            if (! option.dataset.parentId) return;
            const visible = option.dataset.categoryType === selectedType
                && option.dataset.parentId === category.value;
            option.hidden = ! visible;
            option.disabled = ! visible;
            if (option.selected && ! visible) subcategory.value = '';
        });

        [...account.options].forEach((option) => {
            if (! option.dataset.accountType) return;
            const visible = selectedType === 'expense' || option.dataset.accountType !== 'credit_card';
            option.hidden = ! visible;
            option.disabled = ! visible;
            if (option.selected && ! visible) account.value = '';
        });
    };

    type.addEventListener('change', synchronizeMovementFields);
    category.addEventListener('change', synchronizeMovementFields);
    synchronizeMovementFields();
});

document.querySelectorAll('[data-recurrence-form], [data-planned-form]').forEach((form) => {
    const kind = form.querySelector('[data-recurrence-kind]');
    const category = form.querySelector('[data-recurrence-category]');
    const subcategory = form.querySelector('[data-recurrence-subcategory]');
    const source = form.querySelector('[data-recurrence-source]');
    const sourceLabel = form.querySelector('[data-recurrence-source-label]');
    const destination = form.querySelector('[data-recurrence-destination]');
    const goal = form.querySelector('[data-recurrence-goal]');

    const synchronizeRecurrence = () => {
        const selectedKind = kind.value;
        const isTransfer = selectedKind === 'transfer';
        sourceLabel.textContent = isTransfer ? 'Cuenta de origen' : 'Cuenta de cargo';

        form.querySelectorAll('[data-recurrence-standard]').forEach((field) => {
            field.hidden = isTransfer;
            field.querySelectorAll('select, input').forEach((control) => { control.disabled = isTransfer; });
        });
        form.querySelectorAll('[data-recurrence-transfer]').forEach((field) => {
            field.hidden = ! isTransfer;
            field.querySelectorAll('select, input').forEach((control) => { control.disabled = ! isTransfer; });
        });

        [...category.options].forEach((option) => {
            if (! option.dataset.categoryType) return;
            const visible = option.dataset.categoryType === selectedKind;
            option.hidden = ! visible;
            option.disabled = ! visible;
            if (option.selected && ! visible) category.value = '';
        });
        [...subcategory.options].forEach((option) => {
            if (! option.dataset.parentId) return;
            const visible = option.dataset.categoryType === selectedKind && option.dataset.parentId === category.value;
            option.hidden = ! visible;
            option.disabled = ! visible;
            if (option.selected && ! visible) subcategory.value = '';
        });
        [...source.options].forEach((option) => {
            if (! option.dataset.accountType) return;
            const visible = isTransfer
                ? option.dataset.accountType !== 'credit_card'
                : selectedKind === 'expense'
                    ? option.dataset.accountType !== 'external_investment'
                    : ! ['credit_card', 'external_investment'].includes(option.dataset.accountType);
            option.hidden = ! visible;
            option.disabled = ! visible;
            if (option.selected && ! visible) source.value = '';
        });
        if (isTransfer && goal.value) {
            const selectedGoal = goal.options[goal.selectedIndex];
            destination.value = selectedGoal.dataset.accountId || '';
        }
    };

    kind.addEventListener('change', synchronizeRecurrence);
    category.addEventListener('change', synchronizeRecurrence);
    goal.addEventListener('change', synchronizeRecurrence);
    synchronizeRecurrence();
});

document.querySelectorAll('[data-comparison-form]').forEach((form) => {
    const mode = form.querySelector('[data-comparison-mode]');
    const periods = form.querySelectorAll('[data-comparison-period]');

    const synchronizeComparisonPeriods = () => {
        const usesMonths = mode.value === 'months';
        periods.forEach((period) => {
            period.type = usesMonths ? 'month' : 'number';
            if (usesMonths) {
                period.removeAttribute('min');
                period.removeAttribute('max');
                period.removeAttribute('step');
            } else {
                period.min = '1900';
                period.max = '2199';
                period.step = '1';
                if (/^\d{4}-\d{2}$/.test(period.value)) period.value = period.value.slice(0, 4);
            }
        });
    };

    mode.addEventListener('change', synchronizeComparisonPeriods);
    synchronizeComparisonPeriods();
});

document.querySelectorAll('[data-closure-allocation-form]').forEach((form) => {
    const destination = form.querySelector('[data-closure-destination]');
    const goal = form.querySelector('[data-closure-goal]');

    const synchronizeClosureGoals = () => {
        [...goal.options].forEach((option) => {
            if (! option.dataset.accountId) return;
            const belongsToDestination = option.dataset.accountId === destination.value;
            option.hidden = ! belongsToDestination;
            option.disabled = ! belongsToDestination;
            if (option.selected && ! belongsToDestination) goal.value = '';
        });
    };

    destination.addEventListener('change', synchronizeClosureGoals);
    synchronizeClosureGoals();
});
