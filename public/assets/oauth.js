/* Radar Viral BR — OAuth pages. Redirecting to the client after consent is done here (not by a
   form redirect) because the strict CSP `form-action 'self'` also blocks cross-site redirects. */
(() => {
  'use strict';
  const target = document.querySelector('meta[name="oauth-redirect"]')?.content;
  if (target) setTimeout(() => location.replace(target), 400);
})();
