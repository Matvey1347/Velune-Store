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
