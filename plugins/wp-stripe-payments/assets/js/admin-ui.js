document.addEventListener('click', function (event) {
  const trigger = event.target.closest('[data-copy-target]');
  if (!trigger) {
    return;
  }

  const selector = trigger.getAttribute('data-copy-target');
  if (!selector) {
    return;
  }

  const target = document.querySelector(selector);
  if (!target) {
    return;
  }

  const value = target.value || target.textContent || '';
  if (!value) {
    return;
  }

  navigator.clipboard.writeText(value).then(function () {
    const original = trigger.textContent;
    trigger.textContent = 'Copied';
    window.setTimeout(function () {
      trigger.textContent = original;
    }, 1100);
  });
});

function openStripePaymentsRowLink(row) {
  if (!row) {
    return;
  }

  const href = row.getAttribute('data-row-href');
  if (!href) {
    return;
  }

  window.location.href = href;
}

document.addEventListener('click', function (event) {
  const row = event.target.closest('.wp-sp-clickable-row[data-row-href]');
  if (!row) {
    return;
  }

  if (event.target.closest('a,button,input,select,textarea,label')) {
    return;
  }

  openStripePaymentsRowLink(row);
});

document.addEventListener('keydown', function (event) {
  const row = event.target.closest('.wp-sp-clickable-row[data-row-href]');
  if (!row) {
    return;
  }

  if (event.target.closest('a,button,input,select,textarea,label')) {
    return;
  }

  if (event.key !== 'Enter' && event.key !== ' ') {
    return;
  }

  event.preventDefault();
  openStripePaymentsRowLink(row);
});
