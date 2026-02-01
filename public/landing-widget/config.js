/**
 * Landing widget config. Same variable names as mobile app (LANDING_PROMPT_NAME).
 * Edit values below or inject LANDING_API_BASE_URL, LANDING_TEAM_TOKEN, LANDING_PROMPT_NAME, LANDING_SUCCESS_URL before this script.
 * LANDING_SUCCESS_URL: after "profundizar" form success, redirect here. Add ?conversion=profundizar for analytics. If empty, uses same-origin /landing/gracias.
 */
window.LANDING_WIDGET_CONFIG = {
  API_BASE_URL: (typeof LANDING_API_BASE_URL !== 'undefined' ? LANDING_API_BASE_URL : 'https://humano.test').replace(/\/$/, ''),
  TEAM_TOKEN: typeof LANDING_TEAM_TOKEN !== 'undefined' ? LANDING_TEAM_TOKEN : '',
  LANDING_PROMPT_NAME: typeof LANDING_PROMPT_NAME !== 'undefined' ? LANDING_PROMPT_NAME : 'landing',
  SUCCESS_URL: typeof LANDING_SUCCESS_URL !== 'undefined' ? LANDING_SUCCESS_URL : ''
};
