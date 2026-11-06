// QRONEX portal: loading state on submit + confirm prompts. No framework; Alpine handles the rest.
document.addEventListener('submit', (e) => {
  const f = e.target, b = e.submitter;
  if (f.dataset.confirm && !confirm(f.dataset.confirm)) { e.preventDefault(); return; }
  if (b && !b.hasAttribute('data-no-busy')) {
    // disable after the browser has captured the submitter's name/value
    setTimeout(() => { b.disabled = true; b.classList.add('is-busy'); }, 0);
  }
  document.documentElement.classList.add('is-loading');
});
window.addEventListener('pageshow', () => {
  document.documentElement.classList.remove('is-loading');
  document.querySelectorAll('.is-busy').forEach((b) => { b.disabled = false; b.classList.remove('is-busy'); });
});
