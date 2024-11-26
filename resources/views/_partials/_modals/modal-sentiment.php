<!-- Modal Sentiment -->
<div class="modal fade" id="updateSentimentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Actualizar Sentimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateSentimentForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="sentiment_id" class="form-label">Sentimiento</label>
                            <select class="form-select" id="sentiment_id" name="sentiment_id" required>
                                <option value="" selected disabled>Selecciona un estado emocional</option>
                                @foreach (App\Models\ContactSentiment::all() as $sentiment)
                                    <option value="{{ $sentiment->id }}">{{ $sentiment->name }} {{ $sentiment->emoji }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="sentiment_id_error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            <div class="invalid-feedback" id="notes_error"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>