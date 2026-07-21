@extends('layouts/layoutMaster')

@section('title', __('Embudo').' — '.$automation->name)

@section('vendor-style')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.css">
<style>
  #automation-drawflow {
    width: 100%;
    height: 620px;
    border: 1px solid #d9dee3;
    border-radius: .375rem;
    background:
      linear-gradient(90deg, rgba(67,89,113,.06) 1px, transparent 1px) 0 0 / 24px 24px,
      linear-gradient(rgba(67,89,113,.06) 1px, transparent 1px) 0 0 / 24px 24px,
      #f5f5f9;
  }
  .drawflow .drawflow-node {
    background: #fff;
    border: 1px solid #d9dee3;
    border-radius: .5rem;
    min-width: 220px;
    color: #566a7f;
    padding: 0;
  }
  .drawflow .drawflow-node.selected,
  .drawflow .drawflow-node:hover {
    background: #fff;
    border-color: #696cff;
    box-shadow: 0 0 0 2px rgba(105,108,255,.2);
  }
  .drawflow .drawflow-node.selected .box,
  .drawflow .drawflow-node:hover .box {
    background: #fff;
  }
  .drawflow .drawflow-node .title-box {
    background: #696cff;
    color: #fff;
    padding: .5rem .75rem;
    font-weight: 600;
    font-size: .8125rem;
    border-radius: .5rem .5rem 0 0;
  }
  .drawflow .drawflow-node.is-entry .title-box { background: #28c76f; }
  .drawflow .drawflow-node .box {
    padding: .75rem;
    font-size: .75rem;
    background: #fff;
  }
  .drawflow-delete { line-height: 1.2; }
</style>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.js"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Automations') }}/{{ $automation->name }}/</span>
            {{ __('Embudo conversacional') }}
        </h4>
        <p class="text-muted">{{ __('Arrastrá pasos, conectá salidas según el tipo de respuesta esperada del usuario.') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2">
        <a href="{{ route('automation.show', $automation) }}" class="btn btn-label-secondary">{{ __('Volver') }}</a>
        <button type="button" class="btn btn-primary" id="btn-save-flow">
            <i class="ti ti-device-floppy me-1"></i>{{ __('Guardar embudo') }}
        </button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card mb-3">
            <h5 class="card-header">{{ __('Herramientas') }}</h5>
            <div class="card-body d-grid gap-2">
                <button type="button" class="btn btn-outline-primary" id="btn-add-step">
                    <i class="ti ti-plus me-1"></i>{{ __('Añadir paso') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-add-output" disabled>
                    <i class="ti ti-arrow-fork me-1"></i>{{ __('Añadir salida') }}
                </button>
                <div class="form-text">{{ __('Seleccioná un paso para editarlo y añadir salidas (Sí/No, opción, email…). Luego conectá la salida al siguiente paso.') }}</div>
            </div>
        </div>

        <div class="card" id="step-editor-card" style="display:none;">
            <h5 class="card-header">{{ __('Paso seleccionado') }}</h5>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label" for="step-label">{{ __('Etiqueta') }}</label>
                    <input type="text" class="form-control form-control-sm" id="step-label">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="step-prompt">{{ __('Prompt (opcional)') }}</label>
                    <select class="form-select form-select-sm" id="step-prompt">
                        <option value="">{{ __('Sin prompt / solo instrucción') }}</option>
                        @foreach($promptOptions as $option)
                            <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="step-instruction">{{ __('Instrucción / pregunta al usuario') }}</label>
                    <textarea class="form-control form-control-sm" id="step-instruction" rows="4"></textarea>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="step-is-entry">
                    <label class="form-check-label" for="step-is-entry">{{ __('Paso de entrada') }}</label>
                </div>
                <div class="mb-2">
                    <label class="form-label">{{ __('Salidas (respuestas esperadas)') }}</label>
                    <div id="step-outputs-list" class="small text-muted"></div>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="btn-apply-step">{{ __('Aplicar al nodo') }}</button>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="card">
            <div class="card-body p-2">
                <div id="automation-drawflow"></div>
            </div>
        </div>
        <div id="flow-save-status" class="small text-muted mt-2"></div>
    </div>
</div>

{{-- Modal add output --}}
<div class="modal fade" id="outputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Nueva salida') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="out-label">{{ __('Etiqueta') }}</label>
                    <input type="text" class="form-control" id="out-label" placeholder="Sí">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="out-type">{{ __('Tipo de respuesta') }}</label>
                    <select class="form-select" id="out-type">
                        @foreach($replyTypes as $type)
                            <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="out-match">{{ __('Valor a coincidir (opcional)') }}</label>
                    <input type="text" class="form-control" id="out-match" placeholder="yes / cita / …">
                    <div class="form-text">{{ __('Para Sí/No usá yes o no. Para opciones, la palabra clave.') }}</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <button type="button" class="btn btn-primary" id="btn-confirm-output">{{ __('Añadir') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const saveUrl = @json(route('automation.flow.save', $automation));
  const csrf = @json(csrf_token());
  const initialGraph = @json($graph);
  const replyTypes = @json($replyTypes);

  const editor = new Drawflow(document.getElementById('automation-drawflow'));
  editor.reroute = true;
  editor.start();

  let selectedNodeId = null;
  let nodeSeq = 1;
  const nodeData = {}; // drawflowId -> our data

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function htmlForNode(data) {
    const entry = data.is_entry ? 'is-entry' : '';
    const outs = (data.outputs || []).map((o) => escapeHtml(o.label || o.reply_type)).join(', ') || '—';
    return `
      <div class="${entry}">
        <div class="title-box">${escapeHtml(data.label || 'Paso')}</div>
        <div class="box">
          <div><strong>Prompt:</strong> ${escapeHtml(data.prompt_key || '—')}</div>
          <div class="mt-1 text-truncate" style="max-width:200px">${escapeHtml((data.instruction || '').slice(0, 80))}</div>
          <div class="mt-1"><strong>Salidas:</strong> ${outs}</div>
        </div>
      </div>`;
  }

  function outputsCount(data) {
    return Math.max(1, (data.outputs || []).length);
  }

  function addNodeToEditor(data, x, y) {
    const clientId = String(data.client_id || nodeSeq++);
    data.client_id = clientId;
    data.outputs = data.outputs && data.outputs.length ? data.outputs : [{
      id: 'output_1',
      reply_type: 'fallback',
      match_value: null,
      label: 'Otra respuesta'
    }];
    const dfId = editor.addNode(
      'step',
      1,
      outputsCount(data),
      x,
      y,
      data.is_entry ? 'is-entry' : '',
      data,
      htmlForNode(data)
    );
    nodeData[dfId] = data;
    // rename outputs to match ids
    const node = editor.getNodeFromId(dfId);
    if (node && node.outputs) {
      const keys = Object.keys(node.outputs);
      data.outputs.forEach((out, idx) => {
        // Drawflow uses output_1, output_2…
        out.id = keys[idx] || ('output_' + (idx + 1));
      });
      nodeData[dfId] = data;
      editor.updateNodeDataFromId(dfId, data);
    }
    return dfId;
  }

  function loadGraph(graph) {
    editor.clear();
    Object.keys(nodeData).forEach((k) => delete nodeData[k]);
    const idMap = {};
    (graph.nodes || []).forEach((n) => {
      const x = Number(n.position_x || 80);
      const y = Number(n.position_y || 80);
      const dfId = addNodeToEditor({ ...n }, x, y);
      idMap[String(n.client_id)] = dfId;
      const num = parseInt(String(n.client_id).replace(/\D/g, ''), 10);
      if (!isNaN(num) && num >= nodeSeq) nodeSeq = num + 1;
    });
    (graph.edges || []).forEach((e) => {
      const from = idMap[String(e.from_client_id)];
      const to = idMap[String(e.to_client_id)];
      if (!from || !to) return;
      const out = e.from_output || 'output_1';
      try {
        editor.addConnection(from, to, out, 'input_1');
      } catch (err) {
        console.warn('connection skipped', err);
      }
    });
  }

  function exportGraph() {
    const exported = editor.export();
    const nodes = [];
    const edges = [];
    const module = exported.drawflow.Home.data;
    Object.keys(module).forEach((id) => {
      const n = module[id];
      const data = Object.assign({}, n.data || {}, nodeData[id] || {});
      data.client_id = String(data.client_id || id);
      data.position_x = Math.round(n.pos_x || 0);
      data.position_y = Math.round(n.pos_y || 0);
      const outs = [];
      Object.keys(n.outputs || {}).forEach((outKey) => {
        const meta = (data.outputs || []).find((o) => o.id === outKey) || {
          id: outKey,
          reply_type: 'fallback',
          match_value: null,
          label: outKey
        };
        outs.push(meta);
        (n.outputs[outKey].connections || []).forEach((c) => {
          const toNode = module[c.node];
          const toClient = toNode && toNode.data ? toNode.data.client_id : c.node;
          edges.push({
            from_client_id: data.client_id,
            from_output: outKey,
            to_client_id: String(toClient),
            reply_type: meta.reply_type,
            match_value: meta.match_value,
            label: meta.label
          });
        });
      });
      data.outputs = outs;
      nodes.push({
        client_id: data.client_id,
        key: data.key || null,
        label: data.label || 'Paso',
        prompt_key: data.prompt_key || null,
        instruction: data.instruction || null,
        is_entry: !!data.is_entry,
        position_x: data.position_x,
        position_y: data.position_y,
        outputs: data.outputs
      });
    });
    return { nodes, edges };
  }

  function selectNode(id) {
    selectedNodeId = id;
    const data = nodeData[id] || (editor.getNodeFromId(id) || {}).data || {};
    document.getElementById('step-editor-card').style.display = 'block';
    document.getElementById('btn-add-output').disabled = false;
    document.getElementById('step-label').value = data.label || '';
    document.getElementById('step-prompt').value = data.prompt_key || '';
    document.getElementById('step-instruction').value = data.instruction || '';
    document.getElementById('step-is-entry').checked = !!data.is_entry;
    const list = document.getElementById('step-outputs-list');
    list.innerHTML = (data.outputs || []).map((o) =>
      `<div class="border rounded p-1 mb-1">${escapeHtml(o.label || o.id)} · <code>${escapeHtml(o.reply_type)}</code>${o.match_value ? ' · ' + escapeHtml(o.match_value) : ''}</div>`
    ).join('') || '<em>Sin salidas</em>';
  }

  editor.on('nodeSelected', (id) => selectNode(id));
  editor.on('nodeUnselected', () => {
    selectedNodeId = null;
    document.getElementById('step-editor-card').style.display = 'none';
    document.getElementById('btn-add-output').disabled = true;
  });

  document.getElementById('btn-add-step').addEventListener('click', () => {
    const id = addNodeToEditor({
      label: 'Nuevo paso',
      instruction: 'Preguntá al usuario…',
      is_entry: Object.keys(nodeData).length === 0,
      prompt_key: null,
      outputs: [{ id: 'output_1', reply_type: 'fallback', match_value: null, label: 'Otra respuesta' }]
    }, 80 + Object.keys(nodeData).length * 40, 80 + Object.keys(nodeData).length * 30);
    selectNode(id);
  });

  document.getElementById('btn-apply-step').addEventListener('click', () => {
    if (!selectedNodeId) return;
    const data = Object.assign({}, nodeData[selectedNodeId] || {});
    data.label = document.getElementById('step-label').value.trim() || 'Paso';
    data.prompt_key = document.getElementById('step-prompt').value || null;
    data.instruction = document.getElementById('step-instruction').value;
    data.is_entry = document.getElementById('step-is-entry').checked;
    if (data.is_entry) {
      Object.keys(nodeData).forEach((id) => {
        if (String(id) !== String(selectedNodeId) && nodeData[id]) {
          nodeData[id].is_entry = false;
          editor.updateNodeDataFromId(id, nodeData[id]);
          editor.updateNodeHtmlFromId
            ? null
            : (function () {
                const el = document.querySelector(`#node-${id}`);
                if (el) el.classList.remove('is-entry');
              })();
        }
      });
    }
    nodeData[selectedNodeId] = data;
    editor.updateNodeDataFromId(selectedNodeId, data);
    const nodeEl = document.querySelector(`#node-${selectedNodeId}`);
    if (nodeEl) {
      nodeEl.classList.toggle('is-entry', !!data.is_entry);
      const title = nodeEl.querySelector('.title-box');
      if (title) title.textContent = data.label;
      const box = nodeEl.querySelector('.box');
      if (box) {
        box.innerHTML = `
          <div><strong>Prompt:</strong> ${escapeHtml(data.prompt_key || '—')}</div>
          <div class="mt-1 text-truncate" style="max-width:200px">${escapeHtml((data.instruction || '').slice(0, 80))}</div>
          <div class="mt-1"><strong>Salidas:</strong> ${(data.outputs || []).map((o) => escapeHtml(o.label || o.reply_type)).join(', ') || '—'}</div>`;
      }
    }
    selectNode(selectedNodeId);
  });

  const outputModal = new bootstrap.Modal(document.getElementById('outputModal'));
  document.getElementById('btn-add-output').addEventListener('click', () => {
    if (!selectedNodeId) return;
    document.getElementById('out-label').value = '';
    document.getElementById('out-match').value = '';
    document.getElementById('out-type').value = 'yes_no';
    outputModal.show();
  });

  document.getElementById('btn-confirm-output').addEventListener('click', () => {
    if (!selectedNodeId) return;
    const data = Object.assign({}, nodeData[selectedNodeId] || {});
    data.outputs = data.outputs || [];
    const nextIndex = data.outputs.length + 1;
    const outId = 'output_' + nextIndex;
    data.outputs.push({
      id: outId,
      reply_type: document.getElementById('out-type').value,
      match_value: document.getElementById('out-match').value.trim() || null,
      label: document.getElementById('out-label').value.trim() || document.getElementById('out-type').selectedOptions[0].text
    });
    nodeData[selectedNodeId] = data;
    editor.updateNodeDataFromId(selectedNodeId, data);
    // Drawflow needs re-add for new output ports — recreate node is heavy; add output via API
    try {
      editor.addNodeOutput(selectedNodeId);
      // remap last output key
      const node = editor.getNodeFromId(selectedNodeId);
      const keys = Object.keys(node.outputs || {});
      data.outputs[data.outputs.length - 1].id = keys[keys.length - 1];
      nodeData[selectedNodeId] = data;
      editor.updateNodeDataFromId(selectedNodeId, data);
    } catch (e) {
      console.warn(e);
    }
    outputModal.hide();
    selectNode(selectedNodeId);
  });

  document.getElementById('btn-save-flow').addEventListener('click', () => {
    const status = document.getElementById('flow-save-status');
    status.textContent = '{{ __("Guardando…") }}';
    const payload = exportGraph();
    fetch(saveUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    }).then(async (r) => {
      const json = await r.json();
      if (!r.ok) throw new Error(json.message || ('HTTP ' + r.status));
      status.textContent = json.message || 'OK';
      if (json.graph) loadGraph(json.graph);
    }).catch((err) => {
      status.textContent = err.message;
    });
  });

  if (initialGraph && initialGraph.nodes && initialGraph.nodes.length) {
    loadGraph(initialGraph);
  } else {
    addNodeToEditor({
      label: 'Inicio',
      instruction: 'Saludá y preguntá qué necesita el usuario.',
      is_entry: true,
      prompt_key: null,
      outputs: [
        { id: 'output_1', reply_type: 'choice', match_value: 'cita', label: 'Cita' },
        { id: 'output_2', reply_type: 'choice', match_value: 'contacto', label: 'Contacto' },
        { id: 'output_3', reply_type: 'fallback', match_value: null, label: 'Otra respuesta' }
      ]
    }, 120, 120);
  }
});
</script>
@endsection
