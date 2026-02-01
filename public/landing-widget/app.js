(function () {
  'use strict';

  var config = window.LANDING_WIDGET_CONFIG || {};
  var API_BASE = (config.API_BASE_URL || '').replace(/\/$/, '');
  var TEAM_TOKEN = config.TEAM_TOKEN || '';
  var LANDING_PROMPT_NAME = config.LANDING_PROMPT_NAME || 'landing';
  var SUCCESS_URL = config.SUCCESS_URL || '';

  var promptInput = document.getElementById('prompt-input');
  var btnSend = document.getElementById('btn-send');
  var btnText = document.getElementById('btn-text');
  var btnIcon = document.getElementById('btn-icon');
  var btnSpinner = document.getElementById('btn-spinner');
  var errorMsg = document.getElementById('error-msg');
  var resultSection = document.getElementById('result-section');
  var resultContent = document.getElementById('result-content');
  var formProfundizar = document.getElementById('form-profundizar');
  var btnProfundizar = document.getElementById('btn-profundizar');

  function showError(msg) {
    errorMsg.textContent = msg || '';
    errorMsg.style.display = msg ? 'block' : 'none';
  }

  function preprocessSuggestion(text) {
    if (!text) return '';
    return text
      .replace(/Análisis según Strategic Growth Framework/g, 'Análisis de la Estrategia')
      .replace(/Strategic Growth Framework/g, 'Análisis de la Estrategia');
  }

  function setLoading(loading) {
    btnSend.disabled = loading;
    btnText.textContent = loading ? 'Analizando su problema...' : 'Obtener sugerencias';
    if (btnIcon) btnIcon.style.display = loading ? 'none' : 'inline-flex';
    if (btnSpinner) btnSpinner.style.display = loading ? 'inline-flex' : 'none';
  }

  btnSend.addEventListener('click', function () {
    var text = (promptInput.value || '').trim();
    if (!text) {
      showError('Escribe tu problema de negocio para continuar');
      return;
    }
    if (!TEAM_TOKEN) {
      showError('Configura TEAM_TOKEN en la configuración del widget.');
      return;
    }
    if (!API_BASE) {
      showError('Configura API_BASE_URL en la configuración del widget.');
      return;
    }

    showError('');
    setLoading(true);

    fetch(API_BASE + '/api/team/prompt', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + TEAM_TOKEN
      },
      body: JSON.stringify({
        test_message: text,
        prompt_name: LANDING_PROMPT_NAME
      })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { status: res.status, data: data };
        });
      })
      .then(function (_ref) {
        var status = _ref.status;
        var data = _ref.data;
        setLoading(false);
        if (status !== 200) {
          showError(data.message || 'Error ' + status);
          return;
        }
        if (!data.success || data.response == null) {
          showError(data.message || 'No se pudo obtener una sugerencia');
          return;
        }
        var html = '';
        if (typeof marked !== 'undefined') {
          var render = typeof marked.parse === 'function' ? marked.parse : marked;
          html = render(preprocessSuggestion(data.response));
          html = html.replace(/\u2713/g, '<span class="suggestion-check" aria-hidden="true" title="Aplica"><svg class="suggestion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>');
          html = html.replace(/\u2717/g, '<span class="suggestion-cross" aria-hidden="true" title="No aplica"><svg class="suggestion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg></span>');
        } else {
          html = '<pre>' + escapeHtml(preprocessSuggestion(data.response)) + '</pre>';
        }
        resultContent.innerHTML = html;
        resultSection.style.display = 'block';
        resultSection.scrollIntoView({ behavior: 'smooth' });
      })
      .catch(function (err) {
        setLoading(false);
        showError(err.message || 'Error de conexión. Revisa la URL y la configuración.');
      });
  });

  function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  if (formProfundizar) {
    formProfundizar.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = (document.getElementById('name').value || '').trim();
      var surname = (document.getElementById('surname').value || '').trim();
      var email = (document.getElementById('email').value || '').trim();
      var phone = (document.getElementById('phone').value || '').trim();
      if (!email && !phone) {
        alert('Indica al menos email o teléfono');
        return;
      }
      if (!TEAM_TOKEN || !API_BASE) {
        alert('Configura TEAM_TOKEN y API_BASE_URL para enviar el formulario.');
        return;
      }

      btnProfundizar.disabled = true;
      fetch(API_BASE + '/api/team/contacts', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer ' + TEAM_TOKEN
        },
        body: JSON.stringify({
          name: name,
          surname: surname || null,
          email: email || null,
          phone: phone || null
        })
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { status: res.status, data: data };
          });
        })
        .then(function (_ref) {
          var status = _ref.status;
          var data = _ref.data;
          btnProfundizar.disabled = false;
          if (status === 200 || status === 201) {
            var url = SUCCESS_URL && SUCCESS_URL.trim() !== ''
              ? SUCCESS_URL.trim()
              : (window.location.origin + '/landing/gracias');
            var sep = url.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = url + sep + 'conversion=profundizar&from=landing-widget';
          } else {
            alert(data.message || data.errors || 'No se pudo enviar el formulario');
          }
        })
        .catch(function () {
          btnProfundizar.disabled = false;
          alert('Error de conexión.');
        });
    });
  }
})();
