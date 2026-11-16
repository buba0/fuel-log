'use strict';

const numberFormat = new Intl.NumberFormat(document.documentElement.lang || undefined, {
  maximumFractionDigits: 2,
});

function readNumber(field) {
  if (!field || field.value.trim() === '') return NaN;
  return Number(field.value);
}

function formatVolume(value, unit) {
  if (!Number.isFinite(value)) return '—';
  return `${numberFormat.format(value)} ${unit}`.trim();
}

for (const form of document.querySelectorAll('[data-fill-form]')) {
  const car = form.querySelector('[data-fill-car]');
  const volume = form.querySelector('[data-fill-volume]');
  const price = form.querySelector('[data-fill-price]');
  const level = form.querySelector('[data-tank-level]');
  const paid = form.querySelector('[data-paid-preview]');
  const remaining = form.querySelector('[data-tank-preview]');
  const after = form.querySelector('[data-after-preview]');
  const volumeFactor = Number(form.dataset.volumeFactor) || 1;
  const priceFactor = Number(form.dataset.priceFactor) || volumeFactor;
  const unit = form.dataset.volumeUnit || 'L';
  const currencyFormat = new Intl.NumberFormat(document.documentElement.lang || undefined, {
    style: 'currency',
    currency: form.dataset.currency || 'PLN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  function updateEstimates() {
    const quantity = readNumber(volume);
    const unitPrice = readNumber(price);
    const amount = quantity * unitPrice * volumeFactor / priceFactor;
    if (paid) {
      paid.textContent = quantity > 0 && unitPrice >= 0 && Number.isFinite(amount)
        ? currencyFormat.format(amount)
        : '—';
    }

    const capacityLiters = Number(car?.selectedOptions[0]?.dataset.capacityLiters);
    const percent = level?.value.trim() ? readNumber(level) : 0;
    const remainingVolume = percent >= 0 && percent <= 100 && capacityLiters > 0
      ? capacityLiters * percent / (100 * volumeFactor)
      : NaN;
    if (remaining) remaining.textContent = formatVolume(remainingVolume, unit);
    if (after) after.textContent = quantity > 0 ? formatVolume(remainingVolume + quantity, unit) : '—';
  }

  for (const field of [car, volume, price, level]) {
    field?.addEventListener('input', updateEstimates);
  }
  updateEstimates();
}

for (const preset of document.querySelectorAll('[data-unit-preset]')) {
  const preferences = preset.form?.querySelectorAll('[data-unit-preference]') || [];
  preset.addEventListener('change', () => {
    if (!['metric', 'us', 'uk'].includes(preset.value)) return;
    for (const preference of preferences) {
      const nextUnit = preference.dataset[preset.value];
      if (nextUnit && Array.from(preference.options).some(option => option.value === nextUnit)) {
        preference.value = nextUnit;
      }
    }
  });
  for (const preference of preferences) {
    preference.addEventListener('change', () => { preset.value = 'custom'; });
  }
}

for (const preference of document.querySelectorAll('[data-theme-preference]')) {
  preference.addEventListener('change', () => {
    document.documentElement.dataset.theme = preference.value;
  });
}

for (const toggle of document.querySelectorAll('[data-menu-toggle]')) {
  const sidebar = toggle.closest('.sidebar');
  const menu = document.getElementById(toggle.getAttribute('aria-controls'));
  if (!menu || !sidebar?.contains(menu)) continue;

  const mobile = window.matchMedia('(max-width: 850px)');
  let expanded = false;
  function updateMenu() {
    toggle.hidden = !mobile.matches;
    menu.hidden = mobile.matches && !expanded;
    toggle.setAttribute('aria-expanded', String(!menu.hidden));
  }

  toggle.addEventListener('click', () => {
    expanded = !expanded;
    updateMenu();
  });
  mobile.addEventListener('change', updateMenu);
  updateMenu();
}

for (const button of document.querySelectorAll('[data-open-dialog]')) {
  button.addEventListener('click', (event) => {
    const dialog = document.getElementById(button.dataset.openDialog);
    if (dialog && typeof dialog.showModal === 'function') {
      event.preventDefault();
      dialog.showModal();
    }
  });
}
for (const button of document.querySelectorAll('[data-close-dialog]')) {
  button.addEventListener('click', () => button.closest('dialog')?.close());
}
for (const form of document.querySelectorAll('[data-fill-form]')) {
  const car = form.querySelector('[data-fill-car]');
  const meter = form.querySelector('[data-metering-field]');
  function toggleMeter() {
    meter.hidden = car.selectedOptions[0]?.dataset.metering !== '1';
    meter.querySelector('input').disabled = meter.hidden;
  }
  car.addEventListener('change', toggleMeter);
  toggleMeter();
  const odometer = form.querySelector('[name=odometer]');
  const estimated = form.querySelector('[name=estimated_odometer]');
  odometer.addEventListener('input', () => { estimated.value = '0'; });
  form.querySelector('[data-predict]').addEventListener('click', () => {
    const date = form.querySelector('[name=filled_at]').value;
    const history = JSON.parse(car.selectedOptions[0]?.dataset.history || '[]').filter(r => r.date < date);
    const first = history[0], last = history.at(-1);
    if (!first || history.length < 2 || first.date === last.date) {
      form.querySelector('[data-prediction-status]').textContent = document.documentElement.lang === 'pl'
        ? 'Potrzebne są co najmniej dwa wcześniejsze tankowania z różnych dni.' : 'At least two earlier fill-ups on different days are needed.';
      return;
    }
    const daily = (last.km - first.km) / ((Date.parse(last.date) - Date.parse(first.date)) / 86400000);
    const value = (last.km + daily * ((Date.parse(date) - Date.parse(last.date)) / 86400000)) / Number(form.dataset.distanceFactor);
    odometer.value = value.toFixed(1);
    estimated.value = '1';
  });
}
